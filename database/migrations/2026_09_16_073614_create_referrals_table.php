<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('osy_profile_id')->constrained('osy_profiles')->onDelete('cascade');
            $table->foreignId('training_program_id')->constrained('training_programs')->onDelete('cascade');
            $table->foreignId('referred_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');

            $table->enum('status', [
                'Registered',
                'Validated',
                'Referred',
                'Accepted by TESDA',
                'Training Started',
                'Completed',
                'Rejected',
            ])->default('Registered');

            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};