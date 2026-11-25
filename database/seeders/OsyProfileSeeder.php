<?php

namespace Database\Seeders;

use App\Models\OsyProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OsyProfileSeeder extends Seeder
{
      public function run(): void
      {
            $skId = User::where('role', 'sk_officials')->value('id');

            DB::transaction(function () use ($skId) {
                  foreach ($this->dummy() as $i => $d) {
                        $email = sprintf('osy%d@backtotrack.test', $i + 1);

                        $user = User::firstOrNew(['email' => $email]);
                        $user->name = $d[0] . ' ' . $d[1];
                        $user->role = 'osy';
                        if (!$user->exists) {
                              $user->password = 'password'; // hashed by the User cast
                        }
                        $user->email_verified_at = now();
                        $user->must_change_password = false;
                        $user->save();

                        OsyProfile::updateOrCreate(
                              ['user_id' => $user->id],
                              [
                                    'registered_by' => $skId,
                                    'first_name' => $d[0],
                                    'middle_name' => null,
                                    'last_name' => $d[1],
                                    'birthdate' => $d[2],
                                    'sex' => $d[3],
                                    'address' => $d[4],
                                    'contact_number' => $d[5],
                                    'educational_attainment' => $d[6],
                                    'preferred_career' => $d[7],
                                    'available_schedule' => 'Weekdays',
                                    'has_transportation' => $d[8],
                                    'current_status' => $d[9],
                              ]
                        );
                  }
            });
      }

      // first, last, birthdate, sex, address, contact, education, career, transport, status
      private function dummy(): array
      {
            return [
                  ['Juan', 'Dela Cruz', '2003-03-14', 'male', 'Poblacion, Mabini', '09171234501', 'High School Graduate', 'Welding', true, 'Validated'],
                  ['Maria', 'Reyes', '2004-07-22', 'female', 'San Rafael, Mabini', '09171234502', 'Senior High School Undergraduate', 'Bread and Pastry Production', false, 'Registered'],
                  ['Jose', 'Ramos', '2002-11-05', 'male', 'Bacsil, Mabini', '09171234503', 'High School Undergraduate', 'Motorcycle Servicing', true, 'Validated'],
                  ['Kristine', 'Mendoza', '2003-05-09', 'female', 'Bacsil, Mabini', '09171234506', 'High School Graduate', 'Dressmaking', false, 'Registered'],
                  ['Ryan', 'Villanueva', '2002-02-27', 'male', 'Poblacion, Mabini', '09171234507', 'Senior High School Graduate', 'Electrical Installation', true, 'Validated'],
                  ['Carlo', 'Fernandez', '2000-06-15', 'male', 'Bacsil, Mabini', '09171234509', 'High School Undergraduate', 'Carpentry', true, 'Validated'],
                  ['Shaira', 'Navarro', '2005-08-11', 'female', 'Poblacion, Mabini', '09171234510', 'Junior High School Graduate', 'Barista', true, 'Registered'],
                  ['Liza', 'Soriano', '2001-01-08', 'female', 'San Rafael, Mabini', '09171234514', 'High School Graduate', 'Hairdressing', false, 'Validated'],
                  ['Trisha', 'Valdez', '2005-05-06', 'female', 'Bacsil, Mabini', '09171234518', 'Junior High School Graduate', 'Massage Therapy', false, 'Registered'],
                  ['Gloria', 'Estrada', '2003-02-14', 'female', 'San Rafael, Mabini', '09171234520', 'High School Undergraduate', 'Cookery', false, 'Validated'],

                  ['Mark', 'Garcia', '2001-09-18', 'male', 'San Rafael, Mabini', '09171234521', 'College Undergraduate', 'Computer Systems Servicing', true, 'Validated'],
                  ['Angelica', 'Bautista', '2005-01-30', 'female', 'Poblacion, Mabini', '09171234522', 'Junior High School Graduate', 'Cookery', false, 'Registered'],
                  ['Daniel', 'Torres', '2003-10-03', 'male', 'San Rafael, Mabini', '09171234523', 'High School Graduate', 'Driving', false, 'Validated'],
                  ['Princess', 'Castillo', '2002-04-25', 'female', 'Bacsil, Mabini', '09171234524', 'College Undergraduate', 'Contact Center Services', true, 'Registered'],
                  ['Nathaniel', 'Domingo', '2004-03-19', 'male', 'Poblacion, Mabini', '09171234525', 'Senior High School Undergraduate', 'Plumbing', false, 'Registered'],
                  ['Kevin', 'Pascual', '2003-07-30', 'male', 'Bacsil, Mabini', '09171234526', 'High School Undergraduate', 'Agricultural Crops Production', true, 'Validated'],
                  ['Rowena', 'Salazar', '2000-09-12', 'female', 'Poblacion, Mabini', '09171234527', 'Elementary Undergraduate', 'Food Processing', false, 'Validated'],
                  ['Jessa', 'Aquino', '2004-12-01', 'female', 'San Rafael, Mabini', '09171234528', 'Elementary Graduate', 'Caregiving', false, 'Registered'],
                  ['Eman', 'Cabrera', '2001-11-28', 'male', 'Poblacion, Mabini', '09171234529', 'High School Graduate', 'Animation', true, 'Registered'],
                  ['Jay', 'Lopez', '2002-12-20', 'male', 'San Rafael, Mabini', '09171234530', 'Senior High School Graduate', 'Shielded Metal Arc Welding', true, 'Validated'],

                  ['Patricia', 'Gonzales', '2004-02-11', 'female', 'Poblacion, Mabini', '09171234531', 'Junior High School Graduate', 'Housekeeping', false, 'Registered'],
                  ['Miguel', 'Santiago', '2003-08-16', 'male', 'Bacsil, Mabini', '09171234532', 'High School Graduate', 'Motorcycle Servicing', true, 'Validated'],
                  ['Camille', 'Flores', '2005-06-21', 'female', 'San Rafael, Mabini', '09171234533', 'Senior High School Undergraduate', 'Hairdressing', true, 'Registered'],
                  ['Joshua', 'Mercado', '2002-03-07', 'male', 'Poblacion, Mabini', '09171234534', 'High School Undergraduate', 'Electrical Installation', false, 'Validated'],
                  ['Bianca', 'Ocampo', '2004-10-29', 'female', 'Bacsil, Mabini', '09171234535', 'Junior High School Graduate', 'Barista', false, 'Registered'],
                  ['Rico', 'Manalo', '2001-05-13', 'male', 'San Rafael, Mabini', '09171234536', 'Elementary Graduate', 'Carpentry', true, 'Validated'],
                  ['Hazel', 'Tolentino', '2003-12-24', 'female', 'Poblacion, Mabini', '09171234537', 'High School Graduate', 'Bread and Pastry Production', false, 'Validated'],
                  ['Paolo', 'Aguilar', '2005-09-02', 'male', 'Bacsil, Mabini', '09171234538', 'Senior High School Undergraduate', 'Computer Systems Servicing', true, 'Registered'],
                  ['Denise', 'Cruz', '2002-07-19', 'female', 'San Rafael, Mabini', '09171234539', 'High School Undergraduate', 'Dressmaking', false, 'Validated'],
                  ['Luis', 'Bernardo', '2000-01-26', 'male', 'Poblacion, Mabini', '09171234540', 'High School Graduate', 'Welding', true, 'Registered'],
            ];
      }
}