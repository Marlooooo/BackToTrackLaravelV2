<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_code')->nullable();
            $table->timestamp('email_verification_expires_at')->nullable();
            $table->unsignedTinyInteger('email_verification_attempts')->default(0);
            $table->timestamp('email_verification_sent_at')->nullable();
        });

        // Accounts that already exist (including your fake test emails) count as verified
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_verification_code',
                'email_verification_expires_at',
                'email_verification_attempts',
                'email_verification_sent_at',
            ]);
        });
    }
};