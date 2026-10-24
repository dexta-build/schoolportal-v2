<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\Quiz;
use App\Models\School;
use App\Models\User;
use App\Notifications\DeadlineReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDeadlineReminders extends Command
{
    protected $signature = 'reminders:send-deadlines';

    protected $description = 'Send 24-hour deadline reminders for quizzes and exams expiring within the next day';

    public function handle(): int
    {
        $windowStart = now();
        $windowEnd = now()->addDay();

        $this->sendQuizReminders($windowStart, $windowEnd);
        $this->sendExamReminders($windowStart, $windowEnd);

        return self::SUCCESS;
    }

    private function sendQuizReminders(Carbon $start, Carbon $end): void
    {
        $quizzes = Quiz::withoutGlobalScopes()
            ->where('is_published', true)
            ->whereBetween('expires_at', [$start, $end])
            ->get();

        foreach ($quizzes as $quiz) {
            $expiresLabel = $quiz->expires_at->format('M j \a\t g:i A');

            $students = User::where('school_id', $quiz->school_id)
                ->where('role', 'student')
                ->where('is_active', true)
                ->whereHas('studentProfile', fn ($q) => $q->where('class_id', $quiz->class_id))
                ->get();

            foreach ($students as $student) {
                $student->notify(new DeadlineReminderNotification('quiz', $quiz->title, $quiz->id, $expiresLabel, 'student'));
            }

            $studentIds = $students->pluck('id');
            $parents = User::where('school_id', $quiz->school_id)
                ->where('role', 'parent')
                ->where('is_active', true)
                ->whereHas('children', fn ($q) => $q->whereIn('student_id', $studentIds))
                ->get();

            foreach ($parents as $parent) {
                $parent->notify(new DeadlineReminderNotification('quiz', $quiz->title, $quiz->id, $expiresLabel, 'parent'));
            }
        }

        $this->info("Sent reminders for {$quizzes->count()} quiz(zes).");
    }

    private function sendExamReminders(Carbon $start, Carbon $end): void
    {
        $exams = Exam::withoutGlobalScope('tenant')
            ->where('is_published', true)
            ->whereBetween('available_until', [$start, $end])
            ->get();

        foreach ($exams as $exam) {
            $expiresLabel = $exam->available_until->format('M j \a\t g:i A');

            $students = User::where('school_id', $exam->school_id)
                ->where('role', 'student')
                ->where('is_active', true)
                ->whereHas('studentProfile', fn ($q) => $q->where('class_id', $exam->class_id))
                ->get();

            foreach ($students as $student) {
                $student->notify(new DeadlineReminderNotification('exam', $exam->title, $exam->id, $expiresLabel, 'student'));
            }

            // Parents only hear about CBTs when the school shows them CBT results
            if (! School::withoutGlobalScopes()->find($exam->school_id)?->featureEnabled('enable_cbt_results_for_parents')) {
                continue;
            }

            $studentIds = $students->pluck('id');
            $parents = User::where('school_id', $exam->school_id)
                ->where('role', 'parent')
                ->where('is_active', true)
                ->whereHas('children', fn ($q) => $q->whereIn('student_id', $studentIds))
                ->get();

            foreach ($parents as $parent) {
                $parent->notify(new DeadlineReminderNotification('exam', $exam->title, $exam->id, $expiresLabel, 'parent'));
            }
        }

        $this->info("Sent reminders for {$exams->count()} exam(s).");
    }
}
