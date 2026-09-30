<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   class ReferralStatusLog extends Model
   {
      // Change this if your migration named the table differently
      // (the Laravel default for this model would be "referral_status_logs").
      protected $fillable = [
         'referral_id',
         'changed_by',
         'status',
         'remarks',
      ];

      public function referral(): BelongsTo
      {
         return $this->belongsTo(Referral::class);
      }

      public function changedBy(): BelongsTo
      {
         return $this->belongsTo(User::class, 'changed_by');
      }
   }