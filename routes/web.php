<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AiCreditController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BulkResultController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ExamController as AdminExamController;
use App\Http\Controllers\Admin\GameController as AdminGameController;
use App\Http\Controllers\Admin\GradingController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\InsightsController as AdminInsightsController;
use App\Http\Controllers\Admin\LevelController;
use App\Http\Controllers\Admin\NoticeController;
use App\Http\Controllers\Admin\ParentController;
use App\Http\Controllers\Admin\PerformanceController as AdminPerformanceController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\QuestionBankController;
use App\Http\Controllers\Admin\QuizController as AdminQuizController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\ScoreController as AdminScoreController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\AnnouncementReadController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Parent\ChildAssignmentController;
use App\Http\Controllers\Parent\ChildCbtResultController;
use App\Http\Controllers\Parent\ChildController;
use App\Http\Controllers\Parent\ChildGameStatsController;
use App\Http\Controllers\Parent\ChildQuizResultController;
use App\Http\Controllers\Parent\ChildReportCardController;
use App\Http\Controllers\Parent\ChildResultController;
use App\Http\Controllers\Parent\DashboardController as ParentDashboardController;
use App\Http\Controllers\Parent\NoticeController as ParentNoticeController;
use App\Http\Controllers\Parent\OverviewController as ParentOverviewController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\PwaManifestController;
use App\Http\Controllers\Student\AchievementController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\ExamController as StudentExamController;
use App\Http\Controllers\Student\GameController as StudentGameController;
use App\Http\Controllers\Student\NoticeController as StudentNoticeController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Student\ReportCardController as StudentReportCardController;
use App\Http\Controllers\Student\ResultController as StudentResultController;
use App\Http\Controllers\SuperAdmin\AnalyticsController as SuperAdminAnalyticsController;
use App\Http\Controllers\SuperAdmin\AnnouncementController as SuperAdminAnnouncementController;
use App\Http\Controllers\SuperAdmin\AuditLogController as SuperAdminAuditLogController;
use App\Http\Controllers\SuperAdmin\ContentController as SuperAdminContentController;
use App\Http\Controllers\SuperAdmin\CreditController as SuperAdminCreditController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\EmailController as SuperAdminEmailController;
use App\Http\Controllers\SuperAdmin\ImpersonateController;
use App\Http\Controllers\SuperAdmin\ParentController as SuperAdminParentController;
use App\Http\Controllers\SuperAdmin\PlatformSettingsController;
use App\Http\Controllers\SuperAdmin\SchoolController as SuperAdminSchoolController;
use App\Http\Controllers\SuperAdmin\StudentController as SuperAdminStudentController;
use App\Http\Controllers\SuperAdmin\SystemHealthController as SuperAdminSystemHealthController;
use App\Http\Controllers\SuperAdmin\TeacherController as SuperAdminTeacherController;
use App\Http\Controllers\Teacher\AssignmentController as TeacherAssignmentController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\ExamController as TeacherExamController;
use App\Http\Controllers\Teacher\GameController as TeacherGameController;
use App\Http\Controllers\Teacher\InsightsController as TeacherInsightsController;
use App\Http\Controllers\Teacher\NoticeController as TeacherNoticeController;
use App\Http\Controllers\Teacher\PerformanceController as TeacherPerformanceController;
use App\Http\Controllers\Teacher\QuizController as TeacherQuizController;
use App\Http\Controllers\Teacher\ResultController as TeacherResultController;
use App\Http\Controllers\Teacher\ScoreController as TeacherScoreController;
use App\Http\Controllers\Teacher\StudentController as TeacherStudentController;
use App\Http\Controllers\Teacher\StudentImportController as TeacherStudentImportController;
use App\Http\Controllers\Teacher\SubjectController as TeacherSubjectController;
use App\Http\Controllers\Teacher\SubmissionController as TeacherSubmissionController;
use App\Models\Exam;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // If this is a school's custom domain, redirect to login
    $host = request()->getHost();
    $platformHost = parse_url(config('app.url', ''), PHP_URL_HOST);

    if ($host !== $platformHost && $host !== 'localhost' && $host !== '127.0.0.1' && ! str_ends_with($host, '.test')) {
        return redirect('/portal/login');
    }

    return view('landing');
})->name('home');

// Paystack webhook (no auth, no CSRF — verified by HMAC signature)
Route::post('webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');

