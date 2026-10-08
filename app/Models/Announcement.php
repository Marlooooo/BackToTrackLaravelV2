<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;

   class Announcement extends Model
   {
      protected $fillable = ['training_program_id', 'posted_by', 'title', 'content', 'posted_at'];

      protected $casts = ['posted_at' => 'datetime'];

      public function author()
      {
         return $this->belongsTo(User::class, 'posted_by');
      }

      public function trainingProgram()
      {
         return $this->belongsTo(TrainingProgram::class);
      }
   }