<?php

   namespace App\Notifications;

   use App\Models\Referral;
   use Illuminate\Notifications\Notification;

   /**
    * Sent to the SK official who referred the OSY (same person Maxima's concern goes to)
    * when the OSY answers a reason request.
    *   AbsenceReasonSubmitted::send($referral, 3);
    */
   class AbsenceReasonSubmitted extends Notification
   {
      public function __construct(public Referral $referral, public int $days)
      {
      }

      public static function send(Referral $referral, int $days): void
      {
         try {
               $referral->loadMissing(['osyProfile', 'trainingProgram', 'referrer']);

               $referral->referrer?->notify(new self($referral, $days));
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
         $profile = $this->referral->osyProfile;
         $name    = $profile ? trim("{$profile->first_name} {$profile->last_name}") : 'The OSY';
         $program = $this->referral->trainingProgram?->name ?? 'the training program';
         $count   = $this->days === 1 ? '1 absence' : "{$this->days} absences";

         return [
               'type'        => 'training_concern',
               'title'       => 'Reason received',
               'message'     => "{$name} sent a reason for {$count} in {$program}. Open Training Monitoring to read it.",
               'referral_id' => $this->referral->id,
               'status'      => 'Reason received',
         ];
      }
   }