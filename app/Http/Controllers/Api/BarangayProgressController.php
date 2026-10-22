<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsenceConcern;
use App\Models\AttendanceRecord;
use App\Models\ConductRecord;
use App\Models\Referral;
use App\Models\ReferralNote;
use App\Models\ReferralStatusLog;
use App\Models\TrainingAssessment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Read-only training monitoring for SK / barangay officials.
 * Same data as Maxima's Progress Monitoring, but nothing here can change a record.
 */
class BarangayProgressController extends Controller
{
      // set this to the exact role value(s) your SK users have in users.role
      private const SK_ROLES = ['sk_officials'];

      private const MIN_ATTENDANCE = 75;
      private const MAX_ABSENCES   = 3;
      private const STALE_DAYS     = 14;
      private const MIN_ASSESSMENT = 75; // % average below this raises an alert

      private function authorizeSk(Request $request): void
      {
            abort_unless(in_array($request->user()->role, self::SK_ROLES, true), 403, 'Unauthorized');
      }

      private function enrolledQuery()
      {
            // Enrolled = training actually started. Dropped counts only if they were enrolled first.
            return Referral::with(['osyProfile', 'trainingProgram', 'referrer:id,name'])
                  ->where(function ($q) {
                        $q->whereIn('status', ['Training Started', 'Completed'])
                              ->orWhere(function ($q) {
                                    $q->where('status', 'Dropped')
                                          ->whereHas('statusLogs', fn ($l) => $l->where('status', 'Training Started'));
                              });
                  });
                  // SCOPE: this returns ALL enrolled OSY. To limit to the official's own barangay, add e.g.
                  // ->whereHas('osyProfile', fn ($p) => $p->where('barangay', $request->user()->barangay))
      }

      private function days(Carbon $from, Carbon $to): int
      {
            return (int) round(($to->timestamp - $from->timestamp) / 86400);
      }

      private function summary(
            Referral $referral,
            ?string $startedOn,
            Collection $attendance,
            $lastNoteAt,
            int $pending,
            ?Collection $assessments = null,
            ?Collection $conduct = null
      ): array {
            $today       = Carbon::today();
            $assessments = $assessments ?? collect();
            $conduct     = $conduct ?? collect();

            // Attendance (excused days are not counted against the OSY)
            $present = $attendance->where('status', 'present')->count();
            $late    = $attendance->where('status', 'late')->count();
            $absent  = $attendance->where('status', 'absent')->count();
            $excused = $attendance->where('status', 'excused')->count();
            $counted = $present + $late + $absent;
            $rate    = $counted > 0 ? round(($present + $late) / $counted * 100, 1) : null;

            // Assessments (average of score / max score) and latest conduct rating
            $scored        = $assessments->filter(fn ($a) => $a->max_score > 0);
            $assessmentAvg = $scored->isEmpty()
                  ? null
                  : round($scored->avg(fn ($a) => $a->score / $a->max_score * 100), 1);
            $latestConduct = $conduct->sortBy([['recorded_on', 'desc'], ['id', 'desc']])->first();

            // Training day
            $start       = $startedOn ? Carbon::parse($startedOn)->startOfDay() : null;
            $expectedEnd = $referral->expected_end_date ? Carbon::parse($referral->expected_end_date)->startOfDay() : null;

            $trainingDay    = ($start && $referral->status === 'Training Started') ? max(1, $this->days($start, $today) + 1) : null;
            $trainingLength = ($start && $expectedEnd) ? max(1, $this->days($start, $expectedEnd) + 1) : null;

            $stamps       = collect([$referral->updated_at, $lastNoteAt, $attendance->max('updated_at')])->filter();
            $lastActivity = $stamps->isEmpty() ? null : $stamps->max();

            // Alerts (same rules as Maxima's Progress Monitoring)
            $alerts = [];
            if ($referral->status === 'Training Started') {
                  if ($rate !== null && $counted >= 3 && $rate < self::MIN_ATTENDANCE) {
                        $alerts[] = "Low attendance ({$rate}%)";
                  }
                  if ($absent >= self::MAX_ABSENCES) {
                        $alerts[] = "{$absent} absences recorded";
                  }
                  if ($expectedEnd && $today->gt($expectedEnd)) {
                        $d = $this->days($expectedEnd, $today);
                        $alerts[] = "Past the expected end date by {$d} " . ($d === 1 ? 'day' : 'days');
                  }
                  if ($lastActivity) {
                        $idle = $this->days(Carbon::parse($lastActivity)->startOfDay(), $today);
                        if ($idle >= self::STALE_DAYS) {
                              $alerts[] = "No update for {$idle} days";
                        }
                  }
                  if ($pending > 0) {
                        $alerts[] = $pending . ' pending ' . ($pending === 1 ? 'concern' : 'concerns');
                  }
                  if ($assessmentAvg !== null && $scored->count() >= 2 && $assessmentAvg < self::MIN_ASSESSMENT) {
                        $alerts[] = "Low assessment average ({$assessmentAvg}%)";
                  }
                  if ($latestConduct?->rating === 'needs_improvement') {
                        $alerts[] = 'Conduct needs improvement';
                  }
            }

            return [
                  'present'            => $present,
                  'late'               => $late,
                  'absent'             => $absent,
                  'excused'            => $excused,
                  'attendance_days'    => $attendance->count(),
                  'attendance_rate'    => $rate,
                  'assessment_average' => $assessmentAvg,
                  'assessment_count'   => $assessments->count(),
                  'conduct_rating'     => $latestConduct?->rating,
                  'training_day'       => $trainingDay,
                  'training_length'    => $trainingLength,
                  'last_activity'      => $lastActivity,
                  'alerts'             => $alerts,
                  'needs_attention'    => count($alerts) > 0,
            ];
      }

