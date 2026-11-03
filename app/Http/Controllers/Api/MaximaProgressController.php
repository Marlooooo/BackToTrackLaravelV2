<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsenceConcern;
use App\Models\AttendanceRecord;
use App\Models\ConductRecord;
use App\Models\DefaultRequirement;
use App\Models\Referral;
use App\Models\ReferralDocument;
use App\Models\ReferralNote;
use App\Models\ReferralStatusLog;
use App\Models\TrainingAssessment;
use App\Models\User;
use App\Notifications\NewConcern;
use App\Notifications\ReferralUpdateForOsy;
use App\Notifications\TrainingStatusChanged;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaximaProgressController extends Controller
{
    private const MONITORED = ['Accepted by Maxima', 'Training Started', 'Completed', 'Dropped'];

    // Progress Monitoring lists only OSY who were actually enrolled
    private const ENROLLED_STATUSES = ['Training Started', 'Completed'];

    // Statuses where requirements can still be changed and the referral can be dropped
    private const OPEN_STATUSES = ['Accepted by Maxima', 'Training Started'];

    // Attendance, assessments and conduct can be recorded (or corrected) once training has started
    private const ATTENDANCE_STATUSES = ['Training Started', 'Completed', 'Dropped'];

    private const DROP_REASONS = [
        'transport'     => 'Transportation problem',
        'work'          => 'Needs to work',
        'family'        => 'Family reasons',
        'health'        => 'Health reasons',
        'lost_interest' => 'Lost interest',
        'other'         => 'Other',
    ];

    private const CONDUCT_RATINGS = ['excellent', 'good', 'fair', 'needs_improvement'];

    // Monitoring thresholds
    private const MIN_ATTENDANCE   = 75; // % (after at least 3 counted days)
    private const MAX_ABSENCES     = 3;
    private const STALE_DAYS       = 14;
    private const MIN_ASSESSMENT   = 75; // % average below this raises an alert
    private const CONCERN_RATE     = 50; // % attendance rate below this lets Maxima raise a concern
    private const CONCERN_MIN_DAYS = 3;  // counted days (present + late + absent) needed before a concern can be raised

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function authorizeMaxima(Request $request): void
    {
        abort_unless($request->user()->role === 'maxima_tesda_school', 403, 'Unauthorized');
    }

    private function ensureMonitored(Referral $referral): void
    {
        abort_unless(in_array($referral->status, self::MONITORED, true), 404);
    }

    private function assertCanRecord(Referral $referral, string $what): void
    {
        abort_unless(
            in_array($referral->status, self::ATTENDANCE_STATUSES, true),
            422,
            "Start the training first before recording {$what}."
        );
    }

    private function assertDateOrder(?string $start, ?string $end): void
    {
        if ($start && $end && Carbon::parse($end)->lt(Carbon::parse($start))) {
            abort(422, 'The end date cannot be before the start date.');
        }
    }

    private function daysBetween(Carbon $from, Carbon $to): int
    {
        return (int) round(($to->timestamp - $from->timestamp) / 86400);
    }

    /** Every write endpoint returns the refreshed workspace for the OSY. */
    private function respond(Referral $referral): JsonResponse
    {
        return response()->json($this->detail($referral));
    }

    /**
     * Delete a child record (it must have a "referral" relation) and return the refreshed workspace.
     */
    private function deleteAndRespond(Model $model): JsonResponse
    {
        $referral = $model->referral;
        $model->delete();

        return $this->respond($referral);
    }

    /**
     * Default the end date to today, then check it is not before the start date.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withEndDate(array $data, Referral $referral): array
    {
        $data['training_end_date'] = $data['training_end_date'] ?? now()->toDateString();
        $this->assertDateOrder($referral->training_start_date, $data['training_end_date']);

        return $data;
    }

    /**
     * Make sure every monitored referral has the default document rows.
     *
     * @param Collection<int, Referral> $referrals
     */
    private function ensureDocuments(Collection $referrals): void
    {
        ReferralDocument::ensureFor($referrals);
    }

    // ------------------------------------------------------------------
    // Summary + detail payload
    // ------------------------------------------------------------------

    /**
     * @param Collection<int, mixed> $attendance
     * @param Collection<int, mixed> $documents
     * @param Collection<int, mixed> $assessments
     * @param Collection<int, mixed> $conduct
     * @param Carbon|null            $lastNoteAt
     * @return array<string, mixed>
     */
    private function buildSummary(
        Referral $referral,
        ?string $startedOn,
        Collection $attendance,
        Collection $documents,
        Collection $assessments,
        Collection $conduct,
        $lastNoteAt
    ): array {
        $today = Carbon::today();

        // Attendance (excused days are not counted against the OSY)
        $present = $attendance->where('status', 'present')->count();
        $late    = $attendance->where('status', 'late')->count();
        $absent  = $attendance->where('status', 'absent')->count();
        $excused = $attendance->where('status', 'excused')->count();
        $counted = $present + $late + $absent;
        $rate    = $counted > 0 ? round(($present + $late) / $counted * 100, 1) : null;

        // Documents (not-applicable ones are ignored)
        $applicable   = $documents->where('status', '!=', 'not_applicable');
        $docsRequired = $applicable->count();
        $docsReceived = $applicable->where('status', 'received')->count();
        $docsMissing  = $docsRequired - $docsReceived;

        // Assessments (average of score / max score) and latest conduct rating
        $scored        = $assessments->filter(fn ($a) => $a->max_score > 0);
        $assessmentAvg = $scored->isEmpty()
            ? null
            : round($scored->avg(fn ($a) => $a->score / $a->max_score * 100), 1);
        $latestConduct = $conduct->sortBy([['recorded_on', 'desc'], ['id', 'desc']])->first();

        // Training day
        $start       = $startedOn ? Carbon::parse($startedOn)->startOfDay() : null;
        $expectedEnd = $referral->expected_end_date ? Carbon::parse($referral->expected_end_date)->startOfDay() : null;

        $trainingDay    = null;
        $trainingLength = null;

        if ($start && $referral->status === 'Training Started') {
            $trainingDay = max(1, $this->daysBetween($start, $today) + 1);
        }
        if ($start && $expectedEnd) {
            $trainingLength = max(1, $this->daysBetween($start, $expectedEnd) + 1);
        }

        // Last time anything was recorded for this OSY
        $stamps       = collect([$referral->updated_at, $lastNoteAt, $attendance->max('updated_at')])->filter();
        $lastActivity = $stamps->isEmpty() ? null : $stamps->max();

        // Alerts
        $alerts = [];

        if ($referral->status === 'Training Started') {
            if ($rate !== null && $counted >= 3 && $rate < self::MIN_ATTENDANCE) {
                $alerts[] = "Low attendance ({$rate}%)";
            }
            if ($absent >= self::MAX_ABSENCES) {
                $alerts[] = "{$absent} absences recorded";
            }
            if ($expectedEnd && $today->gt($expectedEnd)) {
                $days     = $this->daysBetween($expectedEnd, $today);
                $alerts[] = "Past the expected end date by {$days} " . ($days === 1 ? 'day' : 'days');
            }
            if ($lastActivity) {
                $idle = $this->daysBetween(Carbon::parse($lastActivity)->startOfDay(), $today);
                if ($idle >= self::STALE_DAYS) {
                    $alerts[] = "No update for {$idle} days";
                }
            }
            if ($assessmentAvg !== null && $scored->count() >= 2 && $assessmentAvg < self::MIN_ASSESSMENT) {
                $alerts[] = "Low assessment average ({$assessmentAvg}%)";
            }
            if ($latestConduct?->rating === 'needs_improvement') {
                $alerts[] = 'Conduct needs improvement';
            }
        }

        if (in_array($referral->status, self::OPEN_STATUSES, true) && $docsMissing > 0) {
            $alerts[] = $docsMissing . ' ' . ($docsMissing === 1 ? 'document' : 'documents') . ' still missing';
        }

        return [
            'present'            => $present,
            'late'               => $late,
            'absent'             => $absent,
            'excused'            => $excused,
            'attendance_days'    => $attendance->count(),
            'attendance_rate'    => $rate,
            'concern_rate'       => self::CONCERN_RATE,
            'concern_min_days'   => self::CONCERN_MIN_DAYS,
            'concern_eligible'   => $rate !== null
                && $counted >= self::CONCERN_MIN_DAYS
                && $rate < self::CONCERN_RATE,
            'docs_required'      => $docsRequired,
            'docs_received'      => $docsReceived,
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

    /** Full workspace payload for one OSY. */
    private function detail(Referral $referral): Referral
    {
        $referral = $referral->fresh();

        $this->ensureDocuments(collect([$referral]));

        $referral->load(['osyProfile', 'trainingProgram', 'referrer:id,name', 'reviewer:id,name']);

        $attendance = AttendanceRecord::where('referral_id', $referral->id)
            ->orderByDesc('attendance_date')
            ->get();

        $documents    = ReferralDocument::where('referral_id', $referral->id)->orderBy('id')->get();
        $defaultNames = DefaultRequirement::pluck('name')->all();
        $documents->each(fn ($d) => $d->setAttribute('is_default', in_array($d->name, $defaultNames, true)));

        $assessments = TrainingAssessment::where('referral_id', $referral->id)
            ->orderByDesc('assessed_on')->orderByDesc('id')->get();
        $conduct = ConductRecord::where('referral_id', $referral->id)
            ->orderByDesc('recorded_on')->orderByDesc('id')->get();

        $notes = ReferralNote::with('user:id,name')
            ->where('referral_id', $referral->id)
            ->latest()
            ->get();

        $concerns = AbsenceConcern::with(['submitter:id,name', 'reviewer:id,name'])
            ->where('referral_id', $referral->id)
            ->latest()
            ->get();

        // Maxima only sees THAT the SK asked and the OSY replied, not what the OSY wrote
        $concerns->each(function ($concern) use ($attendance) {
            $asked = $attendance->filter(
                fn ($r) => $r->reason_requested_at && $r->reason_requested_at->gte($concern->created_at)
            );

            $concern->setAttribute('reason_flow', $asked->isEmpty() ? null : [
                'asked_at'      => $asked->min('reason_requested_at'),
                'asked_count'   => $asked->count(),
                'replied_count' => $asked->filter(fn ($r) => $r->reason_submitted_at)->count(),
                'replied_at'    => $asked->max('reason_submitted_at'),
            ]);
        });

        $attendance->each->makeHidden([
            'reason_category', 'reason_followup', 'return_date', 'support_requested',
            'reason_note', 'reason_proof_path', 'reason_request_message',
        ]);

        $logs  = ReferralStatusLog::where('referral_id', $referral->id)->orderBy('created_at')->get();
        $names = User::whereIn('id', $logs->pluck('changed_by')->filter()->unique())->pluck('name', 'id');

        $startLog  = $logs->firstWhere('status', 'Training Started');
        $startedOn = $referral->training_start_date ?: $startLog?->created_at?->toDateString();

        $referral->started_on  = $startedOn;
        $referral->attendance  = $attendance;
        $referral->documents   = $documents;
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

        $referral->summary = $this->buildSummary(
            $referral,
            $startedOn,
            $attendance,
            $documents,
            $assessments,
            $conduct,
            $notes->max('created_at')
        );

        return $referral;
    }

    // ------------------------------------------------------------------
    // List + show
    // ------------------------------------------------------------------

    /**
     * GET /api/maxima/progress?search=&status=
     */
    public function index(Request $request)
    {
        $this->authorizeMaxima($request);

        $referrals = Referral::with(['osyProfile', 'trainingProgram'])
            ->where(function ($q) {
                $q->whereIn('status', self::ENROLLED_STATUSES)
                    // Dropped only counts if they were enrolled first.
                    // An accepted OSY that Maxima ignored was never in training.
                    ->orWhere(function ($q) {
                        $q->where('status', 'Dropped')
                            ->whereHas('statusLogs', fn ($l) => $l->where('status', 'Training Started'));
                    });
            })
            ->when($request->search, function ($q, $search) {
                $q->whereHas('osyProfile', function ($p) use ($search) {
                    $p->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        $this->ensureDocuments($referrals);

        $ids = $referrals->pluck('id');

        $attendanceBy  = AttendanceRecord::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
        $documentsBy   = ReferralDocument::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
        $assessmentsBy = TrainingAssessment::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
        $conductBy     = ConductRecord::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
        $notesBy       = ReferralNote::whereIn('referral_id', $ids)
            ->selectRaw('referral_id, MAX(created_at) as last_at')
            ->groupBy('referral_id')
            ->pluck('last_at', 'referral_id');
        $startLogsBy   = ReferralStatusLog::whereIn('referral_id', $ids)
            ->where('status', 'Training Started')
            ->orderBy('created_at')
            ->get()
            ->groupBy('referral_id');

        $referrals->each(function (Referral $referral) use ($attendanceBy, $documentsBy, $assessmentsBy, $conductBy, $notesBy, $startLogsBy) {
            $startLog  = $startLogsBy->get($referral->id, collect())->first();
            $startedOn = $referral->training_start_date ?: $startLog?->created_at?->toDateString();
            $lastNote  = isset($notesBy[$referral->id]) ? Carbon::parse($notesBy[$referral->id]) : null;

            $referral->started_on = $startedOn;
            $referral->summary    = $this->buildSummary(
                $referral,
                $startedOn,
                $attendanceBy->get($referral->id, collect()),
                $documentsBy->get($referral->id, collect()),
                $assessmentsBy->get($referral->id, collect()),
                $conductBy->get($referral->id, collect()),
                $lastNote
            );
        });

        $counts = [
            'accepted'        => $referrals->where('status', 'Accepted by Maxima')->count(),
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
     * GET /api/maxima/progress/{referral}
     */
    public function show(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        $this->ensureMonitored($referral);

        return $this->respond($referral);
    }

    // ------------------------------------------------------------------
    // Training details + status changes
    // ------------------------------------------------------------------

    /** @return array<string, string> */
    private function detailRules(): array
    {
        return [
            'training_start_date' => 'nullable|date',
            'expected_end_date'   => 'nullable|date',
            'batch_name'          => 'nullable|string|max:100',
            'venue'               => 'nullable|string|max:150',
            'trainer_name'        => 'nullable|string|max:100',
        ];
    }

    /** @return array<string, string> */
    private function outcomeRules(): array
    {
        return [
            'training_end_date'     => 'nullable|date',
            'after_training_status' => 'nullable|in:employed,self_employed,further_training,looking_for_work,other',
            'after_training_note'   => 'nullable|string|max:255',
            'outcome_remarks'       => 'nullable|string|max:1000',
        ];
    }

    /** @return array<string, string> */
    private function dropRules(): array
    {
        return [
            'training_end_date' => 'nullable|date',
            'drop_reason'       => 'required|in:' . implode(',', array_keys(self::DROP_REASONS)),
            'outcome_remarks'   => 'nullable|string|max:1000',
        ];
    }

    /**
     * Save the new status together with its log entry in one transaction.
     *
     * @param array<string, mixed> $fields
     */
    private function changeStatus(Request $request, Referral $referral, string $to, array $fields, ?string $logRemarks): void
    {
        DB::transaction(function () use ($request, $referral, $to, $fields, $logRemarks) {
            $referral->forceFill($fields + [
                'status'      => $to,
                'reviewed_by' => $request->user()->id,
            ])->save();

            ReferralStatusLog::create([
                'referral_id' => $referral->id,
                'changed_by'  => $request->user()->id,
                'status'      => $to,
                'remarks'     => $logRemarks,
            ]);
        });
    }

    private function notifyReferrer(Referral $referral, string $status, ?string $detail = null): void
    {
        try {
            $referral->loadMissing(['osyProfile', 'trainingProgram', 'referrer']);
            $referral->referrer?->notify(new TrainingStatusChanged($referral, $status, $detail));
        } catch (\Throwable $e) {
            report($e); // a notification problem must never block the status change
        }
    }

    /**
     * PUT /api/maxima/progress/{referral}/details
     */
    public function updateDetails(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        $this->ensureMonitored($referral);

        $data = $request->validate($this->detailRules());

        $start = array_key_exists('training_start_date', $data) ? $data['training_start_date'] : $referral->training_start_date;
        $end   = array_key_exists('expected_end_date', $data) ? $data['expected_end_date'] : $referral->expected_end_date;
        $this->assertDateOrder($start, $end);

        $referral->forceFill($data)->save();

        return $this->respond($referral);
    }

    /**
     * POST /api/maxima/progress/{referral}/start
     */
    public function start(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);

        $data = $request->validate($this->detailRules());
        $data['training_start_date'] = $data['training_start_date'] ?? now()->toDateString();
        $this->assertDateOrder($data['training_start_date'], $data['expected_end_date'] ?? null);

        DB::transaction(function () use ($request, $referral, $data) {
            // Lock the row and re-check, so a double click or a second user
            // cannot enroll the same OSY twice or skip the requirements.
            $current = Referral::whereKey($referral->id)->lockForUpdate()->firstOrFail();

            abort_unless($current->status === 'Accepted by Maxima', 422, 'Only accepted referrals can be started.');

            $this->ensureDocuments(collect([$current]));

            $pending = ReferralDocument::where('referral_id', $current->id)
                ->where('status', 'pending')
                ->count();

            abort_if(
                $pending > 0,
                422,
                "Cannot enroll yet: {$pending} " . ($pending === 1 ? 'requirement is' : 'requirements are') . ' still pending.'
            );

            $this->changeStatus($request, $current, 'Training Started', $data, null);
        });

        $referral->refresh(); // the transaction changed a different copy, so reload this one
        $this->notifyReferrer($referral, 'Training Started');
        ReferralUpdateForOsy::send($referral, 'Training Started');

        return $this->respond($referral);
    }

    /**
     * POST /api/maxima/progress/{referral}/complete
     */
    public function complete(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        abort_unless($referral->status === 'Training Started', 422, 'Only ongoing trainings can be completed.');

        $data = $this->withEndDate($request->validate($this->outcomeRules()), $referral);

        $this->changeStatus($request, $referral, 'Completed', $data, $data['outcome_remarks'] ?? null);
        $this->notifyReferrer($referral, 'Completed');
        ReferralUpdateForOsy::send($referral, 'Completed');

        return $this->respond($referral);
    }

    /**
     * POST /api/maxima/progress/{referral}/drop
     */
    public function drop(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        abort_unless(
            in_array($referral->status, self::OPEN_STATUSES, true),
            422,
            'Only accepted or ongoing referrals can be dropped.'
        );

        $data = $this->withEndDate($request->validate($this->dropRules()), $referral);

        $reasonLabel = self::DROP_REASONS[$data['drop_reason']];
        $logRemarks  = $reasonLabel . (!empty($data['outcome_remarks']) ? ': ' . $data['outcome_remarks'] : '');

        $this->changeStatus($request, $referral, 'Dropped', $data, $logRemarks);
        $this->notifyReferrer($referral, 'Dropped', $reasonLabel);
        ReferralUpdateForOsy::send($referral, 'Dropped', $reasonLabel);

        return $this->respond($referral);
    }

    /**
     * PUT /api/maxima/progress/{referral}/outcome  (edit the outcome of a completed or dropped training)
     */
    public function updateOutcome(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        abort_unless(in_array($referral->status, ['Completed', 'Dropped'], true), 422, 'This training has no outcome yet.');

        $data = $request->validate(
            $referral->status === 'Completed' ? $this->outcomeRules() : $this->dropRules()
        );
        $this->assertDateOrder($referral->training_start_date, $data['training_end_date'] ?? null);

        $referral->forceFill($data)->save();

        return $this->respond($referral);
    }

    // ------------------------------------------------------------------
    // Attendance
    // ------------------------------------------------------------------

    /**
     * POST /api/maxima/progress/{referral}/attendance  (one record per date; re-posting a date replaces it)
     */
    public function saveAttendance(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        $this->assertCanRecord($referral, 'attendance');

        $data = $request->validate([
            'attendance_date' => 'required|date',
            'status'          => 'required|in:present,late,absent,excused',
            'remarks'         => 'nullable|string|max:255',
        ]);

        AttendanceRecord::updateOrCreate(
            ['referral_id' => $referral->id, 'attendance_date' => $data['attendance_date']],
            [
                'status'      => $data['status'],
                'remarks'     => $data['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ]
        );

        return $this->respond($referral);
    }

    /**
     * DELETE /api/maxima/progress/attendance/{record}
     */
    public function destroyAttendance(Request $request, AttendanceRecord $record)
    {
        $this->authorizeMaxima($request);

        return $this->deleteAndRespond($record);
    }

    // ------------------------------------------------------------------
    // Assessments + conduct
    // ------------------------------------------------------------------

    /**
     * POST /api/maxima/progress/{referral}/assessments
     */
    public function storeAssessment(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        $this->assertCanRecord($referral, 'assessments');

        $data = $request->validate([
            'title'       => 'required|string|max:100',
            'score'       => 'required|numeric|min:0',
            'max_score'   => 'required|numeric|gt:0',
            'assessed_on' => 'required|date|before_or_equal:today',
            'remarks'     => 'nullable|string|max:255',
        ]);

        abort_if($data['score'] > $data['max_score'], 422, 'The score cannot be higher than the maximum score.');

        TrainingAssessment::create($data + [
            'referral_id' => $referral->id,
            'recorded_by' => $request->user()->id,
        ]);

        return $this->respond($referral);
    }

    /**
     * DELETE /api/maxima/progress/assessments/{assessment}
     */
    public function destroyAssessment(Request $request, TrainingAssessment $assessment)
    {
        $this->authorizeMaxima($request);

        return $this->deleteAndRespond($assessment);
    }

    /**
     * POST /api/maxima/progress/{referral}/conduct
     */
    public function storeConduct(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        $this->assertCanRecord($referral, 'conduct');

        $data = $request->validate([
            'rating'      => 'required|in:' . implode(',', self::CONDUCT_RATINGS),
            'remarks'     => 'required_if:rating,needs_improvement|nullable|string|max:255',
            'recorded_on' => 'required|date|before_or_equal:today',
        ], [
            'remarks.required_if' => 'Please explain what needs improvement.',
        ]);

        ConductRecord::create($data + [
            'referral_id' => $referral->id,
            'recorded_by' => $request->user()->id,
        ]);

        return $this->respond($referral);
    }

    /**
     * DELETE /api/maxima/progress/conduct/{conduct}
     */
    public function destroyConduct(Request $request, ConductRecord $conduct)
    {
        $this->authorizeMaxima($request);

        return $this->deleteAndRespond($conduct);
    }

    // ------------------------------------------------------------------
    // Notes
    // ------------------------------------------------------------------

    /**
     * POST /api/maxima/progress/{referral}/notes
     */
    public function storeNote(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        $this->ensureMonitored($referral);

        $data = $request->validate(['note' => 'required|string|max:1000']);

        ReferralNote::create([
            'referral_id' => $referral->id,
            'user_id'     => $request->user()->id,
            'note'        => $data['note'],
        ]);

        return $this->respond($referral);
    }

    /**
     * DELETE /api/maxima/progress/notes/{note}
     */
    public function destroyNote(Request $request, ReferralNote $note)
    {
        $this->authorizeMaxima($request);

        return $this->deleteAndRespond($note);
    }

    // ------------------------------------------------------------------
    // Documents
    // ------------------------------------------------------------------

    /**
     * POST /api/maxima/progress/{referral}/documents  (add a custom requirement)
     */
    public function storeDocument(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);

        abort_unless($referral->status === 'Accepted by Maxima', 422, 'Requirements can only be added while the OSY is still Accepted.');

        $data = $request->validate(['name' => 'required|string|max:100']);
        $name = preg_replace('/\s+/', ' ', trim($data['name']));

        if (ReferralDocument::where('referral_id', $referral->id)->where('name', $name)->exists()) {
            abort(422, 'That requirement is already in the list.');
        }

        ReferralDocument::create([
            'referral_id' => $referral->id,
            'name'        => $name,
            'status'      => 'pending',
            'updated_by'  => $request->user()->id,
        ]);

        return $this->respond($referral);
    }

    /**
     * PUT /api/maxima/progress/documents/{document}
     */
    public function updateDocument(Request $request, ReferralDocument $document)
    {
        $this->authorizeMaxima($request);

        $referral = $document->referral;

        abort_unless(
            in_array($referral->status, self::OPEN_STATUSES, true),
            422,
            'Requirements can no longer be changed for this OSY.'
        );

        $data = $request->validate([
            'status'  => 'required|in:pending,received,not_applicable',
            'remarks' => 'nullable|string|max:255',
        ]);

        $document->update([
            'status'      => $data['status'],
            'remarks'     => $data['remarks'] ?? null,
            'received_at' => $data['status'] === 'received'
                ? ($document->received_at ?: now()->toDateString())
                : null,
            'updated_by'  => $request->user()->id,
        ]);

        return $this->respond($referral);
    }

    /**
     * DELETE /api/maxima/progress/documents/{document}  (custom documents only)
     */
    public function destroyDocument(Request $request, ReferralDocument $document)
    {
        $this->authorizeMaxima($request);

        abort_if(
            DefaultRequirement::where('name', $document->name)->exists(),
            422,
            'Default requirements cannot be removed from one OSY. Mark it as not applicable instead.'
        );

        return $this->deleteAndRespond($document);
    }

    // ------------------------------------------------------------------
    // Concerns (raised by Maxima when attendance is low; SK follows up)
    // ------------------------------------------------------------------

    private function notifyConcern(AbsenceConcern $concern): void
    {
        try {
            $concern->loadMissing(['referral.osyProfile', 'referral.trainingProgram', 'referral.referrer']);
            $concern->referral?->referrer?->notify(new NewConcern($concern));
        } catch (\Throwable $e) {
            report($e); // a notification problem must never block raising the concern
        }
    }

    /**
     * POST /api/maxima/progress/{referral}/concerns
     */
    public function storeConcern(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);
        $this->assertCanRecord($referral, 'concerns');

        $data = $request->validate([
            'description' => 'required|string|max:1000',
        ]);

        // Same attendance numbers the screen shows, so the rule lives in one place
        $summary = $this->detail($referral)->summary;

        abort_unless(
            $summary['concern_eligible'],
            422,
            'A concern can only be raised when the attendance rate is below ' . self::CONCERN_RATE
                . '% (after at least ' . self::CONCERN_MIN_DAYS . ' counted days).'
        );

        abort_if(
            AbsenceConcern::where('referral_id', $referral->id)->where('status', 'pending')->exists(),
            422,
            'This OSY already has a pending concern.'
        );

        $concern = AbsenceConcern::create([
            'referral_id'  => $referral->id,
            'submitted_by' => $request->user()->id,
            'type'         => 'absence',
            'description'  => $data['description'],
            'status'       => 'pending',
        ]);

        $this->notifyConcern($concern);

        return $this->respond($referral);
    }

    /**
     * DELETE /api/maxima/progress/concerns/{concern}  (pending concerns only, e.g. raised by mistake)
     */
    public function destroyConcern(Request $request, AbsenceConcern $concern)
    {
        $this->authorizeMaxima($request);

        abort_unless($concern->status === 'pending', 422, 'Only pending concerns can be deleted.');

        return $this->deleteAndRespond($concern);
    }
}