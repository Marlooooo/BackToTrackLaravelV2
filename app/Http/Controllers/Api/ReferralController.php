<?php

   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Models\OsyProfile;
   use App\Models\Referral;
   use App\Models\ReferralStatusLog;
   use App\Models\TrainingProgram;
   use Illuminate\Http\Request;
   use Illuminate\Support\Facades\DB;

   class ReferralController extends Controller
   {
      /**
       * GET /api/referrals?search=&status=
       */
      public function index(Request $request)
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
               ->when($request->status, function ($q, $group) {
                  $statuses = Referral::statusesForGroup($group);
                  if ($statuses) {
                     $q->whereIn('status', $statuses);
                  }
               })
               ->when($request->search, function ($q, $search) {
                  $q->whereHas('osyProfile', function ($p) use ($search) {
                     $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
               })
               ->latest()
               ->get();

         $sumGroup = fn (array $statuses) => (int) collect($statuses)->sum(fn ($st) => $counts[$st] ?? 0);

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
      public function osyOptions(Request $request)
      {
         $profiles = OsyProfile::query()
               // OPTIONAL: only show OSYs this official registered.
               // Uncomment if that is how your system should work:
               // ->where('registered_by', $request->user()->id)
               ->when($request->search, function ($q, $search) {
                  $q->where(function ($w) use ($search) {
                     $w->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
               })
               ->select('id', 'first_name', 'middle_name', 'last_name', 'address', 'preferred_career')
               ->orderBy('last_name')
               ->limit(20)
               ->get();

         return response()->json($profiles);
      }

      /**
       * GET /api/referrals/program-options
       * Training programs a referral can be made to.
       */
      public function programOptions()
      {
         return response()->json(TrainingProgram::orderBy('id', 'desc')->get());
      }

      /**
       * POST /api/referrals
       */
      public function store(Request $request)
      {
         $data = $request->validate([
               'osy_profile_id'      => 'required|exists:osy_profiles,id',
               'training_program_id' => 'required|exists:training_programs,id',
               'remarks'             => 'nullable|string|max:1000',
         ]);

         // One open (pending-group) referral per OSY per program
         $duplicate = Referral::where('osy_profile_id', $data['osy_profile_id'])
               ->where('training_program_id', $data['training_program_id'])
               ->whereIn('status', Referral::PENDING_STATUSES)
               ->exists();

         if ($duplicate) {
               return response()->json([
                  'message' => 'This OSY already has a pending referral to that program.',
               ], 422);
         }

         // Create the referral and its first log entry together, or neither.
         $referral = DB::transaction(function () use ($data, $request) {
               $referral = Referral::create([
                  ...$data,
                  'referred_by' => $request->user()->id,
                  'status'      => 'Referred',
               ]);

               ReferralStatusLog::create([
                  'referral_id' => $referral->id,
                  'changed_by'  => $request->user()->id,
                  'status'      => 'Referred',
                  'remarks'     => $data['remarks'] ?? 'Referral created',
               ]);

               return $referral;
         });

         return response()->json($referral->load('osyProfile', 'trainingProgram'), 201);
      }

      public function destroy(Request $request, Referral $referral)
      {
         // SK can only delete referrals they made themselves
         abort_unless($referral->referred_by === $request->user()->id, 403, 'You can only delete your own referrals.');

         // Once Maxima has acted on it, it can no longer be deleted
         if ($referral->status !== 'Referred') {
            return response()->json([
               'message' => 'This referral is already being processed and can no longer be deleted.',
            ], 422);
         }

         DB::transaction(function () use ($referral) {
            $referral->statusLogs()->delete();
            $referral->delete();
         });

         return response()->json(['message' => 'Referral deleted.']);
      }
   }