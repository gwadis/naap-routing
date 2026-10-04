<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;

class SettingsController extends Controller
{
    public function index()
    {
        $role = session('user_role') ?? auth()->user()?->role;
        $user = auth()->user() ?? \App\Models\User::find(session('user_id'));
        $isAdmin = ($user && $user->isAdmin()) || \App\Models\User::isRoleAdmin($role);

        if (!$isAdmin) {
            return redirect()->route('profile');
        }

        // Convert table rows into a simple associative array for the view
        $settings = DB::table('settings')->pluck('value', 'key')->toArray();
        return view('settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        $settingKeys = [
            '2fa_enabled', 'min_password', 'session_timeout', 
            'auto_qr', 'qr_size', 'email_notif', 'log_retention', 'otp_expiry'
        ];

        foreach ($settingKeys as $key) {
            // Default to '0' if the checkbox/input is missing from the request
            $value = $request->has($key) ? $request->input($key) : '0';
            
            // Standardize checkbox 'on' values to '1'
            if ($value === 'on') $value = '1';

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        ActivityLog::create([
            'user' => session('user_name') ?? 'Admin User',
            'action' => 'System settings updated',
            'document_id' => null,
            'ip' => 'REDACTED',
            'meta' => json_encode($request->only($settingKeys)),
        ]);

        return back()->with('success', 'System settings updated successfully!');
    }

    public function testSms(Request $request)
    {
        $this->authorizeAdmin();
        $smsService = app(\App\Services\SmsService::class);
        $phone = $request->input('phone', '09690222557');
        $message = "NAAP Routing Alert: Document TRK-TEST requires your attention.";

        if (!$smsService->isConfigured()) {
            $deviceUri = \App\Services\SmsService::getDeviceSmsUri($phone, $message);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'status' => 'SMS_UNAVAILABLE',
                    'provider' => 'none',
                    'info' => 'SMS unavailable — no SMS provider configured.',
                    'device_sms_uri' => $deviceUri,
                    'draft_label' => 'Open SMS',
                    'recipient' => \App\Services\SmsService::normalizePhoneNumber($phone),
                ]);
            }

            return back()->with('info', "SMS unavailable — no SMS provider configured. Core routing operates via Email & In-App.");
        }

        $result = $smsService->send($phone, $message);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $result['success'],
                'status' => $result['status'] ?? ($result['success'] ? 'SMS_SENT' : 'SMS_UNAVAILABLE'),
                'provider' => $result['provider'] ?? 'none',
                'info' => $result['info'] ?? '',
                'recipient' => $result['recipient'] ?? $phone,
                'device_sms_uri' => $result['device_sms_uri'] ?? null,
                'draft_label' => 'Open SMS',
            ]);
        }

        if ($result['success']) {
            $provider = strtoupper($result['provider'] ?? 'SMS');
            return back()->with('success', "SMS SENT: Verified cellular delivery via [{$provider}] to {$phone}!");
        }

        return back()->with('warning', "SMS unavailable — " . ($result['info'] ?? 'no SMS provider configured.'));
    }

    protected function authorizeAdmin()
    {
        $role = session('user_role') ?? auth()->user()?->role;
        $user = auth()->user() ?? \App\Models\User::find(session('user_id'));
        $isAdmin = ($user && $user->isAdmin()) || \App\Models\User::isRoleAdmin($role);

        if (!$isAdmin) {
            abort(403, 'Administrator privileges are required to access this page.');
        }
    }
}