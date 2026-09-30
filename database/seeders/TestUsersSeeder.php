<?php

   namespace Database\Seeders;

   use App\Models\User;
   use Illuminate\Database\Seeder;
   use Illuminate\Support\Facades\Hash;

   class TestUsersSeeder extends Seeder
   {
      public function run(): void
      {
         User::updateOrCreate(
               ['email' => 'maxima@test.com'],
               [
                  'name' => 'Test Maxima Staff',
                  'password' => Hash::make('password'),
                  'role' => 'maxima_tesda_school',
               ]
         );

         User::updateOrCreate(
               ['email' => 'sk@test.com'],
               [
                  'name' => 'Test SK Official',
                  'password' => Hash::make('password'),
                  'role' => 'sk_officials',
               ]
         );
      }
   }