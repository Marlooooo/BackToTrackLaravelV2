<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralStatusLog;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->notifications()->latest()->paginate(20);
    }

    public function unreadCount(Request $request)
    {
        return ['count' => $request->user()->unreadNotifications()->count()];
    }

    public function markAsRead(Request $request, $id)
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();
        return response()->noContent();
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->noContent();
    }

    /**
     * GET /api/notifications/referrals/{referral}
     * Details shown in the notification modal. Only the user who made the referral can open it.
     */
    public function referralDetail(Request $request, Referral $referral)
    {
        abort_unless(
            (int) $referral->referred_by === (int) $request->user()->id,
            403,
            'Unauthorized'
        );

        $referral->load([
            'osyProfile',
            'trainingProgram',
            'reviewer:id,name',
        ]);

        $logs = ReferralStatusLog::where('referral_id', $referral->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $names = User::whereIn('id', $logs->pluck('changed_by')->filter()->unique())
            ->pluck('name', 'id');

        $history = $logs->map(fn ($log) => [
            'status'     => $log->status,
            'remarks'    => $log->remarks,
            'changed_by' => $names[$log->changed_by] ?? null,
            'created_at' => $log->created_at,
        ])->values();

        return response()->json([
            'referral' => $referral,
            'history'  => $history,
        ]);
    }
}