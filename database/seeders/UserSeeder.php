<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
   public function run(): void
   {
      $users = [
         ['name' => 'Ramon Dela Pena (SK Official)', 'email' => 'sk@backtotrack.test', 'role' => 'sk_officials'],
         ['name' => 'Liza Villanueva (Maxima TESDA)', 'email' => 'maxima@backtotrack.test', 'role' => 'maxima_tesda_school'],
      ];

      foreach ($users as $data) {
         $user = User::firstOrNew(['email' => $data['email']]);
         $user->name = $data['name'];
         $user->role = $data['role'];
         if (!$user->exists) {
            $user->password = 'password'; // hashed by the User cast
         }
         $user->email_verified_at = now();
         $user->must_change_password = false;
         $user->save();
      }
   }
}