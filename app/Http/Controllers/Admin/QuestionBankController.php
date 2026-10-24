<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamQuestion;
use App\Models\QuestionBank;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionBankController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $query = QuestionBank::with(['subject:id,name', 'creator:id,name'])
            ->latest();

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->input('difficulty'));
        }
        if ($request->filled('q')) {
            $query->where('question_text', 'like', '%'.$request->input('q').'%');
        }

        $questions = $query->paginate(20)->withQueryString();
        $subjects = Subject::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.question-bank.index', compact('questions', 'subjects'));
    }

    // ── Store ──────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateQuestion($request);

        QuestionBank::create([
            ...$validated,
            'school_id' => app('current.school')->id,
            'created_by' => auth()->id(),
            'tags' => $this->parseTags($validated['tags'] ?? null),
        ]);

        return redirect()->route($this->indexRoute())
            ->with('success', __('Question added to bank.'));
    }

    // ── Update ─────────────────────────────────────────────────────

    public function update(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $this->authorizeChange($questionBank);

        $validated = $this->validateQuestion($request);

        $questionBank->update([
            ...$validated,
            'tags' => $this->parseTags($validated['tags'] ?? null),
        ]);

        return redirect()->route($this->indexRoute())
            ->with('success', __('Question updated.'));
    }

    // ── Destroy ────────────────────────────────────────────────────

    public function destroy(QuestionBank $questionBank): RedirectResponse
    {
        $this->authorizeChange($questionBank);

        $questionBank->delete();

        return redirect()->route($this->indexRoute())
            ->with('success', __('Question removed from bank.'));
    }

    /**
     * The bank is shared across the school, but a teacher may only change questions they added.
     */
    private function authorizeChange(QuestionBank $questionBank): void
    {
        abort_if(
            auth()->user()->role === 'teacher' && $questionBank->created_by !== auth()->id(),
            403,
            __('You can only edit or remove questions you added to the bank.')
        );
    }

    /**
     * Admins and teachers share this controller — send each back to their own page.
     */
    private function indexRoute(): string
    {
        return auth()->user()->role === 'teacher' ? 'teacher.question-bank.index' : 'admin.question-bank.index';
    }

    // ── JSON: Search (used by import modal) ────────────────────────

    public function search(Request $request): JsonResponse
    {
        $school = app('current.school');

        $query = QuestionBank::with('subject:id,name')
            ->where('school_id', $school->id);

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->input('difficulty'));
        }
        if ($request->filled('q')) {
            $query->where('question_text', 'like', '%'.$request->input('q').'%');
        }

        $questions = $query->latest()->limit(60)->get()->map(fn ($q) => [
            'id' => $q->id,
            'type' => $q->type,
            'type_label' => $q->typeLabel(),
            'difficulty' => $q->difficulty,
            'question_text' => $q->question_text,
            'options' => $q->options,
            'correct_answer' => $q->correct_answer,
            'explanation' => $q->explanation,
            'marking_guide' => $q->marking_guide,
            'sample_answer' => $q->sample_answer,
            'points' => $q->points,
            'min_words' => $q->min_words,
            'max_words' => $q->max_words,
            'subject_name' => $q->subject?->name,
            'times_used' => $q->times_used,
        ]);

        return response()->json(['data' => $questions]);
    }

    // ── JSON: Save exam question (or raw question data) to bank ────

    public function saveFromExam(Request $request): JsonResponse
    {
        $school = app('current.school');

        // Path A: saving directly from the manual editor (no exam_question_id yet)
        if ($request->boolean('_from_editor')) {
            $request->validate([
                'type' => ['required', 'in:multiple_choice,true_false,fill_blank,short_answer,theory,matching'],
                'question_text' => ['required', 'string', 'max:5000'],
                'points' => ['required', 'integer', 'min:1'],
            ]);

            $existing = QuestionBank::where('school_id', $school->id)
                ->where('question_text', $request->string('question_text'))
                ->first();

            if ($existing) {
                return response()->json(['message' => __('Already in question bank.'), 'id' => $existing->id, 'already' => true]);
            }

            $bankQuestion = QuestionBank::create([
                'school_id' => $school->id,
                'created_by' => auth()->id(),
                'type' => $request->input('type'),
                'question_text' => $request->input('question_text'),
                'options' => $request->input('options'),
                'correct_answer' => $request->input('correct_answer'),
                'explanation' => $request->input('explanation'),
                'marking_guide' => $request->input('marking_guide'),
                'sample_answer' => $request->input('sample_answer'),
                'points' => $request->integer('points', 1),
                'min_words' => $request->integer('min_words') ?: null,
                'max_words' => $request->integer('max_words') ?: null,
                'difficulty' => 'medium',
            ]);

            return response()->json(['message' => __('Saved to question bank.'), 'id' => $bankQuestion->id, 'already' => false]);
        }

        // Path B: saving from a persisted exam question
        $request->validate(['exam_question_id' => ['required', 'integer', 'exists:exam_questions,id']]);

        $examQuestion = ExamQuestion::where('school_id', $school->id)
            ->findOrFail($request->integer('exam_question_id'));

        $existing = QuestionBank::where('school_id', $school->id)
            ->where('question_text', $examQuestion->question_text)
            ->first();

        if ($existing) {
            return response()->json(['message' => __('Already in question bank.'), 'id' => $existing->id, 'already' => true]);
        }

        $bankQuestion = QuestionBank::create([
            'school_id' => $school->id,
            'subject_id' => $examQuestion->exam?->subject_id,
            'created_by' => auth()->id(),
            'type' => $examQuestion->type,
            'question_text' => $examQuestion->question_text,
            'options' => $examQuestion->options,
            'correct_answer' => $examQuestion->correct_answer,
            'explanation' => $examQuestion->explanation,
            'marking_guide' => $examQuestion->marking_guide,
            'sample_answer' => $examQuestion->sample_answer,
            'points' => $examQuestion->points,
            'min_words' => $examQuestion->min_words ?? null,
            'max_words' => $examQuestion->max_words ?? null,
            'difficulty' => 'medium',
        ]);

        $examQuestion->update(['question_bank_id' => $bankQuestion->id]);

        return response()->json(['message' => __('Saved to question bank.'), 'id' => $bankQuestion->id, 'already' => false]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validateQuestion(Request $request): array
    {
        return $request->validate([
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'class_id' => ['nullable', 'exists:classes,id'],
            'type' => ['required', 'in:multiple_choice,true_false,fill_blank,short_answer,theory,matching'],
            'question_text' => ['required', 'string', 'max:5000'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:500'],
            'correct_answer' => ['nullable', 'string', 'max:1000'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'marking_guide' => ['nullable', 'string', 'max:2000'],
            'sample_answer' => ['nullable', 'string', 'max:5000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'min_words' => ['nullable', 'integer', 'min:1'],
            'max_words' => ['nullable', 'integer', 'min:1', 'gte:min_words'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /** @return list<string>|null */
    private function parseTags(?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }

        $tags = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return $tags ?: null;
    }
}
