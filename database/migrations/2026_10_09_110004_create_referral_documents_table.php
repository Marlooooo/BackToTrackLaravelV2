<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
      public function up(): void
      {
         Schema::create('referral_documents', function (Blueprint $table) {
               $table->id();
               $table->foreignId('referral_id')->constrained('referrals')->onDelete('cascade');
               $table->string('name', 100);
               $table->enum('status', ['pending', 'received', 'not_applicable'])->default('pending');
               $table->string('remarks')->nullable();
               $table->date('received_at')->nullable();
               $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
               $table->timestamps();

               $table->unique(['referral_id', 'name']);
         });
      }

      public function down(): void
      {
         Schema::dropIfExists('referral_documents');
      }
   };