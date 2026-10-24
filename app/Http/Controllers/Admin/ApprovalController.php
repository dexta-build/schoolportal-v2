<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Game;
use App\Models\Notice;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\ScoreAmendment;
use App\Models\StudentTermReport;
use App\Models\TeacherAction;
use App\Notifications\SubmissionReviewed;
use App\Services\NotificationService;
use App\Services\ScoreCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $query = TeacherAction::with(['teacher:id,name'])
            ->orderByDesc('created_at');

        $status = $request->input('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('type')) {
            $query->where('entity_type', $request->input('type'));
        }

        $actions = $query->paginate(10)->withQueryString();
        $pendingCount = TeacherAction::where('status', 'pending')->count();

        // Eager-load notice entities for inline preview modals
        $noticeIds = $actions->getCollection()
            ->where('entity_type', 'notice')
            ->pluck('entity_id')
            ->unique()
            ->toArray();

        $notices = $noticeIds
            ? Notice::with('creator:id,name')->whereIn('id', $noticeIds)->get()->keyBy('id')
            : collect();

        // Eager-load result entities for inline preview modals
        $resultIds = $actions->getCollection()
            ->where('entity_type', 'result')
            ->pluck('entity_id')
            ->unique()
            ->toArray();

        $results = $resultIds
            ? Result::with(['student:id,name,username', 'class:id,name', 'session:id,name', 'term:id,name'])
                ->whereIn('id', $resultIds)->get()->keyBy('id')
            : collect();

        // Eager-load assignment entities for inline preview modals
        $assignmentIds = $actions->getCollection()
            ->where('entity_type', 'assignment')
            ->pluck('entity_id')
            ->unique()
            ->toArray();

        $assignments = $assignmentIds
            ? Assignment::with(['class:id,name', 'session:id,name', 'term:id,name'])
                ->whereIn('id', $assignmentIds)->get()->keyBy('id')
            : collect();

        // Eager-load exam entities for inline preview modals
        $examIds = $actions->getCollection()
            ->where('entity_type', 'exam')
            ->pluck('entity_id')
            ->unique()
            ->toArray();

        $exams = $examIds
            ? Exam::with(['class:id,name', 'subject:id,name', 'session:id,name', 'term:id,name'])
                ->whereIn('id', $examIds)->get()->keyBy('id')
            : collect();

        // Eager-load report card entities for inline preview
        $reportCardIds = $actions->getCollection()
            ->where('entity_type', 'report_card')
            ->pluck('entity_id')
            ->unique()
            ->toArray();

        $reportCards = $reportCardIds
            ? StudentTermReport::with(['student:id,name,username', 'class:id,name', 'session:id,name', 'term:id,name'])
                ->whereIn('id', $reportCardIds)->get()->keyBy('id')
            : collect();

        // Eager-load score correction requests for their detail line
        $scoreAmendmentIds = $actions->getCollection()
            ->where('entity_type', 'score_amendment')
            ->pluck('entity_id')
            ->unique()
            ->toArray();

        $scoreAmendments = $scoreAmendmentIds
            ? ScoreAmendment::with(['student:id,name', 'subject:id,name', 'scoreComponent:id,name,max_score', 'class:id,name', 'term:id,name'])
                ->whereIn('id', $scoreAmendmentIds)->get()->keyBy('id')
            : collect();

        return view('admin.approvals.index', compact('actions', 'pendingCount', 'status', 'notices', 'results', 'assignments', 'exams', 'reportCards', 'scoreAmendments'));
    }

    public function approve(TeacherAction $action): RedirectResponse
    {
        $action->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Update the related entity status
        $this->updateEntityStatus($action, 'approved');

        // Notify the teacher and downstream parties — wrapped so any notification
        // failure cannot mask the successfully committed approval.
        $teacher = $action->teacher;
        try {
            if ($teacher?->email) {
                $teacher->notify(new SubmissionReviewed($action->entity_type, 'approved'));
            }

            $notificationService = app(NotificationService::class);
            if ($teacher) {
                $entityTitle = $notificationService->resolveEntityTitle($action->entity_type, $action->entity_id);
                $notificationService->notifySubmissionApproved($teacher, $action->entity_type, $entityTitle);
            }

            if (in_array($action->entity_type, ['quiz', 'game', 'notice', 'exam'], true)) {
                match ($action->entity_type) {
                    'quiz' => $notificationService->notifyQuizPublished(Quiz::find($action->entity_id)),
                    'game' => $notificationService->notifyGamePublished(Game::find($action->entity_id)),
                    'notice' => $notificationService->notifyNoticePublished(Notice::find($action->entity_id)),
                    'exam' => $notificationService->notifyExamPublished(Exam::find($action->entity_id)),
                };
            }
            if ($action->entity_type === 'result') {
                $result = Result::find($action->entity_id);
                if ($result) {
                    $notificationService->notifyResultUploaded($result);
                }
            }
            if ($action->entity_type === 'assignment') {
                $assignment = Assignment::find($action->entity_id);
                if ($assignment) {
                    $notificationService->notifyAssignmentUploaded($assignment);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Approval notification failed — approval was committed successfully', [
                'action_id' => $action->id,
                'entity_type' => $action->entity_type,
                'entity_id' => $action->entity_id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.approvals.index')
            ->with('success', __(':type approved.', ['type' => ucfirst(str_replace('_', ' ', $action->entity_type))]));
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action_ids' => ['required', 'array', 'min:1', 'max:50'],
            'action_ids.*' => ['required', 'integer', 'exists:teacher_actions,id'],
        ]);

        $actions = TeacherAction::with('teacher')
            ->whereIn('id', $validated['action_ids'])
            ->where('status', 'pending')
            ->get();

        if ($actions->isEmpty()) {
            return redirect()->route('admin.approvals.index')
                ->with('error', __('No pending submissions found for the selected items.'));
        }

        $notificationService = app(NotificationService::class);
        $count = 0;

        foreach ($actions as $action) {
            $action->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $this->updateEntityStatus($action, 'approved');

            $teacher = $action->teacher;
            try {
                if ($teacher?->email) {
                    $teacher->notify(new SubmissionReviewed($action->entity_type, 'approved'));
                }

                if ($teacher) {
                    $entityTitle = $notificationService->resolveEntityTitle($action->entity_type, $action->entity_id);
                    $notificationService->notifySubmissionApproved($teacher, $action->entity_type, $entityTitle);
                }

                if (in_array($action->entity_type, ['quiz', 'game', 'notice', 'exam'], true)) {
                    match ($action->entity_type) {
                        'quiz' => $notificationService->notifyQuizPublished(Quiz::find($action->entity_id)),
                        'game' => $notificationService->notifyGamePublished(Game::find($action->entity_id)),
                        'notice' => $notificationService->notifyNoticePublished(Notice::find($action->entity_id)),
                        'exam' => $notificationService->notifyExamPublished(Exam::find($action->entity_id)),
                    };
                }

                if ($action->entity_type === 'result') {
                    $result = Result::find($action->entity_id);
                    if ($result) {
                        $notificationService->notifyResultUploaded($result);
                    }
                }

                if ($action->entity_type === 'assignment') {
                    $assignment = Assignment::find($action->entity_id);
                    if ($assignment) {
                        $notificationService->notifyAssignmentUploaded($assignment);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Bulk approval notification failed for one action — approval was committed', [
                    'action_id' => $action->id,
                    'entity_type' => $action->entity_type,
                    'entity_id' => $action->entity_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $count++;
        }

        return redirect()->route('admin.approvals.index')
            ->with('success', __(':count submission(s) approved.', ['count' => $count]));
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action_ids' => ['required', 'array', 'min:1', 'max:50'],
            'action_ids.*' => ['required', 'integer', 'exists:teacher_actions,id'],
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $actions = TeacherAction::with('teacher')
            ->whereIn('id', $validated['action_ids'])
            ->where('status', 'pending')
            ->get();

        if ($actions->isEmpty()) {
            return redirect()->route('admin.approvals.index')
                ->with('error', __('No pending submissions found for the selected items.'));
        }

        $notificationService = app(NotificationService::class);
        $count = 0;

        foreach ($actions as $action) {
            $action->update([
                'status' => 'rejected',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            $this->updateEntityStatus($action, 'rejected', $validated['rejection_reason']);

            $teacher = $action->teacher;
            if ($teacher?->email) {
                $teacher->notify(new SubmissionReviewed($action->entity_type, 'rejected', $validated['rejection_reason']));
            }

            if ($teacher) {
                $entityTitle = $notificationService->resolveEntityTitle($action->entity_type, $action->entity_id);
                $notificationService->notifySubmissionRejected($teacher, $action->entity_type, $entityTitle, $validated['rejection_reason']);
            }

            $count++;
        }

        return redirect()->route('admin.approvals.index')
            ->with('success', __(':count submission(s) rejected.', ['count' => $count]));
    }

    public function reject(Request $request, TeacherAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $action->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        // Update the related entity status
        $this->updateEntityStatus($action, 'rejected', $validated['rejection_reason']);

        // Notify the teacher if they have an email
        $teacher = $action->teacher;
        if ($teacher?->email) {
            $teacher->notify(new SubmissionReviewed($action->entity_type, 'rejected', $validated['rejection_reason']));
        }

        // DB notification to teacher
        if ($teacher) {
            $notificationService = app(NotificationService::class);
            $entityTitle = $notificationService->resolveEntityTitle($action->entity_type, $action->entity_id);
            $notificationService->notifySubmissionRejected($teacher, $action->entity_type, $entityTitle, $validated['rejection_reason']);
        }

        return redirect()->route('admin.approvals.index')
            ->with('success', __(':type rejected.', ['type' => ucfirst(str_replace('_', ' ', $action->entity_type))]));
    }

    private function updateEntityStatus(TeacherAction $action, string $status, ?string $reason = null): void
    {
        // Score corrections are applied through the correction service (audit, report staleness)
        if ($action->entity_type === 'score_amendment') {
            $amendment = ScoreAmendment::find($action->entity_id);

            if ($amendment?->isPending()) {
                $status === 'approved'
                    ? app(ScoreCorrectionService::class)->approve($amendment, auth()->user())
                    : app(ScoreCorrectionService::class)->reject($amendment, auth()->user(), $reason ?? __('Rejected'));
            }

            return;
        }

        $model = match ($action->entity_type) {
            'result' => Result::find($action->entity_id),
            'assignment' => Assignment::find($action->entity_id),
            'notice' => Notice::find($action->entity_id),
            'quiz' => Quiz::find($action->entity_id),
            'game' => Game::find($action->entity_id),
            'exam' => Exam::find($action->entity_id),
            'report_card' => StudentTermReport::find($action->entity_id),
            default => null,
        };

        if ($model) {
            // Report cards use different status values
            if ($action->entity_type === 'report_card') {
                $reportStatus = $status === 'approved' ? 'approved' : 'draft';
                $updateData = ['status' => $reportStatus];

                if ($status === 'approved') {
                    $updateData['approved_by'] = auth()->id();
                    $updateData['approved_at'] = now();
                }

                $model->update($updateData);

                return;
            }

            $updateData = ['status' => $status];

            if ($status === 'approved') {
                $updateData['approved_by'] = auth()->id();
                $updateData['approved_at'] = now();

                if (in_array($action->entity_type, ['quiz', 'game', 'notice', 'exam'], true)) {
                    $updateData['is_published'] = true;
                    $updateData['published_at'] = now();
                }
            }

            $model->update($updateData);
        }
    }
}
