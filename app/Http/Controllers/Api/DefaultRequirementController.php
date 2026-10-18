<?php

   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Models\DefaultRequirement;
   use App\Models\Referral;
   use App\Models\ReferralDocument;
   use Illuminate\Http\JsonResponse;
   use Illuminate\Http\Request;
   use Illuminate\Support\Facades\DB;

   class DefaultRequirementController extends Controller
   {
      private function authorizeMaxima(Request $request): void
      {
         abort_unless($request->user()->role === 'maxima_tesda_school', 403, 'Unauthorized');
      }

      /** GET /api/maxima/default-requirements */
      public function index(Request $request): JsonResponse
      {
         $this->authorizeMaxima($request);

         return response()->json(DefaultRequirement::orderBy('id')->get(['id', 'name']));
      }

      /** POST /api/maxima/default-requirements */
      public function store(Request $request): JsonResponse
      {
         $this->authorizeMaxima($request);

         $data = $request->validate(['name' => 'required|string|max:100']);
         $name = preg_replace('/\s+/', ' ', trim($data['name']));

         $requirement = DB::transaction(function () use ($name) {
               abort_if(
                  DefaultRequirement::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists(),
                  422,
                  'That requirement is already in the list.'
               );

               $requirement = DefaultRequirement::create(['name' => $name]);

               // Every OSY who is still Accepted gets the new item as pending.
               // Inside the transaction, so the list and the checklists never disagree.
               ReferralDocument::ensureFor(
                  Referral::where('status', 'Accepted by Maxima')->get()
               );

               return $requirement;
         });

         return response()->json($requirement, 201);
      }

      /** DELETE /api/maxima/default-requirements/{defaultRequirement} */
      public function destroy(Request $request, DefaultRequirement $defaultRequirement): JsonResponse
      {
         $this->authorizeMaxima($request);

         DB::transaction(function () use ($defaultRequirement) {
               // Only pending items disappear. Received ones stay as a record.
               ReferralDocument::whereIn(
                     'referral_id',
                     Referral::where('status', 'Accepted by Maxima')->select('id')
                  )
                  ->where('name', $defaultRequirement->name)
                  ->where('status', 'pending')
                  ->delete();

               $defaultRequirement->delete();
         });

         return response()->json(['message' => 'Requirement removed.']);
      }
   }