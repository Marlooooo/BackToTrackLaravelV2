<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\OsyProfile;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\AbsenceReasonSubmitted;
use App\Notifications\NewReferral;
use App\Notifications\TrainingStatusChanged;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class NotificationSeeder extends Seeder
{
   // notifications created on or after this date stay unread
   private const UNREAD_FROM = '2026-11-12';

   public function run(): void
   {
      $maxima = User::where('role', 'maxima_tesda_school')->first();

      if (!$maxima) {
         $this->command->error('MAXIMA user missing. Run UserSeeder first.');
         return;
      }

      $osyUserIds = User::where('email', 'like', 'osy%@backtotrack.test')->pluck('id');
      $profileIds = OsyProfile::whereIn('user_id', $osyUserIds)->pluck('id');

      $referrals = Referral::with(['osyProfile', 'trainingProgram', 'referrer'])
         ->whereIn('osy_profile_id', $profileIds)
         ->orderBy('id')
         ->get();

      if ($referrals->isEmpty()) {
         $this->command->warn('No referrals found. Run ReferralSeeder first.');
         return;
      }

      DB::transaction(function () use ($referrals, $maxima) {
         $reasonCount = 0;

         foreach ($referrals as $referral) {
            $sk = $referral->referrer;
            $logs = DB::table('referral_status_logs')
               ->where('referral_id', $referral->id)
               ->pluck('created_at', 'status');

            // maxima: new referral
            $this->put($maxima, new NewReferral($referral), Carbon::parse($referral->created_at), "new_referral:{$referral->id}");

            if (!$sk) {
               continue;
            }

            // sk: status updates
            foreach (['Accepted by Maxima', 'Training Started', 'Completed', 'Rejected'] as $step) {
               if (!isset($logs[$step])) {
                  continue;
               }

               $detail = $step === 'Rejected' && $referral->remarks ? rtrim($referral->remarks, '.') : null;

               $this->put($sk, new TrainingStatusChanged($referral, $step, $detail), Carbon::parse($logs[$step]), "status:{$step}:{$referral->id}");
            }

            if ($referral->status === 'Dropped' && $referral->training_end_date) {
               $this->put(
                  $sk,
                  new TrainingStatusChanged($referral, 'Dropped', $referral->drop_reason),
                  Carbon::parse($referral->training_end_date)->setTime(9, 0),
                  "status:Dropped:{$referral->id}"
               );
            }

            // sk: reason received for low attendance trainees
            if ($referral->status === 'Training Started' && $this->isLowAttendance($referral)) {
               $at = $reasonCount === 0 ? '2026-11-19 10:00:00' : '2026-11-18 15:30:00';
               $this->put($sk, new AbsenceReasonSubmitted($referral, 3), Carbon::parse($at), "reason:{$referral->id}");
               $reasonCount++;
            }
         }
      });
   }

   private function isLowAttendance(Referral $referral): bool
   {
      $total = AttendanceRecord::where('referral_id', $referral->id)->count();

      if ($total === 0) {
         return false;
      }

      $attended = AttendanceRecord::where('referral_id', $referral->id)
         ->whereIn('status', ['present', 'late'])
         ->count();

      return ($attended / $total) < 0.5;
   }

   private function put(User $to, Notification $notification, Carbon $at, string $key): void
   {
      $id = Uuid::uuid5(Uuid::NAMESPACE_URL, "seed:{$key}:{$to->id}")->toString();
      $isRead = $at->lt(Carbon::parse(self::UNREAD_FROM));

      DB::table('notifications')->updateOrInsert(
         ['id' => $id],
         [
            'type' => get_class($notification),
            'notifiable_type' => $to->getMorphClass(),
            'notifiable_id' => $to->id,
            'data' => json_encode($notification->toArray($to)),
            'read_at' => $isRead ? $at->copy()->addDay()->toDateTimeString() : null,
            'created_at' => $at->toDateTimeString(),
            'updated_at' => $at->toDateTimeString(),
         ]
      );
   }
}