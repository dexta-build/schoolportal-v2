<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\FileUsageAlertNotification;
use App\Services\FileUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CheckFileUsage extends Command
{
    protected $signature = 'health:file-usage';

    protected $description = 'Count files on the hosting account and alert when nearing the inode limit';

    public function handle(FileUsageService $service): int
    {
        $usage = $service->scan();
        $threshold = (float) config('services.platform.file_alert_percent', 80);

        $this->info(sprintf(
            'File usage: %s / %s (%s%%) in %s',
            number_format($usage['total']),
            number_format($usage['limit']),
            $usage['percent'],
            $service->scanPath(),
        ));

        foreach ($usage['folders'] as $folder => $count) {
            $this->line(sprintf('  %10s  %s', number_format($count), $folder));
        }

        if ($usage['percent'] >= $threshold) {
            $this->sendAlert($usage);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{total: int, limit: int, percent: float, folders: array<string, int>, scanned_at: string}  $usage
     */
    private function sendAlert(array $usage): void
    {
        Log::warning('Hosting file usage is high.', $usage);

        $alertEmail = config('services.platform.alert_email');

        // At most one email per day
        if (! $alertEmail || ! Cache::add('health:file_usage_alerted', true, now()->addDay())) {
            return;
        }

        try {
            Notification::route('mail', $alertEmail)->notify(new FileUsageAlertNotification($usage));
        } catch (Throwable $e) {
            Log::error('Failed to send file usage alert', ['error' => $e->getMessage()]);
        }
    }
}
