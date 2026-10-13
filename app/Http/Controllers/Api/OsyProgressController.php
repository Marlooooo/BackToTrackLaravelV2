<?php

   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Models\AttendanceRecord;
   use App\Models\OsyProfile;
   use App\Models\Referral;
   use App\Models\ReferralDocument;
   use App\Models\ReferralStatusLog;
   use Carbon\Carbon;
   use Illuminate\Http\Request;
   use Illuminate\Support\Collection;

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

         $trainings = $referrals->map(fn ($referral) => $this->present(
               $referral,
               $attendanceBy->get($referral->id, collect()),
               $documentsBy->get($referral->id, collect()),
               $logsBy->get($referral->id, collect())
         ))->values();

         return response()->json(['trainings' => $trainings]);
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
                  'records' => $attendance->take(60)->map(fn ($record) => [
                     'date'    => $record->attendance_date,
                     'status'  => $record->status,
                     'remarks' => $record->remarks,
                  ])->values(),
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
         ];
      }
   }