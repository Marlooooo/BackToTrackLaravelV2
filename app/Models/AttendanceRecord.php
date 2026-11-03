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
         'reason_category',
         'reason_followup',
         'return_date',
         'support_requested',
         'reason_note',
         'reason_proof_path',
         'reason_submitted_at',
         'reason_requested_at',
         'reason_requested_by',
         'reason_request_message',
      ];

      protected $casts = [
         'support_requested'   => 'array',
         'reason_submitted_at' => 'datetime',
         'reason_requested_at' => 'datetime',
      ];

      public function referral(): BelongsTo
      {
         return $this->belongsTo(Referral::class);
      }
   }