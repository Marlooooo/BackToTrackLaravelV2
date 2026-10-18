<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;
   use Illuminate\Database\Eloquent\Relations\HasMany;

   class Referral extends Model
   {
      // How the enum values map to the three boxes on the barangay page.
   // How the enum values map to the three boxes on the Barangay and Maxima referral pages.
      public const PENDING_STATUSES  = ['Registered', 'Validated', 'Referred'];
      public const APPROVED_STATUSES = ['Accepted by Maxima', 'Training Started', 'Completed'];
      public const REJECTED_STATUSES = ['Rejected', 'Dropped'];

      public static function statusesForGroup(string $group): array
      {
         return match ($group) {
               'pending'  => self::PENDING_STATUSES,
               'approved' => self::APPROVED_STATUSES,
               'rejected' => self::REJECTED_STATUSES,
               default    => [],
         };
      }

      protected $fillable = [
         'osy_profile_id',
         'training_program_id',
         'referred_by',
         'reviewed_by',
         'status',
         'remarks',
      ];

      public function osyProfile(): BelongsTo
      {
         return $this->belongsTo(OsyProfile::class);
      }

      public function trainingProgram(): BelongsTo
      {
         return $this->belongsTo(TrainingProgram::class);
      }

      public function referrer(): BelongsTo
      {
         return $this->belongsTo(User::class, 'referred_by');
      }

      public function reviewer(): BelongsTo
      {
         return $this->belongsTo(User::class, 'reviewed_by');
      }

      public function statusLogs(): HasMany
      {
         return $this->hasMany(ReferralStatusLog::class);
      }

      public function attendanceRecords(): HasMany
      {
         return $this->hasMany(AttendanceRecord::class);
      }

      public function referralNotes(): HasMany
      {
         return $this->hasMany(ReferralNote::class);
      }

      public function referralDocuments(): HasMany
      {
         return $this->hasMany(ReferralDocument::class);
      }

      public function absenceConcerns(): HasMany
      {
         return $this->hasMany(AbsenceConcern::class)->latest();
      }
   }