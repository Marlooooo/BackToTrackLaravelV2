<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   class ConductRecord extends Model
   {
      protected $fillable = [
         'referral_id',
         'rating',
         'remarks',
         'recorded_on',
         'recorded_by',
      ];

      protected $casts = [
         'recorded_on' => 'date:Y-m-d',
      ];

      public function referral(): BelongsTo
      {
         return $this->belongsTo(Referral::class);
      }
   }