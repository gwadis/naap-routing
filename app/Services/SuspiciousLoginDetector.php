<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditTrail;
use App\Models\LoginHistory;
use App\Mail\SecurityMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SuspiciousLoginDetector
{
    public static function detect($user, $request)
    {
        $ip = $request->ip() ?? '127.0.0.1';
        $userAgent = $request->header('User-Agent', '');
        [$browser, $os, $device] = AuditTrail::parseUserAgentExtended($userAgent);

        // Fetch last successful login
        $lastLogin = DB::table('login_histories')
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastLogin) {
            $suspicious = false;
            $reasons = [];

            if ($lastLogin->ip_address !== $ip) {
                $suspicious = true;
                $reasons[] = "Unusual IP address change (from {$lastLogin->ip_address} to {$ip})";
            }
            if ($lastLogin->device !== $device) {
                $suspicious = true;
                $reasons[] = "Unusual device change (from {$lastLogin->device} to {$device})";
            }

            if ($suspicious) {
                $reasonStr = implode(', ', $reasons);
                Log::channel('security')->warning("Suspicious login detected for user {$user->email}: {$reasonStr}");

                // Save Audit Trail
                AuditTrail::log(
                    "Suspicious Login Detected: {$reasonStr}",
                    "users/{$user->id}",
                    ['ip' => $lastLogin->ip_address, 'device' => $lastLogin->device],
                    ['ip' => $ip, 'device' => $device]
                );

                // Notify Administrators
                $admins = User::whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_LEGACY_ADMIN])->get();
                $body = "<p>Suspicious login activity was detected for account <strong>{$user->name}</strong> ({$user->email}).</p>"
                      . "<p><strong>Anomaly reasons:</strong> {$reasonStr}</p>"
                      . "<ul>"
                      . "<li><strong>IP Address:</strong> {$ip}</li>"
                      . "<li><strong>Browser:</strong> {$browser}</li>"
                      . "<li><strong>OS/Device:</strong> {$os} / {$device}</li>"
                      . "<li><strong>Time:</strong> " . now()->format('Y-m-d H:i:s') . "</li>"
                      . "</ul>";

                foreach ($admins as $admin) {
                    try {
                        Mail::to($admin->email)->send(new SecurityMail(
                            "🛡️ Security Alert: Suspicious Login Detected",
                            $admin->name,
                            $body
                        ));
                    } catch (\Exception $e) {
                        Log::channel('email')->error("Failed to notify Admin {$admin->email}: " . $e->getMessage());
                    }
                }

                // Notify User
                try {
                    Mail::to($user->email)->send(new SecurityMail(
                        "⚠️ Security Alert: Login from New Device or IP",
                        $user->name,
                        "<p>A login was detected on your NAAP Routing account from a new location or device.</p>"
                        . "<p><strong>Details:</strong></p>"
                        . "<ul>"
                        . "<li><strong>IP Address:</strong> {$ip}</li>"
                        . "<li><strong>Device:</strong> {$device} ({$os})</li>"
                        . "<li><strong>Time:</strong> " . now()->format('Y-m-d H:i:s') . "</li>"
                        . "</ul>"
                        . "<p>If this was you, you can safely ignore this email. If this wasn't you, please change your password immediately and contact your administrator.</p>"
                    ));
                } catch (\Exception $e) {
                    Log::channel('email')->error("Failed to notify User {$user->email}: " . $e->getMessage());
                }

                // Trigger website system notification
                $user->notify(new \App\Notifications\SystemNotification(
                    'Security',
                    "Suspicious login detected for your account from IP {$ip} / device {$device}."
                ));
            }
        }
    }
}
