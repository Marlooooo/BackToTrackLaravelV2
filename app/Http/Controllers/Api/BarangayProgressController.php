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
use App\Notifications\ReasonRequestedForOsy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Training monitoring for SK / barangay officials.
 * Same data as Maxima's Progress Monitoring. Records are read-only, except that
 * an SK can follow up on the concerns Maxima raises (mark reviewed / resolved).
 */
class BarangayProgressController extends Controller
{
    // set this to the exact role value(s) your SK users have in users.role
    private const SK_ROLES = ['sk_officials'];

    // Enrolled = training actually started (Dropped counts only if they were enrolled first)
    private const ENROLLED_STATUSES = ['Training Started', 'Completed'];
    private const MONITORED         = ['Training Started', 'Completed', 'Dropped'];

    // Monitoring thresholds
    private const MIN_ATTENDANCE = 75;
    private const MAX_ABSENCES   = 3;
    private const STALE_DAYS     = 14;
    private const MIN_ASSESSMENT = 75; // % average below this raises an alert

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function authorizeSk(Request $request): void
    {
        abort_unless(in_array($request->user()->role, self::SK_ROLES, true), 403, 'Unauthorized');
    }

    /**
     * Enrolled = training actually started. Dropped counts only if they were enrolled first.
     *
     * SCOPE: this returns ALL enrolled OSY. To limit to the official's own barangay, add e.g.
     * ->whereHas('osyProfile', fn ($p) => $p->where('barangay', $request->user()->barangay))
     *
     * @return Builder<Referral>
     */
    private function enrolledQuery(): Builder
    {
        return Referral::with(['osyProfile', 'trainingProgram', 'referrer:id,name'])
            ->where(function ($q) {
                $q->whereIn('status', self::ENROLLED_STATUSES)
                    ->orWhere(function ($q) {
                        $q->where('status', 'Dropped')
                            ->whereHas('statusLogs', fn ($l) => $l->where('status', 'Training Started'));
                    });
            });
    }

    private function days(Carbon $from, Carbon $to): int
    {
        return (int) round(($to->timestamp - $from->timestamp) / 86400);
    }

    private function respond(Referral $referral): JsonResponse
    {
        return response()->json($this->detail($referral));
    }

    // ------------------------------------------------------------------
    // Summary + detail payload
    // ------------------------------------------------------------------

