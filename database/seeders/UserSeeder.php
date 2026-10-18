<?php

   namespace Database\Seeders;

   use App\Models\User;
   use Illuminate\Database\Seeder;
   use Illuminate\Support\Facades\Hash;

   class UserSeeder extends Seeder
   {
      public function run(): void
      {
         $users = [
               [
                  'name'  => 'Maxima TESDA School',
                  'email' => 'maxima@backtotrack.test',
                  'role'  => 'maxima_tesda_school',
               ],
               [
                  'name'  => 'SK Official',
                  'email' => 'sk@backtotrack.test',
                  'role'  => 'sk_officials',
               ],
         ];

         foreach ($users as $data) {
               User::updateOrCreate(
                  ['email' => $data['email']],
                  $data + [
                     'password'          => Hash::make('password'),
                     'email_verified_at' => now(),
                  ]
               );
         }
      }
   }