// All portal routes live under /portal
Route::prefix('portal')->group(function () {

    // PWA manifest — public, per-school branding (no auth required for browser install)
    Route::get('manifest.json', PwaManifestController::class)->name('pwa.manifest');

    // Force password change (accessible when must_change_password = true)
    Route::middleware('auth')->group(function () {
        Route::get('password/change', [ChangePasswordController::class, 'show'])->name('password.change');
        Route::post('password/change', [ChangePasswordController::class, 'update'])->name('password.change.update')->middleware('throttle:password-change');

        // Stop impersonating — accessible while logged in as school_admin (not super_admin)
        Route::post('impersonate/stop', [ImpersonateController::class, 'stop'])->name('impersonate.stop');

        // Session keep-alive ping — resets session last_activity timestamp
        Route::post('session/ping', function () {
            // SessionTimeout middleware already updated last_activity, nothing extra needed
            return response()->json(['ok' => true]);
        })->name('session.ping')->middleware('throttle:60,1');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        // Default dashboard redirect based on role
        Route::get('dashboard', function () {
            $user = auth()->user();

            return match ($user->role) {
                'super_admin' => redirect('/portal/super-admin/dashboard'),
                'school_admin' => redirect('/portal/admin/dashboard'),
                'teacher' => redirect('/portal/teacher/dashboard'),
                'student' => redirect('/portal/student/dashboard'),
                'parent' => redirect('/portal/parent/dashboard'),
                default => redirect('/'),
            };
        })->name('dashboard');

        // ── Notifications (all roles) ──
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
            Route::delete('clear-all', [NotificationController::class, 'destroyAll'])->name('notifications.clear-all');
            Route::post('{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
            Route::delete('{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        });

        // Dismiss school announcement (all roles)
        Route::post('announcement/{announcement}/dismiss', [AnnouncementReadController::class, 'dismissSchool'])->name('announcement.dismiss');

        // ── School Admin Routes ──
        Route::prefix('admin')->middleware('role:school_admin')->group(function () {
            Route::get('dashboard', AdminDashboardController::class)->name('admin.dashboard');
            Route::get('analytics', AdminAnalyticsController::class)->name('admin.analytics');
            Route::get('insights', AdminInsightsController::class)->name('admin.insights');

            // Students
            Route::resource('students', StudentController::class)->names('admin.students');
            Route::get('students-export', [StudentController::class, 'exportCsv'])->name('admin.students.export');
            // Bulk Move Student UI
            Route::get('students-move', [StudentController::class, 'moveForm'])->name('admin.students.move');
            Route::post('students-move', [StudentController::class, 'moveProcess'])->name('admin.students.move.process');
            Route::post('students/{student}/reset-password', [StudentController::class, 'resetPassword'])->name('admin.students.reset-password');
            Route::post('students/{student}/deactivate', [StudentController::class, 'deactivate'])->name('admin.students.deactivate');
            Route::post('students/{student}/activate', [StudentController::class, 'activate'])->name('admin.students.activate');
            Route::post('students/{student}/transfer-class', [StudentController::class, 'transferClass'])->name('admin.students.transfer-class');
            Route::get('students/{student}/full-profile-export', [StudentController::class, 'exportFullProfileCsv'])->name('admin.students.full-profile-export');
            Route::get('students/{student}/academic-records-export', [StudentController::class, 'exportAcademicRecordsCsv'])->name('admin.students.academic-records-export');
            Route::post('students/{student}/anonymize', [StudentController::class, 'anonymize'])->name('admin.students.anonymize')->middleware('throttle:sensitive-action');
            Route::get('students-import', [StudentImportController::class, 'create'])->name('admin.students.import');
            Route::post('students-import/preview', [StudentImportController::class, 'preview'])->name('admin.students.import.preview')->middleware('throttle:file-upload');
            Route::post('students-import', [StudentImportController::class, 'store'])->name('admin.students.import.store')->middleware('throttle:sensitive-action');
            Route::get('students-import/template', [StudentImportController::class, 'template'])->name('admin.students.import.template');

            // Teachers
            Route::resource('teachers', TeacherController::class)->names('admin.teachers')->except('show');
            Route::post('teachers/{teacher}/reset-password', [TeacherController::class, 'resetPassword'])->name('admin.teachers.reset-password');
            Route::post('teachers/{teacher}/deactivate', [TeacherController::class, 'deactivate'])->name('admin.teachers.deactivate');
            Route::post('teachers/{teacher}/activate', [TeacherController::class, 'activate'])->name('admin.teachers.activate');

            // Parents
            Route::resource('parents', ParentController::class)->names('admin.parents')->except('show');
            Route::post('parents/{parent}/reset-password', [ParentController::class, 'resetPassword'])->name('admin.parents.reset-password');
            Route::post('parents/{parent}/deactivate', [ParentController::class, 'deactivate'])->name('admin.parents.deactivate');
            Route::post('parents/{parent}/activate', [ParentController::class, 'activate'])->name('admin.parents.activate');

            // Classes
            Route::delete('classes/bulk-delete', [ClassController::class, 'bulkDestroy'])->name('admin.classes.bulk-destroy');
            Route::resource('classes', ClassController::class)->names('admin.classes')->except('show');

            // School Levels
            Route::delete('levels/bulk-delete', [LevelController::class, 'bulkDestroy'])->name('admin.levels.bulk-destroy');
            Route::resource('levels', LevelController::class)->names('admin.levels')->except('show');

            // Subjects
            Route::resource('subjects', SubjectController::class)->names('admin.subjects')->except('show');
            Route::get('subjects-assignments', [SubjectController::class, 'assignments'])->name('admin.subjects.assignments');
            Route::post('subjects-assignments/save', [SubjectController::class, 'saveAssignments'])->name('admin.subjects.save-assignments');
            Route::post('subjects-assignments/quick-assign', [SubjectController::class, 'quickAssign'])->name('admin.subjects.quick-assign');
            Route::delete('subjects-assignments/remove', [SubjectController::class, 'removeAssignment'])->name('admin.subjects.remove-assignment');

            // Grading & Report Settings
            Route::get('grading', [GradingController::class, 'index'])->name('admin.grading.index');
            Route::get('grading/scales/create', [GradingController::class, 'createScale'])->name('admin.grading.scales.create');
            Route::post('grading/scales', [GradingController::class, 'storeScale'])->name('admin.grading.scales.store');
            Route::get('grading/scales/{scale}/edit', [GradingController::class, 'editScale'])->name('admin.grading.scales.edit');
            Route::put('grading/scales/{scale}', [GradingController::class, 'updateScale'])->name('admin.grading.scales.update');
            Route::post('grading/scales/{scale}/make-default', [GradingController::class, 'makeDefaultScale'])->name('admin.grading.scales.make-default');
            Route::post('grading/scales/{scale}/levels', [GradingController::class, 'assignScaleLevels'])->name('admin.grading.scales.levels');
            Route::delete('grading/scales/{scale}', [GradingController::class, 'destroyScale'])->name('admin.grading.scales.destroy');
            Route::post('grading/components', [GradingController::class, 'storeComponents'])->name('admin.grading.components.store');
            Route::put('grading/report-card', [GradingController::class, 'updateReportCard'])->name('admin.grading.report-card.update');

            // Scores & Report Cards
            Route::get('scores', [AdminScoreController::class, 'index'])->name('admin.scores.index');
            Route::post('scores/save', [AdminScoreController::class, 'saveScores'])->name('admin.scores.save');
            Route::post('scores/lock', [AdminScoreController::class, 'lockScores'])->name('admin.scores.lock');
            Route::post('scores/generate-reports', [AdminScoreController::class, 'generateReports'])->name('admin.scores.generate-reports');
            Route::get('scores/class-students/{class}', [AdminScoreController::class, 'classStudents'])->name('admin.scores.class-students');
            Route::get('scores/reports', [AdminScoreController::class, 'reports'])->name('admin.scores.reports');
            Route::post('scores/reports/bulk-approve', [AdminScoreController::class, 'bulkApprove'])->name('admin.scores.reports.bulk-approve');
            Route::post('scores/reports/publish', [AdminScoreController::class, 'publishReports'])->name('admin.scores.reports.publish');
            Route::get('scores/reports/bulk-edit-data', [AdminScoreController::class, 'bulkEditReportData'])->name('admin.scores.reports.bulk-edit-data');
            Route::post('scores/reports/bulk-save-data', [AdminScoreController::class, 'bulkSaveReportData'])->name('admin.scores.reports.bulk-save-data');
            Route::get('scores/reports/{report}', [AdminScoreController::class, 'showReport'])->name('admin.scores.reports.show');
            Route::post('scores/reports/{report}/approve', [AdminScoreController::class, 'approveReport'])->name('admin.scores.reports.approve');
            Route::get('scores/reports/{report}/download', [AdminScoreController::class, 'downloadReport'])->name('admin.scores.reports.download');
            Route::get('scores/reports/{report}/edit-data', [AdminScoreController::class, 'editReportData'])->name('admin.scores.reports.edit-data');
            Route::post('scores/reports/{report}/save-data', [AdminScoreController::class, 'saveReportData'])->name('admin.scores.reports.save-data');
            Route::get('scores/export', [AdminScoreController::class, 'exportCsv'])->name('admin.scores.export');
            Route::get('scores/reports-export-csv', [AdminScoreController::class, 'exportReportsCsv'])->name('admin.scores.reports.export-csv');
            Route::get('scores/reports-download-all', [AdminScoreController::class, 'downloadAllReportsPdf'])->name('admin.scores.reports.download-all');

            // Academic Sessions & Terms
            Route::resource('sessions', SessionController::class)->names('admin.sessions')->except('show');
            Route::post('sessions/{session}/activate', [SessionController::class, 'activate'])->name('admin.sessions.activate');
            Route::post('terms/{term}/activate', [SessionController::class, 'activateTerm'])->name('admin.terms.activate');
            Route::put('terms/{term}', [SessionController::class, 'updateTerm'])->name('admin.terms.update');

            // Results
            Route::resource('results', ResultController::class)->names('admin.results');
            Route::get('results-bulk', [BulkResultController::class, 'create'])->name('admin.results.bulk');
            Route::post('results-bulk/preview', [BulkResultController::class, 'preview'])->name('admin.results.bulk.preview')->middleware('throttle:file-upload');
            Route::post('results-bulk', [BulkResultController::class, 'store'])->name('admin.results.bulk.store')->middleware('throttle:file-upload');

            // Assignments
            Route::resource('assignments', AssignmentController::class)->names('admin.assignments')->except('show');

            // Notices
            Route::resource('notices', NoticeController::class)->names('admin.notices')->except('show');
            Route::post('notices/{notice}/unpublish', [NoticeController::class, 'unpublish'])->name('admin.notices.unpublish');
            Route::post('notices/{notice}/publish', [NoticeController::class, 'publish'])->name('admin.notices.publish');

            // Approvals
            Route::get('approvals', [ApprovalController::class, 'index'])->name('admin.approvals.index');
            Route::post('approvals/bulk-approve', [ApprovalController::class, 'bulkApprove'])->name('admin.approvals.bulk-approve');
            Route::post('approvals/bulk-reject', [ApprovalController::class, 'bulkReject'])->name('admin.approvals.bulk-reject');
            Route::post('approvals/{action}/approve', [ApprovalController::class, 'approve'])->name('admin.approvals.approve');
            Route::post('approvals/{action}/reject', [ApprovalController::class, 'reject'])->name('admin.approvals.reject');

            // Promotions
            Route::get('promotions', [PromotionController::class, 'index'])->name('admin.promotions.index');
            Route::post('promotions/preview', [PromotionController::class, 'preview'])->name('admin.promotions.preview');
            Route::post('promotions', [PromotionController::class, 'store'])->name('admin.promotions.store')->middleware('throttle:sensitive-action');

            // Audit Logs
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
            Route::get('audit-logs/export', [AuditLogController::class, 'exportCsv'])->name('admin.audit-logs.export');

            // Quizzes
            Route::get('quizzes', [AdminQuizController::class, 'index'])->name('admin.quizzes.index');
            Route::get('quizzes/{quiz}', [AdminQuizController::class, 'show'])->name('admin.quizzes.show');
            Route::post('quizzes/{quiz}/publish', [AdminQuizController::class, 'publish'])->name('admin.quizzes.publish');
            Route::post('quizzes/{quiz}/unpublish', [AdminQuizController::class, 'unpublish'])->name('admin.quizzes.unpublish');
            Route::get('quizzes/{quiz}/results', [AdminQuizController::class, 'results'])->name('admin.quizzes.results');
            Route::get('quizzes/{quiz}/results/export', [AdminQuizController::class, 'exportCsv'])->name('admin.quizzes.results.export');
            Route::delete('quizzes/{quiz}', [AdminQuizController::class, 'destroy'])->name('admin.quizzes.destroy');

            // CBT — Legacy redirect aliases (assessments → exams?category=assessment)
            Route::get('assessments', fn () => redirect()->route('admin.exams.index', ['category' => 'assessment']))->name('admin.assessments.index');
            Route::get('assessments/create', fn () => redirect()->route('admin.exams.create', ['category' => 'assessment']))->name('admin.assessments.create');
            Route::get('assessments/{exam}', fn (Exam $exam) => redirect()->route('admin.exams.show', $exam))->name('admin.assessments.show');
            Route::get('assessments/{exam}/edit', fn (Exam $exam) => redirect()->route('admin.exams.edit', $exam))->name('admin.assessments.edit');
            Route::get('assessments/{exam}/results', fn (Exam $exam) => redirect()->route('admin.exams.results', $exam))->name('admin.assessments.results');

            // CBT — Legacy redirect aliases (cbt-assignments → exams?category=assignment)
            Route::get('cbt-assignments', fn () => redirect()->route('admin.exams.index', ['category' => 'assignment']))->name('admin.cbt-assignments.index');
            Route::get('cbt-assignments/create', fn () => redirect()->route('admin.exams.create', ['category' => 'assignment']))->name('admin.cbt-assignments.create');
            Route::get('cbt-assignments/{exam}', fn (Exam $exam) => redirect()->route('admin.exams.show', $exam))->name('admin.cbt-assignments.show');
            Route::get('cbt-assignments/{exam}/edit', fn (Exam $exam) => redirect()->route('admin.exams.edit', $exam))->name('admin.cbt-assignments.edit');
            Route::get('cbt-assignments/{exam}/results', fn (Exam $exam) => redirect()->route('admin.exams.results', $exam))->name('admin.cbt-assignments.results');

            // CBT — Exams (canonical routes for all CBT categories)
            Route::get('exams', [AdminExamController::class, 'index'])->name('admin.exams.index');
            Route::get('exams/create', [AdminExamController::class, 'create'])->name('admin.exams.create');
            Route::post('exams/generate', [AdminExamController::class, 'generate'])->name('admin.exams.generate')->middleware('throttle:ai-generation');
            Route::post('exams/store-subject', [AdminExamController::class, 'storeSubject'])->name('admin.exams.store-subject');
            Route::post('exams', [AdminExamController::class, 'store'])->name('admin.exams.store');
            Route::get('exams/{exam}/reset-access', [AdminExamController::class, 'resetAccessForm'])->name('admin.exams.reset-access');
            Route::post('exams/{exam}/reset-access', [AdminExamController::class, 'resetAccess'])->name('admin.exams.reset-access.store');
            Route::post('exams/{exam}/allow-resume', [AdminExamController::class, 'allowResume'])->name('admin.exams.allow-resume');
            Route::get('exams/{exam}', [AdminExamController::class, 'show'])->name('admin.exams.show');
            Route::get('exams/{exam}/preview', [AdminExamController::class, 'preview'])->name('admin.exams.preview');
            Route::get('exams/{exam}/edit', [AdminExamController::class, 'edit'])->name('admin.exams.edit');
            Route::put('exams/{exam}', [AdminExamController::class, 'update'])->name('admin.exams.update');
            Route::match(['post', 'patch'], 'exams/{exam}/publish', [AdminExamController::class, 'publish'])->name('admin.exams.publish');
            Route::match(['post', 'patch'], 'exams/{exam}/unpublish', [AdminExamController::class, 'unpublish'])->name('admin.exams.unpublish');
            Route::delete('exams/{exam}', [AdminExamController::class, 'destroy'])->name('admin.exams.destroy');
            Route::get('exams/{exam}/results', [AdminExamController::class, 'results'])->name('admin.exams.results');
            Route::get('exams/{exam}/results/{attempt}/grade', [AdminExamController::class, 'gradeStudent'])->name('admin.exams.grade-student');
            Route::post('exams/{exam}/results/{attempt}/grade', [AdminExamController::class, 'saveGrade'])->name('admin.exams.save-grade');
            Route::get('exams/{exam}/results/bulk-grade', [AdminExamController::class, 'bulkGrade'])->name('admin.exams.bulk-grade');
            Route::post('exams/{exam}/results/bulk-grade', [AdminExamController::class, 'saveBulkGrade'])->name('admin.exams.save-bulk-grade');
            Route::get('exams/{exam}/monitor', [AdminExamController::class, 'monitor'])->name('admin.exams.monitor');
            Route::get('exams/{exam}/analytics', [AdminExamController::class, 'analytics'])->name('admin.exams.analytics');
            Route::get('exams/{exam}/results/export-csv', [AdminExamController::class, 'exportResultsCsv'])->name('admin.exams.export-results-csv');
            Route::get('exams/export-bulk-csv', [AdminExamController::class, 'exportBulkResultsCsv'])->name('admin.exams.export-bulk-results-csv');

            // Performance Trends
            Route::get('performance/subjects', [AdminPerformanceController::class, 'subjects'])->name('admin.performance.subjects');
            Route::get('performance/students', [AdminPerformanceController::class, 'students'])->name('admin.performance.students');

            // Games
            Route::get('games', [AdminGameController::class, 'index'])->name('admin.games.index');
            Route::get('games/{game}', [AdminGameController::class, 'show'])->name('admin.games.show');
            Route::post('games/{game}/publish', [AdminGameController::class, 'publish'])->name('admin.games.publish');
            Route::post('games/{game}/unpublish', [AdminGameController::class, 'unpublish'])->name('admin.games.unpublish');
            Route::get('games/{game}/stats', [AdminGameController::class, 'stats'])->name('admin.games.stats');
            Route::delete('games/{game}', [AdminGameController::class, 'destroy'])->name('admin.games.destroy');

            // AI Credits
            Route::get('credits', [AiCreditController::class, 'index'])->name('admin.credits.index');
            Route::get('credits/usage/export', [AiCreditController::class, 'exportUsageCsv'])->name('admin.credits.usage.export');
            Route::get('credits/purchase', [AiCreditController::class, 'purchase'])->name('admin.credits.purchase');
            Route::post('credits/purchase', [AiCreditController::class, 'processPurchase'])->name('admin.credits.purchase.process')->middleware('throttle:credit-purchase');
            Route::get('credits/purchase/callback', [AiCreditController::class, 'purchaseCallback'])->name('admin.credits.purchase.callback');
            Route::post('credits/allocate', [AiCreditController::class, 'allocate'])->name('admin.credits.allocate');

            // School Settings
            Route::get('settings', [SettingsController::class, 'index'])->name('admin.settings.index');
            Route::put('settings', [SettingsController::class, 'update'])->name('admin.settings.update');
            Route::put('settings/branding', [SettingsController::class, 'updateBranding'])->name('admin.settings.branding');
            Route::post('settings/upload-logo', [SettingsController::class, 'uploadLogo'])->name('admin.settings.upload-logo');
            Route::delete('settings/remove-logo', [SettingsController::class, 'removeLogo'])->name('admin.settings.remove-logo');
            Route::put('settings/portal', [SettingsController::class, 'updatePortal'])->name('admin.settings.portal');

            // Question Bank
            Route::get('question-bank', [QuestionBankController::class, 'index'])->name('admin.question-bank.index');
            Route::post('question-bank', [QuestionBankController::class, 'store'])->name('admin.question-bank.store');
            Route::put('question-bank/{questionBank}', [QuestionBankController::class, 'update'])->name('admin.question-bank.update');
            Route::delete('question-bank/{questionBank}', [QuestionBankController::class, 'destroy'])->name('admin.question-bank.destroy');
            Route::get('question-bank/search', [QuestionBankController::class, 'search'])->name('admin.question-bank.search');
            Route::post('question-bank/save-from-exam', [QuestionBankController::class, 'saveFromExam'])->name('admin.question-bank.save-from-exam');

            // Help Guide
            Route::get('help', HelpController::class)->name('admin.help');

            // Announcements
            Route::get('announcements', [AdminAnnouncementController::class, 'index'])->name('admin.announcements.index');
            Route::get('announcements/create', [AdminAnnouncementController::class, 'create'])->name('admin.announcements.create');
            Route::post('announcements', [AdminAnnouncementController::class, 'store'])->name('admin.announcements.store');
            Route::get('announcements/{announcement}/edit', [AdminAnnouncementController::class, 'edit'])->name('admin.announcements.edit');
            Route::put('announcements/{announcement}', [AdminAnnouncementController::class, 'update'])->name('admin.announcements.update');
            Route::post('announcements/{announcement}/deactivate', [AdminAnnouncementController::class, 'deactivate'])->name('admin.announcements.deactivate');
            Route::post('announcements/{announcement}/activate', [AdminAnnouncementController::class, 'activate'])->name('admin.announcements.activate');
            Route::delete('announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('admin.announcements.destroy');

            // Mark platform announcement as read
            Route::post('platform-announcement/{announcement}/read', [AnnouncementReadController::class, 'markPlatformRead'])->name('admin.platform-announcement.read');
        });

        // ── Teacher Routes ──
        Route::prefix('teacher')->middleware('role:teacher')->group(function () {
            Route::get('dashboard', TeacherDashboardController::class)->name('teacher.dashboard');

            // Students (read-only)
            Route::get('students', [TeacherStudentController::class, 'index'])->name('teacher.students.index');
            Route::get('students-export', [TeacherStudentController::class, 'exportCsv'])->name('teacher.students.export');

            Route::get('subjects', [TeacherSubjectController::class, 'index'])->name('teacher.subjects.index');
            Route::get('subjects/create', [TeacherSubjectController::class, 'create'])->name('teacher.subjects.create');
            Route::post('subjects', [TeacherSubjectController::class, 'store'])->name('teacher.subjects.store');
            Route::post('subjects/assign', [TeacherSubjectController::class, 'assign'])->name('teacher.subjects.assign');
            Route::delete('subjects/{subject}', [TeacherSubjectController::class, 'destroy'])->name('teacher.subjects.destroy');
            // CSV import disabled — teachers should not add students
            // Route::get('students/import', [TeacherStudentImportController::class, 'create'])->name('teacher.students.import');
            // Route::post('students/import/preview', [TeacherStudentImportController::class, 'preview'])->name('teacher.students.import.preview');
            // Route::post('students/import', [TeacherStudentImportController::class, 'store'])->name('teacher.students.import.store');
            // Route::get('students/import/template', [TeacherStudentImportController::class, 'template'])->name('teacher.students.import.template');

            // Results
            Route::get('results', [TeacherResultController::class, 'index'])->name('teacher.results.index');
            Route::get('results/create', [TeacherResultController::class, 'create'])->name('teacher.results.create');
            Route::post('results', [TeacherResultController::class, 'store'])->name('teacher.results.store');
            Route::get('results/{result}/edit', [TeacherResultController::class, 'edit'])->name('teacher.results.edit');
            Route::put('results/{result}', [TeacherResultController::class, 'update'])->name('teacher.results.update');

            // Assignments
            Route::get('assignments', [TeacherAssignmentController::class, 'index'])->name('teacher.assignments.index');
            Route::get('assignments/create', [TeacherAssignmentController::class, 'create'])->name('teacher.assignments.create');
            Route::post('assignments', [TeacherAssignmentController::class, 'store'])->name('teacher.assignments.store');
            Route::get('assignments/{assignment}/edit', [TeacherAssignmentController::class, 'edit'])->name('teacher.assignments.edit');
            Route::put('assignments/{assignment}', [TeacherAssignmentController::class, 'update'])->name('teacher.assignments.update');

            // Notices
            Route::get('notices', [TeacherNoticeController::class, 'index'])->name('teacher.notices.index');
            Route::get('notices/create', [TeacherNoticeController::class, 'create'])->name('teacher.notices.create');
            Route::post('notices', [TeacherNoticeController::class, 'store'])->name('teacher.notices.store');
            Route::get('notices/{notice}/edit', [TeacherNoticeController::class, 'edit'])->name('teacher.notices.edit');
            Route::put('notices/{notice}', [TeacherNoticeController::class, 'update'])->name('teacher.notices.update');
            Route::post('notices/{notice}/unpublish', [TeacherNoticeController::class, 'unpublish'])->name('teacher.notices.unpublish');
            Route::post('notices/{notice}/publish', [TeacherNoticeController::class, 'publish'])->name('teacher.notices.publish');
            Route::delete('notices/{notice}', [TeacherNoticeController::class, 'destroy'])->name('teacher.notices.destroy');

            // Quizzes
            Route::get('quizzes', [TeacherQuizController::class, 'index'])->name('teacher.quizzes.index');
            Route::get('quizzes/create', [TeacherQuizController::class, 'create'])->name('teacher.quizzes.create');
            Route::post('quizzes/generate', [TeacherQuizController::class, 'generate'])->name('teacher.quizzes.generate')->middleware('throttle:ai-generation');
            Route::post('quizzes', [TeacherQuizController::class, 'store'])->name('teacher.quizzes.store');
            Route::get('quizzes/{quiz}', [TeacherQuizController::class, 'show'])->name('teacher.quizzes.show');
            Route::get('quizzes/{quiz}/edit', [TeacherQuizController::class, 'edit'])->name('teacher.quizzes.edit');
            Route::put('quizzes/{quiz}', [TeacherQuizController::class, 'update'])->name('teacher.quizzes.update');
            Route::get('quizzes/{quiz}/results', [TeacherQuizController::class, 'results'])->name('teacher.quizzes.results');
            Route::get('quizzes/{quiz}/results/export', [TeacherQuizController::class, 'exportCsv'])->name('teacher.quizzes.results.export');
            Route::delete('quizzes/{quiz}', [TeacherQuizController::class, 'destroy'])->name('teacher.quizzes.destroy');

            // Games
            Route::get('games', [TeacherGameController::class, 'index'])->name('teacher.games.index');
            Route::get('games/create', [TeacherGameController::class, 'create'])->name('teacher.games.create');
            Route::post('games/generate', [TeacherGameController::class, 'generate'])->name('teacher.games.generate')->middleware('throttle:ai-generation');
            Route::post('games', [TeacherGameController::class, 'store'])->name('teacher.games.store');
            Route::get('games/{game}', [TeacherGameController::class, 'show'])->name('teacher.games.show');
            Route::get('games/{game}/edit', [TeacherGameController::class, 'edit'])->name('teacher.games.edit');
            Route::put('games/{game}', [TeacherGameController::class, 'update'])->name('teacher.games.update');
            Route::get('games/{game}/stats', [TeacherGameController::class, 'stats'])->name('teacher.games.stats');
            Route::delete('games/{game}', [TeacherGameController::class, 'destroy'])->name('teacher.games.destroy');

            // Question Bank
            Route::get('question-bank', [QuestionBankController::class, 'index'])->name('teacher.question-bank.index');
            Route::post('question-bank', [QuestionBankController::class, 'store'])->name('teacher.question-bank.store');
            Route::put('question-bank/{questionBank}', [QuestionBankController::class, 'update'])->name('teacher.question-bank.update');
            Route::delete('question-bank/{questionBank}', [QuestionBankController::class, 'destroy'])->name('teacher.question-bank.destroy');
            Route::get('question-bank/search', [QuestionBankController::class, 'search'])->name('teacher.question-bank.search');
            Route::post('question-bank/save-from-exam', [QuestionBankController::class, 'saveFromExam'])->name('teacher.question-bank.save-from-exam');

            // CBT — Legacy redirect aliases (assessments → exams?category=assessment)
            Route::get('assessments', fn () => redirect()->route('teacher.exams.index', ['category' => 'assessment']))->name('teacher.assessments.index');
            Route::get('assessments/create', fn () => redirect()->route('teacher.exams.create', ['category' => 'assessment']))->name('teacher.assessments.create');
            Route::get('assessments/{exam}', fn (Exam $exam) => redirect()->route('teacher.exams.show', $exam))->name('teacher.assessments.show');
            Route::get('assessments/{exam}/edit', fn (Exam $exam) => redirect()->route('teacher.exams.edit', $exam))->name('teacher.assessments.edit');
            Route::get('assessments/{exam}/results', fn (Exam $exam) => redirect()->route('teacher.exams.results', $exam))->name('teacher.assessments.results');

            // CBT — Legacy redirect aliases (cbt-assignments → exams?category=assignment)
            Route::get('cbt-assignments', fn () => redirect()->route('teacher.exams.index', ['category' => 'assignment']))->name('teacher.cbt-assignments.index');
            Route::get('cbt-assignments/create', fn () => redirect()->route('teacher.exams.create', ['category' => 'assignment']))->name('teacher.cbt-assignments.create');
            Route::get('cbt-assignments/{exam}', fn (Exam $exam) => redirect()->route('teacher.exams.show', $exam))->name('teacher.cbt-assignments.show');
            Route::get('cbt-assignments/{exam}/edit', fn (Exam $exam) => redirect()->route('teacher.exams.edit', $exam))->name('teacher.cbt-assignments.edit');
            Route::get('cbt-assignments/{exam}/results', fn (Exam $exam) => redirect()->route('teacher.exams.results', $exam))->name('teacher.cbt-assignments.results');

            // CBT — Exams (canonical routes for all CBT categories)
            Route::get('exams', [TeacherExamController::class, 'index'])->name('teacher.exams.index');
            Route::get('exams/create', [TeacherExamController::class, 'create'])->name('teacher.exams.create');
            Route::post('exams/generate', [TeacherExamController::class, 'generate'])->name('teacher.exams.generate')->middleware('throttle:ai-generation');
            Route::post('exams/store-subject', [TeacherExamController::class, 'storeSubject'])->name('teacher.exams.store-subject');
            Route::post('exams', [TeacherExamController::class, 'store'])->name('teacher.exams.store');
            Route::get('exams/{exam}', [TeacherExamController::class, 'show'])->name('teacher.exams.show');
            Route::get('exams/{exam}/preview', [TeacherExamController::class, 'preview'])->name('teacher.exams.preview');
            Route::get('exams/{exam}/edit', [TeacherExamController::class, 'edit'])->name('teacher.exams.edit');
            Route::put('exams/{exam}', [TeacherExamController::class, 'update'])->name('teacher.exams.update');
            Route::delete('exams/{exam}', [TeacherExamController::class, 'destroy'])->name('teacher.exams.destroy');
            Route::get('exams/{exam}/results', [TeacherExamController::class, 'results'])->name('teacher.exams.results');
            Route::get('exams/{exam}/results/{attempt}/grade', [TeacherExamController::class, 'gradeStudent'])->name('teacher.exams.grade-student');
            Route::post('exams/{exam}/results/{attempt}/grade', [TeacherExamController::class, 'saveGrade'])->name('teacher.exams.save-grade');
            Route::get('exams/{exam}/results/bulk-grade', [TeacherExamController::class, 'bulkGrade'])->name('teacher.exams.bulk-grade');
            Route::post('exams/{exam}/results/bulk-grade', [TeacherExamController::class, 'saveBulkGrade'])->name('teacher.exams.save-bulk-grade');
            Route::get('exams/{exam}/monitor', [TeacherExamController::class, 'monitor'])->name('teacher.exams.monitor');
            Route::get('exams/{exam}/analytics', [TeacherExamController::class, 'analytics'])->name('teacher.exams.analytics');
            Route::get('exams/{exam}/results/export-csv', [TeacherExamController::class, 'exportResultsCsv'])->name('teacher.exams.export-results-csv');

            // Performance Trends
            Route::get('performance/subjects', [TeacherPerformanceController::class, 'subjects'])->name('teacher.performance.subjects');
            Route::get('performance/students', [TeacherPerformanceController::class, 'students'])->name('teacher.performance.students');

            // Scores & Report Cards
            Route::get('scores', [TeacherScoreController::class, 'index'])->name('teacher.scores.index');
            Route::post('scores/save', [TeacherScoreController::class, 'saveScores'])->name('teacher.scores.save');
            Route::get('scores/reports', [TeacherScoreController::class, 'reports'])->name('teacher.scores.reports');
            Route::post('scores/reports/generate', [TeacherScoreController::class, 'generateReports'])->name('teacher.scores.reports.generate');
            Route::post('scores/reports/bulk-submit', [TeacherScoreController::class, 'bulkSubmit'])->name('teacher.scores.reports.bulk-submit');
            Route::get('scores/reports/bulk-edit-data', [TeacherScoreController::class, 'bulkEditReportData'])->name('teacher.scores.reports.bulk-edit-data');
            Route::post('scores/reports/bulk-save-data', [TeacherScoreController::class, 'bulkSaveReportData'])->name('teacher.scores.reports.bulk-save-data');
            Route::get('scores/reports/{report}', [TeacherScoreController::class, 'showReport'])->name('teacher.scores.reports.show');
            Route::post('scores/reports/{report}/comment', [TeacherScoreController::class, 'saveComment'])->name('teacher.scores.reports.comment');
            Route::get('scores/reports/{report}/edit-data', [TeacherScoreController::class, 'editReportData'])->name('teacher.scores.reports.edit-data');
            Route::post('scores/reports/{report}/save-data', [TeacherScoreController::class, 'saveReportData'])->name('teacher.scores.reports.save-data');
            Route::get('scores/export', [TeacherScoreController::class, 'exportCsv'])->name('teacher.scores.export');
            Route::get('scores/reports/{report}/download', [TeacherScoreController::class, 'downloadReport'])->name('teacher.scores.reports.download');
            Route::get('scores/reports-export-csv', [TeacherScoreController::class, 'exportReportsCsv'])->name('teacher.scores.reports.export-csv');
            Route::get('scores/reports-download-all', [TeacherScoreController::class, 'downloadAllReportsPdf'])->name('teacher.scores.reports.download-all');

            // Submissions (approval tracking)
            Route::get('submissions', [TeacherSubmissionController::class, 'index'])->name('teacher.submissions.index');

            // Insights
            Route::get('insights', TeacherInsightsController::class)->name('teacher.insights');

            // Help Guide
            Route::get('help', [HelpController::class, 'teacher'])->name('teacher.help');
        });

        // ── Student Routes ──
        Route::prefix('student')->middleware('role:student')->group(function () {
            Route::get('dashboard', StudentDashboardController::class)->name('student.dashboard');
            Route::get('profile', StudentProfileController::class)->name('student.profile');
            Route::get('achievements', AchievementController::class)->name('student.achievements');

            // Results
            Route::get('results', [StudentResultController::class, 'index'])->name('student.results.index');
            Route::get('results/{result}', [StudentResultController::class, 'show'])->name('student.results.show');

            // Report Cards
            Route::get('report-cards', [StudentReportCardController::class, 'index'])->name('student.report-cards.index');
            Route::get('report-cards/cbt-results', [StudentReportCardController::class, 'cbtResults'])->name('student.report-cards.cbt-results');
            Route::get('report-cards/session/{session}', [StudentReportCardController::class, 'sessionSummary'])->name('student.report-cards.session-summary');
            Route::get('report-cards/{report}', [StudentReportCardController::class, 'show'])->name('student.report-cards.show');
            Route::get('report-cards/{report}/download', [StudentReportCardController::class, 'download'])->name('student.report-cards.download');

            // Assignments
            Route::get('assignments', [StudentAssignmentController::class, 'index'])->name('student.assignments.index');

            // Quizzes
            Route::get('quizzes', [StudentQuizController::class, 'index'])->name('student.quizzes.index');
            Route::post('quizzes/{quiz}/start', [StudentQuizController::class, 'start'])->name('student.quizzes.start');
            Route::get('quizzes/attempt/{attempt}', [StudentQuizController::class, 'take'])->name('student.quizzes.take');
            Route::post('quizzes/attempt/{attempt}/answer', [StudentQuizController::class, 'saveAnswer'])->name('student.quizzes.save-answer');
            Route::post('quizzes/attempt/{attempt}/submit', [StudentQuizController::class, 'submit'])->name('student.quizzes.submit');
            Route::get('quizzes/attempt/{attempt}/results', [StudentQuizController::class, 'results'])->name('student.quizzes.results');

            // CBT — Legacy redirect aliases (assessments → exams?category=assessment)
            Route::get('assessments', fn () => redirect()->route('student.exams.index', ['category' => 'assessment']))->name('student.assessments.index');
            Route::get('assessments/{exam}', fn (Exam $exam) => redirect()->route('student.exams.show', $exam))->name('student.assessments.show');

            // CBT — Legacy redirect aliases (cbt-assignments → exams?category=assignment)
            Route::get('cbt-assignments', fn () => redirect()->route('student.exams.index', ['category' => 'assignment']))->name('student.cbt-assignments.index');
            Route::get('cbt-assignments/{exam}', fn (Exam $exam) => redirect()->route('student.exams.show', $exam))->name('student.cbt-assignments.show');

            // CBT Exams (canonical routes for all CBT categories)
            Route::get('exams', [StudentExamController::class, 'index'])->name('student.exams.index');
            Route::get('exams/attempt/{attempt}', [StudentExamController::class, 'take'])->name('student.exams.take');
            Route::post('exams/attempt/{attempt}/answer', [StudentExamController::class, 'saveAnswer'])->name('student.exams.save-answer');
            Route::post('exams/attempt/{attempt}/tab-switch', [StudentExamController::class, 'tabSwitch'])->name('student.exams.tab-switch');
            Route::post('exams/attempt/{attempt}/submit', [StudentExamController::class, 'submit'])->name('student.exams.submit');
            Route::get('exams/attempt/{attempt}/results', [StudentExamController::class, 'results'])->name('student.exams.results');
            Route::get('exams/{exam}', [StudentExamController::class, 'show'])->name('student.exams.show');
            Route::post('exams/{exam}/start', [StudentExamController::class, 'start'])->name('student.exams.start');

            // Games
            Route::get('games', [StudentGameController::class, 'index'])->name('student.games.index');
            Route::get('games/{game}', [StudentGameController::class, 'play'])->name('student.games.play');
            Route::post('games/{game}/complete', [StudentGameController::class, 'complete'])->name('student.games.complete');
            Route::get('games/{game}/leaderboard', [StudentGameController::class, 'leaderboard'])->name('student.games.leaderboard');

            // Notices
            Route::get('notices', [StudentNoticeController::class, 'index'])->name('student.notices.index');
            Route::get('notices/{notice}', [StudentNoticeController::class, 'show'])->name('student.notices.show');

            // Help Guide
            Route::get('help', [HelpController::class, 'student'])->name('student.help');
        });

        // ── Parent Routes ──
        Route::prefix('parent')->middleware('role:parent')->group(function () {
            Route::get('dashboard', ParentDashboardController::class)->name('parent.dashboard');

            // Overview pages (all children)
            Route::get('results', [ParentOverviewController::class, 'results'])->name('parent.results.index');
            Route::get('report-cards', [ParentOverviewController::class, 'reportCards'])->name('parent.report-cards.index');
            Route::get('assignments', [ParentOverviewController::class, 'assignments'])->name('parent.assignments.index');
            Route::get('quizzes', [ParentOverviewController::class, 'quizzes'])->name('parent.quizzes.index');
            Route::get('games', [ParentOverviewController::class, 'games'])->name('parent.games.index');
            Route::get('cbt', [ParentOverviewController::class, 'cbt'])->name('parent.cbt.index');

            // Children
            Route::get('children/{child}', [ChildController::class, 'show'])->name('parent.children.show');

            // Child Results
            Route::get('children/{child}/results', [ChildResultController::class, 'index'])->name('parent.children.results');
            Route::get('children/{child}/results/{result}', [ChildResultController::class, 'show'])->name('parent.children.results.show');

            // Child Report Cards
            Route::get('children/{child}/report-cards', [ChildReportCardController::class, 'index'])->name('parent.children.report-cards');
            Route::get('children/{child}/report-cards/{report}', [ChildReportCardController::class, 'show'])->name('parent.children.report-cards.show');
            Route::get('children/{child}/report-cards/{report}/download', [ChildReportCardController::class, 'download'])->name('parent.children.report-cards.download');

            // Child Assignments
            Route::get('children/{child}/assignments', [ChildAssignmentController::class, 'index'])->name('parent.children.assignments');

            // Child Quizzes
            Route::get('children/{child}/quizzes', [ChildQuizResultController::class, 'index'])->name('parent.children.quizzes');

            // Child Games
            Route::get('children/{child}/games', [ChildGameStatsController::class, 'index'])->name('parent.children.games');

            // Child CBT Results (Exams / Assessments / Assignments)
            Route::get('children/{child}/cbt-results', [ChildCbtResultController::class, 'index'])->name('parent.children.cbt-results');

            // Notices
            Route::get('notices', [ParentNoticeController::class, 'index'])->name('parent.notices.index');
            Route::get('notices/{notice}', [ParentNoticeController::class, 'show'])->name('parent.notices.show');

            // Help Guide
            Route::get('help', [HelpController::class, 'parent'])->name('parent.help');
        });

        // ── Super Admin Routes ──
        Route::prefix('super-admin')->middleware('role:super_admin')->group(function () {
            Route::get('dashboard', SuperAdminDashboardController::class)->name('super-admin.dashboard');

            // Schools
            Route::get('schools', [SuperAdminSchoolController::class, 'index'])->name('super-admin.schools.index');
            Route::get('schools/create', [SuperAdminSchoolController::class, 'create'])->name('super-admin.schools.create');
            Route::post('schools', [SuperAdminSchoolController::class, 'store'])->name('super-admin.schools.store');
            Route::post('schools/bulk-toggle-setting', [SuperAdminSchoolController::class, 'bulkToggleSetting'])->name('super-admin.schools.bulk-toggle-setting');
            Route::post('schools/bulk-activate', [SuperAdminSchoolController::class, 'bulkActivate'])->name('super-admin.schools.bulk-activate');
            Route::post('schools/bulk-deactivate', [SuperAdminSchoolController::class, 'bulkDeactivate'])->name('super-admin.schools.bulk-deactivate');
            Route::post('schools/bulk-adjust-credits', [SuperAdminSchoolController::class, 'bulkAdjustCredits'])->name('super-admin.schools.bulk-adjust-credits');
            Route::get('schools/{school}', [SuperAdminSchoolController::class, 'show'])->name('super-admin.schools.show');
            Route::get('schools/{school}/edit', [SuperAdminSchoolController::class, 'edit'])->name('super-admin.schools.edit');
            Route::put('schools/{school}', [SuperAdminSchoolController::class, 'update'])->name('super-admin.schools.update');
            Route::post('schools/{school}/activate', [SuperAdminSchoolController::class, 'activate'])->name('super-admin.schools.activate');
            Route::post('schools/{school}/deactivate', [SuperAdminSchoolController::class, 'deactivate'])->name('super-admin.schools.deactivate');
            Route::delete('schools/{school}', [SuperAdminSchoolController::class, 'destroy'])->name('super-admin.schools.destroy')->middleware('throttle:sensitive-action');
            Route::post('schools/{school}/reset-admin-password', [SuperAdminSchoolController::class, 'resetAdminPassword'])->name('super-admin.schools.reset-admin-password');
            Route::get('schools/{school}/admins/create', [SuperAdminSchoolController::class, 'createAdmin'])->name('super-admin.schools.create-admin');
            Route::post('schools/{school}/admins', [SuperAdminSchoolController::class, 'storeAdmin'])->name('super-admin.schools.store-admin');
            Route::delete('schools/{school}/admins/{admin}', [SuperAdminSchoolController::class, 'destroyAdmin'])->name('super-admin.schools.destroy-admin');
            Route::post('schools/{school}/verify-domain', [SuperAdminSchoolController::class, 'verifyDomain'])->name('super-admin.schools.verify-domain');
            Route::post('schools/{school}/upload-logo', [SuperAdminSchoolController::class, 'uploadLogo'])->name('super-admin.schools.upload-logo');
            Route::delete('schools/{school}/remove-logo', [SuperAdminSchoolController::class, 'removeLogo'])->name('super-admin.schools.remove-logo');
            Route::post('schools/{school}/impersonate', [ImpersonateController::class, 'start'])->name('super-admin.schools.impersonate')->middleware('throttle:sensitive-action');
            Route::post('schools/{school}/settings', [SuperAdminSchoolController::class, 'updateSettings'])->name('super-admin.schools.update-settings');
            Route::post('schools/{school}/lock-features', [SuperAdminSchoolController::class, 'lockFeatures'])->name('super-admin.schools.lock-features');

            // AI Credits
            Route::get('credits', [SuperAdminCreditController::class, 'index'])->name('super-admin.credits.index');
            Route::get('credits/analytics', [SuperAdminCreditController::class, 'analytics'])->name('super-admin.credits.analytics'); // S13
            Route::get('credits/history', [SuperAdminCreditController::class, 'history'])->name('super-admin.credits.history');         // S14
            Route::post('credits/{school}/adjust', [SuperAdminCreditController::class, 'adjust'])->name('super-admin.credits.adjust');

            // Students (cross-school)
            Route::get('students', [SuperAdminStudentController::class, 'index'])->name('super-admin.students.index');
            Route::get('students/create', [SuperAdminStudentController::class, 'create'])->name('super-admin.students.create');
            Route::post('students', [SuperAdminStudentController::class, 'store'])->name('super-admin.students.store');
            Route::delete('students/{student}', [SuperAdminStudentController::class, 'destroy'])->name('super-admin.students.destroy');

            // Teachers (cross-school)
            Route::get('teachers', [SuperAdminTeacherController::class, 'index'])->name('super-admin.teachers.index');
            Route::get('teachers/create', [SuperAdminTeacherController::class, 'create'])->name('super-admin.teachers.create');
            Route::post('teachers', [SuperAdminTeacherController::class, 'store'])->name('super-admin.teachers.store');
            Route::delete('teachers/{teacher}', [SuperAdminTeacherController::class, 'destroy'])->name('super-admin.teachers.destroy');

            // Parents (cross-school)
            Route::get('parents', [SuperAdminParentController::class, 'index'])->name('super-admin.parents.index');
            Route::get('parents/create', [SuperAdminParentController::class, 'create'])->name('super-admin.parents.create');
            Route::post('parents', [SuperAdminParentController::class, 'store'])->name('super-admin.parents.store');
            Route::delete('parents/{parent}', [SuperAdminParentController::class, 'destroy'])->name('super-admin.parents.destroy');

            // Announcements
            Route::get('announcements', [SuperAdminAnnouncementController::class, 'index'])->name('super-admin.announcements.index');
            Route::get('announcements/create', [SuperAdminAnnouncementController::class, 'create'])->name('super-admin.announcements.create');
            Route::post('announcements', [SuperAdminAnnouncementController::class, 'store'])->name('super-admin.announcements.store');
            Route::get('announcements/{announcement}', [SuperAdminAnnouncementController::class, 'show'])->name('super-admin.announcements.show');
            Route::get('announcements/{announcement}/edit', [SuperAdminAnnouncementController::class, 'edit'])->name('super-admin.announcements.edit');
            Route::put('announcements/{announcement}', [SuperAdminAnnouncementController::class, 'update'])->name('super-admin.announcements.update');
            Route::post('announcements/{announcement}/deactivate', [SuperAdminAnnouncementController::class, 'deactivate'])->name('super-admin.announcements.deactivate');
            Route::post('announcements/{announcement}/activate', [SuperAdminAnnouncementController::class, 'activate'])->name('super-admin.announcements.activate');
            Route::delete('announcements/{announcement}', [SuperAdminAnnouncementController::class, 'destroy'])->name('super-admin.announcements.destroy');

            // Emails
            Route::get('emails', [SuperAdminEmailController::class, 'index'])->name('super-admin.emails.index');
            Route::get('emails/create', [SuperAdminEmailController::class, 'create'])->name('super-admin.emails.create');
            Route::post('emails', [SuperAdminEmailController::class, 'store'])->name('super-admin.emails.store');
            Route::get('emails/{email}', [SuperAdminEmailController::class, 'show'])->name('super-admin.emails.show');

            // Platform Settings
            Route::get('settings', [PlatformSettingsController::class, 'index'])->name('super-admin.settings.index');
            Route::put('settings', [PlatformSettingsController::class, 'update'])->name('super-admin.settings.update');

            // Platform Analytics
            Route::get('analytics', SuperAdminAnalyticsController::class)->name('super-admin.analytics');
            Route::get('analytics/export', [SuperAdminAnalyticsController::class, 'export'])->name('super-admin.analytics.export');

            // Content Library (S16)
            Route::get('content', [SuperAdminContentController::class, 'index'])->name('super-admin.content.index');

            // Platform Audit Logs (S12)
            Route::get('audit-logs', [SuperAdminAuditLogController::class, 'index'])->name('super-admin.audit-logs.index');
            Route::get('audit-logs/export', [SuperAdminAuditLogController::class, 'export'])->name('super-admin.audit-logs.export');

            // System Health (S24)
            Route::get('system-health', SuperAdminSystemHealthController::class)->name('super-admin.system-health');
        });
    });

}); // End of /portal prefix group

require __DIR__.'/settings.php';
