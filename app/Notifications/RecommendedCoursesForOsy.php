<?php

namespace App\Notifications;

use App\Models\OsyProfile;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecommendedCoursesForOsy extends Notification
{
    private const MAX_COURSES = 3;

    // Page the email button opens (React route)
    private const FRONTEND_PATH = '/osy/recommendations';

    /** @param array<int, array{name: string, career_path: ?string, reason: ?string}> $courses */
    public function __construct(public array $courses)
    {
    }

    /**
     * One-line helper:
     *   RecommendedCoursesForOsy::send($user);
     * Reads the OSY's latest saved recommendations, skips when there are none,
     * and never throws, so a mail problem cannot block anything.
     */
    public static function send(User $user): void
    {
        try {
            $profile = OsyProfile::where('user_id', $user->id)->first();

            if (! $profile) {
                return;
            }

            // The relationship is already ordered by rank
            $courses = $profile->recommendations()
                ->get()
                ->unique('course_name')
                ->take(self::MAX_COURSES)
                ->map(fn ($rec) => [
                    'name' => $rec->course_name,
                    'career_path' => $rec->career_path,
                    'reason' => $rec->getAttributes()['reason'] ?? null,
                ])
                ->filter(fn ($course) => filled($course['name']))
                ->values()
                ->all();

            if (empty($courses)) {
                return;
            }

            $user->notify(new self($courses));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function via(object $notifiable): array
    {
        return empty($notifiable->email) ? [] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/') . self::FRONTEND_PATH;

        $mail = (new MailMessage)
            ->subject('BackToTrack: Your recommended courses')
            ->greeting("Hello {$notifiable->name}!")
            ->line('Your email is now verified. Based on your interview with the SK, these are the courses recommended for you:');

        foreach ($this->courses as $course) {
            $line = "• **{$course['name']}**";

            if (filled($course['career_path'])) {
                $line .= " (Career path: {$course['career_path']})";
            }

            $mail->line($line);

            if (filled($course['reason'])) {
                $mail->line("Why: {$course['reason']}");
            }
        }

        return $mail
            ->line('To apply for any of these, visit your barangay hall or ask your SK official. You can also review your recommendations anytime in your account.')
            ->action('View My Recommendations', $url)
            ->line('Thank you for using BackToTrack.');
    }
}