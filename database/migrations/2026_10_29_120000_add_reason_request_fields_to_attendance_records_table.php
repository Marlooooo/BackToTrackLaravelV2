<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
   {
      Schema::table('attendance_records', function (Blueprint $table) {
         $table->timestamp('reason_requested_at')->nullable();
         $table->unsignedBigInteger('reason_requested_by')->nullable();
         $table->text('reason_request_message')->nullable();
      });
   }

   public function down(): void
   {
      Schema::table('attendance_records', function (Blueprint $table) {
         $table->dropColumn([
            'reason_requested_at',
            'reason_requested_by',
            'reason_request_message',
         ]);
      });
   }
};