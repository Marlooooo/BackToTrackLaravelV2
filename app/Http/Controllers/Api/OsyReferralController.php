<?php

   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Models\DefaultRequirement;
   use App\Models\OsyProfile;
   use App\Models\Referral;
   use App\Models\ReferralDocument;
   use App\Models\User;
   use Illuminate\Http\JsonResponse;
   use Illuminate\Http\Request;

   class OsyReferralController extends Controller
   {
      /**
       * GET /api/osy/referrals
       * The logged-in OSY's own referrals. Read-only.
       */
      public function index(Request $request): JsonResponse
      {
         abort_unless($request->user()->role === 'osy', 403, 'Unauthorized');

         $profile = OsyProfile::where('user_id', $request->user()->id)->first();

         if (! $profile) {
               return response()->json(['referrals' => []]);
         }

         $referrals = Referral::with([
                  'trainingProgram',
                  'referrer',
                  'statusLogs' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
               ])
               ->where('osy_profile_id', $profile->id)
               ->latest()
               ->latest('id')
               ->get();

         $docsBy = ReferralDocument::whereIn('referral_id', $referrals->pluck('id'))
               ->orderBy('id')
               ->get()
               ->groupBy('referral_id');

         $reviewerNames = User::whereIn(
                  'id',
                  $referrals->pluck('statusLogs')->flatten()->pluck('changed_by')->filter()->unique()
               )
               ->pluck('name', 'id');

         $requirements = DefaultRequirement::orderBy('id')->pluck('name');

         $payload = $referrals->map(function (Referral $referral) use ($docsBy, $reviewerNames, $requirements) {
               $history = $referral->statusLogs->map(fn ($log) => [
                  'status'     => $log->status,
                  'remarks'    => $log->remarks,
                  'changed_by' => $reviewerNames[$log->changed_by] ?? null,
                  'created_at' => $log->created_at,
               ])->values();

               $rejection = $referral->status === 'Rejected'
                  ? ($history->where('status', 'Rejected')->last()['remarks'] ?? $referral->remarks)
                  : null;

               $isAccepted = in_array($referral->status, Referral::APPROVED_STATUSES, true)
                  || $referral->status === 'Dropped';

               $docs       = $isAccepted ? $docsBy->get($referral->id, collect()) : collect();
               $applicable = $docs->where('status', '!=', 'not_applicable');

               return [
                  'id'             => $referral->id,
                  'status'         => $referral->status,
                  'referred_at'    => $referral->created_at,
                  'program'        => $referral->trainingProgram ? [
                     'id'   => $referral->trainingProgram->id,
                     'name' => $referral->trainingProgram->name,
                  ] : null,
                  // Only the name and role, nothing else about the referrer
                  'referrer'       => $referral->referrer ? [
                     'name' => $referral->referrer->name,
                     'role' => $referral->referrer->role,
                  ] : null,
                  'history'        => $history,
                  'rejection_note' => $rejection,
                  'documents'      => $docs->map(fn ($d) => [
                     'id'          => $d->id,
                     'name'        => $d->name,
                     'status'      => $d->status,
                     'received_at' => $d->received_at,
                     'remarks'     => $d->remarks,
                  ])->values(),
                  'docs_required'  => $applicable->count(),
                  'docs_received'  => $applicable->where('status', 'received')->count(),
                  // Only while waiting, so the OSY can start preparing
                  'requirements'   => $referral->status === 'Referred' ? $requirements : [],
               ];
         });

         return response()->json(['referrals' => $payload]);
      }
   }