<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
      public function up(): void
      {
         Schema::table('referrals', function (Blueprint $table) {
               $table->string('batch_name', 100)->nullable();
               $table->string('venue', 150)->nullable();
               $table->string('trainer_name', 100)->nullable();
               $table->date('training_start_date')->nullable();
               $table->date('expected_end_date')->nullable();
               $table->date('training_end_date')->nullable();
               $table->string('certificate_number', 100)->nullable();
               $table->date('certificate_date')->nullable();
               $table->string('drop_reason', 50)->nullable();
               $table->string('after_training_status', 50)->nullable();
               $table->string('after_training_note')->nullable();
               $table->text('outcome_remarks')->nullable();
         });
      }

      public function down(): void
      {
         Schema::table('referrals', function (Blueprint $table) {
               $table->dropColumn([
                  'batch_name',
                  'venue',
                  'trainer_name',
                  'training_start_date',
                  'expected_end_date',
                  'training_end_date',
                  'certificate_number',
                  'certificate_date',
                  'drop_reason',
                  'after_training_status',
                  'after_training_note',
                  'outcome_remarks',
               ]);
         });
      }
   };