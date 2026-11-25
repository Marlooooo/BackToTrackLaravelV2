<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,           // 1st: creates SK + Maxima
            TrainingProgramSeeder::class, // 2nd: needs the Maxima user
            OsyProfileSeeder::class,      // 3rd: needs the SK user
        ]);
    }
}