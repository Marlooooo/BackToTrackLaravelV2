<?php

   namespace App\Notifications;

   use App\Models\Referral;
   use App\Models\User;
   use Illuminate\Notifications\Notification;

   class ReferralUpdateForOsy extends Notification
   {
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
         return ['database'];
      }

      public function toArray(object $notifiable): array
      {
         $this->referral->loadMissing(['trainingProgram', 'referrer']);

         $program  = $this->referral->trainingProgram?->name ?? 'the training program';
         $referrer = $this->referral->referrer?->name ?? 'A barangay official';
         $reason   = $this->detail ? " Reason: {$this->detail}." : '';

         [$title, $message, $level, $category] = match ($this->status) {
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

         return [
               'type'         => 'training_status',
               'category'     => $category,
               'level'        => $level,
               'title'        => $title,
               'message'      => $message,
               'referral_id'  => $this->referral->id,
               'status'       => $this->status,
               'program_name' => $program,
               'referred_by'  => $referrer,
               'reason'       => $this->detail,
         ];
      }
   }