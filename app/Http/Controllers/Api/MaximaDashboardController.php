<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MaximaDashboardController extends Controller
{
   private const REFERRED       = 'Referred';
   private const COMPLETED      = 'Completed';
   private const PROGRAM_ACTIVE = 'active';
   private const LIST_LIMIT     = 10;

   public function index(Request $request): JsonResponse
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
         // Uses the date the training ended, so later edits to the record
         // cannot move a completion into a different year.
         'completed_this_year' => Referral::where('status', self::COMPLETED)
            ->whereRaw('YEAR(COALESCE(training_end_date, updated_at)) = ?', [now()->year])
            ->count(),
         'active_trainings'    => TrainingProgram::where('status', self::PROGRAM_ACTIVE)->count(),
         'enrollees'           => Referral::whereIn('status', Referral::APPROVED_STATUSES)->count(),
      ];
   }

   // Referrals that actually reached Maxima (skips Registered/Validated)
   private function recentReferrals(): Collection
   {
      $statuses = [self::REFERRED, ...Referral::APPROVED_STATUSES, ...Referral::REJECTED_STATUSES];

      return Referral::with(['osyProfile', 'trainingProgram'])
         ->whereIn('status', $statuses)
         ->latest()
         ->latest('id')
         ->take(self::LIST_LIMIT)
         ->get()
         ->map(fn ($r) => [
            'id'     => $r->id,
            'name'   => $this->formatName($r->osyProfile),
            'course' => $r->trainingProgram->name ?? '—',
            'date'   => $r->created_at?->format('F j, Y') ?? '—',
            'status' => $r->status,
         ]);
   }

   private function ongoingTrainings(): Collection
   {
      return TrainingProgram::where('status', self::PROGRAM_ACTIVE)
         ->withCount(['referrals as enrollees_count' => fn ($q) => $q->whereIn('status', Referral::APPROVED_STATUSES)])
         ->orderByDesc('enrollees_count')
         ->orderBy('name')
         ->take(self::LIST_LIMIT)
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
      if (! $profile) {
         return '—';
      }

      $middle = $profile->middle_name ? ' ' . strtoupper($profile->middle_name[0]) . '.' : '';

      return "{$profile->last_name}, {$profile->first_name}{$middle}";
   }
}