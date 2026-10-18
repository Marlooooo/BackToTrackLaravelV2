<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\DB;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
      public function up(): void
      {
         Schema::create('default_requirements', function (Blueprint $table) {
               $table->id();
               $table->string('name', 100)->unique();
               $table->timestamps();
         });

         $now = now();
         foreach ([
               'Valid ID',
               'Birth certificate',
               'Barangay certificate',
               'Recent ID photos',
               'Medical certificate',
         ] as $name) {
               DB::table('default_requirements')->insert([
                  'name' => $name,
                  'created_at' => $now,
                  'updated_at' => $now,
               ]);
         }
      }

      public function down(): void
      {
         Schema::dropIfExists('default_requirements');
      }
   };