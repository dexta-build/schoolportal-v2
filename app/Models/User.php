<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasCloudinaryImages;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, BelongsToTenant, HasCloudinaryImages, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'username',
        'password',
        'role',
        'level_id',
        'avatar_url',
        'phone',
        'gender',
        'is_active',
        'is_anonymized',
        'deactivation_reason',
        'deactivated_at',
        'must_change_password',
        'dashboard_preferences',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_anonymized' => 'boolean',
            'deactivated_at' => 'datetime',
            'must_change_password' => 'boolean',
            'school_id' => 'integer',
            'level_id' => 'integer',
            'dashboard_preferences' => 'array',
        ];
    }

    // ── Role Checks ──

    /**
     * Users are always created by platform admins, never through self-registration.
     * Auto-verify email on creation so the `verified` middleware never blocks access.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->email_verified_at ??= now();
        });

        static::saved(function (self $user): void {
            if ($user->school_id) {
                Cache::forget("school:{$user->school_id}:dashboard:stats");
            }
        });

        static::deleted(function (self $user): void {
            if ($user->school_id) {
                Cache::forget("school:{$user->school_id}:dashboard:stats");
            }
        });
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isSchoolAdmin(): bool
    {
        return $this->role === 'school_admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'school_admin']);
    }

    // ── Relationships ──

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'level_id');
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    /**
     * Classes this teacher is assigned to.
     */
    public function assignedClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'teacher_id');
    }

    /**
     * Children linked to this parent.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ParentStudent::class, 'parent_id');
    }

    /**
     * Parents linked to this student (ParentStudent pivot records).
     */
    public function parents(): HasMany
    {
        return $this->hasMany(ParentStudent::class, 'student_id');
    }

    /**
     * Parent Users linked to this student (through the pivot table).
     */
    public function parentUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id');
    }

    /**
     * Achievements earned by this student.
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(StudentAchievement::class, 'student_id');
    }

    // ── Helpers ──

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get dashboard widget preferences with defaults for any missing widgets.
     *
     * @return array<int, array{id: string, visible: bool}>
     */
    public function getDashboardWidgets(): array
    {
        $defaults = self::defaultDashboardWidgets();
        $saved = $this->dashboard_preferences['widgets'] ?? null;

        if (! $saved) {
            return $defaults;
        }

        $validIds = array_column($defaults, 'id');
        $savedIds = array_column($saved, 'id');

        // Append any new widgets that weren't in saved preferences
        foreach ($defaults as $default) {
            if (! in_array($default['id'], $savedIds, true)) {
                $saved[] = $default;
            }
        }

        // Remove any widget IDs that no longer exist
        return array_values(array_filter($saved, fn (array $w): bool => in_array($w['id'], $validIds, true)));
    }

    /**
     * @return array<int, array{id: string, visible: bool}>
     */
    public static function defaultDashboardWidgets(): array
    {
        return [
            ['id' => 'alerts', 'visible' => true],
            ['id' => 'primary_stats', 'visible' => true],
            ['id' => 'term_stats', 'visible' => true],
            ['id' => 'quick_actions', 'visible' => true],
            ['id' => 'approvals_activity', 'visible' => true],
            ['id' => 'analytics_link', 'visible' => true],
        ];
    }

    // ── Cloudinary Image Accessors ──

    /** For nav menus and small header avatars (displayed ~40px, 80×80 for 2× retina). */
    public function avatarThumbUrl(): string
    {
        return self::cloudinaryTransform($this->avatar_url, 'w_80,h_80,c_fill,g_face,f_auto,q_auto');
    }

    /** For list tables and small overview cards (displayed ~32–88px). */
    public function avatarTableUrl(): string
    {
        return self::cloudinaryTransform($this->avatar_url, 'w_200,h_200,c_fill,g_face,f_auto,q_auto');
    }

    /** For profile/show pages (displayed ≥64px). */
    public function avatarProfileUrl(): string
    {
        return self::cloudinaryTransform($this->avatar_url, 'w_400,h_400,c_fill,g_face,f_auto,q_auto');
    }
}
