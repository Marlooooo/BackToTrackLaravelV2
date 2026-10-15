<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('training_progress');
        Schema::dropIfExists('required_documents');
    }

    public function down(): void
    {
        // Intentionally empty: these tables were unused.
    }
};