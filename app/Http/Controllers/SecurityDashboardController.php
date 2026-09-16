<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditTrail;
use Illuminate\Support\Facades\DB;

class SecurityDashboardController extends Controller
{
    public function index()
    {
        if (session('user_role') !== 'ADMIN' && session('user_role') !== 'Administrator' && session('user_role') !== 'Super Administrator') {
            abort(403, 'Unauthorized.');
        }

        // Summary Counts
        $todayLogins = DB::table('login_histories')->whereDate('login_time', now()->toDateString())->count();
        $todayFailed = DB::table('login_failures')->whereDate('created_at', now()->toDateString())->count();
        $lockedAccounts = User::whereNotNull('locked_until')->where('locked_until', '>', now())->count();
        
        // Active Sessions in last 5 minutes
        $onlineUsers = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->count();

        // Recent Logins
        $recentLogins = DB::table('login_histories')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        // Recent Security Alerts
        $recentAlerts = DB::table('notifications')
            ->where('data->type', 'Security')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function($notif) {
                $data = json_decode($notif->data, true);
                return [
                    'message' => $data['message'] ?? 'Security Alert',
                    'time' => date('Y-m-d H:i:s', strtotime($notif->created_at)),
                ];
            });

        // Suspicious Activities
        $suspiciousActivities = DB::table('audit_trails')
            ->where('action', 'like', '%Suspicious%')
            ->orWhere('action', 'like', '%Locked%')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        // OTP requests count
        $otpRequestsCount = DB::table('otps')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        // Weekly chart data
        $chartLabels = [];
        $chartSuccess = [];
        $chartFailed = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $chartLabels[] = now()->subDays($i)->format('M d');
            $chartSuccess[] = DB::table('login_histories')->whereDate('login_time', $date)->count();
            $chartFailed[] = DB::table('login_failures')->whereDate('created_at', $date)->count();
        }

        return view('security-dashboard', compact(
            'todayLogins', 'todayFailed', 'lockedAccounts', 'onlineUsers',
            'recentLogins', 'recentAlerts', 'suspiciousActivities', 'otpRequestsCount',
            'chartLabels', 'chartSuccess', 'chartFailed'
        ));
    }
}
