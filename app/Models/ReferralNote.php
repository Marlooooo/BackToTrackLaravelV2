<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   class ReferralNote extends Model
   {
      protected $fillable = [
         'referral_id',
         'user_id',
         'note',
      ];

      public function referral(): BelongsTo
      {
         return $this->belongsTo(Referral::class);
      }

      public function user(): BelongsTo
      {
         return $this->belongsTo(User::class);
      }
   }