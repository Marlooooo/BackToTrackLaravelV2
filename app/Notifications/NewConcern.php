<?php

namespace App\Notifications;

use App\Models\AbsenceConcern;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewConcern extends Notification
{
    public function __construct(public AbsenceConcern $concern)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $referral = $this->concern->referral;
        $profile  = $referral?->osyProfile;
        $name     = $profile ? trim("{$profile->first_name} {$profile->last_name}") : 'The OSY';
        $program  = $referral?->trainingProgram?->title
            ?? $referral?->trainingProgram?->name
            ?? 'the training program';

        return [
            'type'        => 'training_concern',
            'title'       => 'New concern raised',
            'message'     => "Maxima raised a concern about {$name} in {$program}: "
                . Str::limit($this->concern->description, 120),
            'referral_id' => $this->concern->referral_id,
            'concern_id'  => $this->concern->id,
            'status'      => 'Concern raised',
        ];
    }
}