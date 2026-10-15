<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
      public function up(): void
      {
         foreach (['certificate_number', 'certificate_date'] as $column) {
               if (Schema::hasColumn('referrals', $column)) {
                  Schema::table('referrals', fn (Blueprint $t) => $t->dropColumn($column));
               }
         }
      }

      public function down(): void
      {
         Schema::table('referrals', function (Blueprint $t) {
               $t->string('certificate_number', 100)->nullable();
               $t->date('certificate_date')->nullable();
         });
      }
   };