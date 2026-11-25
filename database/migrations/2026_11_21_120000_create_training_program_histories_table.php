<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
   public function up(): void
   {
      Schema::create('training_program_histories', function (Blueprint $table) {
         $table->id();
         // no foreign key, so history stays after a program is removed
         $table->unsignedBigInteger('training_program_id')->index();
         $table->string('program_name');
         $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
         $table->string('action', 20);
         $table->json('changes')->nullable();
         $table->timestamps();
      });
   }

   public function down(): void
   {
      Schema::dropIfExists('training_program_histories');
   }
};