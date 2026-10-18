<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRecommendation extends Model
{
    protected $fillable = [
        'osy_profile_id', 'training_program_id', 'rank',
        'match_score', 'course_name', 'career_path', 'is_available',
        'mode', 'based_on', 'relation', 'matched_terms', 'generated_at',
    ];

    protected $casts = [
        'matched_terms' => 'array',
        'match_score'   => 'float',
        'is_available'  => 'boolean',
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