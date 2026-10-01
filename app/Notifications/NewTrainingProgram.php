<?php

namespace App\Notifications;

use App\Models\TrainingProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewTrainingProgram extends Notification
{
    use Queueable;

    public function __construct(public TrainingProgram $program) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'       => 'new_program',
            'program_id' => $this->program->id,
            'title'      => 'New training program available',
            'message'    => "{$this->program->title} is now open. Check it out!",
        ];
    }
}