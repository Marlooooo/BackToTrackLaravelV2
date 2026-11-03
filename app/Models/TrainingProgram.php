<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

        protected $appends = ['image_url', 'enrolled_count', 'slots_left'];

    // Referral statuses that hold a seat in the program
    public const SLOT_STATUSES = ['Accepted by Maxima', 'Training Started'];

    public function getEnrolledCountAttribute(): int
    {
        return $this->referrals()->whereIn('status', self::SLOT_STATUSES)->count();
    }

    // null means the program has no slot limit (slots = 0)
    public function getSlotsLeftAttribute(): int
    {
        return max(0, $this->slots - $this->enrolled_count);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function extraRequirementNames(): array
    {
        $defaults = DefaultRequirement::pluck('name')
            ->map(fn ($name) => mb_strtolower($name))
            ->all();

        return collect(preg_split('/[\r\n,]+/', (string) $this->requirements))
            ->map(fn ($item) => mb_substr(trim($item), 0, 100))
            ->filter()
            ->reject(fn ($item) => in_array(mb_strtolower($item), $defaults, true))
            ->unique(fn ($item) => mb_strtolower($item))
            ->values()
            ->all();
    }

    public function requirementNames(): array
    {
        return array_merge(
            DefaultRequirement::orderBy('id')->pluck('name')->all(),
            $this->extraRequirementNames()
        );
    }
}