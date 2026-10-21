<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Ensure config cache never overrides test environment variables
        $configCachePath = __DIR__.'/../bootstrap/cache/config.php';

        if (file_exists($configCachePath)) {
            unlink($configCachePath);
        }

        parent::setUp();

        // Opt-in: DB_STRINGIFY_FETCHES=1 php artisan test
        // Some hosting MySQL drivers return every column as a string. Uncast IDs then break
        // strictly typed code — which Livewire hides in production as "This page has expired".
        if (getenv('DB_STRINGIFY_FETCHES')) {
            DB::connection()->getPdo()->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
