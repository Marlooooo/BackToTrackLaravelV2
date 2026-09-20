<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('osy_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->foreignId('registered_by')->nullable()->constrained('users')->onDelete('set null');

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('birthdate');
            $table->enum('sex', ['male', 'female']);
            $table->string('address');
            $table->string('contact_number')->nullable();

            $table->string('educational_attainment');
            $table->string('preferred_career')->nullable();
            $table->string('available_schedule')->nullable();
            $table->boolean('has_transportation')->default(true);

            $table->text('background_circumstances')->nullable();
            $table->text('personal_observations')->nullable();
            $table->text('expressed_goals')->nullable();

            $table->enum('current_status', [
                'Registered',
                'Validated',
                'Referred',
                'Accepted by TESDA',
                'Training Started',
                'Completed',
            ])->default('Registered');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('osy_profiles');
    }
};