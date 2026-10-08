<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:100',
            'subject'   => 'required|string|max:150',
            'message'   => 'required|string|max:3000',
        ]);

        try {
            Mail::raw(
                "Name: {$data['full_name']}\n" .
                "Subject: {$data['subject']}\n\n" .
                $data['message'],
                function ($mail) use ($data) {
                    $mail->to('marlonavarro012345@gmail.com')
                        ->subject('Contact Form: ' . $data['subject']);
                }
            );
        } catch (\Throwable $e) {
            Log::error('Contact mail failed: ' . $e->getMessage());
            return response()->json(['message' => 'Could not send the message.'], 500);
        }

        return response()->json(['message' => 'Message sent successfully.']);
    }
}