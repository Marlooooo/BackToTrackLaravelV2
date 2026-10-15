<?php

   namespace Database\Seeders;

   use App\Models\OsyProfile;
   use App\Models\User;
   use App\Services\CourseRecommender;
   use Illuminate\Database\Seeder;
   use Illuminate\Support\Facades\Hash;
   use Illuminate\Support\Str;

   class OsyProfileSeeder extends Seeder
   {
      /**
       * Columns:
       * first, middle, last, birthdate, sex, purok, contact, education, career,
       * schedule, has_transportation, background, observations, goals,
       * current_status, registered_days_ago
       *
       * Education values come from OsyProfileController::EDUCATIONAL_ATTAINMENTS.
       * Statuses match the referral created for each person in ReferralSeeder.
       */
      private const PROFILES = [
         // --- Registered (no referral yet) ---
         ['Jerome', 'Baniqued', 'Agcaoili', '2007-03-12', 'male', 'Purok 1', '09177341826', 'High School Undergraduate', 'Welder', 'Weekdays', true,
               'Stopped in Grade 10 to help his father with rice farming.', 'Strong and eager; asks many questions about the trade.', 'Wants to work in fabrication or go abroad as a welder.', 'Registered', 9],
         ['Cherry Ann', 'Ramos', 'Domingo', '2008-08-30', 'female', 'Purok 3', '09287450913', 'Senior High School Undergraduate', 'Barista', 'Afternoons', false,
               'Left Grade 12 when the family sari-sari store closed.', 'Friendly and speaks confidently with strangers.', 'Wants to work in a café in the city.', 'Registered', 7],
         ['Kenneth', 'Lagmay', 'Soriano', '2006-11-19', 'male', 'Purok 5', null, 'Elementary Graduate', 'Carpenter', 'Monday to Saturday', false,
               'Works as a construction helper on weekends.', 'Handy with tools; needs some support with reading.', 'Wants to build houses and make furniture.', 'Registered', 5],
         ['Aileen Mae', 'Tabije', 'Cabrera', '2009-01-22', 'female', 'Purok 2', '09364218870', 'High School Undergraduate', 'Dressmaker', 'Weekday afternoons', false,
               'Stopped school after her mother became ill and now helps at home.', 'Sews well by hand; neat and patient.', 'Wants to sew school uniforms and sell them.', 'Registered', 3],

         // --- Validated (interviewed, not yet referred) ---
         ['Mark Anthony', 'Gaoiran', 'Dumlao', '2003-05-07', 'male', 'Purok 4', '09451139276', 'High School Graduate', 'Computer Technician', 'Mornings', true,
               'Could not afford college; repairs phones for neighbors.', 'Good with gadgets and learns fast.', 'Wants to set up a small repair shop.', 'Validated', 24],
         ['Rhea Joy', 'Quiocho', 'Villamor', '2002-12-03', 'female', 'Purok 6', '09182264057', 'High School Graduate', 'Cook', 'Mornings', false,
               'Cooks for the family and sells snacks near the school gate.', 'Enjoys trying new recipes and keeps a clean kitchen.', 'Wants to work in a restaurant or sell food.', 'Validated', 22],
         ['Alvin', 'Pacleb', 'Sibayan', '2001-09-14', 'male', 'Purok 2', '09557783102', 'Vocational / Technical Course', 'Electrician', 'Tuesday to Saturday', true,
               'Took a short wiring course but never received a certificate.', 'Practical and reliable.', 'Wants to become a certified electrician.', 'Validated', 21],
         ['Joan Marie', 'Tagorda', 'Fernandez', '2005-06-25', 'female', 'Purok 7', '09296612488', 'High School Graduate', 'Housekeeper', 'Weekdays', false,
               'Has worked as a part-time cleaner at a rest house.', 'Responsible, tidy and punctual.', 'Wants to work in a hotel or resort.', 'Validated', 30],

         // --- Referred ---
         ['Ronnel', 'Padua', 'Cacayuran', '2004-02-18', 'male', 'Purok 1', '09178805634', 'High School Graduate', 'Electrician', 'Weekdays', true,
               'Does odd jobs for neighbors, including simple wiring repairs.', 'Careful and safety-minded.', 'Wants a stable job as an electrician.', 'Referred', 26],
         ['Maricris', 'Bumanglag', 'Ocampo', '2005-10-09', 'female', 'Purok 5', '09369047215', 'High School Graduate', 'Cook', 'Mornings', false,
               'Helps at her aunt\'s carinderia.', 'Quick on her feet and good with customers.', 'Wants to work as a cook in a restaurant.', 'Referred', 28],
         ['Jessie', 'Ventura', 'Pimentel', '2000-07-29', 'male', 'Purok 3', '09954471620', 'Senior High School Graduate', 'Computer Technician', 'Mornings', true,
               'Finished senior high school but could not continue to college.', 'Patient problem solver who likes assembling computers.', 'Wants to work in IT support.', 'Referred', 27],

         // --- Accepted by Maxima ---
         ['Lovely Grace', 'Alcantara', 'Rimando', '2004-04-16', 'female', 'Purok 4', '09175329968', 'High School Graduate', 'Baker', 'Afternoons', false,
               'Bakes cakes on order for neighbors.', 'Creative and hardworking.', 'Wants to open a small bakery.', 'Accepted by Maxima', 40],
         ['Dennis', 'Cadiz', 'Baltazar', '2002-01-11', 'male', 'Purok 6', '09283370419', 'High School Graduate', 'Hairdresser', 'Weekends', true,
               'Cuts hair for neighbors for small pay.', 'Has a good eye for styles.', 'Wants to open a small barbershop.', 'Accepted by Maxima', 38],
         ['Christian Jay', 'Millare', 'Layug', '2003-08-02', 'male', 'Purok 2', '09458816307', 'Senior High School Graduate', 'Automotive Mechanic', 'Weekdays', true,
               'Helps a relative at a small motorcycle repair shop.', 'Curious about engines and fixes things at home.', 'Wants to repair motorcycles for a living.', 'Accepted by Maxima', 35],

         // --- Training Started ---
         ['Carlo', 'Ibarra', 'Mangaoang', '2004-09-21', 'male', 'Purok 1', '09176634085', 'High School Undergraduate', 'Welder', 'Weekdays', false,
               'Previously worked as a construction helper.', 'Strong and disciplined.', 'Wants a TESDA welding certificate.', 'Training Started', 55],
         ['Rowena', 'Dacanay', 'Abalos', '2001-03-28', 'female', 'Purok 7', '09351942276', 'Elementary Graduate', 'Dressmaker', 'Weekday mornings', false,
               'Sews and mends clothes for her family.', 'Patient and neat with her work.', 'Wants to sew uniforms and gowns.', 'Training Started', 45],
         ['Paolo', 'Sagun', 'Rabago', '2005-12-05', 'male', 'Purok 3', '09275518890', 'High School Graduate', 'Carpenter', 'Monday to Saturday', false,
               'Worked as a helper on house repairs.', 'Skilled with tools and measures carefully.', 'Wants to build furniture and houses.', 'Training Started', 36],
         ['Shiela Mae', 'Bugarin', 'Corpuz', '2003-07-17', 'female', 'Purok 5', '09462207741', 'High School Graduate', 'Barista', 'Afternoons', true,
               'Worked briefly as a service crew at a food stall.', 'Friendly and good with customers.', 'Wants to work at a coffee shop.', 'Training Started', 30],

         // --- Completed ---
         ['Grace Anne', 'Tamayo', 'Lising', '2002-06-09', 'female', 'Purok 2', '09189935402', 'High School Graduate', 'Baker', 'Afternoons', true,
               'Finished a short baking workshop earlier.', 'Detail-oriented and consistent.', 'Wants to work in a bakery.', 'Completed', 120],
         ['Angelo', 'Rivera', 'Tolentino', '2000-09-21', 'male', 'Purok 4', '09358821194', 'High School Graduate', 'Caregiver', 'Weekdays', true,
               'Cared for his grandmother for several years.', 'Calm, caring and reliable.', 'Wants to work as a caregiver here or abroad.', 'Completed', 135],
         ['Melody', 'Cruz', 'Fabro', '2001-11-30', 'female', 'Purok 6', '09494153067', 'Senior High School Graduate', 'Cook', 'Mornings', false,
               'Cooked for events and gatherings in the neighborhood.', 'Organized and keeps a clean workspace.', 'Wants to sell home-cooked meals.', 'Completed', 105],

         // --- Dropped (the referral is dropped; profile stays at the last stage that fits) ---
         ['Dante', 'Palaganas', 'Esguerra', '2003-02-14', 'male', 'Purok 5', '09173986251', 'High School Undergraduate', 'Automotive Mechanic', 'Weekdays', false,
               'Works as a daily-wage farm hand.', 'Interested in engines but often tired from work.', 'Wants a better-paying job than farm work.', 'Referred', 65],
      ];

      /** firstname.lastname@backtotrack.ph (first word of the first name only). */
      public static function emailFor(string $first, string $last): string
      {
         return Str::slug(explode(' ', $first)[0]) . '.' . Str::slug($last) . '@backtotrack.ph';
      }

      public function run(): void
      {
         $registeredBy = User::where('role', 'sk_officials')->orderBy('id')->value('id');

         if (! $registeredBy) {
               $this->command->warn('No SK official found; registered_by will be null. Run UserSeeder first.');
         }

         foreach (self::PROFILES as $row) {
               [$first, $middle, $last, $birthdate, $sex, $purok, $contact, $education, $career,
                  $schedule, $transport, $background, $observations, $goals, $status, $daysAgo] = $row;

               $registeredAt = now()->subDays($daysAgo);

               $user = User::firstOrNew(['email' => self::emailFor($first, $last)]);
               $user->forceFill([
                  'name' => trim($first . ' ' . $last),
                  'password' => Hash::make('password'),
                  'role' => 'osy',
                  'email_verified_at' => $registeredAt,
               ])->save();

               $profile = OsyProfile::firstOrNew(['user_id' => $user->id]);
               $profile->forceFill([
                  'first_name' => $first,
                  'middle_name' => $middle,
                  'last_name' => $last,
                  'birthdate' => $birthdate,
                  'sex' => $sex,
                  'address' => $purok . ', Barangay Pogo Grande',
                  'contact_number' => $contact,
                  'educational_attainment' => $education,
                  'preferred_career' => $career,
                  'available_schedule' => $schedule,
                  'has_transportation' => $transport,
                  'background_circumstances' => $background,
                  'personal_observations' => $observations,
                  'expressed_goals' => $goals,
                  'current_status' => $status,
                  'registered_by' => $registeredBy,
                  'created_at' => $registeredAt,
                  'updated_at' => $registeredAt,
               ])->save();
         }

         $this->saveRecommendations();
      }

      /** Uses the real CourseRecommender so the saved rows match what the app would generate. */
      private function saveRecommendations(): void
      {
         $recommender = app(CourseRecommender::class);

         foreach (OsyProfile::all() as $profile) {
               try {
                  $recommender->recommendAndSave($profile);
               } catch (\Throwable $e) {
                  $this->command->warn("Recommendations skipped for profile {$profile->id}: {$e->getMessage()}");
               }
         }
      }
   }