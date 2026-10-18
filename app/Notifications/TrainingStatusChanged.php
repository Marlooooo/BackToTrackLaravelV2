<?php

   namespace App\Notifications;

   use App\Models\Referral;
   use Illuminate\Notifications\Notification;

   class TrainingStatusChanged extends Notification
   {
      public function __construct(
         public Referral $referral,
         public string $status,
         public ?string $detail = null
      ) {
      }

      public function via(object $notifiable): array
      {
         return ['database'];
      }

      public function toArray(object $notifiable): array
      {
         $profile = $this->referral->osyProfile;
         $name    = $profile ? trim("{$profile->first_name} {$profile->last_name}") : 'The OSY';
         $program = $this->referral->trainingProgram?->title
            ?? $this->referral->trainingProgram?->name
            ?? 'the training program';

         $reason = $this->detail ? " Reason: {$this->detail}." : '';

         [$title, $message] = match ($this->status) {
            'Accepted by Maxima' => [
                  'Referral accepted',
                  "{$name}'s referral to {$program} was accepted by Maxima.",
            ],
            'Rejected' => [
                  'Referral rejected',
                  "{$name}'s referral to {$program} was rejected.{$reason}",
            ],
            'Training Started' => [
                  'OSY enrolled',
                  "{$name} is now enrolled in the {$program} training.",
            ],
            'Completed' => [
                  'Training completed',
                  "{$name} has completed the {$program} training.",
            ],
            'Dropped' => [
                  'OSY dropped from training',
                  "{$name} was dropped from the {$program} training.{$reason}",
            ],
            default => [
                  'Referral update',
                  "{$name}'s referral status is now {$this->status}.",
            ],
         };

         return [
            'type'        => 'training_status',
            'title'       => $title,
            'message'     => $message,
            'referral_id' => $this->referral->id,
            'status'      => $this->status,
         ];
      }
   }