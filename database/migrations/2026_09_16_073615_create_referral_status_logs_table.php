<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->onDelete('cascade');
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');

            $table->enum('status', [
                'Registered',
                'Validated',
                'Referred',
                'Accepted by TESDA',
                'Training Started',
                'Completed',
                'Rejected',
            ]);

            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_status_logs');
    }
};