<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('required_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');

            $table->string('document_name'); // e.g. Valid ID, Certificate of Residency, Parental Consent
            $table->enum('status', ['pending', 'submitted', 'verified'])->default('pending');
            $table->date('deadline')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('required_documents');
    }
};