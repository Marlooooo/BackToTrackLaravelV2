<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OsyProfile;
use App\Models\User;
use App\Services\CourseRecommender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    private const OSY_ROLE = 'osy';
    private const MIN_AGE = 15;
    private const MAX_AGE = 30;

    public function index(Request $request)
    {
        return OsyProfile::with('user:id,email')->latest()->get();
    }

    public function store(Request $request, CourseRecommender $recommender)
    {
        $this->normalizeInput($request);

        $validated = $request->validate(
            $this->profileRules() + $this->accountRules(),
            $this->validationMessages()
        );

        $account = $validated['account'] ?? null;
        unset($validated['account']);

        $validated['registered_by'] = $request->user()?->id;

        // Profile and user are saved together: if one fails, neither is kept.
        $osyProfile = DB::transaction(function () use ($validated, $account) {
            if ($account) {
                $validated['user_id'] = $this->createUser($validated, $account)->id;
            }

            return OsyProfile::create($validated);
        });

        $this->refreshRecommendations($osyProfile, $recommender);

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

        $account = $validated['account'] ?? null;
        unset($validated['account']);

        DB::transaction(function () use ($validated, $account, $osyProfile) {
            // Only create an account if this OSY doesn't already have one
            if ($account && !$osyProfile->user_id) {
                $validated['user_id'] = $this->createUser($validated, $account)->id;
            }

            $osyProfile->update($validated);
        });

        // Regenerate only when the interview fields changed, or when this OSY
        // has no saved recommendations yet (covers OSYs registered before this feature).
        if ($osyProfile->wasChanged(self::RECOMMENDATION_FIELDS)
            || !$osyProfile->recommendations()->exists()) {
            $this->refreshRecommendations($osyProfile, $recommender);
        }

        return response()->json($osyProfile->fresh()->load('user:id,email'));
    }

    public function destroy(OsyProfile $osyProfile)
    {
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
        if (!is_string($value)) {
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
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Course recommendations                                             */
    /* ------------------------------------------------------------------ */

    private function formatRecommendations($results)
    {
        return $results->values()->map(fn ($r, $i) => [
            'rank' => $i + 1,
            'id' => $r['program']->id,
            'title' => $r['program']->name,
            'description' => $r['program']->description,
            'image_url' => $r['program']->image_url,
            'matched' => $r['matched'],
            'score' => round($r['score'], 1),
            'based_on' => $r['based_on'] ?? null,
        ]);
    }

    // GET: recommendations for a saved OSY
    public function recommendations(OsyProfile $osyProfile, CourseRecommender $recommender)
    {
        return response()->json([
            'data' => $this->formatRecommendations($recommender->recommend($osyProfile)),
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

    /** A recommender failure should never block saving the OSY record. */
    private function refreshRecommendations(OsyProfile $osy, CourseRecommender $recommender): void
    {
        try {
            $recommender->recommendAndSave($osy);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}