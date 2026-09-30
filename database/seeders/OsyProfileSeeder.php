<?php

   namespace Database\Seeders;

   use App\Models\OsyProfile;
   use App\Models\User;
   use Illuminate\Database\Seeder;
   use Illuminate\Support\Facades\Hash;

   class OsyProfileSeeder extends Seeder
   {
      public function run(): void
      {
         // Profiles are registered by the SK official test account.
         $registeredBy = User::where('email', 'sk@test.com')->value('id')
               ?? User::where('role', 'sk_officials')->value('id');

         if (! $registeredBy) {
               $this->command->warn('No SK official found; registered_by will be null. Run TestUsersSeeder first.');
         }

         $osys = [
               [
                  'email' => 'osy@test.com', // the account from TestUsersSeeder gets a profile too
                  'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'OSY',
                  'birthdate' => '2005-03-14', 'sex' => 'male',
                  'address' => 'Purok 1, Barangay Pogo Grande',
                  'contact_number' => '09170000001',
                  'educational_attainment' => 'High School Undergraduate',
                  'preferred_career' => 'Welder',
                  'available_schedule' => 'Weekdays',
                  'has_transportation' => true,
                  'background_circumstances' => 'Stopped schooling to help the family.',
                  'personal_observations' => 'Motivated and punctual.',
                  'expressed_goals' => 'Wants a stable job in construction or fabrication.',
                  'current_status' => 'Registered',
               ],
               [
                  'email' => 'maria.santos@osy.test',
                  'first_name' => 'Maria', 'middle_name' => 'Cruz', 'last_name' => 'Santos',
                  'birthdate' => '2004-07-22', 'sex' => 'female',
                  'address' => 'Purok 2, Barangay Pogo Grande',
                  'contact_number' => '09170000002',
                  'educational_attainment' => 'Elementary Graduate',
                  'preferred_career' => 'Baker',
                  'available_schedule' => 'Afternoons',
                  'has_transportation' => false,
                  'background_circumstances' => 'Works part-time at a small eatery.',
                  'personal_observations' => 'Creative and hardworking.',
                  'expressed_goals' => 'Wants to open a small bakery.',
                  'current_status' => 'Validated',
               ],
               [
                  'email' => 'juan.delacruz@osy.test',
                  'first_name' => 'Juan', 'middle_name' => 'Reyes', 'last_name' => 'Dela Cruz',
                  'birthdate' => '2003-11-05', 'sex' => 'male',
                  'address' => 'Purok 3, Barangay Pogo Grande',
                  'contact_number' => '09170000003',
                  'educational_attainment' => 'High School Graduate',
                  'preferred_career' => 'Computer Technician',
                  'available_schedule' => 'Mornings',
                  'has_transportation' => true,
                  'background_circumstances' => 'Could not afford college.',
                  'personal_observations' => 'Good with gadgets; quick learner.',
                  'expressed_goals' => 'Wants to repair computers and phones.',
                  'current_status' => 'Validated',
               ],
               [
                  'email' => 'ana.reyes@osy.test',
                  'first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Reyes',
                  'birthdate' => '2006-01-30', 'sex' => 'female',
                  'address' => 'Purok 4, Barangay Pogo Grande',
                  'contact_number' => null,
                  'educational_attainment' => 'High School Undergraduate',
                  'preferred_career' => 'Housekeeper',
                  'available_schedule' => 'Weekdays',
                  'has_transportation' => false,
                  'background_circumstances' => 'Left school to care for younger siblings.',
                  'personal_observations' => 'Responsible and tidy.',
                  'expressed_goals' => 'Wants to work in a hotel or resort.',
                  'current_status' => 'Registered',
               ],
               [
                  'email' => 'pedro.ramos@osy.test',
                  'first_name' => 'Pedro', 'middle_name' => 'Lim', 'last_name' => 'Ramos',
                  'birthdate' => '2002-09-18', 'sex' => 'male',
                  'address' => 'Purok 5, Barangay Pogo Grande',
                  'contact_number' => '09170000005',
                  'educational_attainment' => 'ALS Graduate',
                  'preferred_career' => 'Electrician',
                  'available_schedule' => 'Tuesday to Saturday',
                  'has_transportation' => true,
                  'background_circumstances' => 'Does odd jobs for neighbors.',
                  'personal_observations' => 'Practical and reliable.',
                  'expressed_goals' => 'Wants to become a licensed electrician.',
                  'current_status' => 'Referred',
               ],
               [
                  'email' => 'liza.garcia@osy.test',
                  'first_name' => 'Liza', 'middle_name' => 'Mae', 'last_name' => 'Garcia',
                  'birthdate' => '2005-12-02', 'sex' => 'female',
                  'address' => 'Purok 1, Barangay Pogo Grande',
                  'contact_number' => '09170000006',
                  'educational_attainment' => 'High School Graduate',
                  'preferred_career' => 'Event Organizer',
                  'available_schedule' => 'Weekends',
                  'has_transportation' => true,
                  'background_circumstances' => 'Helps organize barangay activities.',
                  'personal_observations' => 'Outgoing and organized.',
                  'expressed_goals' => 'Wants to plan community events.',
                  'current_status' => 'Accepted by TESDA',
               ],
               [
                  'email' => 'carlo.mendoza@osy.test',
                  'first_name' => 'Carlo', 'middle_name' => null, 'last_name' => 'Mendoza',
                  'birthdate' => '2004-04-27', 'sex' => 'male',
                  'address' => 'Purok 2, Barangay Pogo Grande',
                  'contact_number' => '09170000007',
                  'educational_attainment' => 'High School Undergraduate',
                  'preferred_career' => 'Welder',
                  'available_schedule' => 'Weekdays',
                  'has_transportation' => false,
                  'background_circumstances' => 'Previously worked as a construction helper.',
                  'personal_observations' => 'Strong and disciplined.',
                  'expressed_goals' => 'Wants a TESDA welding certificate.',
                  'current_status' => 'Training Started',
               ],
               [
                  'email' => 'grace.tan@osy.test',
                  'first_name' => 'Grace', 'middle_name' => 'Ong', 'last_name' => 'Tan',
                  'birthdate' => '2003-06-09', 'sex' => 'female',
                  'address' => 'Purok 3, Barangay Pogo Grande',
                  'contact_number' => '09170000008',
                  'educational_attainment' => 'High School Graduate',
                  'preferred_career' => 'Baker',
                  'available_schedule' => 'Afternoons',
                  'has_transportation' => true,
                  'background_circumstances' => 'Finished a short baking course earlier.',
                  'personal_observations' => 'Detail-oriented.',
                  'expressed_goals' => 'Wants to work in a bakery.',
                  'current_status' => 'Completed',
               ],
                              [
                  'email' => 'rico.villanueva@osy.test',
                  'first_name' => 'Rico', 'middle_name' => 'Dela', 'last_name' => 'Villanueva',
                  'birthdate' => '2004-02-11', 'sex' => 'male',
                  'address' => 'Purok 4, Barangay Pogo Grande',
                  'contact_number' => '09170000009',
                  'educational_attainment' => 'High School Undergraduate',
                  'preferred_career' => 'Automotive Mechanic',
                  'available_schedule' => 'Weekdays',
                  'has_transportation' => true,
                  'background_circumstances' => 'Helps a relative at a small repair shop.',
                  'personal_observations' => 'Curious about engines and fixes things at home.',
                  'expressed_goals' => 'Wants to repair motorcycles and cars for a living.',
                  'current_status' => 'Registered',
               ],
               [
                  'email' => 'jenny.bautista@osy.test',
                  'first_name' => 'Jenny', 'middle_name' => 'Ramos', 'last_name' => 'Bautista',
                  'birthdate' => '2005-08-19', 'sex' => 'female',
                  'address' => 'Purok 5, Barangay Pogo Grande',
                  'contact_number' => '09170000010',
                  'educational_attainment' => 'High School Graduate',
                  'preferred_career' => 'Cook',
                  'available_schedule' => 'Mornings',
                  'has_transportation' => false,
                  'background_circumstances' => 'Cooks for the family every day.',
                  'personal_observations' => 'Enjoys trying new recipes.',
                  'expressed_goals' => 'Wants to work in a restaurant or sell food.',
                  'current_status' => 'Validated',
               ],
               [
                  'email' => 'mark.aquino@osy.test',
                  'first_name' => 'Mark', 'middle_name' => null, 'last_name' => 'Aquino',
                  'birthdate' => '2003-03-03', 'sex' => 'male',
                  'address' => 'Purok 1, Barangay Pogo Grande',
                  'contact_number' => '09170000011',
                  'educational_attainment' => 'ALS Graduate',
                  'preferred_career' => 'Carpenter',
                  'available_schedule' => 'Monday to Saturday',
                  'has_transportation' => true,
                  'background_circumstances' => 'Worked as a helper on house repairs.',
                  'personal_observations' => 'Skilled with tools and takes measurements carefully.',
                  'expressed_goals' => 'Wants to build furniture and houses.',
                  'current_status' => 'Referred',
               ],
               [
                  'email' => 'kim.castillo@osy.test',
                  'first_name' => 'Kimberly', 'middle_name' => 'Joy', 'last_name' => 'Castillo',
                  'birthdate' => '2006-05-25', 'sex' => 'female',
                  'address' => 'Purok 2, Barangay Pogo Grande',
                  'contact_number' => null,
                  'educational_attainment' => 'High School Undergraduate',
                  'preferred_career' => 'Barista',
                  'available_schedule' => 'Afternoons',
                  'has_transportation' => false,
                  'background_circumstances' => 'Stopped school after her parents lost their income.',
                  'personal_observations' => 'Friendly and good with customers.',
                  'expressed_goals' => 'Wants to work at a coffee shop.',
                  'current_status' => 'Registered',
               ],
               [
                  'email' => 'dennis.flores@osy.test',
                  'first_name' => 'Dennis', 'middle_name' => 'Cruz', 'last_name' => 'Flores',
                  'birthdate' => '2002-12-14', 'sex' => 'male',
                  'address' => 'Purok 3, Barangay Pogo Grande',
                  'contact_number' => '09170000013',
                  'educational_attainment' => 'High School Graduate',
                  'preferred_career' => 'Hairdresser',
                  'available_schedule' => 'Weekends',
                  'has_transportation' => true,
                  'background_circumstances' => 'Cuts hair for neighbors for small pay.',
                  'personal_observations' => 'Has a good eye for styles.',
                  'expressed_goals' => 'Wants to open a small barbershop.',
                  'current_status' => 'Accepted by TESDA',
               ],
               [
                  'email' => 'rowena.pascual@osy.test',
                  'first_name' => 'Rowena', 'middle_name' => 'Diaz', 'last_name' => 'Pascual',
                  'birthdate' => '2004-10-08', 'sex' => 'female',
                  'address' => 'Purok 4, Barangay Pogo Grande',
                  'contact_number' => '09170000014',
                  'educational_attainment' => 'Elementary Graduate',
                  'preferred_career' => 'Dressmaker',
                  'available_schedule' => 'Weekdays, afternoons',
                  'has_transportation' => false,
                  'background_circumstances' => 'Sews and mends clothes for her family.',
                  'personal_observations' => 'Patient and neat with her work.',
                  'expressed_goals' => 'Wants to sew school uniforms and gowns.',
                  'current_status' => 'Training Started',
               ],
               [
                  'email' => 'angelo.navarro@osy.test',
                  'first_name' => 'Angelo', 'middle_name' => 'Sy', 'last_name' => 'Navarro',
                  'birthdate' => '2003-09-21', 'sex' => 'male',
                  'address' => 'Purok 5, Barangay Pogo Grande',
                  'contact_number' => '09170000015',
                  'educational_attainment' => 'High School Graduate',
                  'preferred_career' => 'Caregiver',
                  'available_schedule' => 'Weekdays',
                  'has_transportation' => true,
                  'background_circumstances' => 'Cared for his grandmother for several years.',
                  'personal_observations' => 'Calm, caring, and reliable.',
                  'expressed_goals' => 'Wants to work as a caregiver here or abroad.',
                  'current_status' => 'Completed',
               ],
         ];

         foreach ($osys as $row) {
               $email = $row['email'];
               unset($row['email']);

               // Account first (same fields the controller's createUser() sets).
               $user = User::firstOrNew(['email' => $email]);
               $user->forceFill([
                  'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                  'password' => Hash::make('password'),
                  'role' => 'osy',
                  'email_verified_at' => now(),
               ])->save();

               // Then the profile, linked to that account.
               OsyProfile::updateOrCreate(
                  ['user_id' => $user->id],
                  $row + ['registered_by' => $registeredBy]
               );
         }
      }
   }