<?php

   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Models\Referral;
   use App\Models\TrainingProgram;
   use Illuminate\Http\Request;

   class MaximaDashboardController extends Controller
   {
      private const REFERRED       = 'Referred';
      private const COMPLETED      = 'Completed';
      private const PROGRAM_ACTIVE = 'active';

      public function index(Request $request)
      {
         $user = $request->user();
         abort_unless($user->role === 'maxima_tesda_school', 403, 'Unauthorized');

         return response()->json([
               'user'                 => $user->only(['name', 'email']),
               'unread_notifications' => $user->unreadNotifications()->count(),
               'stats'                => $this->stats(),
               'recent_referrals'     => $this->recentReferrals(),
               'ongoing_trainings'    => $this->ongoingTrainings(),
         ]);
      }

      private function stats(): array
      {
         return [
               'pending_referrals'   => Referral::where('status', self::REFERRED)->count(),
               'completed_this_year' => Referral::where('status', self::COMPLETED)
                                          ->whereYear('updated_at', now()->year)->count(),
               'active_trainings'    => TrainingProgram::where('status', self::PROGRAM_ACTIVE)->count(),
               'enrollees'           => Referral::whereIn('status', Referral::APPROVED_STATUSES)->count(),
         ];
      }

      // Referrals that actually reached Maxima (skips Registered/Validated)
      private function recentReferrals()
      {
         $statuses = [self::REFERRED, ...Referral::APPROVED_STATUSES, ...Referral::REJECTED_STATUSES];

         return Referral::with(['osyProfile', 'trainingProgram'])
               ->whereIn('status', $statuses)
               ->latest()
               ->take(10)
               ->get()
               ->map(fn ($r) => [
                  'id'     => $r->id,
                  'name'   => $this->formatName($r->osyProfile),
                  'course' => $r->trainingProgram->name ?? '—',
                  'date'   => $r->created_at->format('F j, Y'),
                  'status' => $r->status,
               ]);
      }

      private function ongoingTrainings()
      {
         return TrainingProgram::where('status', self::PROGRAM_ACTIVE)
               ->withCount(['referrals as enrollees_count' => fn ($q) => $q->whereIn('status', Referral::APPROVED_STATUSES)])
               ->orderByDesc('enrollees_count')
               ->take(10)
               ->get()
               ->map(fn ($p) => [
                  'id'        => $p->id,
                  'title'     => $p->name,
                  'enrollees' => $p->enrollees_count,
                  'status'    => $p->status,
               ]);
      }

      // "Last, First M."
      private function formatName($profile): string
      {
         if (!$profile) {
               return '—';
         }

         $middle = $profile->middle_name ? ' ' . strtoupper($profile->middle_name[0]) . '.' : '';

         return "{$profile->last_name}, {$profile->first_name}{$middle}";
      }
   }