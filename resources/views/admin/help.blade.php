<x-layouts::app :title="__('Help Guide')">
    <div class="max-w-4xl mx-auto space-y-8">
        {{-- Header --}}
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('Administrator Help Guide') }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Quick reference for managing your school portal.') }}
            </p>
        </div>

        {{-- Table of Contents --}}
        <nav class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6" aria-label="{{ __('Table of contents') }}">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('Contents') }}</h2>
            <ol class="list-decimal list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                <li><a href="#getting-started" class="underline hover:text-[var(--color-primary)]">{{ __('Getting Started') }}</a></li>
                <li><a href="#managing-students" class="underline hover:text-[var(--color-primary)]">{{ __('Managing Students') }}</a></li>
                <li><a href="#managing-teachers" class="underline hover:text-[var(--color-primary)]">{{ __('Managing Teachers & Parents') }}</a></li>
                <li><a href="#sessions-terms" class="underline hover:text-[var(--color-primary)]">{{ __('Academic Sessions & Terms') }}</a></li>
                <li><a href="#results-assignments" class="underline hover:text-[var(--color-primary)]">{{ __('Results & Assignments') }}</a></li>
                <li><a href="#quizzes-games" class="underline hover:text-[var(--color-primary)]">{{ __('AI Quizzes & Games') }}</a></li>
                <li><a href="#cbt" class="underline hover:text-[var(--color-primary)]">{{ __('CBT (Computer-Based Testing)') }}</a></li>
                <li><a href="#approvals" class="underline hover:text-[var(--color-primary)]">{{ __('Teacher Approvals') }}</a></li>
                <li><a href="#notices" class="underline hover:text-[var(--color-primary)]">{{ __('Notice Board') }}</a></li>
                <li><a href="#promotions" class="underline hover:text-[var(--color-primary)]">{{ __('Promoting Students') }}</a></li>
                <li><a href="#settings" class="underline hover:text-[var(--color-primary)]">{{ __('School Settings & Branding') }}</a></li>
                <li><a href="#ai-credits" class="underline hover:text-[var(--color-primary)]">{{ __('AI Credits') }}</a></li>
                <li><a href="#security" class="underline hover:text-[var(--color-primary)]">{{ __('Security & Passwords') }}</a></li>
            </ol>
        </nav>

        {{-- Getting Started --}}
        <section id="getting-started" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('1. Getting Started') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('After logging in, your Dashboard shows a summary of your school: total students, teachers, parents, classes, and recent activity.') }}</p>
                <p><strong>{{ __('First-time setup checklist:') }}</strong></p>
                <ol>
                    <li>{{ __('Go to School Settings to set your school name, logo, colors, and motto.') }}</li>
                    <li>{{ __('Create School Levels (e.g., Nursery, Primary, Secondary).') }}</li>
                    <li>{{ __('Create Classes within each level (e.g., Primary 1, Primary 2).') }}</li>
                    <li>{{ __('Create an Academic Session (e.g., 2025/2026) — this auto-creates 3 terms.') }}</li>
                    <li>{{ __('Activate the current session and term.') }}</li>
                    <li>{{ __('Add students (individually or via CSV bulk import).') }}</li>
                    <li>{{ __('Add teachers and assign them to classes.') }}</li>
                    <li>{{ __('Optionally create parent accounts and link them to students.') }}</li>
                </ol>
            </div>
        </section>

        {{-- Managing Students --}}
        <section id="managing-students" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('2. Managing Students') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p><strong>{{ __('Adding a student:') }}</strong> {{ __('Go to People → Students → "Add Student". Fill in name, username, class, level, and optional details. A default password is set which the student should change on first login.') }}</p>
                <p><strong>{{ __('Bulk import:') }}</strong> {{ __('Click "Import CSV" on the Students page. Download the template, fill it in, then upload. You\'ll see a preview of all students before confirming the import.') }}</p>
                <p><strong>{{ __('Uploading photos:') }}</strong> {{ __('Click "Edit" on a student, then use the avatar upload section. Photos are stored securely in the cloud.') }}</p>
                <p><strong>{{ __('Deactivating:') }}</strong> {{ __('If a student leaves, click "Deactivate" instead of deleting. Deactivated students cannot log in but their data (results, quiz scores) is preserved.') }}</p>
            </div>
        </section>

        {{-- Managing Teachers & Parents --}}
        <section id="managing-teachers" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('3. Managing Teachers & Parents') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p><strong>{{ __('Teachers:') }}</strong> {{ __('Create teacher accounts under People → Teachers. Assign each teacher to one or more classes. Teachers can only see and manage students in their assigned classes.') }}</p>
                <p><strong>{{ __('Important:') }}</strong> {{ __('Everything a teacher uploads (results, assignments, notices, quizzes, games) goes to a pending queue for your approval before students can see it.') }}</p>
                <p><strong>{{ __('Parents:') }}</strong> {{ __('Create parent accounts under People → Parents. Link each parent to their child(ren). One parent can be linked to multiple students across different classes. Parents can view their children\'s results, assignments, quiz scores, and school notices.') }}</p>
            </div>
        </section>

        {{-- Academic Sessions & Terms --}}
        <section id="sessions-terms" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('4. Academic Sessions & Terms') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('An Academic Session (e.g., "2025/2026") contains 3 terms. When you create a session, the 3 terms are created automatically.') }}</p>
                <p><strong>{{ __('Activating:') }}</strong> {{ __('Only one session and one term can be "current" at a time. Click "Set as Current" on the session/term you want active. This affects which term students see results for by default.') }}</p>
                <p><strong>{{ __('End of session:') }}</strong> {{ __('When a session ends, mark it as "Completed". Create the next session before promoting students.') }}</p>
            </div>
        </section>

        {{-- Results & Assignments --}}
        <section id="results-assignments" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('5. Results & Assignments') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p><strong>{{ __('Results:') }}</strong> {{ __('Upload result PDFs for each student per term. Go to Scores & Reports → Result Sheets (PDF) → "Upload Result". Select the student, session, term, and upload the PDF file.') }}</p>
                <p><strong>{{ __('Bulk results:') }}</strong> {{ __('Click "Bulk Upload" to upload multiple result PDFs at once. Name each file as the student\'s username (e.g., "john_doe.pdf") and the system auto-matches them.') }}</p>
                <p><strong>{{ __('Assignments:') }}</strong> {{ __('Upload assignments per class, per week, per term under Academics → Assignments. Students in that class can view and download them.') }}</p>
                <p>{{ __('Results you upload are approved immediately. Results uploaded by teachers require your approval first.') }}</p>
            </div>
        </section>

        {{-- AI Quizzes & Games --}}
        <section id="quizzes-games" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('6. AI Quizzes & Games') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('Create quizzes and educational games for students using AI or manually.') }}</p>
                <p><strong>{{ __('AI-generated:') }}</strong> {{ __('Upload a document (lesson notes, textbook chapter) or type a prompt, and the AI generates quiz questions or game content. You can edit everything before publishing. Each AI generation uses 1 credit.') }}</p>
                <p><strong>{{ __('Manual:') }}</strong> {{ __('Create quizzes and games by typing content directly — always free, no credits needed.') }}</p>
                <p><strong>{{ __('Game types:') }}</strong></p>
                <ul>
                    <li><strong>{{ __('Memory Match') }}</strong> — {{ __('Flip cards to match terms with definitions') }}</li>
                    <li><strong>{{ __('Word Scramble') }}</strong> — {{ __('Unscramble jumbled letters to form key terms') }}</li>
                    <li><strong>{{ __('Quiz Race') }}</strong> — {{ __('Rapid-fire timed questions with class leaderboard') }}</li>
                    <li><strong>{{ __('Flashcard Study') }}</strong> — {{ __('Swipeable study cards with self-assessment') }}</li>
                </ul>
                <p>{{ __('Published quizzes and games appear on students\' dashboards. You can set time limits, passing scores, and expiry dates.') }}</p>
            </div>
        </section>

        {{-- CBT --}}
        <section id="cbt" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('7. CBT (Computer-Based Testing)') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('Create online exams, assessments, and assignments that students take directly on the portal. Go to CBT & Interactive → CBT.') }}</p>
                <p><strong>{{ __('Three categories:') }}</strong></p>
                <ul>
                    <li><strong>{{ __('Exams') }}</strong> — {{ __('Formal end-of-term or mid-term examinations') }}</li>
                    <li><strong>{{ __('Assessments') }}</strong> — {{ __('Continuous assessment tests (CA tests)') }}</li>
                    <li><strong>{{ __('Assignments') }}</strong> — {{ __('Homework or take-home work with flexible deadlines') }}</li>
                </ul>
                <p><strong>{{ __('Creating a CBT exam:') }}</strong></p>
                <ol>
                    <li>{{ __('Click "Create" and choose the category (Exam, Assessment, or Assignment).') }}</li>
                    <li>{{ __('Set the title, class, subject, session, term, and optional time limit.') }}</li>
                    <li>{{ __('Add questions — multiple choice, true/false, or fill-in-the-blank.') }}</li>
                    <li>{{ __('Use "Bulk Grade" to import questions from a spreadsheet if you have many.') }}</li>
                    <li>{{ __('Publish when ready — students see it on their dashboard under the matching category tab.') }}</li>
                </ol>
                <p><strong>{{ __('Scoring:') }}</strong> {{ __('CBT scores can feed into report cards. Scores from CBT exams/assessments appear in the Score Entry section under Scores & Reports.') }}</p>
                <p>{{ __('Teacher-created CBT content follows the same approval flow as other uploads.') }}</p>
            </div>
        </section>

        {{-- Approvals --}}
        <section id="approvals" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('8. Teacher Approvals') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('When teachers upload results, assignments, notices, quizzes, or games, these go into a pending approval queue.') }}</p>
                <p>{{ __('Go to Management → Approvals to review pending items. For each item, you can:') }}</p>
                <ul>
                    <li>{{ __('Preview the content (view PDF, read the notice, preview quiz questions)') }}</li>
                    <li>{{ __('Approve — makes it visible to students and parents') }}</li>
                    <li>{{ __('Reject — sends it back to the teacher with a reason') }}</li>
                </ul>
                <p>{{ __('Your own uploads are auto-approved and visible immediately.') }}</p>
            </div>
        </section>

        {{-- Notices --}}
        <section id="notices" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('9. Notices') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('Post school announcements under Communication → Notices. You can:') }}</p>
                <ul>
                    <li>{{ __('Target specific school levels (e.g., only Primary)') }}</li>
                    <li>{{ __('Target specific roles (e.g., only parents)') }}</li>
                    <li>{{ __('Attach images') }}</li>
                    <li>{{ __('Set an expiry date after which the notice auto-hides') }}</li>
                </ul>
            </div>
        </section>

        {{-- Promotions --}}
        <section id="promotions" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('10. Promoting Students') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('At the end of a session, go to Management → Promotions to move students to the next class.') }}</p>
                <ol>
                    <li>{{ __('Select the source class (e.g., Primary 1)') }}</li>
                    <li>{{ __('Select the destination class (e.g., Primary 2)') }}</li>
                    <li>{{ __('Review the list of students') }}</li>
                    <li>{{ __('Uncheck any students who should not be promoted') }}</li>
                    <li>{{ __('Click "Promote" — all selected students move to the new class') }}</li>
                </ol>
                <p>{{ __('Promotion records are kept in the audit log for reference.') }}</p>
            </div>
        </section>

        {{-- Settings --}}
        <section id="settings" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('11. School Settings & Branding') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('Go to Management → School Settings to customize:') }}</p>
                <ul>
                    <li><strong>{{ __('General:') }}</strong> {{ __('School name, email, phone, address, motto') }}</li>
                    <li><strong>{{ __('Branding:') }}</strong> {{ __('Logo, primary/secondary/accent colors. These colors are used throughout the portal for your school.') }}</li>
                    <li><strong>{{ __('Portal:') }}</strong> {{ __('Enable/disable features like parent portal, AI quiz generator, AI game generator, and teacher approval requirements.') }}</li>
                </ul>
            </div>
        </section>

        {{-- AI Credits --}}
        <section id="ai-credits" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('12. AI Credits') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>{{ __('Your school receives 15 free AI credits each month (resets on the 1st). Each AI quiz or game generation costs 1 credit. Manual creation is always free.') }}</p>
                <p><strong>{{ __('Purchasing more:') }}</strong> {{ __('Go to Management → AI Credits → "Purchase Credits". Credits are sold in multiples of 5 at ₦1,000 per 5 credits. Purchased credits never expire.') }}</p>
                <p><strong>{{ __('Allocating to levels:') }}</strong> {{ __('Optionally allocate credits to specific school levels so each level has its own budget. Teachers see their level\'s remaining credits when creating content.') }}</p>
            </div>
        </section>

        {{-- Security --}}
        <section id="security" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-3">{{ __('13. Security & Passwords') }}</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p><strong>{{ __('Your password:') }}</strong> {{ __('Change it regularly from your Profile page (click your name in the top-right corner). Enable Two-Factor Authentication (2FA) for extra security.') }}</p>
                <p><strong>{{ __('Student/teacher passwords:') }}</strong> {{ __('When you create accounts, a default password is set. Users can change it after logging in. If someone forgets their password, you can reset it from their edit page.') }}</p>
                <p><strong>{{ __('Deactivated accounts:') }}</strong> {{ __('Deactivated users cannot log in. They see a message explaining why their account was deactivated.') }}</p>
                <p><strong>{{ __('Audit logs:') }}</strong> {{ __('All significant actions (creating students, uploading results, changing settings) are logged under Management → Audit Logs. Use this to track who did what and when.') }}</p>
            </div>
        </section>

        {{-- Need More Help --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 p-6 text-center">
            <h2 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">{{ __('Need More Help?') }}</h2>
            <p class="text-sm text-blue-700 dark:text-blue-300">
                {{ __('Contact your platform administrator for technical support or questions not covered here.') }}
            </p>
        </div>
    </div>
</x-layouts::app>
