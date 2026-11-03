<?php

   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Models\AttendanceRecord;
   use App\Models\OsyProfile;
   use App\Models\Referral;
   use App\Models\ReferralDocument;
   use App\Models\ReferralStatusLog;
   use App\Notifications\AbsenceReasonSubmitted;
   use Carbon\Carbon;
   use Illuminate\Http\Request;
   use Illuminate\Support\Collection;
   use Illuminate\Support\Facades\Storage;
   use Illuminate\Validation\Rule;

   class OsyProgressController extends Controller
   {
      private const VISIBLE = ['Accepted by Maxima', 'Training Started', 'Completed', 'Dropped'];

      private const DROP_REASONS = [
         'transport'     => 'Transportation problem',
         'work'          => 'Needs to work',
         'family'        => 'Family reasons',
         'health'        => 'Health reasons',
         'lost_interest' => 'Lost interest',
         'other'         => 'Other',
      ];

      private const AFTER_TRAINING = [
         'employed'         => 'Employed',
         'self_employed'    => 'Self-employed',
         'further_training' => 'Continuing to further training',
         'looking_for_work' => 'Looking for work',
         'other'            => 'Other',
      ];

      // Reasons an OSY can give for an absence. Sent to the frontend so both sides use one list.
      public const ABSENCE_REASONS = [
         'sickness' => [
            'label'            => 'Sick or not feeling well',
            'question'         => 'What best describes it?',
            'asks_return_date' => true,
            'followups'        => [
               'flu'     => 'Fever, cough or flu',
               'injury'  => 'Injury',
               'illness' => 'Other illness',
            ],
         ],
         'transport' => [
            'label'            => 'No fare or transportation',
            'question'         => 'What was the problem?',
            'asks_return_date' => false,
            'followups'        => [
               'no_fare' => 'No money for fare',
               'no_ride' => 'No available ride',
               'too_far' => 'The venue is too far',
            ],
         ],
         'family' => [
            'label'            => 'Family or household duties',
            'question'         => 'What was going on?',
            'asks_return_date' => false,
            'followups'        => [
               'caregiving' => 'Taking care of a family member',
               'chores'     => 'Household duties',
               'emergency'  => 'Family emergency',
            ],
         ],
         'work' => [
            'label'            => 'Work or income',
            'question'         => 'What happened?',
            'asks_return_date' => false,
            'followups'        => [
               'needed_income'  => 'I needed to earn money that day',
               'work_conflict'  => 'My work schedule conflicted',
            ],
         ],
         'schedule' => [
            'label'            => 'Schedule conflict',
            'question'         => 'What was the conflict?',
            'asks_return_date' => false,
            'followups'        => [
               'other_class'      => 'Another class or activity',
               'schedule_changed' => 'My schedule changed',
            ],
         ],
         'safety' => [
            'label'            => 'Safety concern',
            'question'         => 'Where did you feel unsafe?',
            'asks_return_date' => false,
            'followups'        => [
               'route' => 'On the way to the venue',
               'venue' => 'At the venue',
               'other' => 'Somewhere else',
            ],
         ],
         'motivation' => [
            'label'            => 'Feeling discouraged',
            'question'         => 'What is making it hard?',
            'asks_return_date' => false,
            'followups'        => [
               'discouraged'  => 'I feel discouraged',
               'lessons_hard' => 'The lessons are hard for me',
               'not_sure'     => 'I am not sure this program is right for me',
            ],
         ],
         'other' => [
            'label'            => 'Something else',
            'question'         => null,
            'asks_return_date' => false,
            'followups'        => [],
         ],
      ];

      public const SUPPORT_OPTIONS = [
         'transport_allowance' => 'Transport allowance',
         'schedule_adjustment' => 'A schedule adjustment',
         'catch_up_help'       => 'Help catching up on lessons',
         'talk_to_sk'          => 'A talk with an SK official',
         'talk_to_trainer'     => 'A talk with my trainer',
      ];

      private function daysBetween(Carbon $from, Carbon $to): int
      {
         return (int) round(($to->timestamp - $from->timestamp) / 86400);
      }

      /**
       * GET /api/osy/progress
       * The logged-in OSY's own trainings. Read-only.
       */
      public function index(Request $request)
      {
         abort_unless($request->user()->role === 'osy', 403, 'Unauthorized');

         $profile = OsyProfile::where('user_id', $request->user()->id)->first();

         if (!$profile) {
               return response()->json(['trainings' => []]);
         }

         $referrals = Referral::with('trainingProgram')
               ->where('osy_profile_id', $profile->id)
               ->whereIn('status', self::VISIBLE)
               ->latest()
               ->get();

         $ids = $referrals->pluck('id');

         $attendanceBy = AttendanceRecord::whereIn('referral_id', $ids)
               ->orderByDesc('attendance_date')
               ->get()
               ->groupBy('referral_id');

         $documentsBy = ReferralDocument::whereIn('referral_id', $ids)
               ->orderBy('id')
               ->get()
               ->groupBy('referral_id');

         $logsBy = ReferralStatusLog::whereIn('referral_id', $ids)
               ->orderBy('created_at')
               ->get()
               ->groupBy('referral_id');

         $trainings = $referrals->map(fn (Referral $referral) => $this->present(
               $referral,
               $attendanceBy->get($referral->id, collect()),
               $documentsBy->get($referral->id, collect()),
               $logsBy->get($referral->id, collect())
         ))->values();

         return response()->json([
               'trainings'      => $trainings,
               'reason_options' => $this->reasonOptions(),
         ]);
      }

      /**
       * POST /api/osy/progress/{referral}/absence-reasons
       * The logged-in OSY explains one or more of their own absences.
       * Reasons are self-reported and not verified.
       */
      public function submitAbsenceReason(Request $request, Referral $referral)
      {
         abort_unless($request->user()->role === 'osy', 403, 'Unauthorized');

         $profile = OsyProfile::where('user_id', $request->user()->id)->first();
         abort_unless($profile && $referral->osy_profile_id === $profile->id, 403, 'Unauthorized');
         abort_unless($referral->status === 'Training Started', 422, 'You can add reasons while your training is ongoing.');

         $data = $request->validate([
               'record_ids'   => ['required', 'array', 'min:1', 'max:30'],
               'record_ids.*' => ['integer'],
               'category'     => ['required', Rule::in(array_keys(self::ABSENCE_REASONS))],
               'followup'     => ['nullable', 'string', 'max:60'],
               'return_date'  => ['nullable', 'date'],
               'support'      => ['nullable', 'array'],
               'support.*'    => [Rule::in(array_keys(self::SUPPORT_OPTIONS))],
               'note'         => ['nullable', 'string', 'max:1000', 'required_if:category,other'],
               'proof'        => ['nullable', 'image', 'max:4096'],
         ]);

         $config = self::ABSENCE_REASONS[$data['category']];

         if (!empty($config['followups'])) {
               $followup = $data['followup'] ?? null;
               abort_unless($followup && array_key_exists($followup, $config['followups']), 422, 'Please choose what best describes it.');
         } else {
               $data['followup'] = null;
         }

         $ids     = array_values(array_unique($data['record_ids']));
         $records = AttendanceRecord::where('referral_id', $referral->id)->whereIn('id', $ids)->get();

         abort_unless($records->count() === count($ids), 422, 'One of those days could not be found.');
         abort_if($records->contains(fn ($record) => $record->status !== 'absent'), 422, 'Reasons can only be added to days marked absent.');
         abort_if($records->contains(fn ($record) => $record->reason_submitted_at !== null), 422, 'One of those days already has a reason.');
         abort_if($records->contains(fn ($record) => $record->reason_requested_at === null), 422, 'Your SK official has not asked for a reason for one of those days.');

         $proofPath = $request->file('proof')?->store('attendance_proofs', 'public');

         foreach ($records as $record) {
               $record->update([
                  'reason_category'     => $data['category'],
                  'reason_followup'     => $data['followup'] ?? null,
                  'return_date'         => $config['asks_return_date'] ? ($data['return_date'] ?? null) : null,
                  'support_requested'   => array_values(array_unique($data['support'] ?? [])),
                  'reason_note'         => $data['note'] ?? null,
                  'reason_proof_path'   => $proofPath,
                  'reason_submitted_at' => now(),
               ]);
         }

         AbsenceReasonSubmitted::send($referral, $records->count());

         return response()->json([
               'message' => count($ids) === 1 ? 'Your reason was sent.' : 'Your reasons were sent.',
               'records' => $records->sortByDesc('attendance_date')->map(fn ($record) => $this->presentRecord($record))->values(),
         ]);
      }

      private function reasonOptions(): array
      {
         return [
               'reasons' => collect(self::ABSENCE_REASONS)->map(fn ($reason, $key) => [
                  'key'              => $key,
                  'label'            => $reason['label'],
                  'question'         => $reason['question'],
                  'asks_return_date' => $reason['asks_return_date'],
                  'followups'        => collect($reason['followups'])
                     ->map(fn ($label, $followupKey) => ['key' => $followupKey, 'label' => $label])
                     ->values(),
               ])->values(),
               'support' => collect(self::SUPPORT_OPTIONS)
                  ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                  ->values(),
         ];
      }

      private function presentRecord(AttendanceRecord $record): array
      {
         $reason = null;

         if ($record->reason_submitted_at) {
               $config = self::ABSENCE_REASONS[$record->reason_category] ?? null;

               $reason = [
                  'category'       => $record->reason_category,
                  'category_label' => $config['label'] ?? null,
                  'followup_label' => $config['followups'][$record->reason_followup] ?? null,
                  'return_date'    => $record->return_date,
                  'support'        => collect($record->support_requested ?? [])
                     ->map(fn ($key) => self::SUPPORT_OPTIONS[$key] ?? null)
                     ->filter()
                     ->values(),
                  'note'           => $record->reason_note,
                  'proof_url'      => $record->reason_proof_path
                     ? Storage::disk('public')->url($record->reason_proof_path)
                     : null,
                  'submitted_at'   => $record->reason_submitted_at,
               ];
         }

         return [
               'id'      => $record->id,
               'date'    => $record->attendance_date,
               'status'  => $record->status,
               'remarks' => $record->remarks,
               'reason'  => $reason,
               'reason_request' => $record->reason_requested_at ? [
                  'message'      => $record->reason_request_message,
                  'requested_at' => $record->reason_requested_at,
               ] : null,
         ];
      }

      private function present(Referral $referral, Collection $attendance, Collection $documents, Collection $logs): array
      {
         $today = Carbon::today();

         // Attendance (excused days are not counted against the OSY)
         $present = $attendance->where('status', 'present')->count();
         $late    = $attendance->where('status', 'late')->count();
         $absent  = $attendance->where('status', 'absent')->count();
         $excused = $attendance->where('status', 'excused')->count();
         $counted = $present + $late + $absent;
         $rate    = $counted > 0 ? round(($present + $late) / $counted * 100, 1) : null;

         // Dates
         $startLog  = $logs->firstWhere('status', 'Training Started');
         $startedOn = $referral->training_start_date ?: $startLog?->created_at?->toDateString();
         $start     = $startedOn ? Carbon::parse($startedOn)->startOfDay() : null;
         $end       = $referral->expected_end_date ? Carbon::parse($referral->expected_end_date)->startOfDay() : null;

         $trainingDay    = ($start && $referral->status === 'Training Started')
               ? max(1, $this->daysBetween($start, $today) + 1)
               : null;
         $trainingLength = ($start && $end) ? max(1, $this->daysBetween($start, $end) + 1) : null;

         // Documents
         $applicable = $documents->where('status', '!=', 'not_applicable');

         // Outcome
         $outcome = null;
         if ($referral->status === 'Completed') {
               $outcome = [
                  'type'                 => 'completed',
                  'date'                 => $referral->training_end_date,
                  'certificate_number'   => $referral->certificate_number,
                  'certificate_date'     => $referral->certificate_date,
                  'after_training'       => self::AFTER_TRAINING[$referral->after_training_status] ?? null,
                  'after_training_note'  => $referral->after_training_note,
               ];
         } elseif ($referral->status === 'Dropped') {
               $outcome = [
                  'type'   => 'dropped',
                  'date'   => $referral->training_end_date,
                  'reason' => self::DROP_REASONS[$referral->drop_reason] ?? null,
               ];
         }

         $program = $referral->trainingProgram;

         return [
               'id'                  => $referral->id,
               'status'              => $referral->status,
               'program'             => $program ? [
                  'name'        => $program->name,
                  'description' => $program->description,
                  'schedule'    => $program->schedule,
                  'image_url'   => $program->image_url,
               ] : null,
               'batch_name'          => $referral->batch_name,
               'venue'               => $referral->venue,
               'trainer_name'        => $referral->trainer_name,
               'started_on'          => $startedOn,
               'expected_end_date'   => $referral->expected_end_date,
               'training_end_date'   => $referral->training_end_date,
               'training_day'        => $trainingDay,
               'training_length'     => $trainingLength,
               'attendance'          => [
                  'rate'    => $rate,
                  'present' => $present,
                  'late'    => $late,
                  'absent'  => $absent,
                  'excused' => $excused,
                  'days'    => $attendance->count(),
                  'needs_reason' => $attendance
                     ->where('status', 'absent')
                     ->filter(fn ($record) => $record->reason_submitted_at === null && $record->reason_requested_at !== null)
                     ->count(),
                  'records' => $attendance->take(60)->map(fn ($record) => $this->presentRecord($record))->values(),
               ],
               'documents'           => $documents->map(fn ($doc) => [
                  'id'          => $doc->id,
                  'name'        => $doc->name,
                  'status'      => $doc->status,
                  'received_at' => $doc->received_at,
                  'remarks'     => $doc->remarks,
               ])->values(),
               'docs_required'       => $applicable->count(),
               'docs_received'       => $applicable->where('status', 'received')->count(),
               'outcome'             => $outcome,
               'milestones'          => $logs
                  ->filter(fn ($log) => in_array($log->status, self::VISIBLE, true))
                  ->map(fn ($log) => [
                     'status' => $log->status,
                     'date'   => $log->created_at?->toDateString(),
                  ])
                  ->values(),
                  // same list Maxima's Status history shows: every status, newest first, with time
               'history'             => $logs
                  ->reverse()
                  ->map(fn ($log) => [
                     'status'     => $log->status,
                     'created_at' => $log->created_at,
                  ])
                  ->values(),
         ];
      }
   }