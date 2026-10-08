<?php

   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Models\Announcement;
   use Illuminate\Http\Request;

   class AnnouncementController extends Controller
   {
      public function index()
      {
         return Announcement::with(['author:id,name', 'trainingProgram'])
               ->orderByDesc('posted_at')
               ->get();
      }

      private function ensureMaxima(Request $request): void
      {
         abort_unless($request->user()->role === 'maxima_tesda_school', 403, 'Only Maxima can manage announcements.');
      }

      private function rules(): array
      {
         return [
               'title'               => 'required|string|max:255',
               'content'             => 'required|string',
               'training_program_id' => 'nullable|exists:training_programs,id',
         ];
      }

      public function store(Request $request)
      {
         $this->ensureMaxima($request);
         $data = $request->validate($this->rules());

         $announcement = Announcement::create($data + ['posted_by' => $request->user()->id]);

         return response()->json($announcement->load(['author:id,name', 'trainingProgram']), 201);
      }

      public function update(Request $request, Announcement $announcement)
      {
         $this->ensureMaxima($request);
         $announcement->update($request->validate($this->rules()));

         return $announcement->load(['author:id,name', 'trainingProgram']);
      }

      public function destroy(Request $request, Announcement $announcement)
      {
         $this->ensureMaxima($request);
         $announcement->delete();

         return response()->json(['message' => 'Deleted']);
      }
   }