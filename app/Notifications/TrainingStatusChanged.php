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
         $program = $this->referral->trainingProgram?->name ?? 'the training program';

         $completed = $this->status === 'Completed';

         return [
               'type'        => 'training_status',
               'title'       => $completed ? 'Training completed' : 'OSY dropped from training',
               'message'     => $completed
                  ? "{$name} has completed the {$program} training."
                  : "{$name} was dropped from the {$program} training." . ($this->detail ? " Reason: {$this->detail}." : ''),
               'referral_id' => $this->referral->id,
               'status'      => $this->status,
         ];
      }
   }