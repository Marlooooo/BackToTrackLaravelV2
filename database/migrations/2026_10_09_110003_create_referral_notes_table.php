<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
      public function up(): void
      {
         Schema::create('referral_notes', function (Blueprint $table) {
               $table->id();
               $table->foreignId('referral_id')->constrained('referrals')->onDelete('cascade');
               $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
               $table->text('note');
               $table->timestamps();
         });
      }

      public function down(): void
      {
         Schema::dropIfExists('referral_notes');
      }
   };