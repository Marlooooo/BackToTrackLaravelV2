<?php

      namespace Database\Seeders;

      use App\Models\OsyProfile;
      use App\Models\User;
      use Illuminate\Database\Seeder;
      use Illuminate\Support\Facades\DB;

      /**
       * Seeds 20 OSY accounts + their profiles.
       *
       * Login: osy1@backtotrack.test ... osy20@backtotrack.test
       * Password (all accounts): password  (same as your UserSeeder accounts)
       *
       * Replaces the old OsyProfileSeeder: creates the OSY users AND their profiles.
       * Safe to re-run: users are matched by email, profiles by user_id.
       */
      class OsyProfileSeeder extends Seeder
      {
      private const PASSWORD = 'password';

      public function run(): void
      {
            // Some profiles are "registered by" an SK official (if one exists).
            $skOfficialId = User::where('email', 'sk@backtotrack.test')->value('id');

            DB::transaction(function () use ($skOfficialId) {
                  foreach ($this->rows() as $i => $r) {
                  [$first, $middle, $last, $birthdate, $sex, $address, $contact,
                  $education, $career, $schedule, $transport,
                  $background, $observations, $goals, $status] = $r;

                  $email = sprintf('osy%d@backtotrack.test', $i + 1);
                  $fullName = trim("$first " . ($middle ? "$middle " : '') . $last);

                  // 'hashed' cast on User hashes the plain password.
                  // Password is only set on creation so re-runs don't reset changed passwords.
                  $user = User::firstOrNew(['email' => $email]);
                  $user->name = $fullName;
                  $user->role = 'osy';
                  if (!$user->exists) {
                        $user->password = self::PASSWORD;
                        $user->email_verified_at = now();
                  }
                  $user->save();

                  // Every 3rd profile was registered by an SK official, the rest self-registered.
                  $registeredBy = ($skOfficialId && $i % 3 === 0) ? $skOfficialId : null;

                  OsyProfile::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                              'registered_by'            => $registeredBy,
                              'first_name'               => $first,
                              'middle_name'              => $middle,
                              'last_name'                => $last,
                              'birthdate'                => $birthdate,
                              'sex'                      => $sex,
                              'address'                  => $address,
                              'contact_number'           => $contact,
                              'educational_attainment'   => $education,
                              'preferred_career'         => $career,
                              'available_schedule'       => $schedule,
                              'has_transportation'       => $transport,
                              'background_circumstances' => $background,
                              'personal_observations'    => $observations,
                              'expressed_goals'          => $goals,
                              'current_status'           => $status,
                        ]
                  );
                  }
            });
      }

      /**
       * Column order:
       * first, middle, last, birthdate, sex, address, contact,
       * educational_attainment, preferred_career, available_schedule, has_transportation,
       * background, observations, goals, current_status
       *
       * NOTE: current_status is limited to Registered/Validated on purpose.
       * Later stages (Referred, Accepted, Training Started, Completed) should come
       * from referrals, since display_status prefers the latest referral's status.
       */
      private function rows(): array
      {
            return [
                  ['Juan', 'Dela', 'Cruz', '2003-03-14', 'male', 'Poblacion, Mabini', '09171234501', 'High School Graduate', 'Welding', 'Weekdays', true,
                  'Stopped schooling to help the family farm.', 'Hardworking and good with tools.', 'Wants a stable job as a certified welder.', 'Validated'],
                  ['Maria', 'Santos', 'Reyes', '2004-07-22', 'female', 'San Rafael, Mabini', '09171234502', 'Senior High School Undergraduate', 'Bread and Pastry Production', 'Weekends', false,
                  'Left school due to financial difficulties.', 'Creative and enjoys baking.', 'Open a small bakery someday.', 'Registered'],
                  ['Jose', null, 'Ramos', '2002-11-05', 'male', 'Bacsil, Mabini', '09171234503', 'High School Undergraduate', 'Automotive Servicing', 'Weekdays', true,
                  'Dropped out to work as a helper in a motorcycle shop.', 'Fast learner with mechanical aptitude.', 'Become a licensed motorcycle mechanic.', 'Validated'],
                  ['Angelica', 'Mae', 'Bautista', '2005-01-30', 'female', 'Poblacion, Mabini', '09171234504', 'Junior High School Graduate', 'Cookery', 'Weekdays', false,
                  'Could not continue to senior high school.', 'Enthusiastic in the kitchen.', 'Work in a restaurant or hotel.', 'Registered'],
                  ['Mark', 'Anthony', 'Garcia', '2001-09-18', 'male', 'San Rafael, Mabini', '09171234505', 'College Undergraduate', 'Computer Systems Servicing', 'Anytime', true,
                  'Stopped college after the second year due to lack of funds.', 'Comfortable with computers.', 'Get a TESDA certificate to land an IT job.', 'Validated'],
                  ['Kristine', null, 'Mendoza', '2003-05-09', 'female', 'Bacsil, Mabini', '09171234506', 'High School Graduate', 'Dressmaking', 'Weekdays', false,
                  'Young mother, wants a skill she can use at home.', 'Patient and detail-oriented.', 'Start a home-based tailoring business.', 'Registered'],
                  ['Ryan', 'Paul', 'Villanueva', '2002-02-27', 'male', 'Poblacion, Mabini', '09171234507', 'Senior High School Graduate', 'Electrical Installation', 'Weekends', true,
                  'Graduated senior high but could not afford college.', 'Interested in how wiring works.', 'Work as an electrician.', 'Validated'],
                  ['Jessa', 'Marie', 'Aquino', '2004-12-01', 'female', 'San Rafael, Mabini', '09171234508', 'Elementary Graduate', 'Caregiving', 'Weekdays', false,
                  'Left school early to care for younger siblings.', 'Caring and responsible.', 'Become a caregiver and eventually work abroad.', 'Registered'],
                  ['Carlo', null, 'Fernandez', '2000-06-15', 'male', 'Bacsil, Mabini', '09171234509', 'High School Undergraduate', 'Carpentry', 'Anytime', true,
                  'Works occasional construction jobs.', 'Strong and dependable.', 'Get certified in carpentry for steady work.', 'Validated'],
                  ['Shaira', 'Joy', 'Navarro', '2005-08-11', 'female', 'Poblacion, Mabini', '09171234510', 'Junior High School Graduate', 'Barista', 'Weekends', true,
                  'Wants to earn while finishing her studies.', 'Sociable and friendly.', 'Work at a cafe.', 'Registered'],
                  ['Daniel', 'Luis', 'Torres', '2003-10-03', 'male', 'San Rafael, Mabini', '09171234511', 'High School Graduate', 'Driving', 'Weekdays', false,
                  'Unemployed for over a year.', 'Calm and punctual.', 'Get a professional driver certification.', 'Validated'],
                  ['Princess', 'Anne', 'Castillo', '2002-04-25', 'female', 'Bacsil, Mabini', '09171234512', 'College Undergraduate', 'Contact Center Services', 'Anytime', true,
                  'Stopped college to support her family.', 'Good communication skills.', 'Work in a BPO company.', 'Validated'],
                  ['Nathaniel', null, 'Domingo', '2004-03-19', 'male', 'Poblacion, Mabini', '09171234513', 'Senior High School Undergraduate', 'Plumbing', 'Weekends', false,
                  'Dropped out due to family relocation.', 'Practical and resourceful.', 'Become a plumber.', 'Registered'],
                  ['Liza', 'Marie', 'Soriano', '2001-01-08', 'female', 'San Rafael, Mabini', '09171234514', 'High School Graduate', 'Hairdressing', 'Weekdays', false,
                  'Helps at a relative\'s salon but has no certification.', 'Has an eye for style.', 'Own a salon.', 'Validated'],
                  ['Kevin', 'John', 'Pascual', '2003-07-30', 'male', 'Bacsil, Mabini', '09171234515', 'High School Undergraduate', 'Agricultural Crops Production', 'Anytime', true,
                  'Comes from a farming family.', 'Knows basic farming already.', 'Improve farm income with modern techniques.', 'Registered'],
                  ['Rowena', null, 'Salazar', '2000-09-12', 'female', 'Poblacion, Mabini', '09171234516', 'Elementary Undergraduate', 'Food Processing', 'Weekdays', false,
                  'Sells homemade snacks to neighbors.', 'Hardworking and entrepreneurial.', 'Expand her food business.', 'Validated'],
                  ['Jay-R', 'Michael', 'Lopez', '2002-12-20', 'male', 'San Rafael, Mabini', '09171234517', 'Senior High School Graduate', 'Shielded Metal Arc Welding', 'Weekdays', true,
                  'Finished SHS with a technical-vocational track.', 'Skilled with his hands.', 'Work as a welder in a fabrication shop.', 'Validated'],
                  ['Trisha', 'Nicole', 'Valdez', '2005-05-06', 'female', 'Bacsil, Mabini', '09171234518', 'Junior High School Graduate', 'Massage Therapy', 'Weekends', false,
                  'Wants an income source while studying.', 'Gentle and attentive.', 'Become a licensed massage therapist.', 'Registered'],
                  ['Eman', 'Joseph', 'Cabrera', '2001-11-28', 'male', 'Poblacion, Mabini', '09171234519', 'High School Graduate', 'Animation', 'Anytime', true,
                  'Draws and edits videos as a hobby.', 'Creative and self-taught.', 'Work as a digital artist or animator.', 'Registered'],
                  ['Gloria', 'Mae', 'Estrada', '2003-02-14', 'female', 'San Rafael, Mabini', '09171234520', 'High School Undergraduate', 'Cookery', 'Weekdays', false,
                  'Stopped school to work as a kitchen helper.', 'Eager to learn proper techniques.', 'Become a professional cook.', 'Validated'],
            ];
      }
      }