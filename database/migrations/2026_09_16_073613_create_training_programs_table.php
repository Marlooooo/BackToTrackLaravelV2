<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->string('schedule')->nullable();
            $table->unsignedInteger('slots')->default(0);
            $table->boolean('tesda_accredited')->default(true);
            $table->enum('status', ['open', 'closed', 'ongoing', 'completed'])->default('open');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_programs');
    }
};