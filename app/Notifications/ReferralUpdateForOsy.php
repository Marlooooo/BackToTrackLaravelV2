<?php

namespace App\Notifications;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\DefaultRequirement;

class ReferralUpdateForOsy extends Notification
{
   // Statuses that also send an email. Delete a line to turn that email off.
   private const EMAIL_STATUSES = [
      'Referred',
      'Withdrawn',
      'Accepted by Maxima',
      'Rejected',
      'Training Started',
      'Completed',
      'Dropped',
   ];

   public function __construct(
      public Referral $referral,
      public string $status,
      public ?string $detail = null
   ) {
   }

   /**
    * One-line helper for the controllers:
    *   ReferralUpdateForOsy::send($referral, 'Rejected', $reason);
    * A notification problem must never block the status change.
    */
   public static function send(Referral $referral, string $status, ?string $detail = null): void
   {
      try {
         $referral->loadMissing(['osyProfile', 'trainingProgram']);

         $osyUser = $referral->osyProfile
            ? User::find($referral->osyProfile->user_id)
            : null;

         $osyUser?->notify(new self($referral, $status, $detail));
      } catch (\Throwable $e) {
         report($e);
      }
   }

   public function via(object $notifiable): array
   {
      // 'database' is first, so the in-app notification is saved even if the email fails
      $channels = ['database'];

      $canEmail = !empty($notifiable->email)
         && !$notifiable->needsEmailVerification();

      if ($canEmail && in_array($this->status, self::EMAIL_STATUSES, true)) {
         $channels[] = 'mail';
      }

      return $channels;
   }

   private function programName(): string
   {
      $this->referral->loadMissing('trainingProgram');

      return $this->referral->trainingProgram?->name ?? 'the training program';
   }

   private function referrerName(): string
   {
      $this->referral->loadMissing('referrer');

      return $this->referral->referrer?->name ?? 'A barangay official';
   }

   /**
    * The wording used by both the in-app notification and the email.
    *
    * @return array{0: string, 1: string, 2: string, 3: string} [title, message, level, category]
    */
   private function content(): array
   {
      $program = $this->programName();
      $referrer = $this->referrerName();
      $reason = $this->detail ? " Reason: {$this->detail}." : '';

      return match ($this->status) {
         'Referred' => [
            "You've been referred",
            "{$referrer} referred you to {$program}. Maxima will review your referral and you will be notified of their decision.",
            'info',
            'referral',
         ],
         'Withdrawn' => [
            'Referral withdrawn',
            "Your pending referral to {$program} was withdrawn by {$referrer}.",
            'warning',
            'referral',
         ],
         'Accepted by Maxima' => [
            'Referral accepted',
            "Maxima accepted your referral to {$program}. Open My Referrals to see the documents you need to submit.",
            'success',
            'referral',
         ],
         'Rejected' => [
            'Referral not accepted',
            "Maxima did not accept your referral to {$program}.{$reason}",
            'danger',
            'referral',
         ],
         'Training Started' => [
            'Training started',
            "You are now enrolled in {$program}. Check My Training Progress for your schedule.",
            'success',
            'training',
         ],
         'Completed' => [
            'Training completed',
            "Congratulations! You completed {$program}.",
            'success',
            'training',
         ],
         'Dropped' => [
            'Removed from training',
            "You are no longer part of {$program}.{$reason}",
            'warning',
            'training',
         ],
         default => [
            'Referral update',
            "Your referral to {$program} is now {$this->status}.",
            'info',
            'referral',
         ],
      };
   }


   private function requirementNames(): array
   {
      return $this->referral->requirementNames();
   }
   public function toMail(object $notifiable): MailMessage
   {
      [$title, $message, $level, $category] = $this->content();

      $isTraining = $category === 'training';
      $path = $isTraining ? '/osy/training' : '/osy/referrals';
      $label = $isTraining ? 'Open My Training' : 'Open My Referrals';
      $url = rtrim(config('app.frontend_url'), '/') . $path;

      $mail = (new MailMessage)
         ->subject("BackToTrack: {$title}")
         ->greeting("Hello {$notifiable->name}!")
         ->line($message);

      // On acceptance, list what the OSY must submit before they can be enrolled
      if ($this->status === 'Accepted by Maxima') {
         $requirements = $this->requirementNames();

         if ($requirements) {
            $mail->line('Before you can be enrolled, please submit these requirements to Maxima:');

            foreach ($requirements as $name) {
               $mail->line("• {$name}");
            }

            $due = \Illuminate\Support\Carbon::parse($this->referral->requirementsDeadline())->format('F j, Y');

            $mail->line("Please submit them on or before {$due}.");
            $mail->line('Maxima will mark each one as received. You will be enrolled once all of them are complete.');
         }
      }

      $mail->action($label, $url)
         ->line('Thank you for using BackToTrack.');

      return match ($level) {
         'success' => $mail->success(),
         'danger' => $mail->error(),
         default => $mail,
      };
   }

   public function toArray(object $notifiable): array
   {
      [$title, $message, $level, $category] = $this->content();

      return [
         'type' => 'training_status',
         'category' => $category,
         'level' => $level,
         'title' => $title,
         'message' => $message,
         'referral_id' => $this->referral->id,
         'status' => $this->status,
         'program_name' => $this->programName(),
         'referred_by' => $this->referrerName(),
         'reason' => $this->detail,
         'requirements' => $this->status === 'Accepted by Maxima' ? $this->requirementNames() : [],
         'requirements_deadline' => $this->status === 'Accepted by Maxima' ? $this->referral->requirementsDeadline() : null,
      ];
   }
}