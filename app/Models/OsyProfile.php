<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;


class OsyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'registered_by',
        'first_name',
        'middle_name',
        'last_name',
        'birthdate',
        'sex',
        'address',
        'contact_number',
        'educational_attainment',
        'preferred_career',
        'available_schedule',
        'has_transportation',
        'background_circumstances',
        'personal_observations',
        'expressed_goals',
        'current_status',
        'profile_photo'
    ];

    protected $casts = [
        'birthdate' => 'date:Y-m-d',
        'has_transportation' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
    public function recommendations(): HasMany
    {
        return $this->hasMany(TrainingRecommendation::class)->orderBy('rank');
    }
    protected $appends = ['profile_photo_url'];

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->profile_photo
                ? Storage::disk('public')->url($this->profile_photo)
                : null
        );
    }
}