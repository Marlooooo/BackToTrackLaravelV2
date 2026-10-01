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

class TrainingProgramController extends Controller
{
    public function index(Request $request)
    {
        return TrainingProgram::latest()->get();
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

        return response()->json($program, 201);
    }

    public function show(TrainingProgram $trainingProgram)
    {
        return $trainingProgram;
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

        $trainingProgram->update($validated);

        return response()->json($trainingProgram);
    }

    public function destroy(TrainingProgram $trainingProgram)
    {
        if ($trainingProgram->image_path) {
            Storage::disk('public')->delete($trainingProgram->image_path);
        }

        $trainingProgram->delete();

        return response()->json(['message' => 'Program removed.']);
    }
}