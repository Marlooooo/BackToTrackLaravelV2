<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Notifications\Notification;

class NewReferral extends Notification
{
    public function __construct(public Referral $referral)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $osy = $this->referral->osyProfile;
        $osyName = $osy
            ? trim(collect([$osy->first_name, $osy->middle_name, $osy->last_name])->filter()->implode(' '))
            : 'An OSY';

        $referrer = \App\Models\User::find($this->referral->referred_by)?->name
            ?? 'A barangay/SK official';

        // (adjust) use the real title column from your training_programs table
        $program = $this->referral->trainingProgram->title
            ?? $this->referral->trainingProgram->name
            ?? 'a training program';

        return [
            'type'        => 'new_referral',
            'title'       => 'New Referral Received',
            'message'     => "{$referrer} referred {$osyName} to {$program}.",
            'referral_id' => $this->referral->id,
            'osy_name'    => $osyName,
            'referred_by' => $referrer,
        ];
    }
}