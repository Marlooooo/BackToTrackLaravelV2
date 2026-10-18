<?php

   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Models\OsyProfile;
   use App\Models\Referral;
   use App\Models\ReferralStatusLog;
   use App\Models\TrainingProgram;
   use App\Models\User;
   use App\Notifications\NewReferral;
   use Illuminate\Database\Eloquent\Builder;
   use Illuminate\Http\JsonResponse;
   use Illuminate\Http\Request;
   use Illuminate\Support\Facades\DB;
   use Illuminate\Support\Facades\Notification;
   use Illuminate\Validation\Rule;

   class ReferralController extends Controller
   {
      private const PROGRAM_ACTIVE = 'active';

      // Referrals in these statuses block a new referral to the same program
      private const BLOCKING_STATUSES = [
         ...Referral::PENDING_STATUSES,
         'Accepted by Maxima',
         'Training Started',
      ];

      /* ------------------------------------------------------------------ */
      /* Helpers                                                            */
      /* ------------------------------------------------------------------ */

      /**
       * Every word must match the first, middle or last name,
       * so "Juan Cruz" finds "Juan Dela Cruz".
       */
      private function matchName(Builder $query, string $search): Builder
      {
         $terms = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);

         foreach ($terms as $term) {
               $query->where(function ($name) use ($term) {
                  $name->where('first_name', 'like', "%{$term}%")
                     ->orWhere('middle_name', 'like', "%{$term}%")
                     ->orWhere('last_name', 'like', "%{$term}%");
               });
         }

         return $query;
      }

      /* ------------------------------------------------------------------ */
      /* Endpoints                                                          */
      /* ------------------------------------------------------------------ */

      /**
       * GET /api/referrals?search=&status=approved|pending|rejected
       */
      public function index(Request $request): JsonResponse
      {
         $userId = $request->user()->id;

         // Totals ignore search/status filters so the boxes always show true totals.
         $counts = Referral::where('referred_by', $userId)
               ->selectRaw('status, COUNT(*) as total')
               ->groupBy('status')
               ->pluck('total', 'status');

         $referrals = Referral::with([
                  'osyProfile:id,first_name,middle_name,last_name',
                  'trainingProgram',
               ])
               ->where('referred_by', $userId)
               ->when($request->input('status'), function ($q, $group) {
                  $statuses = Referral::statusesForGroup($group);

                  if ($statuses) {
                     $q->whereIn('status', $statuses);
                  }
               })
               ->when($request->filled('search'), function ($q) use ($request) {
                  $q->whereHas('osyProfile', function ($profile) use ($request) {
                     $this->matchName($profile, $request->input('search'));
                  });
               })
               ->latest()
               ->latest('id')
               ->get();

         $sumGroup = fn (array $statuses) => (int) collect($statuses)->sum(fn ($status) => $counts[$status] ?? 0);

         return response()->json([
               'counts' => [
                  'approved' => $sumGroup(Referral::APPROVED_STATUSES),
                  'pending'  => $sumGroup(Referral::PENDING_STATUSES),
                  'rejected' => $sumGroup(Referral::REJECTED_STATUSES),
               ],
               'referrals' => $referrals,
         ]);
      }

      /**
       * GET /api/referrals/osy-options?search=
       * OSY profiles the official can pick from.
       */
      public function osyOptions(Request $request): JsonResponse
      {
         $profiles = OsyProfile::query()
               // OPTIONAL: only show OSYs this official registered.
               // Uncomment if that is how your system should work:
               // ->where('registered_by', $request->user()->id)
               ->when($request->filled('search'), function ($q) use ($request) {
                  $this->matchName($q, $request->input('search'));
               })
               ->select('id', 'first_name', 'middle_name', 'last_name', 'address', 'preferred_career')
               ->orderBy('last_name')
               ->orderBy('first_name')
               ->limit(20)
               ->get();

         return response()->json($profiles);
      }

      /**
       * GET /api/referrals/program-options
       * Active training programs a referral can be made to.
       */
      public function programOptions(): JsonResponse
      {
         return response()->json(
               TrainingProgram::where('status', self::PROGRAM_ACTIVE)
                  ->orderBy('id', 'desc')
                  ->get()
         );
      }

      /**
       * POST /api/referrals
       */
      public function store(Request $request): JsonResponse
      {
         $data = $request->validate([
               'osy_profile_id'      => 'required|exists:osy_profiles,id',
               'training_program_id' => [
                  'required',
                  Rule::exists('training_programs', 'id')->where('status', self::PROGRAM_ACTIVE),
               ],
               'remarks'             => 'nullable|string|max:1000',
         ], [
               'training_program_id.exists' => 'That training program is not available.',
         ]);

         // Create the referral and its first log entry together, or neither.
         $referral = DB::transaction(function () use ($data, $request) {
               // Lock the OSY row so two officials cannot refer the same OSY
               // to the same program at the same moment.
               OsyProfile::whereKey($data['osy_profile_id'])->lockForUpdate()->firstOrFail();

               // One active referral per OSY per program (pending, accepted or enrolled).
               // Rejected, dropped and completed referrals allow a new one.
               $duplicate = Referral::where('osy_profile_id', $data['osy_profile_id'])
                  ->where('training_program_id', $data['training_program_id'])
                  ->whereIn('status', self::BLOCKING_STATUSES)
                  ->exists();

               abort_if($duplicate, 422, 'This OSY already has an active referral to that program.');

               $remarks = isset($data['remarks']) ? trim($data['remarks']) : null;

               $referral = Referral::create([
                  'osy_profile_id'      => $data['osy_profile_id'],
                  'training_program_id' => $data['training_program_id'],
                  'remarks'             => $remarks ?: null,
                  'referred_by'         => $request->user()->id,
                  'status'              => 'Referred',
               ]);

               ReferralStatusLog::create([
                  'referral_id' => $referral->id,
                  'changed_by'  => $request->user()->id,
                  'status'      => 'Referred',
                  'remarks'     => $remarks ?: 'Referral created',
               ]);

               return $referral;
         });

         // Sent after the transaction commits, so Maxima is only notified of referrals that really exist.
         try {
               $maximaUsers = User::where('role', 'maxima_tesda_school')->get();
               Notification::send($maximaUsers, new NewReferral($referral));
         } catch (\Throwable $e) {
               // A notification failure should not fail a referral that is already saved.
               report($e);
         }

         return response()->json($referral->load('osyProfile', 'trainingProgram'), 201);
      }

      /**
       * DELETE /api/referrals/{referral}
       */
      public function destroy(Request $request, Referral $referral): JsonResponse
      {
         // SK can only delete referrals they made themselves
         abort_unless($referral->referred_by === $request->user()->id, 403, 'You can only delete your own referrals.');

         DB::transaction(function () use ($referral) {
               // Re-check under a lock, so a referral Maxima just accepted cannot be deleted.
               $current = Referral::whereKey($referral->id)->lockForUpdate()->firstOrFail();

               abort_if(
                  $current->status !== 'Referred',
                  422,
                  'This referral is already being processed and can no longer be deleted.'
               );

               $current->statusLogs()->delete();
               $current->delete();
         });

         return response()->json(['message' => 'Referral deleted.']);
      }
   }