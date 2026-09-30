<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OsyProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Services\CourseRecommender;


class OsyProfileController extends Controller
{
    private const STATUSES = [
        'Registered',
        'Validated',
        'Referred',
        'Accepted by TESDA',
        'Training Started',
        'Completed',
    ];

    // Change this to whatever value your app uses for OSY users
    private const OSY_ROLE = 'osy';

    public function index(Request $request)
    {
        return OsyProfile::with('user:id,email')->latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->profileRules() + $this->accountRules());

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

        return response()->json($osyProfile->load('user:id,email'), 201);
    }

    public function show(OsyProfile $osyProfile)
    {
        return $osyProfile->load('user:id,email');
    }

    public function update(Request $request, OsyProfile $osyProfile)
    {
        $validated = $request->validate($this->profileRules() + $this->accountRules());

        $account = $validated['account'] ?? null;
        unset($validated['account']);

        DB::transaction(function () use ($validated, $account, $osyProfile) {
            // Only create an account if this OSY doesn't already have one
            if ($account && !$osyProfile->user_id) {
                $validated['user_id'] = $this->createUser($validated, $account)->id;
            }

            $osyProfile->update($validated);
        });

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

    private function accountRules(): array
    {
        return [
            'account' => ['nullable', 'array'],
            'account.email' => ['required_with:account', 'email', 'max:255', 'unique:users,email'],
            'account.password' => ['required_with:account', 'string', 'min:8', 'confirmed'],
        ];
    }

    private function profileRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'address' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'educational_attainment' => ['required', 'string', 'max:255'],
            'preferred_career' => ['nullable', 'string', 'max:255'],
            'available_schedule' => ['nullable', 'string', 'max:255'],
            'has_transportation' => ['boolean'],
            'background_circumstances' => ['nullable', 'string'],
            'personal_observations' => ['nullable', 'string'],
            'expressed_goals' => ['nullable', 'string'],
            'current_status' => ['required', Rule::in(self::STATUSES)],
        ];
    }

    private function formatRecommendations($results)
        {
            return $results->map(fn ($r) => [
                'id' => $r['program']->id,
                'title' => $r['program']->name,
                'description' => $r['program']->description,
                'image_url' => $r['program']->image_url,
                'matched' => $r['matched'],
                'score' => $r['score'],
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
}