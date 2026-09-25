<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TrainingProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'name',
        'description',
        'requirements',
        'schedule',
        'slots',
        'image_path',
        'tesda_accredited',
        'status',
    ];

    protected $casts = [
        'tesda_accredited' => 'boolean',
        'slots' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}