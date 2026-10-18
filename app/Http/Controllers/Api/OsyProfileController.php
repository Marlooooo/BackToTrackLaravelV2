<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OsyProfile;
use App\Models\User;
use App\Services\CourseRecommender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class OsyProfileController extends Controller
{
    private const STATUSES = [
        'Registered',
        'Validated',
        'Referred',
        'Accepted by Maxima',
        'Training Started',
        'Completed',
    ];

    private const EDUCATIONAL_ATTAINMENTS = [
        'No Grade Completed',
        'Elementary Undergraduate',
        'Elementary Graduate',
        'High School Undergraduate',
        'High School Graduate',
        'Senior High School Undergraduate',
        'Senior High School Graduate',
        'Vocational / Technical Course',
        'College Undergraduate',
        'College Graduate',
    ];

    // Fields that drive the course recommender. If any of these change,
    // the saved recommendations are regenerated.
    private const RECOMMENDATION_FIELDS = [
        'preferred_career',
        'expressed_goals',
        'personal_observations',
        'background_circumstances',
    ];

    // Only these fields are ever sent to the OSY. The SK's private interview
    // notes (personal_observations, background_circumstances) are left out.
    private const OWN_PROFILE_FIELDS = [
        'first_name', 'middle_name', 'last_name', 'birthdate', 'sex',
        'address', 'contact_number', 'educational_attainment',
        'preferred_career', 'expressed_goals', 'available_schedule',
        'has_transportation', 'current_status', 'created_at', 'profile_photo_url',
    ];

    private const OSY_ROLE = 'osy';
    private const MIN_AGE = 15;
    private const MAX_AGE = 30;
    private const PHOTO_DIR = 'osy_profiles';

    /* ------------------------------------------------------------------ */
    /* SK side: OSY records                                               */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        return OsyProfile::with(['user:id,email', 'latestReferral'])
            ->latest()
            ->get();
    }

    public function store(Request $request, CourseRecommender $recommender)
    {
        $this->normalizeInput($request);

        $validated = $request->validate(
            $this->profileRules() + $this->accountRules(),
            $this->validationMessages()
        );

        $account = Arr::pull($validated, 'account');
        $validated = $this->attachPhoto($request, $validated);
        $validated['registered_by'] = $request->user()?->id;
        $photoPath = $validated['profile_photo'] ?? null;

        try {
            // Profile and user are saved together: if one fails, neither is kept.
            $osyProfile = DB::transaction(function () use ($validated, $account) {
                if ($account) {
                    $validated['user_id'] = $this->createUser($validated, $account)->id;
                }

                return OsyProfile::create($validated);
            });
        } catch (\Throwable $e) {
            $this->deletePhoto($photoPath); // don't leave an orphan upload behind
            throw $e;
        }

        $this->saveRecommendations($recommender, $osyProfile);

        return response()->json($osyProfile->load('user:id,email'), 201);
    }

    public function show(OsyProfile $osyProfile)
    {
        return $osyProfile->load('user:id,email');
    }

    public function update(Request $request, OsyProfile $osyProfile, CourseRecommender $recommender)
    {
        $this->normalizeInput($request);

        $validated = $request->validate(
            $this->profileRules($osyProfile) + $this->accountRules(),
            $this->validationMessages()
        );

        $account = Arr::pull($validated, 'account');
        $validated = $this->attachPhoto($request, $validated);
        $newPhoto = $validated['profile_photo'] ?? null;
        $oldPhoto = $osyProfile->profile_photo;

        try {
            DB::transaction(function () use ($validated, $account, $osyProfile) {
                // Only create an account if this OSY doesn't already have one
                if ($account && ! $osyProfile->user_id) {
                    $validated['user_id'] = $this->createUser($validated, $account)->id;
                }

                $osyProfile->update($validated);
            });
        } catch (\Throwable $e) {
            $this->deletePhoto($newPhoto);
            throw $e;
        }

        if ($newPhoto) {
            $this->deletePhoto($oldPhoto);
        }

        // Re-save only if the career or notes changed, or nothing was saved yet
        if (
            $osyProfile->wasChanged(self::RECOMMENDATION_FIELDS)
            || ! $osyProfile->recommendations()->exists()
        ) {
            $this->saveRecommendations($recommender, $osyProfile);
        }

        return response()->json($osyProfile->fresh()->load('user:id,email'));
    }

    public function destroy(OsyProfile $osyProfile)
    {
        $photo = $osyProfile->profile_photo;

        DB::transaction(function () use ($osyProfile) {
            $user = $osyProfile->user;

            $osyProfile->delete();

            // Also remove the login account, but only if it is an OSY account,
            // so this can never delete an SK official or admin by mistake.
            if ($user && $user->role === self::OSY_ROLE) {
                $user->tokens()->delete(); // log them out everywhere
                $user->delete();
            }
        });

        $this->deletePhoto($photo);

        return response()->json(['message' => 'OSY record removed.']);
    }

    private function createUser(array $profile, array $account): User
    {
        // forceCreate so this works even if 'role' isn't in User::$fillable.
        // Hash::make is safe with or without the 'hashed' cast (Laravel skips re-hashing).
        return User::forceCreate([
            'name' => trim($profile['first_name'] . ' ' . $profile['last_name']),
            'email' => $account['email'],
            'password' => Hash::make($account['password']),
            'role' => self::OSY_ROLE,
            'email_verified_at' => now(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Input cleanup + validation                                         */
    /* ------------------------------------------------------------------ */

    /** Tidies input before validation so "+63 917 123 4567" is stored as "09171234567". */
    private function normalizeInput(Request $request): void
    {
        $input = ['contact_number' => $this->normalizePhone($request->input('contact_number'))];

        $account = $request->input('account');
        if (is_array($account) && isset($account['email']) && is_string($account['email'])) {
            $account['email'] = strtolower(trim($account['email']));
            $input['account'] = $account;
        }

        $request->merge($input);
    }

    private function normalizePhone(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $v = preg_replace('/[\s()\-]/', '', $value);

        if ($v === '') {
            return null;
        }
        if (preg_match('/^\+639\d{9}$/', $v)) {
            return '0' . substr($v, 3);
        }
        if (preg_match('/^639\d{9}$/', $v)) {
            return '0' . substr($v, 2);
        }
        if (preg_match('/^9\d{9}$/', $v)) {
            return '0' . $v;
        }

        return $v;
    }

    private function accountRules(): array
    {
        return [
            'account' => ['nullable', 'array'],
            'account.email' => ['required_with:account', 'email', 'max:255', 'unique:users,email'],
            'account.password' => [
                'required_with:account',
                'string',
                'max:72',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    private function profileRules(?OsyProfile $existing = null): array
    {
        // Allow an old free-text value to stay as-is when editing an existing record
        $allowedEducation = self::EDUCATIONAL_ATTAINMENTS;
        if ($existing?->educational_attainment) {
            $allowedEducation[] = $existing->educational_attainment;
        }

        $name = ['string', 'max:50', "regex:/^[\\p{L}\\p{M}][\\p{L}\\p{M}\\s.'’-]*$/u"];

        return [
            'first_name' => array_merge(['required', 'min:2'], $name),
            'middle_name' => array_merge(['nullable', 'min:1'], $name),
            'last_name' => array_merge(['required', 'min:2'], $name),
            'birthdate' => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(self::MIN_AGE)->toDateString(),
                'after:' . now()->subYears(self::MAX_AGE + 1)->toDateString(),
            ],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'contact_number' => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'educational_attainment' => ['required', Rule::in($allowedEducation)],
            'preferred_career' => ['nullable', 'string', 'max:255'],
            'available_schedule' => ['nullable', 'string', 'max:255'],
            'has_transportation' => ['boolean'],
            'background_circumstances' => ['nullable', 'string', 'max:1000'],
            'personal_observations' => ['nullable', 'string', 'max:1000'],
            'expressed_goals' => ['nullable', 'string', 'max:1000'],
            'current_status' => ['required', Rule::in(self::STATUSES)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    private function validationMessages(): array
    {
        $min = self::MIN_AGE;
        $max = self::MAX_AGE;

        return [
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens, apostrophes and periods.',
            'middle_name.regex' => 'Middle name can only contain letters, spaces, hyphens, apostrophes and periods.',
            'last_name.regex' => 'Last name can only contain letters, spaces, hyphens, apostrophes and periods.',
            'birthdate.before_or_equal' => "OSY must be at least {$min} years old.",
            'birthdate.after' => "OSY must be {$max} years old or younger.",
            'contact_number.regex' => 'Enter a valid Philippine mobile number starting with 09 (for example, 09171234567).',
            'educational_attainment.in' => 'Select a valid educational attainment.',
            'account.email.unique' => 'This email is already used by another account.',
            'account.password.confirmed' => 'Passwords do not match.',
            'profile_photo.image' => 'The profile photo must be an image.',
            'profile_photo.mimes' => 'The profile photo must be a JPG, PNG or WEBP file.',
            'profile_photo.max' => 'The profile photo must not be larger than 2 MB.',
            'profile_photo.uploaded' => 'The profile photo failed to upload. Try a smaller file.',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Profile photos                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Replaces an uploaded file in the validated data with its stored path.
     * With no upload, the key is dropped, so an existing photo is never wiped by accident.
     */
    private function attachPhoto(Request $request, array $validated): array
    {
        unset($validated['profile_photo']);

        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo'] = $this->storePhoto($request->file('profile_photo'));
        }

        return $validated;
    }

    private function storePhoto(UploadedFile $file): string
    {
        return $file->store(self::PHOTO_DIR, 'public');
    }

    private function deletePhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Course recommendations                                             */
    /* ------------------------------------------------------------------ */

    /** A recommender failure should never block saving the OSY record. */
    private function saveRecommendations(CourseRecommender $recommender, OsyProfile $osyProfile): void
    {
        try {
            $recommender->saveFor($osyProfile);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function formatRecommendations($results)
    {
        return $results->values()->map(function ($r, $i) {
            $program = $r['program'];

            return [
                'rank' => $i + 1,
                'mode' => $r['mode'],
                'basis' => $r['basis'],
                'relation' => $r['relation'],
                'available' => $r['available'],
                'title' => $r['title'],
                'career_path' => $r['career_path'],
                'matched' => $r['matched'],
                // Real details from the training_programs table. Null when Maxima doesn't offer it.
                'program' => $program ? [
                    'id' => $program->id,
                    'name' => $program->name,
                    'description' => $program->description,
                    'image_url' => $program->image_url,
                    // add any other training_programs columns you want shown (duration, schedule, slots...)
                ] : null,
            ];
        });
    }

    // GET: recommendations for a saved OSY (saved copy first, live calculation if none)
    public function recommendations(OsyProfile $osyProfile, CourseRecommender $recommender)
    {
        $saved = $recommender->saved($osyProfile);

        return response()->json([
            'data' => $this->formatRecommendations(
                $saved->isNotEmpty() ? $saved : $recommender->recommend($osyProfile)
            ),
        ]);
    }

    // POST: preview from unsaved interview answers (used while the form is open)
    public function previewRecommendations(Request $request, CourseRecommender $recommender)
    {
        $data = $request->validate([
            'preferred_career' => ['nullable', 'string', 'max:255'],
            'expressed_goals' => ['nullable', 'string'],
            'personal_observations' => ['nullable', 'string'],
            'background_circumstances' => ['nullable', 'string'],
        ]);

        $osy = (new OsyProfile())->forceFill($data);

        return response()->json([
            'data' => $this->formatRecommendations($recommender->recommend($osy)),
        ]);
    }
        // GET: every OSY with their saved recommendations (SK recommendation page)
    public function allRecommendations()
    {
        $profiles = OsyProfile::with(['recommendations.trainingProgram', 'latestReferral'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'data' => $profiles->map(fn ($osy) => [
                'id'                     => $osy->id,
                'name'                   => trim($osy->first_name . ' ' . $osy->last_name),
                'address'                => $osy->address,
                'preferred_career'       => $osy->preferred_career,
                'educational_attainment' => $osy->educational_attainment,
                'status'                 => $osy->display_status,
                'profile_photo_url'      => $osy->profile_photo_url,
                'recommendations'        => $osy->recommendations->map(fn ($r) => [
                    'rank'         => $r->rank,
                    'title'        => $r->trainingProgram?->name ?? $r->course_name,
                    'career_path'  => $r->career_path,
                    'available'    => (bool) $r->trainingProgram,
                    'image_url'    => $r->trainingProgram?->image_url,
                    'matched'      => $r->matched_terms ?? [],
                    'score'        => round($r->match_score ?? 0, 1),
                    'generated_at' => $r->generated_at,
                ])->values(),
            ])->values(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* OSY side: the logged-in OSY's own data                             */
    /* ------------------------------------------------------------------ */

    /** The profile linked to the logged-in user. Never looked up from the URL. */
    private function ownProfile(Request $request): ?OsyProfile
    {
        return OsyProfile::where('user_id', $request->user()->id)->first();
    }

    private function ownProfilePayload(OsyProfile $osy): array
    {
        $osy->loadMissing('user:id,email');

        return Arr::only($osy->toArray(), self::OWN_PROFILE_FIELDS)
            + ['email' => $osy->user?->email];
    }

    private function noProfileResponse(): JsonResponse
    {
        return response()->json(['message' => 'No OSY profile is linked to this account.'], 404);
    }

    // GET: the logged-in OSY's saved course recommendations
    public function myRecommendations(Request $request)
    {
        $osy = $this->ownProfile($request);

        if (! $osy) {
            return response()->json(['data' => []]);
        }

        $rows = $osy->recommendations()
            ->whereHas('trainingProgram', fn ($q) => $q->where('status', 'active'))
            ->with('trainingProgram')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'rank'             => $r->rank,
                'id'               => $r->trainingProgram->id,
                'title'            => $r->trainingProgram->name,
                'description'      => $r->trainingProgram->description,
                'image_url'        => $r->trainingProgram->image_url,
                'requirements'     => $r->trainingProgram->requirements,
                'schedule'         => $r->trainingProgram->schedule,
                'slots'            => $r->trainingProgram->slots,
                'tesda_accredited' => $r->trainingProgram->tesda_accredited,
                'matched'          => $r->matched_terms ?? [],
                'score'            => round($r->match_score ?? 0, 1),
                'based_on'         => $r->based_on,
                'generated_at'     => $r->generated_at,
            ])->values(),
        ]);
    }

    // GET: the logged-in OSY's profile
    public function myProfile(Request $request)
    {
        $osy = $this->ownProfile($request);

        if (! $osy) {
            return $this->noProfileResponse();
        }

        return response()->json(['data' => $this->ownProfilePayload($osy)]);
    }

    // PUT: the OSY can only change these four fields
    public function updateMyProfile(Request $request)
    {
        $osy = $this->ownProfile($request);

        if (! $osy) {
            return $this->noProfileResponse();
        }

        $request->merge([
            'contact_number' => $this->normalizePhone($request->input('contact_number')),
        ]);

        $data = $request->validate([
            'contact_number'     => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'address'            => ['required', 'string', 'min:5', 'max:255'],
            'available_schedule' => ['nullable', 'string', 'max:255'],
            'has_transportation' => ['boolean'],
        ], $this->validationMessages());

        $osy->update($data);

        return response()->json(['data' => $this->ownProfilePayload($osy->fresh())]);
    }

    // POST: the logged-in OSY changes their own photo
    public function updateMyPhoto(Request $request)
    {
        $osy = $this->ownProfile($request);

        if (! $osy) {
            return $this->noProfileResponse();
        }

        $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], $this->validationMessages());

        $old = $osy->profile_photo;
        $osy->update(['profile_photo' => $this->storePhoto($request->file('profile_photo'))]);
        $this->deletePhoto($old);

        return response()->json(['data' => $this->ownProfilePayload($osy->fresh())]);
    }
}