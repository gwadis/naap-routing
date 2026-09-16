<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;

class SettingsController extends Controller
{
    public function index()
    {
        if (session('user_role') !== 'ADMIN') {
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

    protected function authorizeAdmin()
    {
        if (session('user_role') !== 'ADMIN') {
            abort(403, 'Administrator privileges are required to access this page.');
        }
    }
}