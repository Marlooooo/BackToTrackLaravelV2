<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        ]);

        $validated['created_by'] = $request->user()?->id;

        $program = TrainingProgram::create($validated);

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
        ]);

        $trainingProgram->update($validated);

        return response()->json($trainingProgram);
    }

    public function destroy(TrainingProgram $trainingProgram)
    {
        $trainingProgram->delete();

        return response()->json(['message' => 'Program removed.']);
    }
}