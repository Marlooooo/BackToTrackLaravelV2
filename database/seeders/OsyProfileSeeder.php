<?php

      namespace Database\Seeders;

      use App\Models\OsyProfile;
      use App\Models\User;
      use Illuminate\Database\Seeder;
      use Illuminate\Support\Facades\Schema;

      class OsyProfileSeeder extends Seeder
      {
      public function run(): void
      {
            $sk      = User::where('email', 'sk@backtotrack.test')->first();
            $columns = array_flip(Schema::getColumnListing('osy_profiles'));

            $profiles = [
                  ['osy1@backtotrack.test', 'Juan',  'Mendoza', 'Dela Cruz', 'Male',   '2004-03-12', 'Poblacion, Mabini',  '09171234501', 'Welder'],
                  ['osy2@backtotrack.test', 'Maria', 'Lopez',   'Santos',    'Female', '2005-07-25', 'San Rafael, Mabini', '09171234502', 'Cook'],
                  ['osy3@backtotrack.test', 'Pedro', 'Cruz',    'Reyes',     'Male',   '2003-11-02', 'Poblacion, Mabini',  '09171234503', 'Electrician'],
                  ['osy4@backtotrack.test', 'Ana',   'Torres',  'Garcia',    'Female', '2006-01-18', 'Bacsil, Mabini',    '09171234504', 'Hairdresser'],
                  ['osy5@backtotrack.test', 'Jose',  'Aquino',  'Ramos',     'Male',   '2004-09-30', 'San Rafael, Mabini', '09171234505', 'Mechanic'],
            ];

            foreach ($profiles as [$email, $first, $middle, $last, $sex, $birth, $address, $contact, $career]) {
                  $user = User::where('email', $email)->first();

                  if (!$user) {
                  continue;
                  }

                  $attributes = [
                  'user_id'          => $user->id,
                  'registered_by'    => $sk?->id,
                  'first_name'       => $first,
                  'middle_name'      => $middle,
                  'last_name'        => $last,
                  'sex'              => $sex,
                  'gender'           => $sex,
                  'birthdate'        => $birth,
                  'birth_date'       => $birth,
                  'address'          => $address,
                  'contact_number'   => $contact,
                  'preferred_career' => $career,
                  ];

                  // Only use columns that really exist in your osy_profiles table
                  $attributes = array_intersect_key($attributes, $columns);

                  OsyProfile::updateOrCreate(['user_id' => $user->id], $attributes);
            }
      }
      }