      /**
       * GET /api/barangay/training-monitoring?search=&program_id=
       */
      public function index(Request $request)
      {
            $this->authorizeSk($request);

            $referrals = $this->enrolledQuery()
                  ->when($request->program_id, fn ($q, $id) => $q->where('training_program_id', $id))
                  ->when($request->search, function ($q, $search) {
                        $q->whereHas('osyProfile', function ($p) use ($search) {
                              $p->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('middle_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                        });
                  })
                  ->latest()
                  ->get();

            $ids = $referrals->pluck('id');

            $attendanceBy  = AttendanceRecord::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
            $assessmentsBy = TrainingAssessment::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
            $conductBy     = ConductRecord::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
            $notesBy       = ReferralNote::whereIn('referral_id', $ids)
                  ->selectRaw('referral_id, MAX(created_at) as last_at')
                  ->groupBy('referral_id')
                  ->pluck('last_at', 'referral_id');
            $concernsBy    = AbsenceConcern::whereIn('referral_id', $ids)
                  ->where('status', 'pending')
                  ->selectRaw('referral_id, COUNT(*) as total')
                  ->groupBy('referral_id')
                  ->pluck('total', 'referral_id');
            $startLogsBy   = ReferralStatusLog::whereIn('referral_id', $ids)
                  ->where('status', 'Training Started')
                  ->orderBy('created_at')
                  ->get()
                  ->groupBy('referral_id');

            $referrals->each(function (Referral $r) use ($attendanceBy, $assessmentsBy, $conductBy, $notesBy, $concernsBy, $startLogsBy) {
                  $startLog  = $startLogsBy->get($r->id, collect())->first();
                  $startedOn = $r->training_start_date ?: $startLog?->created_at?->toDateString();
                  $lastNote  = isset($notesBy[$r->id]) ? Carbon::parse($notesBy[$r->id]) : null;

                  $r->started_on = $startedOn;
                  $r->summary    = $this->summary(
                        $r,
                        $startedOn,
                        $attendanceBy->get($r->id, collect()),
                        $lastNote,
                        (int) ($concernsBy[$r->id] ?? 0),
                        $assessmentsBy->get($r->id, collect()),
                        $conductBy->get($r->id, collect())
                  );
            });

            $counts = [
                  'ongoing'         => $referrals->where('status', 'Training Started')->count(),
                  'completed'       => $referrals->where('status', 'Completed')->count(),
                  'dropped'         => $referrals->where('status', 'Dropped')->count(),
                  'needs_attention' => $referrals->filter(fn ($r) => $r->summary['needs_attention'])->count(),
            ];

            if (in_array($request->status, ['Training Started', 'Completed', 'Dropped'], true)) {
                  $referrals = $referrals->where('status', $request->status);
            }

            return response()->json([
                  'counts'    => $counts,
                  'enrollees' => $referrals->values(),
            ]);
      }

      /**
       * GET /api/barangay/training-monitoring/{referral}
       */
      public function show(Request $request, Referral $referral)
      {
            $this->authorizeSk($request);

            $referral = $this->enrolledQuery()->whereKey($referral->id)->firstOrFail();

            $attendance = AttendanceRecord::where('referral_id', $referral->id)->orderByDesc('attendance_date')->get();

            $assessments = TrainingAssessment::where('referral_id', $referral->id)
                  ->orderByDesc('assessed_on')->orderByDesc('id')->get();
            $conduct = ConductRecord::where('referral_id', $referral->id)
                  ->orderByDesc('recorded_on')->orderByDesc('id')->get();

            $notes    = ReferralNote::with('user:id,name')->where('referral_id', $referral->id)->latest()->get();
            $concerns = AbsenceConcern::with(['submitter:id,name', 'reviewer:id,name'])
                  ->where('referral_id', $referral->id)->latest()->get();

            $logs  = ReferralStatusLog::where('referral_id', $referral->id)->orderBy('created_at')->get();
            $names = User::whereIn('id', $logs->pluck('changed_by')->filter()->unique())->pluck('name', 'id');

            $startLog  = $logs->firstWhere('status', 'Training Started');
            $startedOn = $referral->training_start_date ?: $startLog?->created_at?->toDateString();

            $referral->started_on  = $startedOn;
            $referral->attendance  = $attendance;
            $referral->assessments = $assessments;
            $referral->conduct     = $conduct;
            $referral->notes       = $notes;
            $referral->concerns    = $concerns;
            $referral->history     = $logs->map(fn ($log) => [
                  'status'     => $log->status,
                  'remarks'    => $log->remarks,
                  'changed_by' => $names[$log->changed_by] ?? null,
                  'created_at' => $log->created_at,
            ])->values();
            $referral->summary = $this->summary(
                  $referral,
                  $startedOn,
                  $attendance,
                  $notes->max('created_at'),
                  $concerns->where('status', 'pending')->count(),
                  $assessments,
                  $conduct
            );

            return response()->json($referral);
      }
}