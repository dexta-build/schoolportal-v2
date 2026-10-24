<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Runs every score-correction test again with the database driver returning all columns as
 * strings, as some shared-hosting MySQL drivers do. Uncast foreign keys then arrive as "12",
 * not 12, and strictly typed code throws a TypeError — which Livewire hides in production
 * as a "This page has expired" (419) dialog after the change has already been saved.
 */
class ScoreCorrectionStringDriverTest extends ScoreCorrectionTest
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->getPdo()->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    }
}
