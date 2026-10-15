<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('osy_profiles', function (Blueprint $table) {
            $table->string('profile_photo')->nullable()->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('osy_profiles', function (Blueprint $table) {
            $table->dropColumn('profile_photo');
        });
    }
};