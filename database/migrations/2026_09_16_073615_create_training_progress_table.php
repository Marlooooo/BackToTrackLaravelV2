<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->onDelete('cascade');
            $table->foreignId('submitted_by')->constrained('users')->onDelete('cascade');

            $table->decimal('attendance_percentage', 5, 2)->nullable();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->decimal('skill_assessment_score', 5, 2)->nullable();
            $table->text('conduct_remarks')->nullable();
            $table->enum('training_status', ['ongoing', 'completed', 'dropped'])->default('ongoing');

            $table->date('report_period_start')->nullable();
            $table->date('report_period_end')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_progress');
    }
};