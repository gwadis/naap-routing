<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    protected $fillable = [
        'user',
        'action',
        'ip_address',
        'browser',
        'os',
        'device',
        'affected_record',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public static function log($action, $affectedRecord = null, $oldValues = null, $newValues = null)
    {
        $request = request();
        $userAgent = $request->header('User-Agent', '');

        [$browser, $os, $device] = self::parseUserAgentExtended($userAgent);

        return self::create([
            'user' => session('user_name') ?? (auth()->user()?->name ?? 'Guest'),
            'action' => $action,
            'ip_address' => 'REDACTED',
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'affected_record' => $affectedRecord,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public static function parseUserAgentExtended($userAgentString)
    {
        $os = "Unknown OS";
        $browser = "Unknown Browser";
        $device = "Desktop";

        if (empty($userAgentString)) {
            return [$browser, $os, $device];
        }

        // Detect OS
        if (preg_match('/windows|win32/i', $userAgentString)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgentString)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $userAgentString)) {
            $os = 'Linux';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgentString)) {
            $os = 'iOS';
            $device = 'Mobile/Tablet';
        } elseif (preg_match('/android/i', $userAgentString)) {
            $os = 'Android';
            $device = 'Mobile/Tablet';
        }

        // Detect Browser
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

        // Detect Mobile Device specifics
        if (preg_match('/mobile/i', $userAgentString)) {
            $device = 'Mobile';
        }
        if (preg_match('/tablet|ipad/i', $userAgentString)) {
            $device = 'Tablet';
        }

        return [$browser, $os, $device];
    }
}
