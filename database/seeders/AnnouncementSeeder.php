<?php

   namespace Database\Seeders;

   use App\Models\Announcement;
   use App\Models\TrainingProgram;
   use App\Models\User;
   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Seeder;

   class AnnouncementSeeder extends Seeder
   {
      public function run(): void
      {
         Model::unguarded(fn () => $this->seed());
      }

      private function seed(): void
      {
         $maxima = User::where('role', 'maxima_tesda_school')->orderBy('id')->first();
         $sk = User::where('role', 'sk_officials')->orderBy('id')->first();

         if (! $maxima || ! $sk) {
               $this->command->error('SK or Maxima account missing. Run UserSeeder first.');
               return;
         }

         // [program name or null, posted by, days ago, title, content]
         $items = [
               [
                  'Shielded Metal Arc Welding (SMAW) NC II', $maxima, 40,
                  'Enrollment open: Welding NC II',
                  'Slots are open for the next Shielded Metal Arc Welding batch. Applicants must be at least 18 years old and physically fit. Please coordinate with your SK official for your referral.',
               ],
               [
                  null, $sk, 20,
                  'OSY registration drive at the barangay hall',
                  'Out-of-school youth aged 15 to 30 can register at the barangay hall on weekdays. Bring one valid ID or your birth certificate. Registered youth will be interviewed and matched with suitable TESDA programs.',
               ],
               [
                  'Bread and Pastry Production NC II', $maxima, 12,
                  'Bread and Pastry: new batch orientation',
                  'An orientation for the new Bread and Pastry Production batch will be held before classes start. Please bring a notebook and wear closed shoes.',
               ],
               [
                  null, $maxima, 9,
                  'Reminder: bring your original documents',
                  'Accepted trainees should submit their PSA birth certificate, barangay clearance, valid ID photocopy, 1x1 photos and medical certificate before the first week of training ends.',
               ],
               [
                  'Hairdressing NC II', $maxima, 3,
                  'Hairdressing NC II: limited slots left',
                  'Only a few slots remain for the weekend Hairdressing batch. Ask your SK official to refer you as soon as possible.',
               ],
         ];

         foreach ($items as [$programName, $author, $daysAgo, $title, $content]) {
               $postedAt = now()->subDays($daysAgo)->setTime(9, 0);
               $programId = $programName ? TrainingProgram::where('name', $programName)->value('id') : null;

               Announcement::updateOrCreate(
                  ['title' => $title],
                  [
                     'training_program_id' => $programId,
                     'posted_by' => $author->id,
                     'content' => $content,
                     'posted_at' => $postedAt,
                     'created_at' => $postedAt,
                     'updated_at' => $postedAt,
                  ]
               );
         }
      }
   }