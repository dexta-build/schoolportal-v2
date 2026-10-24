<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * "How this page works" guides for the hardest pages: short numbered steps written for people who
 * are not comfortable with computers, plus an optional video the super admin links in Platform Settings.
 *
 * Wording follows the labels on the pages themselves; keep the two in step when a page changes.
 */
class PageHelp
{
    /** topic => Platform Settings key holding its video link */
    public const VIDEO_SETTINGS = [
        'gradebook' => 'help_video_gradebook',
        'cbt-create' => 'help_video_cbt_create',
        'report-cards' => 'help_video_report_cards',
    ];

    public const TOPIC_NAMES = [
        'gradebook' => 'Score Entry (Gradebook)',
        'cbt-create' => 'Creating a CBT',
        'report-cards' => 'Report Cards',
    ];

    /**
     * @return array{title: string, steps: list<string>, tips: list<string>}
     */
    public static function guide(string $topic, string $portal): array
    {
        $teacher = $portal === 'teacher';

        $guide = match ($topic) {
            'gradebook' => [
                'title' => 'How to enter scores',
                'steps' => [
                    $teacher
                        ? 'Choose your class and the session & term at the top. You see the classes where you are the class teacher.'
                        : 'Choose the class and the session & term at the top.',
                    'Each subject has a box for every part of the score (for example CA and Exam) and a Total. The number under each heading is the highest score allowed.',
                    'Click a box and type the score. Press Tab or the arrow keys to move across, and Enter to move down.',
                    'Boxes you have changed turn yellow. Click "Save Scores" — nothing is kept until you save.',
                    'Blue boxes come from CBT. They fill in by themselves when students finish, so you do not type them.',
                    $teacher
                        ? 'When your scores are complete, tell the school admin. The admin locks them so they become final.'
                        : 'When every score is in, click "Lock All" so the scores become final.',
                    $teacher
                        ? 'Your class\'s report cards are made from these scores: open "Report Cards" next.'
                        : 'Next, open "Report Cards" to generate the report cards from these scores.',
                ],
                'tips' => [
                    'On a phone, pick one subject at a time from the "Subject" list.',
                    $teacher
                        ? 'To change a locked score, click it, type the right score and the reason. The school admin approves the change.'
                        : 'To change a locked score, click it, type the right score and the reason. The change is saved and recorded with your name.',
                ],
            ],
            'cbt-create' => [
                'title' => 'How to create a CBT',
                'steps' => [
                    'Choose how to make the questions: "AI Generate" writes them for you from your lesson notes (uses 1 AI credit). "Manual Create" lets you type them yourself and is always free.',
                    'Pick the class and the subject. If the mark should go into Score Entry by itself, choose the part of the score it counts for (for example CA 1) under "Score Component".',
                    'With AI: upload your notes (PDF or Word) or paste a link, choose how many questions, the difficulty and the question types, then click "Generate Questions".',
                    'Read every question on the next screen and fix anything wrong before saving. AI can make mistakes.',
                    'Typing them yourself: add each question, its options and the correct answer. "Import from Bank" reuses questions you saved before.',
                    'Set the time limit, the maximum score, how many attempts students get, and when they can start ("Available From" and "Available Until").',
                    $teacher
                        ? 'Save. It goes to the school admin for approval, and students see it once it is approved.'
                        : 'Save. Students in that class can take it from the "Available From" time.',
                ],
                'tips' => array_values(array_filter([
                    'Leave "Prevent tab switch" on for exams: the CBT submits itself if a student keeps leaving the page.',
                    'Theory and short answers are marked by hand: open the CBT\'s results after students finish.',
                    $teacher ? null : 'If a student misses a CBT or is cut off, use "Let a student retake / resume" in the CBT list.',
                ])),
            ],
            'report-cards' => $teacher ? [
                'title' => 'How report cards work',
                'steps' => [
                    'Make sure all your class\'s scores are saved in Score Entry first.',
                    'Click "Generate Report Cards", choose the report type and click "Generate". They are made from the saved scores.',
                    'Write a comment for each student and click "Submit", or use "Bulk Edit Report Data" to fill in comments and attendance for the whole class on one page.',
                    'Click "Submit All Draft Reports for Approval".',
                    'The school admin checks and approves them, then publishes them. Students and parents only see report cards after they are published.',
                ],
                'tips' => [
                    'Once a report card is approved you cannot edit it. Ask the school admin if something needs to change.',
                ],
            ] : [
                'title' => 'How report cards work',
                'steps' => [
                    'Scores first: all scores should be entered and locked in Score Entry. The "End of Term checklist" shows what is still missing.',
                    'Choose the class and term, then click "Generate Reports". Report cards are made from the saved scores.',
                    'Class teachers add their comments and submit the report cards to you. "Bulk Edit Report Data" fills in comments, attendance and other details for a whole class on one page.',
                    'Check a report card with "View report card" in its ⋯ menu, then click "Approve" — or "Bulk Approve" for the whole class.',
                    'Click "Publish". Students and parents only see report cards after this.',
                    '"Download All PDFs" gives you every report card in the class, ready to print.',
                ],
                'tips' => [
                    'If a score is corrected after report cards are made, click "Update report cards" in Score Entry. Published report cards stay published and their earlier version is kept.',
                    '"Result Sheets (PDF)" is a different page: it is for uploading result sheets made outside the portal.',
                ],
            ],
            default => throw new \InvalidArgumentException("Unknown help topic [{$topic}]."),
        };

        return [
            'title' => __($guide['title']),
            'steps' => array_map(fn (string $line) => __($line), $guide['steps']),
            'tips' => array_map(fn (string $line) => __($line), $guide['tips']),
        ];
    }

    /**
     * The embeddable player link for a topic's video, or null when none is set.
     */
    public static function videoEmbedUrl(string $topic): ?string
    {
        $setting = self::VIDEO_SETTINGS[$topic] ?? null;

        return $setting ? self::embedUrl((string) PlatformSetting::get($setting, '')) : null;
    }

    /**
     * Turns a YouTube or Vimeo link (watch, share, shorts or embed) into its embed link.
     * Returns null for anything else, so only those two players are ever framed.
     */
    public static function embedUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $patterns = [
            '~^https?://(?:www\.|m\.)?youtube\.com/watch\?(?:.*&)?v=([\w-]{11})~' => 'https://www.youtube-nocookie.com/embed/%s?rel=0',
            '~^https?://youtu\.be/([\w-]{11})~' => 'https://www.youtube-nocookie.com/embed/%s?rel=0',
            '~^https?://(?:www\.)?youtube(?:-nocookie)?\.com/(?:embed|shorts|live)/([\w-]{11})~' => 'https://www.youtube-nocookie.com/embed/%s?rel=0',
            '~^https?://(?:www\.)?vimeo\.com/(?:video/)?(\d+)~' => 'https://player.vimeo.com/video/%s',
            '~^https?://player\.vimeo\.com/video/(\d+)~' => 'https://player.vimeo.com/video/%s',
        ];

        foreach ($patterns as $pattern => $embed) {
            if (preg_match($pattern, $url, $match)) {
                return sprintf($embed, $match[1]);
            }
        }

        return null;
    }
}
