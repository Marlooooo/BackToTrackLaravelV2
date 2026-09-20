<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('osy_profile_id')->constrained('osy_profiles')->onDelete('cascade');
            $table->foreignId('training_program_id')->constrained('training_programs')->onDelete('cascade');

            $table->unsignedTinyInteger('rank'); // 1, 2, or 3
            $table->decimal('match_score', 5, 2)->nullable();
            $table->timestamp('generated_at')->useCurrent();

            $table->timestamps();

            $table->unique(['osy_profile_id', 'training_program_id'], 'osy_training_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_recommendations');
    }
};