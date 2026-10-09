<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
      public function up(): void
      {
         Schema::create('attendance_records', function (Blueprint $table) {
               $table->id();
               $table->foreignId('referral_id')->constrained('referrals')->onDelete('cascade');
               $table->date('attendance_date');
               $table->enum('status', ['present', 'late', 'absent', 'excused']);
               $table->string('remarks')->nullable();
               $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
               $table->timestamps();

               $table->unique(['referral_id', 'attendance_date']);
         });
      }

      public function down(): void
      {
         Schema::dropIfExists('attendance_records');
      }
   };