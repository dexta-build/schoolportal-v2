<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\SchoolClass;
use App\Models\ScoreComponent;
use App\Models\Subject;
use App\Models\TeacherAction;
use App\Services\AiCreditService;
use App\Services\ExamGeneratorService;
use App\Services\ExamGradingService;
use App\Services\FileUploadService;
use App\Services\RecentlyDeletedService;
use App\Services\ScoreAggregationService;
use App\Traits\NotifiesAdminsOnSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamController extends Controller
{
    use NotifiesAdminsOnSubmission;

    public function __construct(
        private readonly ExamGeneratorService $examGenerator,
        private readonly AiCreditService $creditService,
        private readonly FileUploadService $fileUploader,
        private readonly ExamGradingService $gradingService,
        private readonly ScoreAggregationService $scoreService,
    ) {}

    // ── Category & Route Helpers ──

    private function resolveCategory(): ?string
    {
        // 1. Check form input first so POST requests keep the selected CBT type.
        $inputCategory = request()->input('category');
        if (in_array($inputCategory, ['exam', 'assessment', 'assignment'], true)) {
            return $inputCategory;
        }

        // 2. Check query param (unified index/create pages use ?category=)
        $queryCategory = request()->query('category');
        if (in_array($queryCategory, ['exam', 'assessment', 'assignment'], true)) {
            return $queryCategory;
        }

        // 3. Check route-bound exam model (for show/edit/results/etc.)
        $exam = request()->route('exam');
        if ($exam instanceof Exam) {
            return $exam->category;
        }

        // 4. For the unified index (teacher.exams.index without ?category), return null = all
        $name = request()->route()->getName();
        if ($name === 'teacher.exams.index' && ! $queryCategory) {
            return null;
        }

        return 'exam';
    }

    private function routePrefix(): string
    {
        return 'teacher.exams';
    }

    private function categoryLabel(): string
    {
        return match ($this->resolveCategory()) {
            'assessment' => __('Assessment'),
            'assignment' => __('Assignment'),
            null => __('CBT'),
            default => __('Exam'),
        };
    }

    private function viewData(): array
    {
        return [
            'category' => $this->resolveCategory(),
            'categoryLabel' => $this->categoryLabel(),
            'routePrefix' => $this->routePrefix(),
        ];
    }

    public function index(Request $request): View
    {
        $teacher = auth()->user();
        $category = $this->resolveCategory();
        $classIds = $teacher->assignedClasses()->pluck('id');

        $query = Exam::with([
            'class:id,name',
            'subject:id,name',
            'scoreComponent:id,name,short_name',
            'session:id,name',
            'term:id,name',
            'creator:id,name',
            'latestTeacherAction',
        ])->withCount('questions')->where(function ($q) use ($teacher, $classIds) {
            // Teacher's own exams (any status)
            $q->where('created_by', $teacher->id)
                // OR approved/published exams for their assigned classes
                ->orWhere(function ($sub) use ($classIds) {
                    $sub->whereIn('class_id', $classIds)
                        ->where('status', 'approved');
                });
        });

        // When category is null (unified view), show all; otherwise filter
        if ($category !== null) {
            $query->forCategory($category);
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $exams = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        $classes = SchoolClass::whereIn('id', $classIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('teacher.exams.index', array_merge(
            compact('exams', 'classes'),
            $this->viewData(),
        ));
    }

    public function create(): View
    {
        $teacher = auth()->user();
        $school = app('current.school');
        $classIds = $teacher->assignedClasses()->pluck('id');

        $classes = SchoolClass::whereIn('id', $classIds)
            ->with('level:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $subjects = Subject::with(['classes' => fn ($query) => $query->whereIn('classes.id', $classIds)])
            ->where('is_active', true)
            ->whereHas('classes', fn ($query) => $query->whereIn('classes.id', $classIds))
            ->orderBy('name')
            ->get();
        $scoreComponents = ScoreComponent::where('is_active', true)->orderBy('sort_order')->get();
        $currentSession = $school->currentSession();
        $currentTerm = $school->currentTerm();

        $levelId = $this->creditService->resolveTeacherLevelId($teacher);
        $availableCredits = $this->creditService->getAvailableCredits($school, $levelId);

        return view('teacher.exams.create', array_merge(
            compact('classes', 'subjects', 'scoreComponents', 'currentSession', 'currentTerm', 'availableCredits'),
            $this->viewData(),
        ));
    }

    /**
     * AI-generate exam questions from a prompt or document.
     */
    public function generate(Request $request): RedirectResponse|View
    {
        $teacher = auth()->user();
        $school = app('current.school');
        $category = $this->resolveCategory();
        $routePrefix = $this->routePrefix();
        $classIds = $teacher->assignedClasses()->pluck('id')->toArray();

        $validated = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'score_component_id' => ['nullable', 'exists:score_components,id'],
            'source_type' => ['required', 'in:file,link'],
            'source_file' => ['required_if:source_type,file', 'nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'document_url' => ['required_if:source_type,link', 'nullable', 'url', 'max:2000'],
            'question_count' => ['required', 'integer', 'min:1', 'max:100'],
            'question_types' => ['required', 'array', 'min:1'],
            'question_types.*' => ['in:multiple_choice,true_false,fill_blank,short_answer,theory,matching'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
        ]);

        if (! in_array((int) $validated['class_id'], $classIds, true)) {
            abort(403, 'You can only create items for your assigned classes.');
        }
        $this->ensureSubjectAssignedToClass((int) $validated['class_id'], (int) $validated['subject_id']);

        $levelId = $this->creditService->resolveTeacherLevelId($teacher, (int) $validated['class_id']);
        if (! $this->creditService->hasCredits($school, $levelId)) {
            return redirect()->route("{$routePrefix}.create")
                ->with('error', __('No AI credits remaining. You can create manually or ask your admin to purchase more credits.'));
        }

        $class = SchoolClass::with('level:id,name')->findOrFail($validated['class_id']);
        $subject = Subject::findOrFail($validated['subject_id']);
        $classLevel = $class->level->name.' - '.$class->name;

        $sourceDocumentUrl = null;
        $sourceDocumentPublicId = null;

        if ($validated['source_type'] === 'file') {
            $uploaded = $this->fileUploader->uploadRawDocument($request->file('source_file'), $school->id);
            $sourceDocumentUrl = $uploaded['url'];
            $sourceDocumentPublicId = $uploaded['public_id'];

            $mimeType = $request->file('source_file')->getMimeType() ?? 'application/pdf';
            $content = $this->examGenerator->extractTextFromDocument($sourceDocumentUrl, $mimeType);
        } else {
            $sourceDocumentUrl = $validated['document_url'];
            $content = $this->examGenerator->extractTextFromUrl($validated['document_url']);
        }

        if (empty($content)) {
            return redirect()->route("{$routePrefix}.create")
                ->with('error', $this->examGenerator->lastError ?? __('Could not extract text from the source. Please try a different file or link.'));
        }

        $questions = $this->examGenerator->generateFromContent(
            content: $content,
            classLevel: $classLevel,
            subjectName: $subject->name,
            questionCount: (int) $validated['question_count'],
            questionTypes: $validated['question_types'],
            difficulty: $validated['difficulty'],
            category: $category,
        );

        if (empty($questions)) {
            return redirect()->route("{$routePrefix}.create")
                ->with('error', $this->examGenerator->lastError ?? __('AI could not generate questions. Please try again or create manually.'));
        }

        $this->creditService->deductCredit($school, $teacher, $category, levelId: $levelId);

        $classes = SchoolClass::whereIn('id', $classIds)
            ->with('level:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $subjects = Subject::with(['classes' => fn ($query) => $query->whereIn('classes.id', $classIds)])
            ->where('is_active', true)
            ->whereHas('classes', fn ($query) => $query->whereIn('classes.id', $classIds))
            ->orderBy('name')
            ->get();
        $scoreComponents = ScoreComponent::where('is_active', true)->orderBy('sort_order')->get();
        $currentSession = $school->currentSession();
        $currentTerm = $school->currentTerm();

        return view('teacher.exams.review', array_merge([
            'questions' => $questions,
            'classes' => $classes,
            'subjects' => $subjects,
            'scoreComponents' => $scoreComponents,
            'selectedClassId' => (int) $validated['class_id'],
            'selectedSubjectId' => (int) $validated['subject_id'],
            'selectedScoreComponentId' => $validated['score_component_id'] ? (int) $validated['score_component_id'] : null,
            'currentSession' => $currentSession,
            'currentTerm' => $currentTerm,
            'sourceType' => 'document',
            'sourcePrompt' => null,
            'sourceDocumentUrl' => $sourceDocumentUrl,
            'sourceDocumentPublicId' => $sourceDocumentPublicId,
            'difficulty' => $validated['difficulty'],
        ], $this->viewData()));
    }

    /**
     * Store exam with questions — teacher-created = pending approval.
     */
    public function store(Request $request): RedirectResponse|View
    {
        $teacher = auth()->user();
        $classIds = $teacher->assignedClasses()->pluck('id')->toArray();
        $school = app('current.school');
        $category = $this->resolveCategory();
        $routePrefix = $this->routePrefix();

        if ($request->filled('class_id') && ! in_array($request->integer('class_id'), $classIds, true)) {
            abort(403, __('You can only create CBTs for your assigned classes.'));
        }

        $validator = Validator::make($request->all(), [
            'category' => ['nullable', 'in:exam,assessment,assignment'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'score_component_id' => ['nullable', 'exists:score_components,id'],
            'source_type' => ['required', 'in:document,manual'],
            'source_prompt' => ['nullable', 'string'],
            'source_document_url' => ['nullable', 'url'],
            'source_document_public_id' => ['nullable', 'string'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:300'],
            'max_score' => ['required', 'integer', 'min:1', 'max:200'],
            'passing_score' => ['required', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'show_correct_answers' => ['boolean'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],
            'prevent_tab_switch' => ['boolean'],
            'prevent_copy_paste' => ['boolean'],
            'randomize_per_student' => ['boolean'],
            'max_tab_switches' => ['nullable', 'integer', 'min:1', 'max:10'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.type' => ['required', 'in:multiple_choice,true_false,fill_blank,short_answer,theory,matching'],
            'questions.*.question_text' => ['required', 'string', 'max:5000'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.correct_answer' => ['nullable', 'string', 'max:2000'],
            'questions.*.marking_guide' => ['nullable', 'string', 'max:3000'],
            'questions.*.sample_answer' => ['nullable', 'string', 'max:5000'],
            'questions.*.min_words' => ['nullable', 'integer', 'min:1'],
            'questions.*.max_words' => ['nullable', 'integer', 'min:1'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1000'],
            'questions.*.points' => ['required', 'integer', 'min:1', 'max:100'],
            'questions.*.section_label' => ['nullable', 'string', 'max:100'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($request->filled(['class_id', 'subject_id'])
                && ! ClassSubject::where('class_id', $request->integer('class_id'))
                    ->where('subject_id', $request->integer('subject_id'))
                    ->exists()) {
                $validator->errors()->add('subject_id', __('Select a subject assigned to the selected class.'));
            }
        });

        if ($validator->fails()) {
            $classes = SchoolClass::with('level:id,name')
                ->whereIn('id', $classIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
            $subjects = Subject::with(['classes' => fn ($query) => $query->whereIn('classes.id', $classIds)])
                ->where('is_active', true)
                ->whereHas('classes', fn ($query) => $query->whereIn('classes.id', $classIds))
                ->orderBy('name')
                ->get();
            $scoreComponents = ScoreComponent::where('is_active', true)->orderBy('sort_order')->get();

            $sourceType = $request->input('source_type', 'manual');
            $viewName = $sourceType === 'document' ? 'teacher.exams.review' : 'teacher.exams.create';
            $levelId = $this->creditService->resolveTeacherLevelId(
                $teacher,
                $request->filled('class_id') ? (int) $request->input('class_id') : null,
            );

            return view($viewName, array_merge([
                'questions' => $request->input('questions', []),
                'classes' => $classes,
                'subjects' => $subjects,
                'scoreComponents' => $scoreComponents,
                'currentSession' => $school->currentSession(),
                'currentTerm' => $school->currentTerm(),
                'selectedClassId' => (int) $request->input('class_id'),
                'selectedSubjectId' => (int) $request->input('subject_id'),
                'selectedScoreComponentId' => $request->input('score_component_id') ? (int) $request->input('score_component_id') : null,
                'sourceType' => $sourceType,
                'sourcePrompt' => $request->input('source_prompt'),
                'sourceDocumentUrl' => $request->input('source_document_url'),
                'sourceDocumentPublicId' => $request->input('source_document_public_id'),
                'difficulty' => $request->input('difficulty', 'medium'),
                'availableCredits' => $this->creditService->getAvailableCredits($school, $levelId),
                'category' => $category,
                'storeRoute' => route($routePrefix.'.store', ['category' => $category]),
                'indexRoute' => route($routePrefix.'.index', $category ? ['category' => $category] : []),
                'routePrefix' => $routePrefix,
            ], $this->viewData()))->withErrors($validator);
        }

        $validated = $validator->validated();

        $currentSession = $school->currentSession();
        $currentTerm = $school->currentTerm();

        if (! $currentSession || ! $currentTerm) {
            return redirect()->route("{$routePrefix}.index")
                ->with('error', __('No active academic session or term. Please contact your admin.'));
        }

        $totalPoints = collect($validated['questions'])->sum('points');

        DB::transaction(function () use ($validated, $teacher, $currentSession, $currentTerm, $totalPoints, $category) {
            $exam = Exam::create([
                'class_id' => $validated['class_id'],
                'subject_id' => $validated['subject_id'],
                'score_component_id' => $validated['score_component_id'] ?? null,
                'session_id' => $currentSession->id,
                'term_id' => $currentTerm->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'category' => $category,
                'source_type' => $validated['source_type'],
                'source_prompt' => $validated['source_prompt'] ?? null,
                'source_document_url' => $validated['source_document_url'] ?? null,
                'source_document_public_id' => $validated['source_document_public_id'] ?? null,
                'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
                'max_score' => $validated['max_score'],
                'passing_score' => $validated['passing_score'],
                'max_attempts' => $validated['max_attempts'],
                'shuffle_questions' => $validated['shuffle_questions'] ?? false,
                'shuffle_options' => $validated['shuffle_options'] ?? false,
                'show_correct_answers' => $validated['show_correct_answers'] ?? false,
                'difficulty' => $validated['difficulty'] ?? 'medium',
                'available_from' => $validated['available_from'] ?? null,
                'available_until' => $validated['available_until'] ?? null,
                'prevent_tab_switch' => $validated['prevent_tab_switch'] ?? true,
                'prevent_copy_paste' => $validated['prevent_copy_paste'] ?? true,
                'randomize_per_student' => $validated['randomize_per_student'] ?? false,
                'max_tab_switches' => $validated['max_tab_switches'] ?? 3,
                'instructions' => $validated['instructions'] ?? null,
                'created_by' => $teacher->id,
                'status' => 'pending',
                'total_questions' => count($validated['questions']),
                'total_points' => $totalPoints,
            ]);

            foreach ($validated['questions'] as $index => $qData) {
                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'school_id' => $teacher->school_id,
                    'type' => $qData['type'],
                    'question_text' => $qData['question_text'],
                    'options' => $qData['options'] ?? null,
                    'correct_answer' => $qData['correct_answer'] ?? null,
                    'marking_guide' => $qData['marking_guide'] ?? null,
                    'sample_answer' => $qData['sample_answer'] ?? null,
                    'min_words' => $qData['min_words'] ?? null,
                    'max_words' => $qData['max_words'] ?? null,
                    'explanation' => $qData['explanation'] ?? null,
                    'points' => $qData['points'],
                    'sort_order' => $index,
                    'section_label' => $qData['section_label'] ?? null,
                ]);
            }

            $action = TeacherAction::create([
                'school_id' => $teacher->school_id,
                'teacher_id' => $teacher->id,
                'action_type' => 'create_exam',
                'entity_type' => 'exam',
                'entity_id' => $exam->id,
                'status' => 'pending',
            ]);

            $this->notifyAdminsOfPendingSubmission($action, $teacher);
        });

        return redirect()->route("{$routePrefix}.index")
            ->with('success', __(':type created and submitted for approval.', ['type' => $this->categoryLabel()]));
    }

    public function show(Exam $exam): View
    {
        $teacher = auth()->user();
        $this->authorizeTeacherAccess($exam, $teacher);

        $exam->load([
            'class:id,name',
            'subject:id,name',
            'scoreComponent:id,name,short_name',
            'questions',
            'session:id,name',
            'term:id,name',
            'latestTeacherAction',
        ]);

        return view('teacher.exams.show', array_merge(
            compact('exam'),
            $this->viewData(),
        ));
    }

    public function edit(Exam $exam): View
    {
        $teacher = auth()->user();
        if ($exam->created_by !== $teacher->id) {
            abort(403);
        }

        if (! in_array($exam->status, ['draft', 'pending', 'rejected'])) {
            abort(403, 'Approved items cannot be edited.');
        }

        $exam->load('questions');
        $classIds = $teacher->assignedClasses()->pluck('id');

        $classes = SchoolClass::whereIn('id', $classIds)
            ->with('level:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $subjects = Subject::with(['classes' => fn ($query) => $query->whereIn('classes.id', $classIds)])
            ->where('is_active', true)
            ->whereHas('classes', fn ($query) => $query->whereIn('classes.id', $classIds))
            ->orderBy('name')
            ->get();
        $scoreComponents = ScoreComponent::where('is_active', true)->orderBy('sort_order')->get();

        return view('teacher.exams.edit', array_merge(
            compact('exam', 'classes', 'subjects', 'scoreComponents'),
            $this->viewData(),
        ));
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $teacher = auth()->user();
        $routePrefix = $this->routePrefix();
        if ($exam->created_by !== $teacher->id) {
            abort(403);
        }

        if (! in_array($exam->status, ['draft', 'pending', 'rejected'])) {
            abort(403, 'Approved items cannot be edited.');
        }

        // Block editing if students have already taken the exam (answers reference question IDs)
        if ($exam->attempts()->exists()) {
            return redirect()->back()->with('error', __('This :type cannot be edited because students have already taken it.', ['type' => $this->categoryLabel()]));
        }

        $classIds = $teacher->assignedClasses()->pluck('id')->toArray();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'score_component_id' => ['nullable', 'exists:score_components,id'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:300'],
            'max_score' => ['required', 'integer', 'min:1', 'max:200'],
            'passing_score' => ['required', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'show_correct_answers' => ['boolean'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],
            'prevent_tab_switch' => ['boolean'],
            'prevent_copy_paste' => ['boolean'],
            'randomize_per_student' => ['boolean'],
            'max_tab_switches' => ['nullable', 'integer', 'min:1', 'max:10'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.type' => ['required', 'in:multiple_choice,true_false,fill_blank,short_answer,theory,matching'],
            'questions.*.question_text' => ['required', 'string', 'max:5000'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.correct_answer' => ['nullable', 'string', 'max:2000'],
            'questions.*.marking_guide' => ['nullable', 'string', 'max:3000'],
            'questions.*.sample_answer' => ['nullable', 'string', 'max:5000'],
            'questions.*.min_words' => ['nullable', 'integer', 'min:1'],
            'questions.*.max_words' => ['nullable', 'integer', 'min:1'],
            'questions.*.explanation' => ['nullable', 'string', 'max:1000'],
            'questions.*.points' => ['required', 'integer', 'min:1', 'max:100'],
            'questions.*.section_label' => ['nullable', 'string', 'max:100'],
        ]);

        if (! in_array((int) $validated['class_id'], $classIds, true)) {
            abort(403, 'You can only create items for your assigned classes.');
        }
        $this->ensureSubjectAssignedToClass((int) $validated['class_id'], (int) $validated['subject_id']);

        $totalPoints = collect($validated['questions'])->sum('points');

        DB::transaction(function () use ($validated, $exam, $teacher, $totalPoints) {
            $exam->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'class_id' => $validated['class_id'],
                'subject_id' => $validated['subject_id'],
                'score_component_id' => $validated['score_component_id'] ?? null,
                'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
                'max_score' => $validated['max_score'],
                'passing_score' => $validated['passing_score'],
                'max_attempts' => $validated['max_attempts'],
                'shuffle_questions' => $validated['shuffle_questions'] ?? false,
                'shuffle_options' => $validated['shuffle_options'] ?? false,
                'show_correct_answers' => $validated['show_correct_answers'] ?? false,
                'difficulty' => $validated['difficulty'] ?? 'medium',
                'available_from' => $validated['available_from'] ?? null,
                'available_until' => $validated['available_until'] ?? null,
                'prevent_tab_switch' => $validated['prevent_tab_switch'] ?? true,
                'prevent_copy_paste' => $validated['prevent_copy_paste'] ?? true,
                'randomize_per_student' => $validated['randomize_per_student'] ?? false,
                'max_tab_switches' => $validated['max_tab_switches'] ?? 3,
                'instructions' => $validated['instructions'] ?? null,
                'total_questions' => count($validated['questions']),
                'total_points' => $totalPoints,
                'status' => 'pending',
            ]);

            $exam->questions()->delete();

            foreach ($validated['questions'] as $index => $qData) {
                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'school_id' => $exam->school_id,
                    'type' => $qData['type'],
                    'question_text' => $qData['question_text'],
                    'options' => $qData['options'] ?? null,
                    'correct_answer' => $qData['correct_answer'] ?? null,
                    'marking_guide' => $qData['marking_guide'] ?? null,
                    'sample_answer' => $qData['sample_answer'] ?? null,
                    'min_words' => $qData['min_words'] ?? null,
                    'max_words' => $qData['max_words'] ?? null,
                    'explanation' => $qData['explanation'] ?? null,
                    'points' => $qData['points'],
                    'sort_order' => $index,
                    'section_label' => $qData['section_label'] ?? null,
                ]);
            }

            // Update or create teacher action
            $existingAction = TeacherAction::where('entity_type', 'exam')
                ->where('entity_id', $exam->id)
                ->where('teacher_id', $teacher->id)
                ->latest()
                ->first();

            if ($existingAction) {
                $existingAction->update([
                    'status' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                ]);
            } else {
                $action = TeacherAction::create([
                    'school_id' => $teacher->school_id,
                    'teacher_id' => $teacher->id,
                    'action_type' => 'create_exam',
                    'entity_type' => 'exam',
                    'entity_id' => $exam->id,
                    'status' => 'pending',
                ]);
                $this->notifyAdminsOfPendingSubmission($action, $teacher);
            }
        });

        return redirect()->route("{$routePrefix}.index")
            ->with('success', __(':type updated and resubmitted for approval.', ['type' => $this->categoryLabel()]));
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $teacher = auth()->user();
        $routePrefix = $this->routePrefix();
        if ($exam->created_by !== $teacher->id) {
            abort(403);
        }

        if ($exam->is_published) {
            return redirect()->back()->with('error', __('Published items cannot be deleted.'));
        }

        // Block deletion if students have already taken the exam
        if ($exam->attempts()->exists()) {
            return redirect()->back()->with('error', __('This :type cannot be deleted because students have already taken it.', ['type' => $this->categoryLabel()]));
        }

        $exam->delete();

        return redirect()->route("{$routePrefix}.index")
            ->with('undo', app(RecentlyDeletedService::class)->deleted($exam, 'teacher'));
    }

    /**
     * Inline subject creation: create a subject and optionally assign to a class.
     */
    public function storeSubject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['nullable', 'string', 'max:20'],
            'class_id' => ['required', 'exists:classes,id'],
            'assign_existing' => ['nullable', 'boolean'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
        ]);

        $school = app('current.school');
        $slug = Str::slug($validated['name']);

        $teacher = auth()->user();
        $class = SchoolClass::where('school_id', $school->id)->findOrFail($validated['class_id']);

        if (! $teacher->assignedClasses()->where('is_active', true)->whereKey($class->id)->exists()) {
            abort(403, __('You can only add subjects to your assigned classes.'));
        }

        $subject = ! empty($validated['assign_existing']) && ! empty($validated['subject_id'])
            ? Subject::findOrFail($validated['subject_id'])
            : Subject::where('slug', $slug)->first();

        if ($subject && ! $subject->is_active) {
            return response()->json([
                'status' => 'inactive',
                'message' => __('":name" already exists but is inactive. Ask your school admin to reactivate it.', ['name' => $subject->name]),
            ], 409);
        }

        if ($subject && empty($validated['assign_existing'])) {
            $isAssigned = ClassSubject::where('class_id', $class->id)->where('subject_id', $subject->id)->exists();

            return response()->json([
                'status' => $isAssigned ? 'already_assigned' : 'existing_unassigned',
                'message' => $isAssigned
                    ? __('":name" already exists and is assigned to this class. Use the existing subject.', ['name' => $subject->name])
                    : __('":name" already exists in the school subject pool. Assign it to this class instead.', ['name' => $subject->name]),
                'subject' => ['id' => $subject->id, 'name' => $subject->name],
            ], 409);
        }

        if (! $subject) {
            $subject = Subject::create([
                'school_id' => $school->id,
                'created_by' => $teacher->id,
                'name' => $validated['name'],
                'slug' => $slug,
                'short_name' => $validated['short_name'] ?? null,
                'is_active' => true,
            ]);
        }

        ClassSubject::firstOrCreate(
            ['class_id' => $class->id, 'subject_id' => $subject->id],
            ['school_id' => $school->id, 'teacher_id' => $teacher->id],
        );

        return response()->json([
            'status' => ! empty($validated['assign_existing']) ? 'assigned_existing' : 'created',
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
            ],
        ]);
    }

    private function ensureSubjectAssignedToClass(int $classId, int $subjectId): void
    {
        if (! ClassSubject::where('class_id', $classId)->where('subject_id', $subjectId)->exists()) {
            throw ValidationException::withMessages([
                'subject_id' => __('Select a subject assigned to the selected class.'),
            ]);
        }
    }

    // ── Results & Grading ──

    /**
     * Show all student attempts for an exam (results list).
     */
    public function results(Exam $exam): View
    {
        $teacher = auth()->user();
        $this->authorizeTeacherAccess($exam, $teacher);

        $exam->load(['class:id,name,level_id', 'subject:id,name', 'session:id,name', 'term:id,name']);

        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->whereIn('status', ['submitted', 'timed_out', 'grading', 'graded'])
            ->with('student:id,name,username')
            ->orderBy('submitted_at', 'desc')
            ->get();

        // Count theory questions to determine if grading UI is needed
        $theoryQuestionCount = $exam->questions()->whereIn('type', ['short_answer', 'theory'])->count();

        // Summary stats
        $gradedAttempts = $attempts->whereNotNull('percentage');
        $stats = [
            'total_attempts' => $attempts->count(),
            'pending_grading' => $attempts->where('status', 'grading')->count(),
            'graded' => $gradedAttempts->count(),
            'average_score' => $gradedAttempts->count() > 0 ? round($gradedAttempts->avg('percentage'), 1) : null,
            'highest_score' => $gradedAttempts->max('percentage'),
            'lowest_score' => $gradedAttempts->min('percentage'),
            'pass_count' => $gradedAttempts->where('passed', true)->count(),
            'fail_count' => $gradedAttempts->where('passed', false)->count(),
        ];

        // Build grade map for each attempt
        $levelId = $exam->class?->level_id;
        $grades = $attempts->mapWithKeys(fn (ExamAttempt $a) => [
            $a->id => $a->percentage !== null
                ? $this->scoreService->getGrade($teacher->school_id, (float) $a->percentage, $levelId)
                : null,
        ]);

        return view('teacher.exams.results', array_merge(
            compact('exam', 'attempts', 'theoryQuestionCount', 'stats', 'grades'),
            $this->viewData(),
        ));
    }

    /**
     * Grade a single student's theory answers.
     */
    public function gradeStudent(Exam $exam, ExamAttempt $attempt): View
    {
        $teacher = auth()->user();
        $this->authorizeTeacherAccess($exam, $teacher);

        if ($attempt->exam_id !== $exam->id) {
            abort(404);
        }

        $exam->load(['class:id,name', 'subject:id,name']);

        // Load answers with their questions, only theory questions needing grading
        $theoryQuestions = $exam->questions()
            ->whereIn('type', ['short_answer', 'theory'])
            ->orderBy('sort_order')
            ->get();

        $answers = $attempt->answers()
            ->whereIn('question_id', $theoryQuestions->pluck('id'))
            ->get()
            ->keyBy('question_id');

        // Also load objective score for context
        $objectiveScore = $attempt->answers()
            ->whereNotIn('question_id', $theoryQuestions->pluck('id'))
            ->sum('points_earned');

        $objectiveTotal = $exam->questions()
            ->whereNotIn('type', ['short_answer', 'theory'])
            ->sum('points');

        return view('teacher.exams.grade-student', array_merge(
            compact('exam', 'attempt', 'theoryQuestions', 'answers', 'objectiveScore', 'objectiveTotal'),
            $this->viewData(),
        ));
    }

    /**
     * Save grades for a single student's theory answers.
     */
    public function saveGrade(Request $request, Exam $exam, ExamAttempt $attempt): RedirectResponse
    {
        $teacher = auth()->user();
        $routePrefix = $this->routePrefix();

        $this->authorizeTeacherAccess($exam, $teacher);

        if ($attempt->exam_id !== $exam->id) {
            abort(404);
        }

        $validated = $request->validate([
            'grades' => ['required', 'array'],
            'grades.*.answer_id' => ['required', 'integer', 'exists:exam_answers,id'],
            'grades.*.points' => ['required', 'integer', 'min:0'],
            'grades.*.comment' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated, $attempt, $teacher) {
            foreach ($validated['grades'] as $grade) {
                $answer = ExamAnswer::where('id', $grade['answer_id'])
                    ->where('attempt_id', $attempt->id)
                    ->firstOrFail();

                $maxPoints = $answer->question->points;
                $points = min((int) $grade['points'], $maxPoints);

                $answer->update([
                    'points_earned' => $points,
                    'is_correct' => $points > 0,
                    'teacher_comment' => $grade['comment'] ?? null,
                    'graded_by' => $teacher->id,
                    'graded_at' => now(),
                ]);
            }

            $this->gradingService->recalculateAttemptScore($attempt);
        });

        return redirect()->route("{$routePrefix}.results", $exam)
            ->with('success', __('Grades saved for :student.', ['student' => $attempt->student->name]));
    }

    /**
     * Bulk grade: view all students' answers to one question at a time.
     */
    public function bulkGrade(Request $request, Exam $exam): View
    {
        $teacher = auth()->user();
        $this->authorizeTeacherAccess($exam, $teacher);

        $exam->load(['class:id,name', 'subject:id,name']);

        $theoryQuestions = $exam->questions()
            ->whereIn('type', ['short_answer', 'theory'])
            ->orderBy('sort_order')
            ->get();

        if ($theoryQuestions->isEmpty()) {
            abort(404, 'No theory questions to grade.');
        }

        // Current question (from query param or first)
        $currentQuestionId = $request->input('question', $theoryQuestions->first()->id);
        $currentQuestion = $theoryQuestions->firstWhere('id', (int) $currentQuestionId);

        if (! $currentQuestion) {
            $currentQuestion = $theoryQuestions->first();
        }

        // Get all attempts that need grading or are submitted
        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->whereIn('status', ['submitted', 'timed_out', 'grading'])
            ->with('student:id,name,username')
            ->orderBy('submitted_at')
            ->get();

        // Get all answers for this question across all attempts
        $answersForQuestion = ExamAnswer::where('question_id', $currentQuestion->id)
            ->whereIn('attempt_id', $attempts->pluck('id'))
            ->get()
            ->keyBy('attempt_id');

        return view('teacher.exams.bulk-grade', array_merge(
            compact('exam', 'theoryQuestions', 'currentQuestion', 'attempts', 'answersForQuestion'),
            $this->viewData(),
        ));
    }

    /**
     * Save bulk grades for one question across all students.
     */
    public function saveBulkGrade(Request $request, Exam $exam): RedirectResponse
    {
        $teacher = auth()->user();
        $routePrefix = $this->routePrefix();

        $this->authorizeTeacherAccess($exam, $teacher);

        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'exists:exam_questions,id'],
            'grades' => ['required', 'array'],
            'grades.*.answer_id' => ['required', 'integer', 'exists:exam_answers,id'],
            'grades.*.points' => ['required', 'integer', 'min:0'],
            'grades.*.comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $question = ExamQuestion::where('id', $validated['question_id'])
            ->where('exam_id', $exam->id)
            ->firstOrFail();

        $attemptIds = [];

        DB::transaction(function () use ($validated, $question, $teacher, &$attemptIds) {
            foreach ($validated['grades'] as $grade) {
                $answer = ExamAnswer::where('id', $grade['answer_id'])
                    ->where('question_id', $question->id)
                    ->firstOrFail();

                $points = min((int) $grade['points'], $question->points);

                $answer->update([
                    'points_earned' => $points,
                    'is_correct' => $points > 0,
                    'teacher_comment' => $grade['comment'] ?? null,
                    'graded_by' => $teacher->id,
                    'graded_at' => now(),
                ]);

                $attemptIds[] = $answer->attempt_id;
            }
        });

        // Recalculate scores for all affected attempts
        $uniqueAttemptIds = array_unique($attemptIds);
        foreach ($uniqueAttemptIds as $attemptId) {
            $attempt = ExamAttempt::find($attemptId);
            if ($attempt) {
                $this->gradingService->recalculateAttemptScore($attempt);
            }
        }

        // Navigate to next ungraded question or back to results
        $nextQuestion = $exam->questions()
            ->whereIn('type', ['short_answer', 'theory'])
            ->where('id', '>', $validated['question_id'])
            ->orderBy('sort_order')
            ->first();

        if ($nextQuestion) {
            return redirect()->route("{$routePrefix}.bulk-grade", ['exam' => $exam, 'question' => $nextQuestion->id])
                ->with('success', __('Grades saved. Now grading next question.'));
        }

        return redirect()->route("{$routePrefix}.results", $exam)
            ->with('success', __('All theory questions graded successfully.'));
    }

    // ── Preview ──

    public function preview(Exam $exam): View
    {
        $teacher = auth()->user();
        $this->authorizeTeacherAccess($exam, $teacher);

        $exam->load(['questions', 'class:id,name', 'subject:id,name']);

        $questions = $exam->shuffle_questions
            ? $exam->questions->shuffle(crc32((string) $exam->id))
            : $exam->questions->sortBy('sort_order');

        $category = $this->resolveCategory();

        return view('shared.exams.preview', array_merge(
            compact('exam', 'questions', 'category'),
            ['label' => $this->categoryLabel($category), 'routePrefix' => $this->routePrefix()],
        ));
    }

    // ── Live Monitor & Analytics ──

    public function monitor(Exam $exam): View
    {
        $teacher = auth()->user();
        $this->authorizeTeacherAccess($exam, $teacher);

        if (! $exam->is_published) {
            abort(403, 'Monitoring is only available for published items.');
        }

        $exam->load(['class:id,name', 'subject:id,name', 'session:id,name', 'term:id,name']);

        return view('teacher.exams.monitor', array_merge(compact('exam'), $this->viewData()));
    }

    public function analytics(Exam $exam): View
    {
        $teacher = auth()->user();
        $this->authorizeTeacherAccess($exam, $teacher);

        $exam->load(['class:id,name', 'subject:id,name', 'session:id,name', 'term:id,name']);

        return view('teacher.exams.analytics', array_merge(compact('exam'), $this->viewData()));
    }

    /**
     * Export CBT results as CSV for a single exam.
     */
    public function exportResultsCsv(Exam $exam): StreamedResponse
    {
        $teacher = auth()->user();
        $this->authorizeTeacherAccess($exam, $teacher);

        $exam->load(['class:id,name,level_id', 'subject:id,name']);

        $schoolId = $teacher->school_id;
        $levelId = $exam->class?->level_id;
        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->whereIn('status', ['submitted', 'timed_out', 'grading', 'graded'])
            ->with('student:id,name,username')
            ->orderBy('submitted_at')
            ->get();

        $headers = ['Student Name', 'Username', 'Score', 'Total Points', 'Percentage', 'Grade', 'Passed', 'Status', 'Submitted At'];
        $rows = [];

        foreach ($attempts as $attempt) {
            $grade = $attempt->percentage !== null
                ? $this->scoreService->getGrade($schoolId, (float) $attempt->percentage, $levelId)
                : null;

            $rows[] = [
                $attempt->student?->name ?? '—',
                $attempt->student?->username ?? '—',
                $attempt->score ?? '',
                $attempt->total_points ?? '',
                $attempt->percentage !== null ? number_format((float) $attempt->percentage, 1) : '',
                $grade ? $grade['grade'].' ('.$grade['label'].')' : '',
                $attempt->passed ? 'Yes' : ($attempt->passed === false ? 'No' : ''),
                ucfirst($attempt->status),
                $attempt->submitted_at?->format('Y-m-d H:i') ?? '',
            ];
        }

        $categoryLabel = $this->categoryLabel();
        $className = str_replace(' ', '_', $exam->class?->name ?? 'Class');
        $filename = "{$categoryLabel}_Results_{$exam->title}_{$className}.csv";

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Authorize teacher access: own exams (any status) or assigned-class exams (approved only).
     */
    private function authorizeTeacherAccess(Exam $exam, $teacher): void
    {
        if ($exam->created_by === $teacher->id) {
            return;
        }

        $classIds = $teacher->assignedClasses()->pluck('id');
        if ($classIds->contains($exam->class_id) && $exam->status === 'approved') {
            return;
        }

        abort(403);
    }
}
