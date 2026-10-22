<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
      public function up(): void
      {
         Schema::create('training_assessments', function (Blueprint $table) {
               $table->id();
               $table->foreignId('referral_id')->constrained('referrals')->onDelete('cascade');
               $table->string('title', 100);
               $table->decimal('score', 6, 2);
               $table->decimal('max_score', 6, 2)->default(100);
               $table->date('assessed_on');
               $table->string('remarks')->nullable();
               $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
               $table->timestamps();
         });

         Schema::create('conduct_records', function (Blueprint $table) {
               $table->id();
               $table->foreignId('referral_id')->constrained('referrals')->onDelete('cascade');
               $table->enum('rating', ['excellent', 'good', 'fair', 'needs_improvement']);
               $table->string('remarks')->nullable();
               $table->date('recorded_on');
               $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
               $table->timestamps();
         });
      }

      public function down(): void
      {
         Schema::dropIfExists('conduct_records');
         Schema::dropIfExists('training_assessments');
      }
   };