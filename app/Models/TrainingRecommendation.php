<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRecommendation extends Model
{
    protected $fillable = [
        'osy_profile_id', 'training_program_id', 'rank',
        'match_score', 'based_on', 'matched_terms', 'generated_at',
    ];

    protected $casts = [
        'matched_terms' => 'array',
        'match_score'   => 'float',
        'generated_at'  => 'datetime',
    ];

    public function osyProfile(): BelongsTo
    {
        return $this->belongsTo(OsyProfile::class);
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }
}