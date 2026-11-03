<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsenceConcern;
use App\Models\AttendanceRecord;
use App\Models\OsyProfile;
use App\Models\Referral;
use App\Models\TrainingAssessment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Overall statistics for SK / barangay officials (basic counts and distributions only).
 * Per-OSY details stay on the Training Monitoring page.
 */
class BarangayReportController extends Controller
{
    // same role value(s) as BarangayProgressController
    private const SK_ROLES = ['sk_officials'];

    private const ACTIVE_STATUSES   = ['Referred', 'Accepted by Maxima', 'Training Started'];
    private const ENROLLED_STATUSES = ['Training Started', 'Completed'];

    private const OSY_STAGES = [
        'Registered',
        'Validated',
        'Referred',
        'Accepted by Maxima',
        'Training Started',
        'Completed',
        'Dropped',
    ];

    private const REFERRAL_STAGES = [
        'Referred',
        'Accepted by Maxima',
        'Training Started',
        'Completed',
        'Dropped',
    ];

    private const AGE_GROUPS = [
        '15-17' => [15, 17],
        '18-21' => [18, 21],
        '22-25' => [22, 25],
        '26-30' => [26, 30],
    ];

    // same thresholds as BarangayProgressController, so "at risk" matches the monitoring page
    private const MIN_ATTENDANCE = 75;
    private const MAX_ABSENCES   = 3;
    private const MIN_ASSESSMENT = 75;

    /**
     * GET /api/barangay/reports?from=&to=&program_id=
     *
     * - OSY statistics (gender, age, stages, transportation): every OSY registered on or before "to".
     * - Referral and program statistics: referrals created between "from" and "to" (and for program_id, if given).
     */
    public function index(Request $request)
    {
        abort_unless(in_array($request->user()->role, self::SK_ROLES, true), 403, 'Unauthorized');

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

        // ---------------- OSY statistics ----------------
        $profiles = OsyProfile::with('latestReferral')
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->get();
        $totalOsy = $profiles->count();

        $gender = collect(['male' => 'Male', 'female' => 'Female'])
            ->map(function ($label, $key) use ($profiles, $totalOsy) {
                $count = $profiles->where('sex', $key)->count();

                return ['label' => $label, 'count' => $count, 'percent' => $this->pct($count, $totalOsy)];
            })
            ->values();

        $ages = $profiles->map(fn ($p) => $p->birthdate ? $p->birthdate->age : null);

        $ageGroups = collect(self::AGE_GROUPS)
            ->map(function ($range, $label) use ($ages, $totalOsy) {
                $count = $ages->filter(fn ($age) => $age !== null && $age >= $range[0] && $age <= $range[1])->count();

                return ['label' => $label, 'count' => $count, 'percent' => $this->pct($count, $totalOsy)];
            })
            ->values();

        $stageCounts = $profiles->countBy(fn ($p) => $p->display_status);
        $osyStages   = collect(self::OSY_STAGES)
            ->map(fn ($stage) => [
                'label'   => $stage,
                'count'   => (int) ($stageCounts[$stage] ?? 0),
                'percent' => $this->pct((int) ($stageCounts[$stage] ?? 0), $totalOsy),
            ])
            ->values();

        $lacking = $profiles->where('has_transportation', false)->count();
        $transportation = [
            'total'        => $totalOsy,
            'with_support' => $totalOsy - $lacking,
            'lacking'      => $lacking,
            'lacking_percent' => $this->pct($lacking, $totalOsy),
        ];

        // ---------------- Referral statistics ----------------
        $referrals = Referral::query()->tap($inRange)->get();

        $statusCounts   = $referrals->countBy('status');
        $referralStages = collect(self::REFERRAL_STAGES)
            ->map(fn ($stage) => [
                'label'   => $stage,
                'count'   => (int) ($statusCounts[$stage] ?? 0),
                'percent' => $this->pct((int) ($statusCounts[$stage] ?? 0), $referrals->count()),
            ])
            ->values();

        // ---------------- Enrolled OSY: programs + at risk ----------------
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
        $pendingBy     = AbsenceConcern::whereIn('referral_id', $ids)
            ->where('status', 'pending')
            ->selectRaw('referral_id, COUNT(*) as total')
            ->groupBy('referral_id')
            ->pluck('total', 'referral_id');

        $rows = $enrolled->map(function (Referral $referral) use ($attendanceBy, $assessmentsBy, $pendingBy) {
            $m       = $this->metrics(
                $attendanceBy->get($referral->id, collect()),
                $assessmentsBy->get($referral->id, collect())
            );
            $pending = (int) ($pendingBy[$referral->id] ?? 0);

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
                'pending_concerns'   => $pending,
                'reasons'            => $reasons,
            ];
        });

        $programs = $rows->groupBy('program_id')
            ->map(function (Collection $group) {
                $enrollees = $group->count();
                $completed = $group->where('status', 'Completed')->count();
                $dropped   = $group->where('status', 'Dropped')->count();
                $rates     = $group->pluck('attendance_rate')->filter(fn ($v) => $v !== null);
                $averages  = $group->pluck('assessment_average')->filter(fn ($v) => $v !== null);

                return [
                    'program_id'         => $group->first()['program_id'],
                    'program'            => $group->first()['program'],
                    'enrollees'          => $enrollees,
                    'ongoing'            => $group->where('status', 'Training Started')->count(),
                    'completed'          => $completed,
                    'dropped'            => $dropped,
                    'completion_rate'    => $enrollees > 0 ? round($completed / $enrollees * 100, 1) : null,
                    'attendance_rate'    => $rates->isEmpty() ? null : round($rates->avg(), 1),
                    'assessment_average' => $averages->isEmpty() ? null : round($averages->avg(), 1),
                ];
            })
            ->sortBy([['assessment_average', 'desc'], ['completion_rate', 'desc']])
            ->values();

        $atRisk = $rows
            ->filter(fn ($row) => count($row['reasons']) > 0)
            ->sortByDesc(fn ($row) => count($row['reasons']))
            ->values();

        return response()->json([
            'filters' => [
                'from'       => $from?->toDateString(),
                'to'         => $to?->toDateString(),
                'program_id' => $programId,
            ],
            'summary' => [
                'total_osy'        => $totalOsy,
                'active_referrals' => $referrals->whereIn('status', self::ACTIVE_STATUSES)->count(),
                'ongoing'          => $referrals->where('status', 'Training Started')->count(),
                'completed'        => $referrals->where('status', 'Completed')->count(),
                'dropped'          => $referrals->where('status', 'Dropped')->count(),
                'at_risk'          => $atRisk->count(),
            ],
            'gender'          => $gender,
            'age_groups'      => $ageGroups,
            'osy_stages'      => $osyStages,
            'referral_stages' => $referralStages,
            'transportation'  => $transportation,
            'programs'        => $programs,
            'at_risk'         => $atRisk,
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