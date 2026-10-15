<?php

   namespace Database\Seeders;

   use App\Models\AbsenceConcern;
   use App\Models\AttendanceRecord;
   use App\Models\OsyProfile;
   use App\Models\Referral;
   use App\Models\ReferralDocument;
   use App\Models\ReferralNote;
   use App\Models\ReferralStatusLog;
   use App\Models\TrainingProgram;
   use App\Models\User;
   use Carbon\Carbon;
   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Seeder;

   class ReferralSeeder extends Seeder
   {
      private const DOCUMENTS = [
         'PSA Birth Certificate',
         'Barangay Clearance',
         'Valid ID (photocopy)',
         '1x1 ID Photos',
         'Certificate of Indigency',
         'Medical Certificate',
      ];

      private const ABSENT_REMARKS = ['Sick', 'Transportation problem', 'Family matter', 'No notice'];
      private const LATE_REMARKS = ['Arrived 30 minutes late', 'Heavy rain', 'Missed the ride'];
      private const EXCUSED_REMARKS = ['Medical appointment', 'Barangay activity', 'Family emergency'];

      public function run(): void
      {
         // The seeders write timestamps and fields that may not be in $fillable.
         Model::unguarded(fn () => $this->seed());
      }

      private function seed(): void
      {
         $sk = User::where('role', 'sk_officials')->orderBy('id')->first();
         $maxima = User::where('role', 'maxima_tesda_school')->orderBy('id')->first();

         if (! $sk || ! $maxima) {
               $this->command->error('SK or Maxima account missing. Run UserSeeder first.');
               return;
         }

         foreach ($this->plans() as $plan) {
               $user = User::where('email', $plan['osy'] . '@backtotrack.ph')->first();
               $profile = $user ? OsyProfile::where('user_id', $user->id)->first() : null;
               $program = TrainingProgram::where('name', $plan['program'])->first();

               if (! $profile || ! $program) {
                  $this->command->warn("Skipped {$plan['osy']}: profile or program not found.");
                  continue;
               }

               $this->seedReferral($plan, $profile, $program, $sk, $maxima);
         }
      }

      private function seedReferral(array $plan, OsyProfile $profile, TrainingProgram $program, User $sk, User $maxima): void
      {
         $status = $plan['status'];
         $day = fn (?int $n, int $hour = 9) => $n === null ? null : Carbon::today()->subDays($n)->setTime($hour, 30);

         $referredAt = $day($plan['referred']);
         $acceptedAt = $day($plan['accepted'] ?? null);
         $startedAt = $day($plan['started'] ?? null);
         $endedAt = $day($plan['ended'] ?? null);
         $rejectedAt = $day($plan['rejected'] ?? null);

         $latestDaysAgo = min(array_filter(
               [$plan['referred'], $plan['accepted'] ?? null, $plan['started'] ?? null, $plan['ended'] ?? null, $plan['rejected'] ?? null],
               fn ($v) => $v !== null
         ));

         $referral = Referral::updateOrCreate(
               ['osy_profile_id' => $profile->id, 'training_program_id' => $program->id],
               [
                  'referred_by' => $sk->id,
                  'reviewed_by' => $status === 'Referred' ? null : $maxima->id,
                  'status' => $status,
                  'remarks' => $plan['remarks'] ?? null,
                  'batch_name' => $plan['batch'] ?? null,
                  'venue' => $plan['venue'] ?? null,
                  'trainer_name' => $plan['trainer'] ?? null,
                  'training_start_date' => $startedAt?->toDateString(),
                  'expected_end_date' => isset($plan['expected_end'])
                     ? Carbon::today()->subDays($plan['expected_end'])->toDateString()
                     : null,
                  'training_end_date' => $endedAt?->toDateString(),
                  'certificate_number' => $plan['certificate_number'] ?? null,
                  'certificate_date' => isset($plan['certificate_days_ago'])
                     ? Carbon::today()->subDays($plan['certificate_days_ago'])->toDateString()
                     : null,
                  'drop_reason' => $plan['drop_reason'] ?? null,
                  'after_training_status' => $plan['after_status'] ?? null,
                  'after_training_note' => $plan['after_note'] ?? null,
                  'outcome_remarks' => $plan['outcome_remarks'] ?? null,
                  'created_at' => $referredAt,
                  'updated_at' => $day($latestDaysAgo),
               ]
         );

         $this->seedLogs($referral, $plan, $program, $sk, $maxima, $referredAt, $acceptedAt, $startedAt, $endedAt, $rejectedAt);

         if ($startedAt) {
               $this->seedAttendance($referral, $plan, $startedAt, $endedAt, $maxima);
         }

         if (isset($plan['docs'])) {
               $this->seedDocuments($referral, $plan, $maxima);
         }

         foreach ($plan['notes'] ?? [] as [$daysAgo, $author, $text]) {
               ReferralNote::updateOrCreate(
                  ['referral_id' => $referral->id, 'user_id' => $author === 'sk' ? $sk->id : $maxima->id, 'note' => $text],
                  ['created_at' => $day($daysAgo, 14), 'updated_at' => $day($daysAgo, 14)]
               );
         }

         foreach ($plan['concerns'] ?? [] as [$type, $description, $concernStatus, $daysAgo, $reviewRemarks]) {
               AbsenceConcern::updateOrCreate(
                  ['referral_id' => $referral->id, 'description' => $description],
                  [
                     'submitted_by' => $sk->id,
                     'reviewed_by' => $concernStatus === 'pending' ? null : $maxima->id,
                     'type' => $type,
                     'status' => $concernStatus,
                     'review_remarks' => $reviewRemarks,
                     'created_at' => $day($daysAgo, 11),
                     'updated_at' => $day($daysAgo, 11),
                  ]
               );
         }
      }

      private function seedLogs(Referral $referral, array $plan, TrainingProgram $program, User $sk, User $maxima,
         ?Carbon $referredAt, ?Carbon $acceptedAt, ?Carbon $startedAt, ?Carbon $endedAt, ?Carbon $rejectedAt): void
      {
         $entries = [['Referred', $referredAt, $sk, "Referred to {$program->name}."]];

         if ($rejectedAt) {
               $entries[] = ['Rejected', $rejectedAt, $maxima, $plan['remarks'] ?? 'Not accepted for this batch.'];
         }
         if ($acceptedAt) {
               $entries[] = ['Accepted by Maxima', $acceptedAt, $maxima, 'Accepted into the batch.'];
         }
         if ($startedAt) {
               $entries[] = ['Training Started', $startedAt, $maxima, 'Training started.'];
         }
         if ($endedAt && $plan['status'] === 'Completed') {
               $entries[] = ['Completed', $endedAt, $maxima, 'Completed all training requirements.'];
         }
         if ($endedAt && $plan['status'] === 'Dropped') {
               $entries[] = ['Dropped', $endedAt, $maxima, $plan['outcome_remarks'] ?? 'Dropped from the program.'];
         }

         foreach ($entries as [$logStatus, $when, $by, $remarks]) {
               ReferralStatusLog::updateOrCreate(
                  ['referral_id' => $referral->id, 'status' => $logStatus],
                  ['changed_by' => $by->id, 'remarks' => $remarks, 'created_at' => $when, 'updated_at' => $when]
               );
         }
      }

      private function seedAttendance(Referral $referral, array $plan, Carbon $startedAt, ?Carbon $endedAt, User $maxima): void
      {
         // The unique key is (referral_id, attendance_date), so rebuild the records each run.
         AttendanceRecord::where('referral_id', $referral->id)->delete();

         // Same seed per person, so re-running gives the same attendance.
         mt_srand(crc32($plan['osy']));
         $pick = fn (array $options) => $options[mt_rand(0, count($options) - 1)];

         $from = $startedAt->copy()->startOfDay();
         $to = $endedAt ? $endedAt->copy()->startOfDay() : Carbon::yesterday();
         $noShowFrom = $plan['status'] === 'Dropped' ? $to->copy()->subDays(8) : null;

         for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
               if ($date->isWeekend()) {
                  continue;
               }

               $roll = mt_rand(1, 100);

               if ($noShowFrom && $date->gte($noShowFrom)) {
                  [$attendance, $remarks] = ['absent', 'No show; could not be reached'];
               } elseif ($roll <= 84) {
                  [$attendance, $remarks] = ['present', null];
               } elseif ($roll <= 91) {
                  [$attendance, $remarks] = ['late', $pick(self::LATE_REMARKS)];
               } elseif ($roll <= 96) {
                  [$attendance, $remarks] = ['absent', $pick(self::ABSENT_REMARKS)];
               } else {
                  [$attendance, $remarks] = ['excused', $pick(self::EXCUSED_REMARKS)];
               }

               AttendanceRecord::create([
                  'referral_id' => $referral->id,
                  'attendance_date' => $date->toDateString(),
                  'status' => $attendance,
                  'remarks' => $remarks,
                  'recorded_by' => $maxima->id,
                  'created_at' => $date->copy()->setTime(17, 0),
                  'updated_at' => $date->copy()->setTime(17, 0),
               ]);
         }
      }

      private function seedDocuments(Referral $referral, array $plan, User $maxima): void
      {
         $received = 0;

         foreach (self::DOCUMENTS as $i => $name) {
               $receivedAt = null;
               $remarks = null;

               if (in_array($name, $plan['na'] ?? [], true)) {
                  $docStatus = 'not_applicable';
                  $remarks = 'Not required for this applicant.';
               } elseif ($received < $plan['docs']) {
                  $received++;
                  $docStatus = 'received';
                  $receivedAt = Carbon::today()->subDays(max($plan['accepted'] - $i, 0))->toDateString();
               } else {
                  $docStatus = 'pending';
               }

               ReferralDocument::updateOrCreate(
                  ['referral_id' => $referral->id, 'name' => $name],
                  [
                     'status' => $docStatus,
                     'remarks' => $remarks,
                     'received_at' => $receivedAt,
                     'updated_by' => $maxima->id,
                  ]
               );
         }
      }

      /**
       * Days are "days ago" counted from today, so the data always looks current.
       * expected_end is also "days ago"; a negative number means in the future.
       * docs = how many applicable documents are already received.
       */
      private function plans(): array
      {
         return [
               // --- Referred ---
               [
                  'osy' => 'ronnel.cacayuran', 'program' => 'Electrical Installation and Maintenance NC II',
                  'status' => 'Referred', 'referred' => 3,
                  'remarks' => 'Meets the age and schooling requirements; interested in residential wiring.',
               ],
               [
                  'osy' => 'maricris.ocampo', 'program' => 'Cookery NC II',
                  'status' => 'Referred', 'referred' => 8,
                  'remarks' => 'Has kitchen experience at a family carinderia.',
               ],
               [
                  'osy' => 'jessie.pimentel', 'program' => 'Computer Systems Servicing NC II',
                  'status' => 'Referred', 'referred' => 5,
                  'remarks' => 'Senior high school graduate with basic computer skills.',
               ],

               // --- Rejected (profile stays Validated) ---
               [
                  'osy' => 'joan.fernandez', 'program' => 'Housekeeping NC II',
                  'status' => 'Rejected', 'referred' => 21, 'rejected' => 16,
                  'remarks' => 'The current batch is already full. To be re-referred when the next intake opens.',
               ],

               // --- Accepted by Maxima ---
               [
                  'osy' => 'lovely.rimando', 'program' => 'Bread and Pastry Production NC II',
                  'status' => 'Accepted by Maxima', 'referred' => 14, 'accepted' => 9,
                  'batch' => 'Batch 4 - 2026', 'venue' => 'Barangay Pogo Grande Multi-Purpose Hall', 'trainer' => 'Corazon Pangilinan',
                  'docs' => 3,
               ],
               [
                  'osy' => 'dennis.baltazar', 'program' => 'Hairdressing NC II',
                  'status' => 'Accepted by Maxima', 'referred' => 12, 'accepted' => 6,
                  'batch' => 'Weekend Batch 2', 'venue' => 'Municipal Skills Training Center', 'trainer' => 'Rodel Balolong',
                  'docs' => 2,
               ],
               [
                  'osy' => 'christian.layug', 'program' => 'Motorcycle and Small Engine Servicing NC II',
                  'status' => 'Accepted by Maxima', 'referred' => 10, 'accepted' => 4,
                  'batch' => 'Batch 3 - 2026', 'venue' => 'Municipal Skills Training Center', 'trainer' => 'Arnel Bumatay',
                  'docs' => 1,
               ],

               // --- Training Started ---
               [
                  'osy' => 'carlo.mangaoang', 'program' => 'Shielded Metal Arc Welding (SMAW) NC II',
                  'status' => 'Training Started', 'referred' => 50, 'accepted' => 45, 'started' => 35, 'expected_end' => -40,
                  'batch' => 'Batch 2 - 2026', 'venue' => 'Municipal Skills Training Center', 'trainer' => 'Danilo Sabado',
                  'docs' => 5, 'na' => ['Certificate of Indigency'],
                  'notes' => [
                     [30, 'maxima', 'Good start. Follows safety rules and brings his own gloves.'],
                     [12, 'sk', 'Visited the training center. Carlo says the walk to class is long but manageable.'],
                  ],
               ],
               [
                  'osy' => 'rowena.abalos', 'program' => 'Dressmaking NC II',
                  'status' => 'Training Started', 'referred' => 38, 'accepted' => 34, 'started' => 28, 'expected_end' => -35,
                  'batch' => 'Batch 1 - 2026', 'venue' => 'Barangay Pogo Grande Multi-Purpose Hall', 'trainer' => 'Luzviminda Cabading',
                  'docs' => 6,
                  'notes' => [
                     [20, 'maxima', 'Finished her first pattern set ahead of the class.'],
                  ],
               ],
               [
                  'osy' => 'paolo.rabago', 'program' => 'Carpentry NC II',
                  'status' => 'Training Started', 'referred' => 30, 'accepted' => 26, 'started' => 21, 'expected_end' => -50,
                  'batch' => 'Batch 2 - 2026', 'venue' => 'Municipal Skills Training Center', 'trainer' => 'Bernardo Quitevis',
                  'docs' => 5,
                  'notes' => [
                     [14, 'maxima', 'Medical certificate is still missing. Reminded the SK to follow up with the family.'],
                  ],
                  'concerns' => [
                     ['absence', 'Paolo missed two days because of a family emergency and asked that the days be excused.', 'resolved', 12, 'Confirmed with the family. Both days were marked excused.'],
                     ['concern', 'Paolo has trouble carrying his tools to the training center because of the distance.', 'pending', 3, null],
                  ],
               ],
               [
                  'osy' => 'shiela.corpuz', 'program' => 'Barista NC II',
                  'status' => 'Training Started', 'referred' => 24, 'accepted' => 20, 'started' => 14, 'expected_end' => -25,
                  'batch' => 'Batch 1 - 2026', 'venue' => 'Municipal Skills Training Center', 'trainer' => 'Patricia Valenzuela',
                  'docs' => 4,
                  'concerns' => [
                     ['absence', 'Shiela was absent on Tuesday because of a fever.', 'reviewed', 6, 'Noted. Please bring a medical note when she returns.'],
                  ],
               ],

               // --- Completed ---
               [
                  'osy' => 'grace.lising', 'program' => 'Bread and Pastry Production NC II',
                  'status' => 'Completed', 'referred' => 110, 'accepted' => 105, 'started' => 98, 'ended' => 14, 'expected_end' => 14,
                  'batch' => 'Batch 2 - 2026', 'venue' => 'Barangay Pogo Grande Multi-Purpose Hall', 'trainer' => 'Corazon Pangilinan',
                  'docs' => 6,
                  'certificate_number' => 'BPP-NCII-2026-0187', 'certificate_days_ago' => 9,
                  'after_status' => 'employed', 'after_note' => 'Hired as a baker at a local bakeshop.',
                  'notes' => [
                     [20, 'maxima', 'Passed the NC II assessment on the first attempt.'],
                  ],
               ],
               [
                  'osy' => 'angelo.tolentino', 'program' => 'Caregiving NC II',
                  'status' => 'Completed', 'referred' => 130, 'accepted' => 125, 'started' => 120, 'ended' => 25, 'expected_end' => 25,
                  'batch' => 'Batch 1 - 2026', 'venue' => 'Municipal Skills Training Center', 'trainer' => 'Remedios Ancheta',
                  'docs' => 6,
                  'certificate_number' => 'CGV-NCII-2026-0064', 'certificate_days_ago' => 20,
                  'after_status' => 'further_training', 'after_note' => 'Enrolled in a caregiver review course for overseas work.',
               ],
               [
                  'osy' => 'melody.fabro', 'program' => 'Cookery NC II',
                  'status' => 'Completed', 'referred' => 100, 'accepted' => 95, 'started' => 90, 'ended' => 8, 'expected_end' => 8,
                  'batch' => 'Batch 2 - 2026', 'venue' => 'Municipal Skills Training Center', 'trainer' => 'Gerardo Mallari',
                  'docs' => 5, 'na' => ['Certificate of Indigency'],
                  'certificate_number' => 'COK-NCII-2026-0231', 'certificate_days_ago' => 3,
                  'after_status' => 'self_employed', 'after_note' => 'Started selling meals and snacks from home.',
               ],

               // --- Dropped ---
               [
                  'osy' => 'dante.esguerra', 'program' => 'Motorcycle and Small Engine Servicing NC II',
                  'status' => 'Dropped', 'referred' => 60, 'accepted' => 55, 'started' => 40, 'ended' => 22, 'expected_end' => 5,
                  'batch' => 'Batch 2 - 2026', 'venue' => 'Municipal Skills Training Center', 'trainer' => 'Arnel Bumatay',
                  'docs' => 4,
                  'drop_reason' => 'work',
                  'outcome_remarks' => 'Found a daily-wage job and could no longer attend the afternoon sessions.',
                  'notes' => [
                     [24, 'maxima', 'Absent for a week. SK was asked to check on him.'],
                     [22, 'sk', 'Spoke with Dante. He took a farm job and cannot attend. May return in a later batch.'],
                  ],
               ],
         ];
      }
   }