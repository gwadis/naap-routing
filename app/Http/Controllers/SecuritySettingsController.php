<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditTrail;
use Illuminate\Support\Facades\{Hash, Auth, DB, Log, Response};

class SecuritySettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user() ?? User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        // Get Login History
        $loginHistory = DB::table('login_histories')
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        // Get Active Sessions
        $activeSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->get();

        $currentSessionId = session()->getId();

        $sessionsList = [];
        foreach ($activeSessions as $sess) {
            [$browser, $os, $device] = AuditTrail::parseUserAgentExtended($sess->user_agent);
            $sessionsList[] = [
                'id' => $sess->id,
                'ip_address' => $sess->ip_address,
                'browser' => $browser,
                'os' => $os,
                'device' => $device,
                'last_active' => date('Y-m-d H:i:s', $sess->last_activity),
                'is_current' => ($sess->id === $currentSessionId),
            ];
        }

        return view('security-settings', compact('user', 'loginHistory', 'sessionsList'));
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user() ?? User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $request->validate([
            'current_password' => 'required',
            'new_password' => [
                'required',
                'string',
                'min:10',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
                'confirmed'
            ]
        ], [
            'new_password.min' => 'The password must be at least 10 characters.',
            'new_password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

        $validCurrent = false;
        try {
            $validCurrent = Hash::check($request->input('current_password'), $user->password);
        } catch (\RuntimeException $e) {
            if (preg_match('/^\$2[ayb]\$/', $user->password)) {
                $validCurrent = Hash::driver('bcrypt')->check($request->input('current_password'), $user->password);
            }
        }

        if (!$validCurrent) {
            return back()->with('error', 'Your current password is incorrect.');
        }

        $oldValues = $user->toArray();
        $user->update([
            'password' => Hash::make($request->input('new_password'))
        ]);

        // Dispatch confirmation email
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\SecurityMail(
                "🔐 Security Notice: Password Updated Successfully",
                $user->name,
                "<p>This email confirms that your password for NAAP Routing was updated successfully from your security settings page.</p>"
                . "<p>If you did not perform this change, please notify your administrator immediately.</p>"
            ));
            Log::channel('email')->info("Password changed confirmation email sent to {$user->email}");
        } catch (\Exception $mailEx) {
            Log::channel('email')->error("Failed to send password update confirmation email to {$user->email}: " . $mailEx->getMessage());
        }

        AuditTrail::log("User Password Changed", "users/{$user->id}", $oldValues, $user->toArray());
        Log::channel('security')->info("Password changed by user {$user->email}");

        return back()->with('success', 'Password updated successfully!');
    }

    public function toggle2FA(Request $request)
    {
        $user = Auth::user() ?? User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $enable = $request->input('enable_2fa') === '1';

        $oldValues = $user->toArray();

        if ($enable) {
            $user->update([
                'two_factor_confirmed_at' => now(),
            ]);
            AuditTrail::log("2FA Enabled", "users/{$user->id}", $oldValues, $user->toArray());
            Log::channel('security')->info("2FA enabled by user {$user->email}");
            return back()->with('success', 'Two-Factor Authentication enabled successfully!');
        } else {
            $user->update([
                'two_factor_confirmed_at' => null,
                'two_factor_secret' => null
            ]);
            AuditTrail::log("2FA Disabled", "users/{$user->id}", $oldValues, $user->toArray());
            Log::channel('security')->warning("2FA disabled by user {$user->email}");
            return back()->with('success', 'Two-Factor Authentication disabled.');
        }
    }

    public function updateRecoveryEmail(Request $request)
    {
        $user = Auth::user() ?? User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $request->validate([
            'recovery_email' => 'required|email|max:255',
        ]);

        $oldValues = $user->toArray();

        $user->update([
            'recovery_email' => $request->input('recovery_email'),
        ]);

        AuditTrail::log("Recovery Email Updated", "users/{$user->id}", $oldValues, $user->toArray());

        return back()->with('success', 'Recovery email updated successfully!');
    }

    public function terminateSession(Request $request)
    {
        $user = Auth::user() ?? User::find(session('user_id'));
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $sessionId = $request->input('session_id');
        if ($sessionId === session()->getId()) {
            return response()->json(['success' => false, 'message' => 'Cannot terminate current session here.'], 400);
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        AuditTrail::log("Remote Session Terminated", "sessions/{$sessionId}");
        Log::channel('security')->info("Active session terminated by user {$user->email}");

        return response()->json(['success' => true, 'message' => 'Session terminated successfully.']);
    }

    public function downloadActivity()
    {
        $user = Auth::user() ?? User::find(session('user_id'));
        if (!$user) {
            abort(403);
        }

        $logs = DB::table('audit_trails')
            ->where('user', $user->name)
            ->orderBy('id', 'desc')
            ->get();

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=activity_history_" . $user->username . ".csv",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Action', 'IP Address', 'Browser', 'OS', 'Device', 'Affected Record', 'Timestamp']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->action,
                    $log->ip_address,
                    $log->browser,
                    $log->os,
                    $log->device,
                    $log->affected_record,
                    $log->created_at
                ]);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}
