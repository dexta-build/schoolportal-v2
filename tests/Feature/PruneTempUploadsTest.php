<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneTempUploadsTest extends TestCase
{
    public function test_deletes_only_abandoned_temp_uploads(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $disk->put('imports/students_old.csv', 'name,username');
        $disk->put('bulk-results/old.pdf', 'pdf');
        $disk->put('imports/students_new.csv', 'name,username');
        $disk->put('archive/school_1/session_1.json', '{}');

        $twoDaysAgo = now()->subDays(2)->getTimestamp();
        foreach (['imports/students_old.csv', 'bulk-results/old.pdf', 'archive/school_1/session_1.json'] as $file) {
            touch($disk->path($file), $twoDaysAgo);
        }

        $this->artisan('storage:prune-temp')
            ->expectsOutput('Deleted 2 abandoned temporary upload file(s).')
            ->assertSuccessful();

        $disk->assertMissing('imports/students_old.csv');
        $disk->assertMissing('bulk-results/old.pdf');
        // An import still in its preview step is kept
        $disk->assertExists('imports/students_new.csv');
        // Session archive exports are not temporary and are never touched
        $disk->assertExists('archive/school_1/session_1.json');
    }
}
