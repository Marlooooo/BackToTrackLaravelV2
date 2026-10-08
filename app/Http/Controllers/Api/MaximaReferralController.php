<?php

    namespace App\Http\Controllers\Api;

    use App\Http\Controllers\Controller;
    use App\Models\Referral;
    use App\Models\ReferralStatusLog;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;

    class MaximaReferralController extends Controller
    {
    // current status => statuses Maxima may move it to
    private const TRANSITIONS = [
        'Referred'          => ['Accepted by Maxima', 'Rejected'],
        'Accepted by Maxima' => ['Training Started'],
        'Training Started'  => ['Completed'],
    ];

    private function authorizeMaxima(Request $request): void
    {
        abort_unless($request->user()->role === 'maxima_tesda_school', 403, 'Unauthorized');
    }

    /**
        * GET /api/maxima/referrals?search=&status=pending|approved|rejected
        */
    public function index(Request $request)
    {
        $this->authorizeMaxima($request);

        $counts = Referral::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sumGroup = fn (array $statuses) => (int) collect($statuses)->sum(fn ($st) => $counts[$st] ?? 0);

        $referrals = Referral::with([
                'osyProfile',
                'trainingProgram',
                'referrer:id,name',
                'reviewer:id,name',
            ])
            ->when($request->status, function ($q, $group) {
                $statuses = Referral::statusesForGroup($group);
                if ($statuses) {
                $q->whereIn('status', $statuses);
                }
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

        return response()->json([
            'counts' => [
                'approved' => $sumGroup(Referral::APPROVED_STATUSES),
                'pending'  => $sumGroup(Referral::PENDING_STATUSES),
                'rejected' => $sumGroup(Referral::REJECTED_STATUSES),
            ],
            'referrals' => $referrals,
        ]);
    }

    /**
        * PATCH /api/maxima/referrals/{referral}/status
        */
    public function updateStatus(Request $request, Referral $referral)
    {
        $this->authorizeMaxima($request);

        $data = $request->validate([
            'status'  => 'required|string',
            'remarks' => 'required_if:status,Rejected|nullable|string|max:1000',
        ]);

        $allowed = self::TRANSITIONS[$referral->status] ?? [];

        if (!in_array($data['status'], $allowed, true)) {
            return response()->json([
                'message' => "Cannot change from {$referral->status} to {$data['status']}.",
            ], 422);
        }

        DB::transaction(function () use ($referral, $data, $request) {
            $referral->update([
                'status'      => $data['status'],
                'reviewed_by' => $request->user()->id,
                'remarks'     => $data['remarks'] ?? $referral->remarks,
            ]);

            ReferralStatusLog::create([
                'referral_id' => $referral->id,
                'changed_by'  => $request->user()->id,
                'status'      => $data['status'],
                'remarks'     => $data['remarks'] ?? null,
            ]);
        });

        return response()->json(
            $referral->fresh()->load('osyProfile', 'trainingProgram', 'referrer:id,name', 'reviewer:id,name')
        );
    }

        /**
     * GET /api/maxima/enrollees?search=&status=
     */
    public function enrollees(Request $request)
    {
        $this->authorizeMaxima($request);

        $enrolledStatuses = ['Accepted by Maxima', 'Training Started', 'Completed'];

        $referrals = Referral::with([
                'osyProfile',
                'trainingProgram',
                'referrer:id,name',
                'reviewer:id,name',
            ])
            ->whereIn('status', $enrolledStatuses)
            ->when($request->status, function ($q, $status) use ($enrolledStatuses) {
                if (in_array($status, $enrolledStatuses, true)) {
                    $q->where('status', $status);
                }
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

        $logs = ReferralStatusLog::whereIn('referral_id', $referrals->pluck('id'))
            ->orderBy('created_at')
            ->get();

        $userNames = \App\Models\User::whereIn('id', $logs->pluck('changed_by')->filter()->unique())
            ->pluck('name', 'id');

        $logsByReferral = $logs->groupBy('referral_id');

        $referrals->each(function ($referral) use ($logsByReferral, $userNames) {
            $history = ($logsByReferral[$referral->id] ?? collect())->map(fn ($log) => [
                'status'     => $log->status,
                'remarks'    => $log->remarks,
                'changed_by' => $userNames[$log->changed_by] ?? null,
                'created_at' => $log->created_at,
            ])->values();

            $accepted = $history->firstWhere('status', 'Accepted by Maxima');

            $referral->history = $history;
            $referral->accepted_at = $accepted['created_at'] ?? $referral->updated_at;
        });

        return response()->json([
            'counts' => [
                'accepted' => $referrals->where('status', 'Accepted by Maxima')->count(),
                'ongoing'  => $referrals->where('status', 'Training Started')->count(),
                'completed' => $referrals->where('status', 'Completed')->count(),
            ],
            'enrollees' => $referrals->values(),
        ]);
    }
}