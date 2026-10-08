<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user',
        'action',
        'document_id',
        'ip',
        'browser',
        'os',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($log) {
            // Automatically resolve user_id if not explicitly provided
            if (empty($log->user_id)) {
                $resolvedId = session('user_id') ?? auth()->id();
                if ($resolvedId) {
                    $log->user_id = $resolvedId;
                }
            }

            // Resolve authoritative user name
            if (empty($log->user)) {
                $userModel = !empty($log->user_id) ? User::find($log->user_id) : null;
                $log->user = session('user_name') ?? ($userModel?->name ?? (auth()->user()?->name ?? 'System'));
            }

            // Standardize meta payload with server-side actor info and timestamp
            $meta = is_array($log->meta) ? $log->meta : (json_decode($log->meta, true) ?? []);
            if (!isset($meta['actor_user_id'])) {
                $meta['actor_user_id'] = $log->user_id;
            }
            if (!isset($meta['actor_type'])) {
                $actorUser = !empty($log->user_id) ? User::find($log->user_id) : null;
                $meta['actor_type'] = $actorUser ? ($actorUser->isAdmin() ? 'admin' : 'user') : ($log->user_id ? 'user' : 'system');
            }
            if (!isset($meta['timestamp'])) {
                $meta['timestamp'] = now()->toIso8601String();
            }
            $log->meta = $meta;
        });
    }

    public static function log($action, $documentId = null, array $meta = [], $userId = null)
    {
        try {
            $request = request();
            $userAgent = $request ? $request->header('User-Agent') : null;
            [$browser, $os] = self::parseUserAgent($userAgent);

            $resolvedUserId = $userId ?? (session('user_id') ?? auth()->id());
            $userModel = $resolvedUserId ? User::find($resolvedUserId) : null;

            if ($userModel && !isset($meta['department'])) {
                $meta['department'] = $userModel->department?->name ?? 'System';
            }
            if ($userModel && !isset($meta['office'])) {
                $meta['office'] = $userModel->office?->name ?? null;
            }

            $meta['actor_user_id'] = $resolvedUserId;
            $meta['actor_type'] = $userModel ? ($userModel->isAdmin() ? 'admin' : 'user') : ($resolvedUserId ? 'user' : 'system');
            $meta['timestamp'] = now()->toIso8601String();

            $logData = [
                'user_id' => $resolvedUserId,
                'user' => session('user_name') ?? ($userModel?->name ?? (auth()->user()?->name ?? 'System')),
                'action' => $action,
                'document_id' => $documentId,
                'ip' => 'REDACTED',
                'browser' => $browser,
                'os' => $os,
                'meta' => $meta,
            ];

            if (\Illuminate\Support\Facades\Schema::hasTable('activity_logs')) {
                $columns = \Illuminate\Support\Facades\Schema::getColumnListing('activity_logs');
                $logData = array_intersect_key($logData, array_flip($columns));
                return self::create($logData);
            }

            return null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ActivityLog failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function parseUserAgent($userAgentString)
    {
        $os = "Unknown OS";
        $browser = "Unknown Browser";

        if (empty($userAgentString)) {
            return [$browser, $os];
        }

        if (preg_match('/windows|win32/i', $userAgentString)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgentString)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $userAgentString)) {
            $os = 'Linux';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgentString)) {
            $os = 'iOS';
        } elseif (preg_match('/android/i', $userAgentString)) {
            $os = 'Android';
        }

        if (preg_match('/msie|trident/i', $userAgentString)) {
            $browser = 'Internet Explorer';
        } elseif (preg_match('/firefox/i', $userAgentString)) {
            $browser = 'Firefox';
        } elseif (preg_match('/chrome/i', $userAgentString)) {
            $browser = 'Chrome';
        } elseif (preg_match('/safari/i', $userAgentString)) {
            $browser = 'Safari';
        } elseif (preg_match('/opera|opr/i', $userAgentString)) {
            $browser = 'Opera';
        } elseif (preg_match('/edge/i', $userAgentString)) {
            $browser = 'Edge';
        }

        return [$browser, $os];
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getUserAttribute($value)
    {
        return $value ?? $this->actor?->name ?? 'System';
    }

    public function getActorUserIdAttribute()
    {
        return $this->user_id;
    }

    public function setActorUserIdAttribute($value)
    {
        $this->attributes['user_id'] = $value;
    }
}
