<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   class ReferralDocument extends Model
   {
      protected $fillable = [
         'referral_id',
         'name',
         'status',
         'remarks',
         'received_at',
         'updated_by',
      ];

      public function referral(): BelongsTo
      {
         return $this->belongsTo(Referral::class);
      }
   }