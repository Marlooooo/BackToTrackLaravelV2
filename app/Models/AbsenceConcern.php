<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   class AbsenceConcern extends Model
   {
      protected $fillable = [
         'referral_id',
         'submitted_by',
         'reviewed_by',
         'type',
         'description',
         'status',
         'review_remarks',
      ];

      public function referral(): BelongsTo
      {
         return $this->belongsTo(Referral::class);
      }

      public function submitter(): BelongsTo
      {
         return $this->belongsTo(User::class, 'submitted_by');
      }

      public function reviewer(): BelongsTo
      {
         return $this->belongsTo(User::class, 'reviewed_by');
      }
   }