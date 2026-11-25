<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\OsyProfile;
use App\Models\Referral;
use App\Models\TrainingProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferralSeeder extends Seeder
{
   private const TODAY = '2026-11-20';
   private const VENUE = 'Maxima TESDA Training Center';

   // key => [program name, trainer]
   private const PROGRAMS = [
      'smaw'       => ['Shielded Metal Arc Welding (SMAW) NC II', 'Mr. Dennis Ocampo'],
      'bread'      => ['Bread and Pastry Production NC II', 'Ms. Elena Robles'],
      'css'        => ['Computer Systems Servicing NC II', 'Mr. Allan Mercado'],
      'electrical' => ['Electrical Installation and Maintenance NC II', 'Mr. Ramil Cortez'],
      'housekeep'  => ['Housekeeping NC II', 'Ms. Nora Dizon'],
      'cookery'    => ['Cookery NC II', 'Chef Marco Libunao'],
      'motorcycle' => ['Motorcycle and Small Engine Servicing NC II', 'Mr. Jun Pangilinan'],
      'carpentry'  => ['Carpentry NC II', 'Mr. Teodoro Sison'],
      'barista'    => ['Barista NC II', 'Ms. Kath Manansala'],
      'hair'       => ['Hairdressing NC II', 'Ms. Joy Banzon'],
      'dress'      => ['Dressmaking NC II', 'Ms. Luz Quiambao'],
      'caregiving' => ['Caregiving NC II', 'Ms. Ana Villaruz'],
   ];

   public function run(): void
   {
      $sk = User::where('role', 'sk_officials')->first();
      $maxima = User::where('role', 'maxima_tesda_school')->first();

      if (!$sk || !$maxima) {
         $this->command->error('SK or MAXIMA user missing. Run UserSeeder first.');
         return;
      }

      DB::transaction(function () use ($sk, $maxima) {
         foreach ($this->rows() as $r) {
            $user = User::where('email', sprintf('osy%d@backtotrack.test', $r['osy']))->first();
            $profile = $user ? OsyProfile::where('user_id', $user->id)->first() : null;

            [$programName, $trainer] = self::PROGRAMS[$r['program']];
            $program = TrainingProgram::where('name', $programName)->first();

            if (!$profile || !$program) {
               $this->command->warn("Skipped osy{$r['osy']} ({$r['program']}): profile or program not found.");
               continue;
            }

            $this->seedReferral($r, $profile, $program, $trainer, $sk, $maxima);
         }
      });
   }

   private function seedReferral(array $r, OsyProfile $profile, TrainingProgram $program, string $trainer, User $sk, User $maxima): void
   {
      $status = $r['status'];
      $referred = Carbon::parse($r['referred']);
      $start = isset($r['start']) ? Carbon::parse($r['start']) : null;
      $end = isset($r['end']) ? Carbon::parse($r['end']) : null;

      // referral row
      $referral = Referral::firstOrNew([
         'osy_profile_id' => $profile->id,
         'training_program_id' => $program->id,
      ]);

      if (!$referral->exists) {
         $referral->created_at = $referred;
         $referral->updated_at = $referred;
      }

      $referral->forceFill([
         'referred_by' => $sk->id,
         'reviewed_by' => $status === 'Referred' ? null : $maxima->id,
         'status' => $status,
         'remarks' => $r['remarks'] ?? null,
         'batch_name' => $start ? 'Batch 1 - 2026' : null,
         'venue' => $start ? self::VENUE : null,
         'trainer_name' => $start ? $trainer : null,
         'training_start_date' => $start?->toDateString(),
         'expected_end_date' => $start?->copy()->addWeeks(12)->toDateString(),
         'training_end_date' => $end?->toDateString(),
         'drop_reason' => $status === 'Dropped' ? 'Too many absences' : null,
         'outcome_remarks' => $status === 'Completed' ? 'Completed all training requirements.' : null,
      ]);
      $referral->save();

      // status logs
      foreach ($this->logSteps($status) as $step) {
         $when = match ($step) {
            'Referred' => $referred,
            'Accepted by Maxima', 'Rejected' => $referred->copy()->addDays(3),
            'Training Started' => $start,
            'Completed' => $end,
         };

         DB::table('referral_status_logs')->updateOrInsert(
            ['referral_id' => $referral->id, 'status' => $step],
            [
               'changed_by' => $step === 'Referred' ? $sk->id : $maxima->id,
               'remarks' => $step === 'Rejected' ? ($r['remarks'] ?? null) : null,
               'created_at' => $when->toDateTimeString(),
               'updated_at' => $when->toDateTimeString(),
            ]
         );
      }

      // attendance records
      if (in_array($status, ['Training Started', 'Completed', 'Dropped'], true) && $start) {
         $last = $end ?? Carbon::parse(self::TODAY)->subDay();
         $this->seedAttendance($referral, $start, $last, $r['pattern'] ?? 'good', $maxima);
      }
   }

   private function seedAttendance(Referral $referral, Carbon $start, Carbon $last, string $pattern, User $maxima): void
   {
      $i = 0;

      for ($d = $start->copy(); $d->lte($last); $d->addDay()) {
         if ($d->isWeekend()) {
            continue;
         }

         AttendanceRecord::updateOrCreate(
            ['referral_id' => $referral->id, 'attendance_date' => $d->toDateString()],
            ['status' => $this->attendanceStatus($pattern, $i), 'recorded_by' => $maxima->id]
         );

         $i++;
      }
   }

   private function attendanceStatus(string $pattern, int $i): string
   {
      return match ($pattern) {
         'good' => $i % 10 === 7 ? 'absent' : ($i % 6 === 4 ? 'late' : ($i % 13 === 9 ? 'excused' : 'present')),
         'mid' => $i % 3 === 1 ? 'absent' : ($i % 5 === 2 ? 'late' : 'present'),
         default => $i % 4 === 0 ? 'present' : ($i % 9 === 5 ? 'excused' : 'absent'),
      };
   }

   private function logSteps(string $status): array
   {
      return match ($status) {
         'Referred' => ['Referred'],
         'Accepted by Maxima' => ['Referred', 'Accepted by Maxima'],
         'Rejected' => ['Referred', 'Rejected'],
         'Completed' => ['Referred', 'Accepted by Maxima', 'Training Started', 'Completed'],
         default => ['Referred', 'Accepted by Maxima', 'Training Started'],
      };
   }

   // osy number, program key, status, referred date, start, end, attendance pattern
   private function rows(): array
   {
      return [
         // pending
         ['osy' => 2, 'program' => 'bread', 'status' => 'Referred', 'referred' => '2026-11-17'],
         ['osy' => 7, 'program' => 'barista', 'status' => 'Referred', 'referred' => '2026-11-18'],
         ['osy' => 12, 'program' => 'cookery', 'status' => 'Referred', 'referred' => '2026-11-18'],
         ['osy' => 23, 'program' => 'electrical', 'status' => 'Referred', 'referred' => '2026-11-19'],

         // accepted, not started
         ['osy' => 4, 'program' => 'dress', 'status' => 'Accepted by Maxima', 'referred' => '2026-11-10', 'start' => '2026-11-30'],
         ['osy' => 8, 'program' => 'hair', 'status' => 'Accepted by Maxima', 'referred' => '2026-11-11', 'start' => '2026-11-30'],
         ['osy' => 21, 'program' => 'motorcycle', 'status' => 'Accepted by Maxima', 'referred' => '2026-11-12', 'start' => '2026-11-30'],

         // training started
         ['osy' => 1, 'program' => 'smaw', 'status' => 'Training Started', 'referred' => '2026-10-05', 'start' => '2026-10-19', 'pattern' => 'good'],
         ['osy' => 3, 'program' => 'motorcycle', 'status' => 'Training Started', 'referred' => '2026-10-05', 'start' => '2026-10-19', 'pattern' => 'low'],
         ['osy' => 5, 'program' => 'electrical', 'status' => 'Training Started', 'referred' => '2026-10-05', 'start' => '2026-10-19', 'pattern' => 'good'],
         ['osy' => 6, 'program' => 'carpentry', 'status' => 'Training Started', 'referred' => '2026-10-05', 'start' => '2026-10-19', 'pattern' => 'low'],
         ['osy' => 10, 'program' => 'cookery', 'status' => 'Training Started', 'referred' => '2026-10-05', 'start' => '2026-10-19', 'pattern' => 'mid'],
         ['osy' => 11, 'program' => 'css', 'status' => 'Training Started', 'referred' => '2026-10-05', 'start' => '2026-10-19', 'pattern' => 'mid'],
         ['osy' => 24, 'program' => 'barista', 'status' => 'Training Started', 'referred' => '2026-10-05', 'start' => '2026-10-19', 'pattern' => 'good'],

         // completed
         ['osy' => 20, 'program' => 'smaw', 'status' => 'Completed', 'referred' => '2026-06-22', 'start' => '2026-07-06', 'end' => '2026-09-28', 'pattern' => 'good'],
         ['osy' => 26, 'program' => 'carpentry', 'status' => 'Completed', 'referred' => '2026-06-22', 'start' => '2026-07-06', 'end' => '2026-09-28', 'pattern' => 'good'],
         ['osy' => 27, 'program' => 'bread', 'status' => 'Completed', 'referred' => '2026-06-22', 'start' => '2026-07-06', 'end' => '2026-09-28', 'pattern' => 'mid'],

         // rejected
         ['osy' => 17, 'program' => 'caregiving', 'status' => 'Rejected', 'referred' => '2026-11-05', 'remarks' => 'Incomplete requirements.'],
         ['osy' => 16, 'program' => 'housekeep', 'status' => 'Rejected', 'referred' => '2026-11-05', 'remarks' => 'No available slots for this batch.'],

         // dropped
         ['osy' => 30, 'program' => 'smaw', 'status' => 'Dropped', 'referred' => '2026-09-21', 'start' => '2026-10-05', 'end' => '2026-10-23', 'pattern' => 'low'],
         ['osy' => 22, 'program' => 'hair', 'status' => 'Dropped', 'referred' => '2026-09-21', 'start' => '2026-10-05', 'end' => '2026-10-23', 'pattern' => 'low'],
      ];
   }
}