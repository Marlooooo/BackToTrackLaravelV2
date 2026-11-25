<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
   public function run(): void
   {
      $maxima = User::where('role', 'maxima_tesda_school')->first();

      if (!$maxima) {
         $this->command->error('MAXIMA user missing. Run UserSeeder first.');
         return;
      }

      foreach ($this->rows() as $row) {
         $programId = $row['program']
            ? TrainingProgram::where('name', $row['program'])->value('id')
            : null;

         if ($row['program'] && !$programId) {
            $this->command->warn("Skipped '{$row['title']}': program not found.");
            continue;
         }

         Announcement::updateOrCreate(
            ['title' => $row['title']],
            [
               'training_program_id' => $programId,
               'posted_by' => $maxima->id,
               'content' => $row['content'],
               'posted_at' => $row['posted_at'],
            ]
         );
      }
   }

   // program name (null = general), title, content, posted_at
   private function rows(): array
   {
      return [
         [
            'program' => null,
            'title' => 'Welcome to the BackToTrack Training Program',
            'content' => 'Welcome, enrollees! Please attend your sessions on time, wear proper attire, and coordinate with your SK official for any concerns about your attendance.',
            'posted_at' => '2026-10-01 09:00:00',
         ],
         [
            'program' => null,
            'title' => 'Office Hours Update',
            'content' => 'The Maxima TESDA office is open Monday to Friday, 8:00 AM to 5:00 PM. Please bring a valid ID when submitting your requirements.',
            'posted_at' => '2026-11-12 08:30:00',
         ],
         [
            'program' => null,
            'title' => 'Enrollment Open for the Next Batch',
            'content' => 'Referrals for the next batch are now open. SK officials may refer registered OSYs to any available program until slots are filled.',
            'posted_at' => '2026-11-18 10:00:00',
         ],
         [
            'program' => 'Cookery NC II',
            'title' => 'Cookery: Bring Your Own Apron and Knife Set',
            'content' => 'Cookery trainees are reminded to bring a clean apron, hairnet, and knife set starting next week. Closed shoes are required inside the kitchen.',
            'posted_at' => '2026-10-16 14:00:00',
         ],
         [
            'program' => 'Shielded Metal Arc Welding (SMAW) NC II',
            'title' => 'Welding: Safety Gear Required',
            'content' => 'Welding trainees must wear a welding helmet, gloves, and safety shoes during all workshop sessions. Gear is not allowed to be shared.',
            'posted_at' => '2026-10-16 15:00:00',
         ],
         [
            'program' => 'Computer Systems Servicing NC II',
            'title' => 'Computer Systems Servicing: Lab Schedule Change',
            'content' => 'The hands-on lab session this Friday moves to 1:00 PM. Lecture sessions remain at the usual time.',
            'posted_at' => '2026-11-13 11:00:00',
         ],
         [
            'program' => 'Barista NC II',
            'title' => 'Barista: Mid-training Assessment',
            'content' => 'The mid-training assessment will be held next week. Review espresso preparation and customer service procedures.',
            'posted_at' => '2026-11-16 09:30:00',
         ],
         [
            'program' => 'Bread and Pastry Production NC II',
            'title' => 'Bread and Pastry: Slots Available',
            'content' => 'A few slots are still available for the next Bread and Pastry batch. Ask your SK official to submit your referral.',
            'posted_at' => '2026-11-17 13:00:00',
         ],
      ];
   }
}