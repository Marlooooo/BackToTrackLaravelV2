<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use App\Models\TrainingProgramHistory;
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
    // Fields written to the history
    private const TRACKED = [
        'name',
        'description',
        'requirements',
        'schedule',
        'slots',
        'tesda_accredited',
        'status',
    ];

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

        $this->recordHistory(
            $request,
            $program,
            'created',
            $this->createdChanges($program, $request->hasFile('image'))
        );

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

        // Values before the edit, for the history
        $before   = $this->snapshot($trainingProgram);
        $hadImage = (bool) $trainingProgram->image_path;

        if ($request->hasFile('image')) {
            if ($trainingProgram->image_path) {
                Storage::disk('public')->delete($trainingProgram->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('training_programs', 'public');
        }
        unset($validated['image']);

        // Remember the extras as they were, so we know which ones were removed.
        $extrasBefore = $trainingProgram->extraRequirementNames();

        $trainingProgram->update($validated);

        $changes = $this->diffChanges($before, $trainingProgram, $request->hasFile('image'), $hadImage);

        if ($changes) {
            $this->recordHistory($request, $trainingProgram, 'updated', $changes);
        }

        $this->syncRequirementChecklists($trainingProgram, $extrasBefore);

        return response()->json($this->withRequirements($trainingProgram));
    }

    public function destroy(Request $request, TrainingProgram $trainingProgram)
    {
        $this->recordHistory($request, $trainingProgram, 'deleted', []);

        if ($trainingProgram->image_path) {
            Storage::disk('public')->delete($trainingProgram->image_path);
        }

        $trainingProgram->delete();

        return response()->json(['message' => 'Program removed.']);
    }

    /**
     * GET /api/training-programs/{trainingProgram}/history
     */
    public function history(Request $request, TrainingProgram $trainingProgram)
    {
        abort_unless($request->user()->role === 'maxima_tesda_school', 403, 'Unauthorized');

        return TrainingProgramHistory::with('user:id,name')
            ->where('training_program_id', $trainingProgram->id)
            ->latest('id')
            ->get();
    }
    /**
     * GET /api/maxima/program-history
     */
    public function allHistory(Request $request)
    {
        abort_unless($request->user()->role === 'maxima_tesda_school', 403, 'Unauthorized');

        $data = $request->validate([
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date'],
            'program_id' => ['nullable', 'integer'],
        ]);

        return TrainingProgramHistory::with('user:id,name')
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($data['program_id'] ?? null, fn ($q, $v) => $q->where('training_program_id', $v))
            ->latest('id')
            ->limit(500)
            ->get();
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

    // ------------------------------------------------------------------
    // History helpers
    // ------------------------------------------------------------------

    private function recordHistory(Request $request, TrainingProgram $program, string $action, array $changes): void
    {
        TrainingProgramHistory::create([
            'training_program_id' => $program->id,
            'program_name'        => $program->name,
            'changed_by'          => $request->user()?->id,
            'action'              => $action,
            'changes'             => $changes,
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(TrainingProgram $program): array
    {
        $values = [];

        foreach (self::TRACKED as $field) {
            $values[$field] = $program->getAttribute($field);
        }

        return $values;
    }

    // Trim text, treat empty as null, and unify line breaks before comparing
    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim(str_replace("\r\n", "\n", $value));

            return $value === '' ? null : $value;
        }

        return $value;
    }

    /** @return array<int, array<string, mixed>> */
    private function createdChanges(TrainingProgram $program, bool $hasImage): array
    {
        $changes = [];

        foreach ($this->snapshot($program) as $field => $value) {
            $value = $this->normalize($value);

            if ($value !== null) {
                $changes[] = ['field' => $field, 'old' => null, 'new' => $value];
            }
        }

        if ($hasImage) {
            $changes[] = ['field' => 'image', 'old' => null, 'new' => 'Image uploaded'];
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $before
     * @return array<int, array<string, mixed>>
     */
    private function diffChanges(array $before, TrainingProgram $program, bool $imageChanged, bool $hadImage): array
    {
        $after   = $this->snapshot($program);
        $changes = [];

        foreach (self::TRACKED as $field) {
            $old = $this->normalize($before[$field]);
            $new = $this->normalize($after[$field]);

            if ($old !== $new) {
                $changes[] = ['field' => $field, 'old' => $old, 'new' => $new];
            }
        }

        if ($imageChanged) {
            $changes[] = [
                'field' => 'image',
                'old'   => $hadImage ? 'Previous image' : 'No image',
                'new'   => 'New image uploaded',
            ];
        }

        return $changes;
    }
}