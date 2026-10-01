<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
}