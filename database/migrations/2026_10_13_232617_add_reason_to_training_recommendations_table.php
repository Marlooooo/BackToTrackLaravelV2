<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('training_recommendations', function (Blueprint $table) {
            $table->string('based_on')->nullable()->after('match_score');
            $table->json('matched_terms')->nullable()->after('based_on');
        });
    }

    public function down(): void
    {
        Schema::table('training_recommendations', function (Blueprint $table) {
            $table->dropColumn(['based_on', 'matched_terms']);
        });
    }
    
};
