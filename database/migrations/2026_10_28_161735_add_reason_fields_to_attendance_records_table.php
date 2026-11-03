<?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('reason_category', 40)->nullable();
            $table->string('reason_followup', 60)->nullable();
            $table->date('return_date')->nullable();
            $table->json('support_requested')->nullable();
            $table->text('reason_note')->nullable();
            $table->string('reason_proof_path')->nullable();
            $table->timestamp('reason_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn([
                'reason_category',
                'reason_followup',
                'return_date',
                'support_requested',
                'reason_note',
                'reason_proof_path',
                'reason_submitted_at',
            ]);
        });
    }
    };