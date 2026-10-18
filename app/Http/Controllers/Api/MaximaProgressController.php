<?php

      namespace App\Http\Controllers\Api;

      use App\Http\Controllers\Controller;
      use App\Models\AbsenceConcern;
      use App\Models\AttendanceRecord;
      use App\Models\Referral;
      use App\Models\ReferralDocument;
      use App\Models\ReferralNote;
      use App\Models\ReferralStatusLog;
      use App\Models\User;
      use App\Notifications\TrainingStatusChanged;
      use Carbon\Carbon;
      use Illuminate\Http\Request;
      use Illuminate\Support\Arr;
      use Illuminate\Support\Collection;
      use Illuminate\Support\Facades\DB;
      use App\Models\DefaultRequirement;

      class MaximaProgressController extends Controller
      {
      private const MONITORED = ['Accepted by Maxima', 'Training Started', 'Completed', 'Dropped'];

      // Attendance can be recorded (or corrected) once training has started
      private const ATTENDANCE_STATUSES = ['Training Started', 'Completed', 'Dropped'];



      private const DROP_REASONS = [
            'transport'     => 'Transportation problem',
            'work'          => 'Needs to work',
            'family'        => 'Family reasons',
            'health'        => 'Health reasons',
            'lost_interest' => 'Lost interest',
            'other'         => 'Other',
      ];

      // Monitoring thresholds
      private const MIN_ATTENDANCE = 75;  // % (after at least 3 counted days)
      private const MAX_ABSENCES   = 3;
      private const STALE_DAYS     = 14;

      private function authorizeMaxima(Request $request): void
      {
            abort_unless($request->user()->role === 'maxima_tesda_school', 403, 'Unauthorized');
      }

      private function ensureMonitored(Referral $referral): void
      {
            abort_unless(in_array($referral->status, self::MONITORED, true), 404);
      }

      private function daysBetween(Carbon $from, Carbon $to): int
      {
            return (int) round(($to->timestamp - $from->timestamp) / 86400);
      }

      /** Make sure every monitored referral has the default document rows. */
      private function ensureDocuments(Collection $referrals): void
      {
            ReferralDocument::ensureFor($referrals);
      }

      private function buildSummary(
            Referral $referral,
            ?string $startedOn,
            Collection $attendance,
            Collection $documents,
            $lastNoteAt,
            int $pendingConcerns
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
            $stamps = collect([$referral->updated_at, $lastNoteAt, $attendance->max('updated_at')])->filter();
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
                  if ($pendingConcerns > 0) {
                  $alerts[] = $pendingConcerns . ' pending ' . ($pendingConcerns === 1 ? 'concern' : 'concerns');
                  }
            }

            if (in_array($referral->status, ['Accepted by Maxima', 'Training Started'], true) && $docsMissing > 0) {
                  $alerts[] = $docsMissing . ' ' . ($docsMissing === 1 ? 'document' : 'documents') . ' still missing';
            }

            return [
                  'present'          => $present,
                  'late'             => $late,
                  'absent'           => $absent,
                  'excused'          => $excused,
                  'attendance_days'  => $attendance->count(),
                  'attendance_rate'  => $rate,
                  'docs_required'    => $docsRequired,
                  'docs_received'    => $docsReceived,
                  'training_day'     => $trainingDay,
                  'training_length'  => $trainingLength,
                  'last_activity'    => $lastActivity,
                  'alerts'           => $alerts,
                  'needs_attention'  => count($alerts) > 0,
            ];
      }

      /** Full workspace payload for one OSY. Every write endpoint returns this too. */
      private function detail(Referral $referral): Referral
      {
            $referral = $referral->fresh();

            $this->ensureDocuments(collect([$referral]));

            $referral->load(['osyProfile', 'trainingProgram', 'referrer:id,name', 'reviewer:id,name']);

            $attendance = AttendanceRecord::where('referral_id', $referral->id)
                  ->orderByDesc('attendance_date')
                  ->get();

            $documents = ReferralDocument::where('referral_id', $referral->id)->orderBy('id')->get();
            $defaultNames = DefaultRequirement::pluck('name')->all();
            $documents->each(fn ($d) => $d->setAttribute('is_default', in_array($d->name, $defaultNames, true)));


            $notes = ReferralNote::with('user:id,name')
                  ->where('referral_id', $referral->id)
                  ->latest()
                  ->get();

            $concerns = AbsenceConcern::with(['submitter:id,name', 'reviewer:id,name'])
                  ->where('referral_id', $referral->id)
                  ->latest()
                  ->get();

            $logs  = ReferralStatusLog::where('referral_id', $referral->id)->orderBy('created_at')->get();
            $names = User::whereIn('id', $logs->pluck('changed_by')->filter()->unique())->pluck('name', 'id');

            $startLog  = $logs->firstWhere('status', 'Training Started');
            $startedOn = $referral->training_start_date ?: $startLog?->created_at?->toDateString();

            $referral->started_on = $startedOn;
            $referral->attendance = $attendance;
            $referral->documents  = $documents;
            $referral->notes      = $notes;
            $referral->concerns   = $concerns;
            $referral->history    = $logs->map(fn ($log) => [
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
                  $notes->max('created_at'),
                  $concerns->where('status', 'pending')->count()
            );

            return $referral;
      }

      /**
       * GET /api/maxima/progress?search=&status=
       */
      public function index(Request $request)
      {
            $this->authorizeMaxima($request);

            $referrals = Referral::with(['osyProfile', 'trainingProgram'])
                  ->whereIn('status', self::MONITORED)
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

            $attendanceBy = AttendanceRecord::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
            $documentsBy  = ReferralDocument::whereIn('referral_id', $ids)->get()->groupBy('referral_id');
            $notesBy      = ReferralNote::whereIn('referral_id', $ids)
                  ->selectRaw('referral_id, MAX(created_at) as last_at')
                  ->groupBy('referral_id')
                  ->pluck('last_at', 'referral_id');
            $concernsBy   = AbsenceConcern::whereIn('referral_id', $ids)
                  ->where('status', 'pending')
                  ->selectRaw('referral_id, COUNT(*) as total')
                  ->groupBy('referral_id')
                  ->pluck('total', 'referral_id');
            $startLogsBy  = ReferralStatusLog::whereIn('referral_id', $ids)
                  ->where('status', 'Training Started')
                  ->orderBy('created_at')
                  ->get()
                  ->groupBy('referral_id');

            $referrals->each(function ($referral) use ($attendanceBy, $documentsBy, $notesBy, $concernsBy, $startLogsBy) {
                  $startLog  = $startLogsBy->get($referral->id, collect())->first();
                  $startedOn = $referral->training_start_date ?: $startLog?->created_at?->toDateString();
                  $lastNote  = isset($notesBy[$referral->id]) ? Carbon::parse($notesBy[$referral->id]) : null;

                  $referral->started_on = $startedOn;
                  $referral->summary    = $this->buildSummary(
                  $referral,
                  $startedOn,
                  $attendanceBy->get($referral->id, collect()),
                  $documentsBy->get($referral->id, collect()),
                  $lastNote,
                  (int) ($concernsBy[$referral->id] ?? 0)
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

            return response()->json($this->detail($referral));
      }

      // ------------------------------------------------------------------
      // Training details + status changes
      // ------------------------------------------------------------------

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

      private function outcomeRules(): array
      {
            return [
                  'training_end_date'    => 'nullable|date',
                  'after_training_status' => 'nullable|in:employed,self_employed,further_training,looking_for_work,other',
                  'after_training_note'  => 'nullable|string|max:255',
                  'outcome_remarks'      => 'nullable|string|max:1000',
            ];
      }

      private function dropRules(): array
      {
            return [
                  'training_end_date' => 'nullable|date',
                  'drop_reason'       => 'required|in:' . implode(',', array_keys(self::DROP_REASONS)),
                  'outcome_remarks'   => 'nullable|string|max:1000',
            ];
      }

      private function assertDateOrder(?string $start, ?string $end): void
      {
            if ($start && $end && Carbon::parse($end)->lt(Carbon::parse($start))) {
                  abort(422, 'The end date cannot be before the start date.');
            }
      }

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

            return response()->json($this->detail($referral));
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

                  ReferralDocument::ensureFor(collect([$current]));

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

            return response()->json($this->detail($referral));
      }

      /**
       * POST /api/maxima/progress/{referral}/complete
       */
      public function complete(Request $request, Referral $referral)
      {
            $this->authorizeMaxima($request);
            abort_unless($referral->status === 'Training Started', 422, 'Only ongoing trainings can be completed.');

            $data = $request->validate($this->outcomeRules());
            $data['training_end_date'] = $data['training_end_date'] ?? now()->toDateString();
            $this->assertDateOrder($referral->training_start_date, $data['training_end_date']);

            $this->changeStatus($request, $referral, 'Completed', $data, $data['outcome_remarks'] ?? null);
            $this->notifyReferrer($referral, 'Completed');

            return response()->json($this->detail($referral));
      }

      /**
       * POST /api/maxima/progress/{referral}/drop
       */
      public function drop(Request $request, Referral $referral)
      {
            $this->authorizeMaxima($request);
            abort_unless(
                  in_array($referral->status, ['Accepted by Maxima', 'Training Started'], true),
                  422,
                  'Only accepted or ongoing referrals can be dropped.'
            );

            $data = $request->validate($this->dropRules());
            $data['training_end_date'] = $data['training_end_date'] ?? now()->toDateString();
            $this->assertDateOrder($referral->training_start_date, $data['training_end_date']);

            $reasonLabel = self::DROP_REASONS[$data['drop_reason']];
            $logRemarks  = $reasonLabel . (!empty($data['outcome_remarks']) ? ': ' . $data['outcome_remarks'] : '');

            $this->changeStatus($request, $referral, 'Dropped', $data, $logRemarks);
            $this->notifyReferrer($referral, 'Dropped', $reasonLabel);

            return response()->json($this->detail($referral));
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

            return response()->json($this->detail($referral));
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
            abort_unless(
                  in_array($referral->status, self::ATTENDANCE_STATUSES, true),
                  422,
                  'Start the training first before recording attendance.'
            );

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

            return response()->json($this->detail($referral));
      }

      /**
       * DELETE /api/maxima/progress/attendance/{record}
       */
      public function destroyAttendance(Request $request, AttendanceRecord $record)
      {
            $this->authorizeMaxima($request);

            $referral = $record->referral;
            $record->delete();

            return response()->json($this->detail($referral));
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

            return response()->json($this->detail($referral));
      }

      /**
       * DELETE /api/maxima/progress/notes/{note}
       */
      public function destroyNote(Request $request, ReferralNote $note)
      {
            $this->authorizeMaxima($request);

            $referral = $note->referral;
            $note->delete();

            return response()->json($this->detail($referral));
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

      return response()->json($this->detail($referral));
      }

      /**
       * PUT /api/maxima/progress/documents/{document}
       */
/**
 * PUT /api/maxima/progress/documents/{document}
 */
      public function updateDocument(Request $request, ReferralDocument $document)
      {
      $this->authorizeMaxima($request);

      $referral = $document->referral;

      abort_unless(
            in_array($referral->status, ['Accepted by Maxima', 'Training Started'], true),
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

      return response()->json($this->detail($referral));
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

            $referral = $document->referral;
            $document->delete();

            return response()->json($this->detail($referral));
      }

      // ------------------------------------------------------------------
      // Concerns
      // ------------------------------------------------------------------

      /**
       * PATCH /api/maxima/absence-concerns/{concern}
       */
      public function reviewConcern(Request $request, AbsenceConcern $concern)
      {
            $this->authorizeMaxima($request);

            $data = $request->validate([
                  'status'         => 'required|in:reviewed,resolved',
                  'review_remarks' => 'nullable|string|max:1000',
            ]);

            $concern->update($data + ['reviewed_by' => $request->user()->id]);

            return response()->json($this->detail($concern->referral));
      }
}