<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsenceConcern;
use App\Models\OsyProfile;
use App\Models\Referral;
use App\Notifications\AbsenceReasonSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BarangayDashboardController extends Controller
{
   private const LIST_LIMIT = 6;

   private const IN_TRAINING = 'Training Started';
   private const COMPLETED   = 'Completed';

   // Referred to Maxima but training has not started yet
   private const ACTIVE_REFERRAL_STATUSES = ['Referred', 'Accepted by Maxima'];

   // OSY who still need to be referred
   private const AWAITING_REFERRAL_STATUSES = ['Registered', 'Validated'];

   public function index(Request $request): JsonResponse
   {
      $user = $request->user();
      abort_unless($user->role === 'sk_officials', 403, 'Unauthorized');

      return response()->json([
         'user'                 => $user->only(['name', 'email']),
         'unread_notifications' => $user->unreadNotifications()->count(),
         'stats'                => $this->stats(),
         'attention'            => $this->attention($user),
         'recent_referrals'     => $this->recentReferrals(),
      ]);
   }

   private function stats(): array
   {
      return [
         'total_osy'        => OsyProfile::count(),
         'active_referrals' => Referral::whereIn('status', self::ACTIVE_REFERRAL_STATUSES)->count(),
         'in_training'      => Referral::where('status', self::IN_TRAINING)->count(),
         'completed'        => Referral::where('status', self::COMPLETED)->count(),
      ];
   }

   private function attention($user): array
   {
      return [
         // Concerns from Maxima that no SK official has reviewed yet
         'concerns' => AbsenceConcern::whereNull('reviewed_by')->count(),

         // Absence reasons the OSY submitted that SK has not opened yet
         'reasons' => $user->unreadNotifications()
            ->where('type', AbsenceReasonSubmitted::class)
            ->count(),

         // OSY with no referral yet, or still at Registered / Validated
         'awaiting_referral' => OsyProfile::with('latestReferral')
            ->get()
            ->filter(fn ($profile) => ! $profile->latestReferral
               || in_array($profile->latestReferral->status, self::AWAITING_REFERRAL_STATUSES, true))
            ->count(),
      ];
   }

   // Referrals that actually reached Maxima (skips Registered/Validated)
   private function recentReferrals(): Collection
   {
      $statuses = ['Referred', ...Referral::APPROVED_STATUSES, ...Referral::REJECTED_STATUSES];

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