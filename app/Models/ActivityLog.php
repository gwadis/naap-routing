<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
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

    public static function log($action, $documentId = null, array $meta = [])
    {
        try {
            $request = request();
            $userAgent = $request->header('User-Agent');
            [$browser, $os] = self::parseUserAgent($userAgent);

            $userId = session('user_id') ?? auth()->id();
            if ($userId) {
                $userModel = User::find($userId);
                if ($userModel) {
                    $meta['department'] = $userModel->department?->name ?? 'System';
                }
            }

            $logData = [
                'user' => session('user_name') ?? (auth()->user()?->name ?? 'Guest'),
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

    public function document() { return $this->belongsTo(Document::class); }
}
