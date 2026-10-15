<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters: later seeders look up the rows created by earlier ones.
        $this->call([
            UserSeeder::class,
            TrainingProgramSeeder::class,
            OsyProfileSeeder::class,
            ReferralSeeder::class,
            AnnouncementSeeder::class,
        ]);
    }
}