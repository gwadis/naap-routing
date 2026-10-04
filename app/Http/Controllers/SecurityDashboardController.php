<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditTrail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SecurityDashboardController extends Controller
{
    public function index()
    {
        $role = session('user_role') ?? auth()->user()?->role;
        $user = auth()->user() ?? User::find(session('user_id'));
        $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($role);

        if (!$isAdmin) {
            abort(403, 'Unauthorized.');
        }

        $today = now()->toDateString();

        // 1. PRIMARY SECURITY KPIS
        $todayLogins = DB::table('login_histories')->whereDate('login_time', $today)->count();
        $todayFailed = DB::table('login_failures')->whereDate('created_at', $today)->count();
        $lockedAccounts = User::whereNotNull('locked_until')->where('locked_until', '>', now())->count();
        
        // Active Sessions in last 5 minutes
        $onlineUsers = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->count();

        // 2. SUBSYSTEM HEALTH & INTEGRITY CHECKS
        // Document PINs & Protected Document Access
        $confidentialDocsCount = DB::table('documents')->where('is_confidential', 1)->count();
        $pinsIssuedToday = DB::table('document_pins')->whereDate('created_at', $today)->count();
        $pinsVerifiedToday = DB::table('document_pins')
            ->where(function($q) use ($today) {
                $q->whereDate('used_at', $today)
                  ->orWhere(function($sub) use ($today) {
                      $sub->whereDate('updated_at', $today)->where('is_used', 1);
                  });
            })->count();
        $pinsFailedToday = DB::table('document_pins')
            ->whereDate('updated_at', $today)
            ->where('attempts', '>', 0)
            ->count();
        $pinsFailedTotal = DB::table('document_pins')->where('attempts', '>', 0)->count();
        $totalPinsVerified = DB::table('document_pins')->where('is_used', 1)->count();

        // QR Verification Security Metrics
        $qrScannedToday = DB::table('activity_logs')
            ->whereDate('created_at', $today)
            ->where('action', 'like', '%QR%')
            ->where('action', 'not like', '%Fail%')
            ->count();
        $qrFailedToday = DB::table('activity_logs')
            ->whereDate('created_at', $today)
            ->where('action', 'like', '%QR%')
            ->where('action', 'like', '%Fail%')
            ->count();
        $qrTotalVerified = DB::table('activity_logs')
            ->where(function($q) {
                $q->where('action', 'like', '%QR%')
                  ->orWhere('action', 'like', '%QR Code Verified%');
            })
            ->where('action', 'not like', '%Fail%')
            ->count();
        $qrTotalFailed = DB::table('activity_logs')
            ->where('action', 'like', '%QR%')
            ->where('action', 'like', '%Fail%')
            ->count();
        $confidentialDocsWithQr = DB::table('documents')
            ->where('is_confidential', 1)
            ->whereNotNull('qr_id')
            ->count();

        // OTP Security Metrics
        $otpRequestsToday = DB::table('otps')->whereDate('created_at', $today)->count() + $pinsIssuedToday;
        $otpSuccessToday = DB::table('otps')->whereDate('updated_at', $today)->where('is_used', 1)->count() + $pinsVerifiedToday;
        $otpFailedToday = DB::table('otps')->whereDate('updated_at', $today)->where('attempts', '>', 0)->count() + $pinsFailedToday;
        $otpExpiredToday = DB::table('otps')
            ->whereDate('expires_at', $today)
            ->where('expires_at', '<', now())
            ->where('is_used', 0)
            ->count()
            + DB::table('document_pins')
                ->whereDate('expires_at', $today)
                ->where('expires_at', '<', now())
                ->where('is_used', 0)
                ->count();

        // Compute Subsystem Health based on real data
        $authHealth = ($lockedAccounts > 0 || $todayFailed >= 5) ? 'Attention Required' : 'Healthy';
        $docAccessHealth = ($pinsFailedToday >= 3) ? 'Attention Required' : 'Healthy';
        $qrHealth = ($qrFailedToday >= 3) ? 'Attention Required' : 'Healthy';
        $otpHealth = ($otpFailedToday >= 3) ? 'Attention Required' : 'Healthy';
        $systemIntegrityStatus = ($authHealth === 'Healthy' && $docAccessHealth === 'Healthy' && $qrHealth === 'Healthy' && $otpHealth === 'Healthy')
            ? 'Healthy'
            : 'Attention Required';

        // 3. LOGIN TRENDS (LAST 7 DAYS)
        $chartLabels = [];
        $chartSuccess = [];
        $chartFailed = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $chartLabels[] = now()->subDays($i)->format('M d');
            $chartSuccess[] = DB::table('login_histories')->whereDate('login_time', $date)->count();
            $chartFailed[] = DB::table('login_failures')->whereDate('created_at', $date)->count();
        }

        // 4. SECURITY ALERTS
        $recentAlerts = collect();

        // Active locked accounts alert
        $lockedUsers = User::whereNotNull('locked_until')->where('locked_until', '>', now())->get();
        foreach ($lockedUsers as $lockedUser) {
            $recentAlerts->push([
                'severity' => 'HIGH',
                'event' => 'Account Lockout Triggered',
                'user' => $lockedUser->name . ' (' . $lockedUser->email . ')',
                'time' => $lockedUser->updated_at ? Carbon::parse($lockedUser->updated_at)->format('M d, Y • h:i A') : 'Recently',
                'status' => 'Locked until ' . Carbon::parse($lockedUser->locked_until)->format('h:i A'),
            ]);
        }

        // Multiple failed logins today alert
        if ($todayFailed >= 5) {
            $recentAlerts->push([
                'severity' => 'HIGH',
                'event' => 'Multiple Failed Login Attempts',
                'user' => 'Multiple accounts affected',
                'time' => 'Today • ' . now()->format('h:i A'),
                'status' => 'Under Monitoring',
            ]);
        }

        // System notification alerts
        $systemAlerts = DB::table('notifications')
            ->where('data->type', 'Security')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        foreach ($systemAlerts as $notif) {
            $data = json_decode($notif->data, true);
            $recentAlerts->push([
                'severity' => $data['severity'] ?? 'MEDIUM',
                'event' => $data['title'] ?? 'Security Event Notification',
                'user' => $data['user'] ?? 'System',
                'time' => Carbon::parse($notif->created_at)->format('M d, Y • h:i A'),
                'status' => $data['status'] ?? 'Active',
            ]);
        }

        // 5. RECENT SUCCESSFUL LOGINS (STRICT PRIVACY: NO IP ADDRESSES)
        $rawLogins = DB::table('login_histories')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $userIds = $rawLogins->pluck('user_id')->filter()->unique();
        $usersMap = User::whereIn('id', $userIds)->get()->keyBy('id');

        $recentLogins = $rawLogins->map(function($login) use ($usersMap) {
            $u = $login->user_id ? ($usersMap[$login->user_id] ?? null) : null;
            $roleStr = $u ? ($u->role ?? 'User') : 'User';
            if (User::isRoleAdmin($roleStr)) {
                $roleStr = 'Administrator';
            } elseif (in_array(strtoupper($roleStr), ['OFFICE_HEAD', 'OFFICE HEAD'])) {
                $roleStr = 'Office Head';
            } elseif (in_array(strtoupper($roleStr), ['EMPLOYEE'])) {
                $roleStr = 'Employee';
            } elseif (in_array(strtoupper($roleStr), ['STAFF'])) {
                $roleStr = 'Staff';
            }

            return [
                'user_name' => $u ? $u->name : ($login->email ?: 'Unknown Account'),
                'email' => $login->email,
                'role' => $roleStr,
                'device' => !empty($login->device) ? $login->device : 'Desktop Browser',
                'time' => Carbon::parse($login->login_time)->isToday() 
                    ? 'Today • ' . Carbon::parse($login->login_time)->format('h:i A')
                    : Carbon::parse($login->login_time)->format('M d, Y • h:i A'),
                'status' => 'Successful',
            ];
        });

        // 6. SUSPICIOUS ACTIVITIES & ACCOUNT LOCKOUTS (STRICT PRIVACY: NO IP ADDRESSES)
        $suspiciousItems = collect();

        // From login_failures
        $recentFailures = DB::table('login_failures')
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get();

        foreach ($recentFailures as $fail) {
            $suspiciousItems->push([
                'event' => 'Failed Login Attempt',
                'user' => $fail->username ?: 'Unknown Account',
                'time' => Carbon::parse($fail->created_at)->isToday()
                    ? 'Today • ' . Carbon::parse($fail->created_at)->format('h:i A')
                    : Carbon::parse($fail->created_at)->format('M d, Y • h:i A'),
                'raw_time' => $fail->created_at,
                'status' => 'Authentication Failed',
                'badge_class' => 'bg-danger-subtle text-danger border-danger-subtle',
                'icon' => 'bi-x-octagon-fill text-danger',
            ]);
        }

        // From audit_trails
        $suspiciousTrails = DB::table('audit_trails')
            ->where(function($q) {
                $q->where('action', 'like', '%Suspicious%')
                  ->orWhere('action', 'like', '%Locked%')
                  ->orWhere('action', 'like', '%Terminated%')
                  ->orWhere('action', 'like', '%Password Reset%');
            })
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get();

        foreach ($suspiciousTrails as $trail) {
            $isLock = stripos($trail->action, 'Locked') !== false;
            $suspiciousItems->push([
                'event' => $trail->action,
                'user' => $trail->user ?: 'System',
                'time' => Carbon::parse($trail->created_at)->isToday()
                    ? 'Today • ' . Carbon::parse($trail->created_at)->format('h:i A')
                    : Carbon::parse($trail->created_at)->format('M d, Y • h:i A'),
                'raw_time' => $trail->created_at,
                'status' => $isLock ? 'Account Locked' : 'Security Logged',
                'badge_class' => $isLock ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                'icon' => $isLock ? 'bi-lock-fill text-danger' : 'bi-shield-exclamation text-warning',
            ]);
        }

        // From activity_logs (Failed security verifications only - NO communication errors)
        $failedActivities = DB::table('activity_logs')
            ->where(function($q) {
                $q->where('action', 'like', '%PIN Verification Failed%')
                  ->orWhere('action', 'like', '%QR Verification Failed%')
                  ->orWhere('action', 'like', '%Unauthorized%')
                  ->orWhere('action', 'like', '%Access Denied%')
                  ->orWhere('action', 'like', '%Security Violation%');
            })
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get();

        foreach ($failedActivities as $act) {
            $suspiciousItems->push([
                'event' => $act->action,
                'user' => $act->user ?: 'Document Requester',
                'time' => Carbon::parse($act->created_at)->isToday()
                    ? 'Today • ' . Carbon::parse($act->created_at)->format('h:i A')
                    : Carbon::parse($act->created_at)->format('M d, Y • h:i A'),
                'raw_time' => $act->created_at,
                'status' => 'Verification Denied',
                'badge_class' => 'bg-danger-subtle text-danger border-danger-subtle',
                'icon' => 'bi-shield-x text-danger',
            ]);
        }

        $suspiciousActivities = $suspiciousItems
            ->sortByDesc('raw_time')
            ->values()
            ->take(8);

        // 7. COMPACT OPERATIONAL "SECURITY ACTIVITY" SECTION (REAL RECENT SECURITY EVENTS - STRICT PRIVACY: NO IP)
        $securityActivityFeed = collect();

        // Successful Logins
        foreach ($rawLogins->take(4) as $login) {
            $u = $login->user_id ? ($usersMap[$login->user_id] ?? null) : null;
            $securityActivityFeed->push([
                'event' => 'Successful Login',
                'user' => $u ? $u->name : ($login->email ?: 'User Account'),
                'time' => Carbon::parse($login->login_time)->isToday()
                    ? 'Today • ' . Carbon::parse($login->login_time)->format('h:i A')
                    : Carbon::parse($login->login_time)->format('M d, Y • h:i A'),
                'raw_time' => $login->login_time,
                'result' => 'Successful',
                'type' => 'success',
                'icon' => 'bi-check-circle-fill text-success',
            ]);
        }

        // Failed Logins
        foreach ($recentFailures->take(3) as $fail) {
            $securityActivityFeed->push([
                'event' => 'Failed Login Attempt',
                'user' => $fail->username ?: 'Unknown User',
                'time' => Carbon::parse($fail->created_at)->isToday()
                    ? 'Today • ' . Carbon::parse($fail->created_at)->format('h:i A')
                    : Carbon::parse($fail->created_at)->format('M d, Y • h:i A'),
                'raw_time' => $fail->created_at,
                'result' => 'Failed',
                'type' => 'danger',
                'icon' => 'bi-x-circle-fill text-danger',
            ]);
        }

        // Protected document PIN & QR security events from activity_logs
        $docSecurityLogs = DB::table('activity_logs')
            ->where(function($q) {
                $q->where('action', 'like', '%PIN%')
                  ->orWhere('action', 'like', '%QR%')
                  ->orWhere('action', 'like', '%Password%');
            })
            ->where('action', 'not like', '%Notification%')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        foreach ($docSecurityLogs as $dlog) {
            $isFail = stripos($dlog->action, 'Fail') !== false;
            $isQr = stripos($dlog->action, 'QR') !== false;
            $isPin = stripos($dlog->action, 'PIN') !== false;

            $eventTitle = $dlog->action;
            $resultTitle = $isFail ? 'Failed' : 'Completed';
            $icon = 'bi-shield-check text-success';
            $type = 'success';

            if ($isFail) {
                $icon = 'bi-shield-x text-danger';
                $type = 'danger';
            } elseif ($isQr) {
                $icon = 'bi-qr-code text-primary';
                $type = 'info';
            } elseif ($isPin) {
                $icon = 'bi-key-fill text-primary';
                $type = 'primary';
            }

            $securityActivityFeed->push([
                'event' => $eventTitle,
                'user' => $dlog->user ?: 'System User',
                'time' => Carbon::parse($dlog->created_at)->isToday()
                    ? 'Today • ' . Carbon::parse($dlog->created_at)->format('h:i A')
                    : Carbon::parse($dlog->created_at)->format('M d, Y • h:i A'),
                'raw_time' => $dlog->created_at,
                'result' => $resultTitle,
                'type' => $type,
                'icon' => $icon,
            ]);
        }

        // Audit Trail Security Events
        $securityTrails = DB::table('audit_trails')
            ->where(function($q) {
                $q->where('action', 'like', '%Password%')
                  ->orWhere('action', 'like', '%2FA%')
                  ->orWhere('action', 'like', '%OTP%')
                  ->orWhere('action', 'like', '%Session%')
                  ->orWhere('action', 'like', '%Locked%');
            })
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get();

        foreach ($securityTrails as $strail) {
            $isLocked = stripos($strail->action, 'Locked') !== false;
            $isTerm = stripos($strail->action, 'Terminated') !== false;
            $securityActivityFeed->push([
                'event' => $strail->action,
                'user' => $strail->user ?: 'System',
                'time' => Carbon::parse($strail->created_at)->isToday()
                    ? 'Today • ' . Carbon::parse($strail->created_at)->format('h:i A')
                    : Carbon::parse($strail->created_at)->format('M d, Y • h:i A'),
                'raw_time' => $strail->created_at,
                'result' => $isLocked ? 'Locked' : ($isTerm ? 'Terminated' : 'Verified'),
                'type' => $isLocked ? 'danger' : ($isTerm ? 'warning' : 'success'),
                'icon' => $isLocked ? 'bi-lock-fill text-danger' : 'bi-shield-check text-success',
            ]);
        }

        $securityActivity = $securityActivityFeed
            ->sortByDesc('raw_time')
            ->values()
            ->take(10);

        return view('security-dashboard', compact(
            'todayLogins', 'todayFailed', 'lockedAccounts', 'onlineUsers',
            'authHealth', 'docAccessHealth', 'qrHealth', 'otpHealth', 'systemIntegrityStatus',
            'confidentialDocsCount', 'pinsIssuedToday', 'pinsVerifiedToday', 'pinsFailedToday', 'pinsFailedTotal', 'totalPinsVerified',
            'qrScannedToday', 'qrFailedToday', 'qrTotalVerified', 'qrTotalFailed', 'confidentialDocsWithQr',
            'otpRequestsToday', 'otpSuccessToday', 'otpFailedToday', 'otpExpiredToday',
            'recentLogins', 'recentAlerts', 'suspiciousActivities', 'securityActivity',
            'chartLabels', 'chartSuccess', 'chartFailed'
        ));
    }
}
