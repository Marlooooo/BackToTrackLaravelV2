<?php

   namespace Database\Seeders;

   use App\Models\User;
   use Illuminate\Database\Seeder;
   use Illuminate\Support\Facades\Hash;

   class UserSeeder extends Seeder
   {
      public function run(): void
      {
         // The first account of each role is the one to use for quick logins.
         $staff = [
               ['Ramon Dizon',        'ramon.dizon@backtotrack.ph',        'maxima_tesda_school'],
               ['Teresita Agbayani',  'teresita.agbayani@backtotrack.ph',  'maxima_tesda_school'],
               ['Jericho Valdez',     'jericho.valdez@backtotrack.ph',     'sk_officials'],
               ['Shaira Manuel',      'shaira.manuel@backtotrack.ph',      'sk_officials'],
         ];

         foreach ($staff as [$name, $email, $role]) {
               $user = User::firstOrNew(['email' => $email]);
               $user->forceFill([
                  'name' => $name,
                  'password' => Hash::make('password'),
                  'role' => $role,
                  'email_verified_at' => now(),
               ])->save();
         }
      }
   }