<?php

   namespace Database\Seeders;

   use App\Models\TrainingProgram;
   use App\Models\User;
   use Illuminate\Database\Seeder;

   class TrainingProgramSeeder extends Seeder
   {
      public function run(): void
      {
         // Programs are owned by a MAXIMA/TESDA staff account.
         $creator = User::where('email', 'maxima@test.com')->first()
               ?? User::where('role', 'maxima_tesda_school')->first();

         if (! $creator) {
               $this->command->error('No MAXIMA user found. Run TestUsersSeeder first.');
               return;
         }

         $programs = [
               [
                  'name' => 'Shielded Metal Arc Welding (SMAW) NC II',
                  'description' => 'Hands-on training in arc welding for structural and fabrication work.',
                  'requirements' => 'Able to read and write; at least 18 years old; physically fit.',
                  'schedule' => 'Mon-Fri, 8:00 AM - 5:00 PM',
                  'slots' => 25,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Bread and Pastry Production NC II',
                  'description' => 'Learn to prepare and bake breads, cakes, and pastries for small business or employment.',
                  'requirements' => 'Able to read and write; at least 16 years old.',
                  'schedule' => 'Mon-Fri, 1:00 PM - 5:00 PM',
                  'slots' => 20,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Computer Systems Servicing NC II',
                  'description' => 'Install, configure, and maintain computer systems and networks.',
                  'requirements' => 'High school level or ALS graduate; basic computer literacy is a plus.',
                  'schedule' => 'Mon-Fri, 8:00 AM - 12:00 NN',
                  'slots' => 30,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Electrical Installation and Maintenance NC II',
                  'description' => 'Wiring, installation, and maintenance of residential electrical systems.',
                  'requirements' => 'At least 18 years old; able to read and write.',
                  'schedule' => 'Tue-Sat, 8:00 AM - 5:00 PM',
                  'slots' => 25,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Housekeeping NC II',
                  'description' => 'Professional cleaning and room-care skills for hotels and resorts.',
                  'requirements' => 'At least 18 years old; able to read and write.',
                  'schedule' => 'Mon-Fri, 8:00 AM - 12:00 NN',
                  'slots' => 20,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Events Management Basics',
                  'description' => 'Short course on planning and running community events.',
                  'requirements' => 'Open to all out-of-school youth.',
                  'schedule' => 'Sat, 9:00 AM - 3:00 PM',
                  'slots' => 40,
                  'tesda_accredited' => false,
                  'status' => 'inactive',
               ],               [
                  'name' => 'Cookery NC II',
                  'description' => 'Prepare and present appetizers, soups, main dishes, and desserts for commercial kitchens.',
                  'requirements' => 'Able to read and write; at least 16 years old.',
                  'schedule' => 'Mon-Fri, 8:00 AM - 12:00 NN',
                  'slots' => 25,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Motorcycle and Small Engine Servicing NC II',
                  'description' => 'Diagnose, repair, and maintain motorcycles and small engines.',
                  'requirements' => 'At least 18 years old; able to read and write.',
                  'schedule' => 'Mon-Fri, 1:00 PM - 5:00 PM',
                  'slots' => 20,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Carpentry NC II',
                  'description' => 'Measure, cut, and assemble wood for buildings, furniture, and formwork.',
                  'requirements' => 'At least 18 years old; physically fit.',
                  'schedule' => 'Mon-Sat, 8:00 AM - 4:00 PM',
                  'slots' => 25,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Barista NC II',
                  'description' => 'Prepare espresso-based drinks, serve customers, and run a coffee station.',
                  'requirements' => 'Able to read and write; at least 16 years old.',
                  'schedule' => 'Mon-Fri, 1:00 PM - 5:00 PM',
                  'slots' => 20,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Hairdressing NC II',
                  'description' => 'Haircutting, hair coloring, and basic salon services.',
                  'requirements' => 'At least 16 years old; able to read and write.',
                  'schedule' => 'Sat-Sun, 9:00 AM - 4:00 PM',
                  'slots' => 20,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Dressmaking NC II',
                  'description' => 'Pattern making, cutting, and sewing of dresses, uniforms, and other garments.',
                  'requirements' => 'Able to read and write; at least 16 years old.',
                  'schedule' => 'Mon-Fri, 8:00 AM - 12:00 NN',
                  'slots' => 25,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
               [
                  'name' => 'Caregiving NC II',
                  'description' => 'Basic care for children, the elderly, and people who need assistance at home or in care facilities.',
                  'requirements' => 'At least 18 years old; high school graduate or ALS graduate.',
                  'schedule' => 'Mon-Fri, 8:00 AM - 5:00 PM',
                  'slots' => 30,
                  'tesda_accredited' => true,
                  'status' => 'active',
               ],
         ];

         foreach ($programs as $program) {
               TrainingProgram::updateOrCreate(
                  ['name' => $program['name']],
                  $program + ['created_by' => $creator->id, 'image_path' => null]
               );
         }
      }
   }