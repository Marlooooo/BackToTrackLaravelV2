<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingProgramHistory extends Model
{
    protected $table = 'training_program_histories';

    protected $fillable = [
        'training_program_id',
        'program_name',
        'changed_by',
        'action',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}