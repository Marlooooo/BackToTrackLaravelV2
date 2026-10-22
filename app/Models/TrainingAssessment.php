<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   class TrainingAssessment extends Model
   {
      protected $fillable = [
         'referral_id',
         'title',
         'score',
         'max_score',
         'assessed_on',
         'remarks',
         'recorded_by',
      ];

      protected $casts = [
         'score'       => 'float',
         'max_score'   => 'float',
         'assessed_on' => 'date:Y-m-d',
      ];

      public function referral(): BelongsTo
      {
         return $this->belongsTo(Referral::class);
      }
   }