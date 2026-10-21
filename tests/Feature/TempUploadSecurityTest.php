<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ImportStudentsCsvJob;
use App\Models\AcademicSession;
use App\Models\Result;
use App\Models\StudentProfile;
use App\Models\Term;
use App\Models\User;
use App\Services\CsvImportService;
use App\Services\FileUploadService;
use App\Services\TempUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * Temporary preview uploads (bulk result PDFs, student CSV imports) must never clash between
 * uploads, and the confirm step must only accept the current school's own preview files.
 */
class TempUploadSecurityTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private User $student;

    private AcademicSession $session;

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();
        Storage::fake('local');

        $this->session = AcademicSession::withoutGlobalScopes()->where('school_id', $this->school->id)->firstOrFail();
        $this->term = Term::withoutGlobalScopes()->where('school_id', $this->school->id)->firstOrFail();

        $this->student = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'student',
            'username' => 'john',
            'level_id' => $this->level->id,
        ]);

        StudentProfile::create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
        ]);
    }

    public function test_same_named_result_pdfs_from_two_uploads_do_not_overwrite_each_other(): void
    {
        $first = $this->previewBulkResult(UploadedFile::fake()->createWithContent('john.pdf', 'FIRST UPLOAD'));
        $second = $this->previewBulkResult(UploadedFile::fake()->createWithContent('john.pdf', 'SECOND UPLOAD'));

        $this->assertNotSame($first, $second);
        $this->assertSame('FIRST UPLOAD', Storage::disk('local')->get($first));
        $this->assertSame('SECOND UPLOAD', Storage::disk('local')->get($second));
        $this->assertStringStartsWith("bulk-results/school_{$this->school->id}/", $first);
    }

    public function test_confirm_uploads_this_schools_preview_file_and_cleans_it_up(): void
    {
        $tempPath = $this->previewBulkResult(UploadedFile::fake()->createWithContent('john.pdf', '%PDF result'));

        $this->mock(FileUploadService::class)
            ->shouldReceive('uploadResultFromPath')->once()
            ->andReturn(['url' => 'https://res.cloudinary.com/demo/raw/upload/john.pdf', 'public_id' => 'john']);

        $this->confirmBulkResult($tempPath)->assertRedirect(route('admin.results.index'));

        $this->assertSame(1, Result::where('student_id', $this->student->id)->count());
        Storage::disk('local')->assertMissing($tempPath);
    }

    public function test_confirm_rejects_a_path_outside_the_temp_folder(): void
    {
        $this->mock(FileUploadService::class)->shouldNotReceive('uploadResultFromPath');

        // Tampered hidden field trying to publish the server's .env as a "result"
        $this->confirmBulkResult('../../.env')->assertRedirect(route('admin.results.index'));

        $this->assertSame(0, Result::count());
    }

    public function test_confirm_rejects_another_schools_preview_file(): void
    {
        $otherSchoolPath = 'bulk-results/school_'.($this->school->id + 1).'/'.Str::uuid().'/john.pdf';
        Storage::disk('local')->put($otherSchoolPath, '%PDF other school');

        $this->mock(FileUploadService::class)->shouldNotReceive('uploadResultFromPath');

        $this->confirmBulkResult($otherSchoolPath)->assertRedirect(route('admin.results.index'));

        $this->assertSame(0, Result::count());
        Storage::disk('local')->assertExists($otherSchoolPath);
    }

    public function test_student_import_confirm_rejects_a_tampered_path(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.students.import.store'), [
                'temp_path' => '../../.env',
                'default_password' => 'Secret123',
            ])
            ->assertRedirect(route('admin.students.import'))
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_student_import_preview_and_confirm_use_a_per_upload_folder(): void
    {
        Queue::fake();

        $csv = UploadedFile::fake()->createWithContent('students.csv', "name,username,gender,class\nAda Obi,ada.obi,female,{$this->class->name}\n");

        $tempPath = $this->actingAs($this->admin)
            ->post(route('admin.students.import.preview'), ['csv_file' => $csv, 'default_password' => 'Secret123'])
            ->assertOk()
            ->viewData('tempPath');

        $this->assertTrue(app(TempUploadService::class)->belongsTo($tempPath, TempUploadService::TYPE_STUDENT_IMPORT, $this->school->id));

        $this->actingAs($this->admin)
            ->post(route('admin.students.import.store'), ['temp_path' => $tempPath, 'default_password' => 'Secret123'])
            ->assertRedirect(route('admin.students.index'));

        Queue::assertPushed(ImportStudentsCsvJob::class);
    }

    public function test_import_job_does_not_leave_its_school_bound_for_the_next_queued_job(): void
    {
        $batch = app(TempUploadService::class)->newBatch(TempUploadService::TYPE_STUDENT_IMPORT, $this->school->id);
        Storage::disk('local')->put("{$batch}/students.csv", "name,username,gender,class\nAda Obi,ada.obi,female,{$this->class->name}\n");

        // A queue worker process starts with no school bound
        app()->forgetInstance('current.school');

        (new ImportStudentsCsvJob($this->school->id, "{$batch}/students.csv", 'Secret123', 'test-key'))
            ->handle(app(CsvImportService::class));

        $this->assertFalse(app()->bound('current.school'), 'The next job in the same worker must not inherit this school');
        $this->assertTrue(User::withoutGlobalScopes()->where('username', 'ada.obi')->exists());
    }

    public function test_belongs_to_only_accepts_the_schools_own_batch_files(): void
    {
        $service = app(TempUploadService::class);
        $type = TempUploadService::TYPE_BULK_RESULTS;
        $id = $this->school->id;
        $uuid = Str::uuid()->toString();

        $this->assertTrue($service->belongsTo("bulk-results/school_{$id}/{$uuid}/john.pdf", $type, $id));

        $this->assertFalse($service->belongsTo('../../.env', $type, $id));
        $this->assertFalse($service->belongsTo("bulk-results/school_{$id}/{$uuid}/../../../.env", $type, $id));
        $this->assertFalse($service->belongsTo("bulk-results/school_{$id}/{$uuid}/..", $type, $id));
        $this->assertFalse($service->belongsTo('bulk-results/school_'.($id + 1)."/{$uuid}/john.pdf", $type, $id));
        $this->assertFalse($service->belongsTo("imports/school_{$id}/{$uuid}/john.pdf", $type, $id));
        $this->assertFalse($service->belongsTo('bulk-results/john.pdf', $type, $id));
        $this->assertFalse($service->belongsTo("/bulk-results/school_{$id}/{$uuid}/john.pdf", $type, $id));
    }

    private function previewBulkResult(UploadedFile $file): string
    {
        $matches = $this->actingAs($this->admin)
            ->post(route('admin.results.bulk.preview'), [
                'class_id' => $this->class->id,
                'session_id' => $this->session->id,
                'term_id' => $this->term->id,
                'result_files' => [$file],
            ])
            ->assertOk()
            ->viewData('matches');

        $this->assertCount(1, $matches);

        return $matches[0]['temp_path'];
    }

    private function confirmBulkResult(string $tempPath): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.results.bulk.store'), [
                'class_id' => $this->class->id,
                'session_id' => $this->session->id,
                'term_id' => $this->term->id,
                'imports' => [['student_id' => $this->student->id, 'temp_path' => $tempPath]],
            ]);
    }
}
