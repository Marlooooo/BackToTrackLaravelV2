<?php

   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Models\AbsenceConcern;
   use App\Models\AttendanceRecord;
   use App\Models\ConductRecord;
   use App\Models\Referral;
   use App\Models\TrainingAssessment;
   use App\Models\TrainingProgram;
   use Carbon\Carbon;
   use Illuminate\Http\Request;
   use Illuminate\Support\Collection;

   /**
    * Summary report for Maxima personnel: referrals, programs and enrolled OSY for a chosen period.
    * Per-OSY records (attendance log, assessments, notes...) stay on the Progress Monitoring page.
    */
   class MaximaReportController extends Controller
   {
      private const MAXIMA_ROLE = 'maxima_tesda_school';

      private const ENROLLED_STATUSES = ['Training Started', 'Completed'];

      private const REFERRAL_STAGES = [
         'Referred',
         'Accepted by Maxima',
         'Training Started',
         'Completed',
         'Dropped',
      ];

      // same thresholds as MaximaProgressController, so "needs follow-up" matches the monitoring page
      private const MIN_ATTENDANCE = 75;
      private const MAX_ABSENCES   = 3;
      private const MIN_ASSESSMENT = 75;

      /**
       * GET /api/maxima/reports?from=&to=&program_id=
       *
       * Everything here covers referrals created between "from" and "to" (and for program_id, if given).
       */
      public function index(Request $request)
      {
         abort_unless($request->user()->role === self::MAXIMA_ROLE, 403, 'Unauthorized');

         $request->validate([
               'from'       => ['nullable', 'date'],
               'to'         => ['nullable', 'date'],
               'program_id' => ['nullable', 'integer'],
         ]);

         $from      = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : null;
         $to        = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : null;
         $programId = $request->filled('program_id') ? (int) $request->input('program_id') : null;

         abort_if($from && $to && $to->lt($from), 422, 'The end date must be on or after the start date.');

         $inRange = function ($query) use ($from, $to, $programId) {
               $query
                  ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                  ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                  ->when($programId, fn ($q) => $q->where('training_program_id', $programId));
         };

         // ---------------- Referrals ----------------
         $referrals    = Referral::query()->tap($inRange)->get();
         $total        = $referrals->count();
         $statusCounts = $referrals->countBy('status');

         $stageRow = fn ($stage) => [
               'label'   => $stage,
               'count'   => (int) ($statusCounts[$stage] ?? 0),
               'percent' => $this->pct((int) ($statusCounts[$stage] ?? 0), $total),
         ];

         // Any other status (for example a declined referral) is listed too, so the counts add up
         $otherStatuses = $statusCounts->keys()->diff(self::REFERRAL_STAGES)->values();

         $referralStages = collect(self::REFERRAL_STAGES)
               ->concat($otherStatuses)
               ->map($stageRow)
               ->values();

         // ---------------- Enrolled OSY ----------------
         $enrolled = Referral::with([
                  'osyProfile:id,first_name,middle_name,last_name',
                  'trainingProgram:id,name',
               ])
               ->where(function ($q) {
                  $q->whereIn('status', self::ENROLLED_STATUSES)
                     ->orWhere(function ($q) {
                           $q->where('status', 'Dropped')
                              ->whereHas('statusLogs', fn ($l) => $l->where('status', 'Training Started'));
                     });
               })
               ->tap($inRange)
               ->get();

         $ids = $enrolled->pluck('id');

         $attendanceBy  = AttendanceRecord::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
         $assessmentsBy = TrainingAssessment::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
         $conductBy     = ConductRecord::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
         $pendingBy     = AbsenceConcern::whereIn('referral_id', $ids)
               ->where('status', 'pending')
               ->selectRaw('referral_id, COUNT(*) as total')
               ->groupBy('referral_id')
               ->pluck('total', 'referral_id');

         $rows = $enrolled->map(function (Referral $referral) use ($attendanceBy, $assessmentsBy, $conductBy, $pendingBy) {
               $m       = $this->metrics(
                  $attendanceBy->get($referral->id, collect()),
                  $assessmentsBy->get($referral->id, collect())
               );
               $pending = (int) ($pendingBy[$referral->id] ?? 0);
               $conduct = $conductBy->get($referral->id, collect())
                  ->sortBy([['recorded_on', 'desc'], ['id', 'desc']])
                  ->first();

               $reasons = [];
               if ($referral->status === 'Training Started') {
                  if ($m['rate'] !== null && $m['counted'] >= 3 && $m['rate'] < self::MIN_ATTENDANCE) {
                     $reasons[] = "Low attendance ({$m['rate']}%)";
                  }
                  if ($m['absent'] >= self::MAX_ABSENCES) {
                     $reasons[] = "{$m['absent']} absences recorded";
                  }
                  if ($pending > 0) {
                     $reasons[] = $pending . ' pending ' . ($pending === 1 ? 'concern' : 'concerns');
                  }
                  if ($m['assessment_average'] !== null && $m['assessment_count'] >= 2 && $m['assessment_average'] < self::MIN_ASSESSMENT) {
                     $reasons[] = "Low assessment average ({$m['assessment_average']}%)";
                  }
               }

               $osy = $referral->osyProfile;

               return [
                  'referral_id'        => $referral->id,
                  'osy_name'           => $osy ? trim($osy->first_name . ' ' . $osy->last_name) : 'Unknown',
                  'program_id'         => $referral->training_program_id,
                  'program'            => $referral->trainingProgram?->name ?? 'Unknown program',
                  'status'             => $referral->status,
                  'attendance_rate'    => $m['rate'],
                  'absences'           => $m['absent'],
                  'assessment_average' => $m['assessment_average'],
                  'conduct_rating'     => $conduct?->rating,
                  'pending_concerns'   => $pending,
                  'reasons'            => $reasons,
               ];
         });

         // ---------------- Programs ----------------
         $names = TrainingProgram::pluck('name', 'id');

         $programs = $referrals->groupBy('training_program_id')
               ->map(function (Collection $group, $pid) use ($rows, $names) {
                  $mine      = $rows->where('program_id', $pid);
                  $enrollees = $mine->count();
                  $completed = $mine->where('status', 'Completed')->count();
                  $rates     = $mine->pluck('attendance_rate')->filter(fn ($v) => $v !== null);
                  $averages  = $mine->pluck('assessment_average')->filter(fn ($v) => $v !== null);

                  return [
                     'program_id'         => $pid === '' ? null : $pid,
                     'program'            => $names[$pid] ?? 'Unknown program',
                     'referrals'          => $group->count(),
                     'enrollees'          => $enrollees,
                     'ongoing'            => $mine->where('status', 'Training Started')->count(),
                     'completed'          => $completed,
                     'dropped'            => $mine->where('status', 'Dropped')->count(),
                     'completion_rate'    => $enrollees > 0 ? round($completed / $enrollees * 100, 1) : null,
                     'attendance_rate'    => $rates->isEmpty() ? null : round($rates->avg(), 1),
                     'assessment_average' => $averages->isEmpty() ? null : round($averages->avg(), 1),
                  ];
               })
               ->sortBy('program')
               ->values();

         // People who need follow-up first, then by name
         $enrollees = $rows
               ->sort(fn ($a, $b) => [count($b['reasons']), $a['osy_name']] <=> [count($a['reasons']), $b['osy_name']])
               ->values();

         return response()->json([
               'filters' => [
                  'from'       => $from?->toDateString(),
                  'to'         => $to?->toDateString(),
                  'program_id' => $programId,
               ],
               'program_options' => TrainingProgram::orderBy('name')->get(['id', 'name']),
               'summary' => [
                  'referrals_received' => $total,
                  'pending_review'     => (int) ($statusCounts['Referred'] ?? 0),
                  'accepted'           => (int) ($statusCounts['Accepted by Maxima'] ?? 0),
                  'ongoing'            => (int) ($statusCounts['Training Started'] ?? 0),
                  'completed'          => (int) ($statusCounts['Completed'] ?? 0),
                  'dropped'            => (int) ($statusCounts['Dropped'] ?? 0),
                  'needs_follow_up'    => $enrollees->filter(fn ($row) => count($row['reasons']) > 0)->count(),
               ],
               'referral_stages' => $referralStages,
               'programs'        => $programs,
               'enrollees'       => $enrollees,
         ]);
      }

      /** Attendance rate (excused days not counted) and assessment average, same rules as the monitoring page. */
      private function metrics(Collection $attendance, Collection $assessments): array
      {
         $present = $attendance->where('status', 'present')->count();
         $late    = $attendance->where('status', 'late')->count();
         $absent  = $attendance->where('status', 'absent')->count();
         $counted = $present + $late + $absent;

         $scored = $assessments->filter(fn ($a) => $a->max_score > 0);

         return [
               'rate'               => $counted > 0 ? round(($present + $late) / $counted * 100, 1) : null,
               'counted'            => $counted,
               'absent'             => $absent,
               'assessment_average' => $scored->isEmpty()
                  ? null
                  : round($scored->avg(fn ($a) => $a->score / $a->max_score * 100), 1),
               'assessment_count'   => $scored->count(),
         ];
      }

      private function pct(int $part, int $total): float
      {
         return $total > 0 ? round($part / $total * 100, 1) : 0.0;
      }
   }