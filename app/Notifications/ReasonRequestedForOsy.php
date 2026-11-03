<?php

   namespace App\Notifications;

   use App\Models\Referral;
   use App\Models\User;
   use Illuminate\Notifications\Notification;

   /**
    * Sent to the OSY when an SK official asks them to explain one or more absences.
    *   ReasonRequestedForOsy::send($referral, 3, 'Hi, can you tell us what happened?', 'Juan Dela Cruz');
    * A notification problem must never block the request itself.
    */
   class ReasonRequestedForOsy extends Notification
   {
      public function __construct(
         public Referral $referral,
         public int $days,
         public ?string $note = null,
         public ?string $requestedBy = null
      ) {
      }

      public static function send(Referral $referral, int $days, ?string $note = null, ?string $requestedBy = null): void
      {
         try {
               $referral->loadMissing(['osyProfile', 'trainingProgram']);

               $osyUser = $referral->osyProfile
                  ? User::find($referral->osyProfile->user_id)
                  : null;

               $osyUser?->notify(new self($referral, $days, $note, $requestedBy));
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
         $this->referral->loadMissing(['trainingProgram']);

         $program = $this->referral->trainingProgram?->name ?? 'the training program';
         $who     = $this->requestedBy ?: 'Your SK official';
         $count   = $this->days === 1 ? '1 absence' : "{$this->days} absences";
         $note    = $this->note ? " They wrote: \"{$this->note}\"" : '';

         return [
               'type'         => 'training_status',
               'category'     => 'training',
               'level'        => 'info',
               'title'        => 'Your SK official asked for a reason',
               'message'      => "{$who} asked you to explain {$count} in {$program}. Open My Training Progress and tap Add reason.{$note}",
               'referral_id'  => $this->referral->id,
               'status'       => 'Reason requested',
               'program_name' => $program,
               'reason'       => $this->note,
         ];
      }
   }
