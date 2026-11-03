<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Notifications\NewTrainingProgram;
use Illuminate\Support\Facades\Notification;
use App\Models\Referral;
use App\Models\ReferralDocument;
use App\Models\DefaultRequirement;


class TrainingProgramController extends Controller
{
    public function index(Request $request)
    {
        return TrainingProgram::latest()->get()
            ->each(fn ($program) => $this->withRequirements($program));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'slots' => ['required', 'integer', 'min:0'],
            'tesda_accredited' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('training_programs', 'public');
        }
        unset($validated['image']);

        $validated['created_by'] = $request->user()?->id;

        $program = TrainingProgram::create($validated);

        $osyUsers = User::where('role', 'osy')->get();
        Notification::send($osyUsers, new NewTrainingProgram($program));

        return response()->json($this->withRequirements($program), 201);
    }

    public function show(TrainingProgram $trainingProgram)
    {
        return $this->withRequirements($trainingProgram);
    }

    public function update(Request $request, TrainingProgram $trainingProgram)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'slots' => ['required', 'integer', 'min:0'],
            'tesda_accredited' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            if ($trainingProgram->image_path) {
                Storage::disk('public')->delete($trainingProgram->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('training_programs', 'public');
        }
        unset($validated['image']);

        // Remember the extras as they were, so we know which ones were removed.
        $before = $trainingProgram->extraRequirementNames();

        $trainingProgram->update($validated);

        $this->syncRequirementChecklists($trainingProgram, $before);

        return response()->json($this->withRequirements($trainingProgram));
    }

    public function destroy(TrainingProgram $trainingProgram)
    {
        if ($trainingProgram->image_path) {
            Storage::disk('public')->delete($trainingProgram->image_path);
        }

        $trainingProgram->delete();

        return response()->json(['message' => 'Program removed.']);
    }

    /**
     * Adds default_requirements, extra_requirements and all_requirements
     * to the program so the frontend never has to work them out.
     */
    private function withRequirements(TrainingProgram $program): TrainingProgram
    {
        $program->setAttribute(
            'default_requirements',
            DefaultRequirement::orderBy('id')->pluck('name')->all()
        );
        $program->setAttribute('extra_requirements', $program->extraRequirementNames());
        $program->setAttribute('all_requirements', $program->requirementNames());

        return $program;
    }

    /**
     * Keeps the checklists of OSY who are still Accepted in step with the
     * program's requirements after an edit.
     */
    private function syncRequirementChecklists(TrainingProgram $program, array $before): void
    {
        $removed = array_values(array_diff(
            array_map('mb_strtolower', $before),
            array_map('mb_strtolower', $program->extraRequirementNames())
        ));

        $referrals = Referral::where('training_program_id', $program->id)
            ->where('status', 'Accepted by Maxima')
            ->get();

        // Only extras removed from this program, and only while still pending.
        // Received items and documents added by hand stay untouched.
        if ($removed && $referrals->isNotEmpty()) {
            ReferralDocument::whereIn('referral_id', $referrals->pluck('id'))
                ->where('status', 'pending')
                ->where(function ($query) use ($removed) {
                    foreach ($removed as $name) {
                        $query->orWhereRaw('LOWER(name) = ?', [$name]);
                    }
                })
                ->delete();
        }

        // Newly added extras show up as pending.
        ReferralDocument::ensureFor($referrals);
    }
}