    /**
     * @param Collection<int, mixed> $attendance
     * @param Carbon|null            $lastNoteAt
     * @param Collection<int, mixed> $assessments
     * @param Collection<int, mixed> $conduct
     * @return array<string, mixed>
     */
    private function summary(
        Referral $referral,
        ?string $startedOn,
        Collection $attendance,
        $lastNoteAt,
        int $pending,
        Collection $assessments,
        Collection $conduct
    ): array {
        $today = Carbon::today();

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

        // Last time anything was recorded for this OSY
        $stamps       = collect([$referral->updated_at, $lastNoteAt, $attendance->max('updated_at')])->filter();
        $lastActivity = $stamps->isEmpty() ? null : $stamps->max();

        // Alerts (same rules as Maxima's Progress Monitoring, plus pending concerns)
        $alerts = [];

        if ($referral->status === 'Training Started') {
            if ($rate !== null && $counted >= 3 && $rate < self::MIN_ATTENDANCE) {
                $alerts[] = "Low attendance ({$rate}%)";
            }
            if ($absent >= self::MAX_ABSENCES) {
                $alerts[] = "{$absent} absences recorded";
            }
            if ($expectedEnd && $today->gt($expectedEnd)) {
                $overdue  = $this->days($expectedEnd, $today);
                $alerts[] = "Past the expected end date by {$overdue} " . ($overdue === 1 ? 'day' : 'days');
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

    /** Full record for one enrolled OSY. */
    private function detail(Referral $referral): Referral
    {
        $attendance = AttendanceRecord::where('referral_id', $referral->id)->orderByDesc('attendance_date')->get();
        $attendance->each(fn ($record) => $record->setAttribute('reason', $this->reasonPayload($record)));

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

        return $referral;
    }

    // ------------------------------------------------------------------
    // List + show
    // ------------------------------------------------------------------

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

        $referrals->each(function (Referral $referral) use ($attendanceBy, $assessmentsBy, $conductBy, $notesBy, $concernsBy, $startLogsBy) {
            $startLog  = $startLogsBy->get($referral->id, collect())->first();
            $startedOn = $referral->training_start_date ?: $startLog?->created_at?->toDateString();
            $lastNote  = isset($notesBy[$referral->id]) ? Carbon::parse($notesBy[$referral->id]) : null;

            $referral->started_on = $startedOn;
            $referral->summary    = $this->summary(
                $referral,
                $startedOn,
                $attendanceBy->get($referral->id, collect()),
                $lastNote,
                (int) ($concernsBy[$referral->id] ?? 0),
                $assessmentsBy->get($referral->id, collect()),
                $conductBy->get($referral->id, collect())
            );
        });

        $counts = [
            'ongoing'         => $referrals->where('status', 'Training Started')->count(),
            'completed'       => $referrals->where('status', 'Completed')->count(),
            'dropped'         => $referrals->where('status', 'Dropped')->count(),
            'needs_attention' => $referrals->filter(fn ($r) => $r->summary['needs_attention'])->count(),
        ];

        if (in_array($request->status, self::MONITORED, true)) {
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

        return $this->respond($referral);
    }

    // ------------------------------------------------------------------
    // Concern follow-up
    // ------------------------------------------------------------------

    /**
     * PATCH /api/barangay/concerns/{concern}
     * The SK follows up on a concern that Maxima raised.
     */
    public function reviewConcern(Request $request, AbsenceConcern $concern)
    {
        $this->authorizeSk($request);

        // Same visibility rule as the monitoring list
        $referral = $this->enrolledQuery()->whereKey($concern->referral_id)->firstOrFail();

        $data = $request->validate([
            'status'         => 'required|in:reviewed,resolved',
            'review_remarks' => 'nullable|string|max:1000',
        ]);

        abort_if($concern->status === 'resolved', 422, 'This concern is already resolved.');
        abort_if(
            $data['status'] === 'reviewed' && $concern->status === 'reviewed',
            422,
            'This concern is already marked as reviewed.'
        );

        // Keep earlier remarks when none are typed this time
        $data['review_remarks'] = $data['review_remarks'] ?? $concern->review_remarks;

        $concern->update($data + ['reviewed_by' => $request->user()->id]);

        return $this->respond($referral);
    }

    // ------------------------------------------------------------------
    // Ask the OSY for a reason
    // ------------------------------------------------------------------

    /**
     * POST /api/barangay/training-monitoring/{referral}/request-reason
     * The SK asks the OSY to explain one or more absences. This is what unlocks
     * the "Add reason" form on the OSY's Training Progress page.
     */
    public function requestReason(Request $request, Referral $referral)
    {
        $this->authorizeSk($request);

        $referral = $this->enrolledQuery()->whereKey($referral->id)->firstOrFail();

        abort_unless($referral->status === 'Training Started', 422, 'Reasons can only be requested while the training is ongoing.');

        // The SK can only ask for a reason after Maxima has raised a concern (and it is not resolved yet)
        abort_unless(
            AbsenceConcern::where('referral_id', $referral->id)->whereIn('status', ['pending', 'reviewed'])->exists(),
            422,
            'Maxima has not raised an open concern for this OSY, so you cannot ask for a reason yet.'
        );

        $data = $request->validate([
            'record_ids'   => 'required|array|min:1|max:30',
            'record_ids.*' => 'integer',
            'message'      => 'nullable|string|max:500',
        ]);

        $ids     = array_values(array_unique($data['record_ids']));
        $records = AttendanceRecord::where('referral_id', $referral->id)->whereIn('id', $ids)->get();

        abort_unless($records->count() === count($ids), 422, 'One of those days could not be found.');
        abort_if($records->contains(fn ($record) => $record->status !== 'absent'), 422, 'You can only ask about days marked absent.');
        abort_if($records->contains(fn ($record) => $record->reason_submitted_at !== null), 422, 'One of those days already has a reason.');

        $message = isset($data['message']) ? trim($data['message']) : null;

        foreach ($records as $record) {
            $record->update([
                'reason_requested_at'    => now(),
                'reason_requested_by'    => $request->user()->id,
                'reason_request_message' => $message ?: null,
            ]);
        }

        ReasonRequestedForOsy::send($referral, $records->count(), $message ?: null, $request->user()->name);

        return $this->respond($referral);
    }

    /** The OSY's reason for one absence, with readable labels (or null if none yet). */
    private function reasonPayload(AttendanceRecord $record): ?array
    {
        if (!$record->reason_submitted_at) {
            return null;
        }

        $config = OsyProgressController::ABSENCE_REASONS[$record->reason_category] ?? null;

        return [
            'category'       => $record->reason_category,
            'category_label' => $config['label'] ?? null,
            'followup_label' => $config['followups'][$record->reason_followup] ?? null,
            'return_date'    => $record->return_date,
            'support'        => collect($record->support_requested ?? [])
                ->map(fn ($key) => OsyProgressController::SUPPORT_OPTIONS[$key] ?? null)
                ->filter()
                ->values(),
            'note'           => $record->reason_note,
            'proof_url'      => $record->reason_proof_path
                ? Storage::disk('public')->url($record->reason_proof_path)
                : null,
            'submitted_at'   => $record->reason_submitted_at,
        ];
    }
}