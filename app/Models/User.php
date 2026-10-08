<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Department;

class User extends Authenticatable
{
    use Notifiable;

    // Valid roles
    const ROLE_SUPER_ADMIN   = 'Super Administrator';
    const ROLE_ADMIN         = 'Administrator';
    const ROLE_OFFICE_HEAD   = 'Office Head';
    const ROLE_STAFF         = 'Staff';
    const ROLE_EMPLOYEE      = 'Employee';
    const ROLE_LEGACY_ADMIN  = 'ADMIN';
    const ROLE_LEGACY_USER   = 'USER';

    protected $fillable = [
        'name', 'email', 'employee_id', 'position', 'username', 'password', 'role', 'department_id', 'office_id', 'signature', 'phone', 'avatar', 'status', 'two_factor_secret', 'two_factor_confirmed_at',
        'failed_login_attempts', 'locked_until', 'needs_password_change', 'must_change', 'login_otp', 'login_otp_expires_at', 'login_otp_sent_at', 'recovery_email',
        'telegram_chat_id', 'telegram_username', 'telegram_connected_at', 'telegram_connection_status',
        'telegram_connect_token', 'telegram_connect_token_expires_at',
        'telegram_notif_announcements', 'telegram_notif_documents', 'telegram_notif_urgent',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
        'login_otp_expires_at' => 'datetime',
        'login_otp_sent_at' => 'datetime',
        'needs_password_change' => 'boolean',
        'telegram_connected_at' => 'datetime',
        'telegram_connect_token_expires_at' => 'datetime',
        'telegram_notif_announcements' => 'boolean',
        'telegram_notif_documents' => 'boolean',
        'telegram_notif_urgent' => 'boolean',
    ];

    public function isTelegramConnected(): bool
    {
        return $this->telegram_connection_status === 'connected' && !empty($this->telegram_chat_id);
    }

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function setPasswordAttribute($value)
    {
        if ($value === null) {
            return;
        }

        $this->attributes['password'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    /**
     * Accessor and mutator for must_change alias mapping to needs_password_change.
     */
    public function getMustChangeAttribute(): bool
    {
        return (bool) ($this->needs_password_change ?? false);
    }

    public function setMustChangeAttribute($value): void
    {
        $this->attributes['needs_password_change'] = (bool) $value;
    }

    /**
     * Route notifications for the mail channel.
     * Always resolves strictly to this user's registered designated email address.
     *
     * @param  \Illuminate\Notifications\Notification|null  $notification
     * @return string|null
     */
    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->email;
    }

    /**
     * Get the e-mail address where password reset links are sent.
     *
     * @return string
     */
    public function getEmailForPasswordReset(): string
    {
        return (string) $this->email;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }


    protected static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            if (!$model->role || !in_array($model->role, self::getValidRoles())) {
                $model->role = self::ROLE_EMPLOYEE;
            }
        });

        self::updating(function ($model) {
            if ($model->role && !in_array($model->role, self::getValidRoles())) {
                $model->role = self::ROLE_EMPLOYEE;
            }
        });
    }

    public static function getValidRoles()
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_OFFICE_HEAD,
            self::ROLE_STAFF,
            self::ROLE_EMPLOYEE,
            self::ROLE_LEGACY_ADMIN,
            self::ROLE_LEGACY_USER,
        ];
    }

    /**
     * Check if a role string represents an administrator.
     */
    public static function isRoleAdmin($role): bool
    {
        if (!$role) {
            return false;
        }
        $normalized = strtoupper(trim(str_replace(['_', '-'], ' ', (string) $role)));
        return in_array($normalized, [
            'ADMIN',
            'ADMINISTRATOR',
            'SUPER ADMINISTRATOR',
            'SUPER ADMIN',
            'SUPERADMIN',
        ]) || in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_LEGACY_ADMIN]);
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return self::isRoleAdmin($this->role ?? session('user_role'))
            || in_array(strtolower($this->username ?? ''), ['admin', 'vpaa'])
            || ($this->email ?? '') === 'vpaa@naap.org';
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        $normalized = strtoupper(trim(str_replace(['_', '-'], ' ', (string) ($this->role ?? session('user_role')))));
        return in_array($normalized, ['SUPER ADMINISTRATOR', 'SUPER ADMIN', 'SUPERADMIN'])
            || $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Check if user is a regular user.
     */
    public function isUser(): bool
    {
        return !$this->isAdmin();
    }

    /**
     * Check if user is an Office Head.
     * Evaluates role, position, or explicit head assignment.
     */
    public function isOfficeHead(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        $role = strtoupper(trim(str_replace(['_', '-'], ' ', (string) ($this->role ?? session('user_role')))));
        if ($role === 'OFFICE HEAD' || str_contains($role, 'OFFICE HEAD') || str_contains($role, 'HEAD')) {
            return true;
        }

        $pos = strtoupper(trim(str_replace(['_', '-'], ' ', (string) ($this->position ?? ''))));
        if ($pos && (
            str_contains($pos, 'HEAD') ||
            str_contains($pos, 'DIRECTOR') ||
            str_contains($pos, 'CHAIR') ||
            str_contains($pos, 'DEAN') ||
            str_contains($pos, 'CHIEF')
        )) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is Staff or regular Office Personnel / Employee.
     */
    public function isStaff(): bool
    {
        if ($this->isAdmin() || $this->isOfficeHead()) {
            return false;
        }

        return true;
    }

    /**
     * Define the relationship to the Department model.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Define the relationship to the Office model.
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    /**
     * Determine if the user has a valid signature file or data.
     */
    public function hasValidSignature(): bool
    {
        if (empty($this->signature)) {
            return false;
        }

        if (str_starts_with((string) $this->signature, 'data:image')) {
            return true;
        }

        $cleanPath = ltrim(str_replace(['public/', 'storage/'], '', (string) $this->signature), '/');

        return Storage::disk('public')->exists($cleanPath);
    }

    /**
     * Get the accessible public URL or base64 data for the digital signature.
     */
    public function getSignatureUrlAttribute(): ?string
    {
        if (!$this->hasValidSignature()) {
            return null;
        }

        if (str_starts_with((string) $this->signature, 'data:image')) {
            return $this->signature;
        }

        $cleanPath = ltrim(str_replace(['public/', 'storage/'], '', (string) $this->signature), '/');

        return asset('storage/' . $cleanPath);
    }
}