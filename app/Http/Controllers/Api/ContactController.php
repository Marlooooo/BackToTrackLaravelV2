<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    // Keys must match the "value" of each option in contact.jsx
    private const INQUIRY_TYPES = [
        'register'    => 'Registering as an OSY',
        'training'    => 'Question about training programs',
        'account'     => 'Problem with my account',
        'partnership' => 'Partnership or collaboration',
        'other'       => 'Other',
    ];

    public function send(Request $request)
    {
        $data = $request->validate([
            'full_name'    => 'required|string|max:100',
            'email'        => 'required|email|max:150',
            'inquiry_type' => 'required|in:' . implode(',', array_keys(self::INQUIRY_TYPES)),
            'subject'      => 'required|string|max:150',
            'message'      => 'required|string|max:3000',
            'website'      => 'nullable|string|max:255', // hidden spam trap
        ], [
            'full_name.required'    => 'Please enter your full name.',
            'email.required'        => 'Please enter your email address.',
            'email.email'           => 'Please enter a valid email address.',
            'inquiry_type.required' => 'Please choose what your message is about.',
            'inquiry_type.in'       => 'Please choose what your message is about.',
            'subject.required'      => 'Please enter a subject.',
            'message.required'      => 'Please type your message.',
        ]);

        // Real people never see or fill the hidden "website" field. Bots usually do.
        // Pretend it worked so the bot learns nothing, but send nothing.
        if (filled($data['website'] ?? null)) {
            return response()->json(['message' => 'Message sent successfully.']);
        }

        // New lines are not allowed in names, subjects or emails (blocks header tricks)
        $name = $this->oneLine($data['full_name']);
        $email = $this->oneLine($data['email']);
        $subject = $this->oneLine($data['subject']);
        $typeLabel = self::INQUIRY_TYPES[$data['inquiry_type']];

        $inbox = config('mail.from.address');

        if (blank($inbox)) {
            Log::error('Contact mail failed: MAIL_FROM_ADDRESS is not set.');

            return response()->json(['message' => 'Could not send the message.'], 500);
        }

        // 1) The message for the BackToTrack inbox. Replying in Gmail goes to the sender.
        try {
            Mail::raw(
                "Name: {$name}\n" .
                "Email: {$email}\n" .
                "About: {$typeLabel}\n" .
                "Subject: {$subject}\n\n" .
                $data['message'],
                function ($mail) use ($inbox, $email, $name, $typeLabel, $subject) {
                    $mail->to($inbox)
                        ->replyTo($email, $name)
                        ->subject("Contact Form [{$typeLabel}]: {$subject}");
                }
            );
        } catch (\Throwable $e) {
            Log::error('Contact mail failed: ' . $e->getMessage());

            return response()->json(['message' => 'Could not send the message.'], 500);
        }

        // 2) A confirmation for the sender. If this fails, the message was still delivered.
        try {
            Mail::raw(
                "Hello {$name},\n\n" .
                "Thank you for contacting BackToTrack. We received your message and will reply to this email address as soon as we can.\n\n" .
                "BackToTrack",
                function ($mail) use ($email, $name) {
                    $mail->to($email, $name)
                        ->subject('We received your message - BackToTrack');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Contact confirmation mail failed: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Message sent successfully.']);
    }

    private function oneLine(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}