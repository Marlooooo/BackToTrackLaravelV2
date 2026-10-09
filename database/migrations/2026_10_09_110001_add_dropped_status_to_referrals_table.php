<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
      public function up(): void
      {
         Schema::table('referrals', function (Blueprint $table) {
               $table->enum('status', [
                  'Registered',
                  'Validated',
                  'Referred',
                  'Accepted by Maxima',
                  'Training Started',
                  'Completed',
                  'Rejected',
                  'Dropped',
               ])->default('Registered')->change();
         });

         // The status log copies referral statuses. If it was created as an enum it would
         // reject 'Dropped', so make it a plain string (harmless if it already is one).
         if (Schema::hasColumn('referral_status_logs', 'status')) {
               Schema::table('referral_status_logs', function (Blueprint $table) {
                  $table->string('status')->change();
               });
         }
      }

      public function down(): void
      {
         Schema::table('referrals', function (Blueprint $table) {
               $table->enum('status', [
                  'Registered',
                  'Validated',
                  'Referred',
                  'Accepted by Maxima',
                  'Training Started',
                  'Completed',
                  'Rejected',
               ])->default('Registered')->change();
         });
      }
   };