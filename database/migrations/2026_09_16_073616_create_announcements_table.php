<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->nullable()->constrained('training_programs')->onDelete('cascade');
            $table->foreignId('posted_by')->constrained('users')->onDelete('cascade');

            $table->string('title');
            $table->text('content');
            $table->timestamp('posted_at')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};