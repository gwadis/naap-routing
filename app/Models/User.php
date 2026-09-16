<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
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
        'failed_login_attempts', 'locked_until', 'needs_password_change', 'login_otp', 'login_otp_expires_at', 'login_otp_sent_at', 'recovery_email',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
        'login_otp_expires_at' => 'datetime',
        'login_otp_sent_at' => 'datetime',
        'needs_password_change' => 'boolean',
    ];

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
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_LEGACY_ADMIN]);
    }

    /**
     * Check if user is a regular user.
     */
    public function isUser(): bool
    {
        return !in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_LEGACY_ADMIN]);
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
}