<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DefaultRequirement;
use App\Models\Referral;
use App\Models\ReferralDocument;
use App\Models\ReferralStatusLog;
use App\Models\User;
use App\Notifications\ReferralUpdateForOsy;
use App\Notifications\TrainingStatusChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaximaReferralController extends Controller
{
    // current status => statuses Maxima may move it to.
    // Maxima only decides here. Everything after acceptance happens in Progress Monitoring.
    private const TRANSITIONS = [
        'Referred' => ['Accepted by Maxima', 'Rejected'],
    ];

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function authorizeMaxima(Request $request): void
    {
        abort_unless($request->user()->role === 'maxima_tesda_school', 403, 'Unauthorized');
    }

    private function notifyReferrer(Referral $referral, string $status, ?string $detail = null): void
    {
        try {
            $referral->loadMissing(['osyProfile', 'trainingProgram', 'referrer']);
            $referral->referrer?->notify(new TrainingStatusChanged($referral, $status, $detail));
        } catch (\Throwable $e) {
            report($e); // a notification problem must never block the decision
        }
    }

    /** Referrals with their relations, optionally filtered by OSY name. */
    private function referralQuery(Request $request): Builder
    {
        return Referral::with([
                'osyProfile',
                'trainingProgram',
                'referrer:id,name',
                'reviewer:id,name',
            ])
            ->when($request->filled('search'), function ($q) use ($request) {
                // Every word must match the first, middle or last name,
                // so "Juan Cruz" finds "Juan Dela Cruz".
                $terms = preg_split('/\s+/', trim($request->input('search')), -1, PREG_SPLIT_NO_EMPTY);

                $q->whereHas('osyProfile', function ($profile) use ($terms) {
                    foreach ($terms as $term) {
                        $profile->where(function ($name) use ($term) {
                            $name->where('first_name', 'like', "%{$term}%")
                                ->orWhere('middle_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                    }
                });
            });
    }

    /** Totals per status. Ignores search and filters so the cards always show true totals. */
    private function statusCounts(): Collection
    {
        return Referral::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    private function sumStatuses(Collection $counts, array $statuses): int
    {
        return (int) collect($statuses)->sum(fn ($status) => $counts[$status] ?? 0);
    }

    /* ------------------------------------------------------------------ */
    /* Endpoints                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * GET /api/maxima/referrals?search=&status=pending|approved|rejected
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeMaxima($request);

        $counts = $this->statusCounts();

        $referrals = $this->referralQuery($request)
            ->when($request->input('status'), function ($q, $group) {
                $statuses = Referral::statusesForGroup($group);

                if ($statuses) {
                    $q->whereIn('status', $statuses);
                }
            })
            ->latest()
            ->latest('id')
            ->get();

        return response()->json([
            'counts' => [
                'approved' => $this->sumStatuses($counts, Referral::APPROVED_STATUSES),
                'pending'  => $this->sumStatuses($counts, Referral::PENDING_STATUSES),
                'rejected' => $this->sumStatuses($counts, Referral::REJECTED_STATUSES),
            ],
            'referrals' => $referrals,
        ]);
    }

    /**
     * PATCH /api/maxima/referrals/{referral}/status
     */
    public function updateStatus(Request $request, Referral $referral): JsonResponse
    {
        $this->authorizeMaxima($request);

        $data = $request->validate([
            'status'  => ['required', Rule::in(['Accepted by Maxima', 'Rejected'])],
            'remarks' => 'required_if:status,Rejected|nullable|string|max:1000',
        ]);

        $referral = DB::transaction(function () use ($referral, $data, $request) {
            // Lock the row and re-check its status, so two reviewers
            // cannot both act on the same referral.
            $current = Referral::whereKey($referral->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$current->status] ?? [];

            if (! in_array($data['status'], $allowed, true)) {
                abort(422, "Cannot change from {$current->status} to {$data['status']}.");
            }

            $current->update([
                'status'      => $data['status'],
                'reviewed_by' => $request->user()->id,
                'remarks'     => $data['remarks'] ?? $current->remarks,
            ]);

            ReferralStatusLog::create([
                'referral_id' => $current->id,
                'changed_by'  => $request->user()->id,
                'status'      => $data['status'],
                'remarks'     => $data['remarks'] ?? null,
            ]);

            if ($data['status'] === 'Accepted by Maxima') {
                ReferralDocument::ensureFor(collect([$current]));
            }

            return $current;
        });

        $rejectionReason = $data['status'] === 'Rejected' ? ($data['remarks'] ?? null) : null;

        // Tell the SK official who made the referral
        $this->notifyReferrer($referral, $data['status'], $rejectionReason);

        // Tell the OSY themselves
        ReferralUpdateForOsy::send($referral, $data['status'], $rejectionReason);

        return response()->json(
            $referral->load('osyProfile', 'trainingProgram', 'referrer:id,name', 'reviewer:id,name')
        );
    }

    /**
     * GET /api/maxima/enrollees?search=&status=
     */
    public function enrollees(Request $request): JsonResponse
    {
        $this->authorizeMaxima($request);

        $statusFilter = $request->input('status');

        $referrals = $this->referralQuery($request)
            ->whereIn('status', Referral::APPROVED_STATUSES)
            ->when(
                in_array($statusFilter, Referral::APPROVED_STATUSES, true),
                fn ($q) => $q->where('status', $statusFilter)
            )
            ->latest()
            ->latest('id')
            ->get();

        ReferralDocument::ensureFor($referrals);

        $ids = $referrals->pluck('id');

        $docsBy = ReferralDocument::whereIn('referral_id', $ids)
            ->orderBy('id')
            ->get()
            ->groupBy('referral_id');

        $logs = ReferralStatusLog::whereIn('referral_id', $ids)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $userNames = User::whereIn('id', $logs->pluck('changed_by')->filter()->unique())
            ->pluck('name', 'id');

        $logsByReferral = $logs->groupBy('referral_id');

        // Names on the Default requirements list. Any other document is a custom one.
        $defaultNames = DefaultRequirement::pluck('name')->all();

        $referrals->each(function ($referral) use ($logsByReferral, $userNames, $docsBy, $defaultNames) {
            $history = ($logsByReferral[$referral->id] ?? collect())->map(fn ($log) => [
                'status'     => $log->status,
                'remarks'    => $log->remarks,
                'changed_by' => $userNames[$log->changed_by] ?? null,
                'created_at' => $log->created_at,
            ])->values();

            $accepted = $history->firstWhere('status', 'Accepted by Maxima');

            $docs       = $docsBy->get($referral->id, collect());
            $applicable = $docs->where('status', '!=', 'not_applicable');

            // The page uses this to show a Remove button on custom requirements only
            $docs->each(fn ($d) => $d->setAttribute('is_default', in_array($d->name, $defaultNames, true)));

            $referral->history       = $history;
            $referral->accepted_at   = $accepted['created_at'] ?? $referral->updated_at;
            $referral->documents     = $docs->values();
            $referral->docs_required = $applicable->count();
            $referral->docs_received = $applicable->where('status', 'received')->count();
        });

        // Totals come from the whole table, not the filtered list,
        // so the summary cards stay correct while searching or filtering.
        $counts = $this->statusCounts();

        return response()->json([
            'counts' => [
                'accepted'  => (int) ($counts['Accepted by Maxima'] ?? 0),
                'ongoing'   => (int) ($counts['Training Started'] ?? 0),
                'completed' => (int) ($counts['Completed'] ?? 0),
            ],
            'enrollees' => $referrals->values(),
        ]);
    }
}