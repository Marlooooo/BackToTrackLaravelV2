<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   class AttendanceRecord extends Model
   {
      protected $fillable = [
         'referral_id',
         'attendance_date',
         'status',
         'remarks',
         'recorded_by',
      ];

      public function referral(): BelongsTo
      {
         return $this->belongsTo(Referral::class);
      }
   }