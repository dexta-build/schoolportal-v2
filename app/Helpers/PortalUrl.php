<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\School;

/**
 * Links in notifications must open on the school's own domain.
 *
 * Notifications are built in the queue worker, where url() falls back to APP_URL (the
 * platform domain). A user on portal.myschool.com sent to the platform domain is logged
 * out there, and can't log in without picking their school.
 */
class PortalUrl
{
    /**
     * Absolute link for an email to a school user, on their school's domain when it has one.
     */
    public static function forRecipient(object $notifiable, string $path): string
    {
        $school = $notifiable->school ?? null;

        return self::forSchool($school instanceof School ? $school : null, $path);
    }

    public static function forSchool(?School $school, string $path): string
    {
        if ($school?->custom_domain) {
            return 'https://'.$school->custom_domain.'/'.ltrim($path, '/');
        }

        return url($path);
    }

    /**
     * The path of a stored notification link, so opening it keeps the user on the domain
     * they are using. Also means a stored link can never redirect off-site.
     */
    public static function local(?string $url): string
    {
        if (! $url || $url === '#') {
            return '#';
        }

        $parts = parse_url($url);
        $path = '/'.ltrim($parts['path'] ?? '', '/');

        return $path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
