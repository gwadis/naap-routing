<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Storage, Log};
use Illuminate\Support\Str;
use App\Models\User;

class ProfileController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user() ?? (session('user_id')
                ? User::with(['department', 'office'])->find(session('user_id'))
                : User::with(['department', 'office'])->where('email', session('user_email'))->first());

            if (!$user) {
                return redirect()->route('home')->with('error', 'Please log in again.');
            }

            // Ensure public storage link exists
            if (!file_exists(public_path('storage'))) {
                try {
                    \Illuminate\Support\Facades\Artisan::call('storage:link');
                } catch (\Throwable $linkEx) {
                    Log::warning('Storage link check failed: ' . $linkEx->getMessage());
                }
            }

            $departments = \App\Models\Department::all();
            $offices = \App\Models\Office::orderBy('name', 'asc')->get();

            // Fetch last login history if available
            $lastLogin = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('login_histories')) {
                $lastLogin = \Illuminate\Support\Facades\DB::table('login_histories')
                    ->where('user_id', $user->id)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            // Fetch last password change audit if available
            $lastPasswordChange = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('audit_trails')) {
                $lastPasswordChange = \App\Models\AuditTrail::where('affected_record', "users/{$user->id}")
                    ->where(function($q) {
                        $q->where('action', 'like', '%Password%');
                    })
                    ->latest()
                    ->first();
            }

            return view('profile', compact('user', 'departments', 'offices', 'lastLogin', 'lastPasswordChange'));

        } catch (\Exception $e) {
            Log::error('Profile Load Error: ' . $e->getMessage());
            return redirect()->route('home')->with('error', 'Failed to load profile.');
        }
    }

    public function updateInfo(Request $request)
    {
        try {
            $user = User::where('email', session('user_email'))->first();

            if (!$user) {
                return back()->with('error', 'User not found.');
            }

            $role = session('user_role');
            $isAdmin = in_array($role, ['ADMIN', 'Administrator', 'Super Administrator']);

            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:255',
                'avatar' => 'nullable|image|max:2048',
            ];

            if ($isAdmin) {
                $rules['employee_id'] = 'nullable|string|max:255|unique:users,employee_id,' . $user->id;
                $rules['position'] = 'nullable|string|max:255';
                $rules['department_id'] = 'nullable|exists:departments,id';
                $rules['office_id'] = 'nullable|exists:offices,id';
            }

            $validated = $request->validate($rules);

            if (!empty($validated['phone'])) {
                $validated['phone'] = \App\Services\SmsService::normalizePhoneNumber($validated['phone']) ?? $validated['phone'];
            }

            if ($request->hasFile('avatar')) {
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
            }

            $oldValues = $user->toArray();
            $user->update($validated);

            \App\Models\AuditTrail::log("User Profile Updated via Web UI", "users/{$user->id}", $oldValues, $user->toArray());
            \App\Models\ActivityLog::log('User profile updated', null, [
                'email' => $user->email,
                'employee_id' => $user->employee_id,
                'position' => $user->position,
                'phone' => $user->phone
            ]);

            session([
                'user_name' => $user->name,
                'user_email' => $user->email
            ]);

            return back()->with('success', 'Profile updated successfully!');

        } catch (\Exception $e) {
            Log::error('Profile Update Info Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update profile.');
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'password' => [
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
                'password.min' => 'The password must be at least 10 characters.',
                'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            ]);

            $user = User::where('email', session('user_email'))->first();

            if (!$user) {
                return back()->with('error', 'User not found.');
            }

            $oldValues = $user->toArray();
            $user->update([
                'password' => Hash::make($request->password)
            ]);

            // Dispatch confirmation email
            try {
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\SecurityMail(
                    "🔐 Security Notice: Password Updated Successfully",
                    $user->name,
                    "<p>This email confirms that your password for NAAP Routing was updated successfully from your profile page.</p>"
                    . "<p>If you did not perform this change, please notify your administrator immediately.</p>"
                ));
                Log::channel('email')->info("Password changed confirmation email sent to {$user->email}");
            } catch (\Exception $mailEx) {
                Log::channel('email')->error("Failed to send password update confirmation email to {$user->email}: " . $mailEx->getMessage());
            }

            \App\Models\AuditTrail::log("Password Changed via Profile", "users/{$user->id}", $oldValues, $user->toArray());

            return back()->with('success', 'Password changed successfully!');

        } catch (\Exception $e) {
            Log::error('Password Update Error: ' . $e->getMessage());
            return back()->with('error', $e instanceof \Illuminate\Validation\ValidationException ? $e->getMessage() : 'Failed to change password.');
        }
    }

    public function updateSignature(Request $request)
    {
        try {
            $user = auth()->user() ?? (session('user_id')
                ? User::find(session('user_id'))
                : User::where('email', session('user_email'))->first());

            if (!$user) {
                return back()->with('error', 'Session expired. Please log in again.');
            }

            // Ensure public storage link exists
            if (!file_exists(public_path('storage'))) {
                try {
                    \Illuminate\Support\Facades\Artisan::call('storage:link');
                } catch (\Throwable $linkEx) {
                    Log::warning('Storage link generation attempt failed: ' . $linkEx->getMessage());
                }
            }

            // Image validation
            $request->validate([
                'sig_file' => 'nullable|file|image|mimes:png,jpg,jpeg|max:2048',
                'signature_data' => 'nullable|string',
            ], [
                'sig_file.image' => 'The uploaded signature must be a valid image file.',
                'sig_file.mimes' => 'Only PNG, JPG, or JPEG signature images are supported.',
                'sig_file.max' => 'The signature file size must not exceed 2MB.',
            ]);

            $path = null;

            // 1. FILE UPLOAD
            if ($request->hasFile('sig_file')) {
                $file = $request->file('sig_file');
                if (!$file->isValid()) {
                    return back()->with('error', 'Uploaded signature file is not valid.');
                }

                $imageInfo = @getimagesize($file->getRealPath());
                if ($imageInfo === false) {
                    return back()->with('error', 'The uploaded file is not a valid or readable image.');
                }

                $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
                if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
                    $ext = 'png';
                }

                $filename = 'sig_' . Str::random(24) . '.' . $ext;
                $path = $file->storeAs('signatures', $filename, 'public');
            }

            // 2. CANVAS DRAWN SIGNATURE (Base64)
            elseif ($request->filled('signature_data')) {
                $data = (string) $request->signature_data;
                if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $data)) {
                    return back()->with('error', 'Invalid signature format.');
                }

                $base64Data = preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $data);
                $base64Data = str_replace(' ', '+', $base64Data);
                $decoded = base64_decode($base64Data, true);

                if ($decoded === false || strlen($decoded) < 10) {
                    return back()->with('error', 'Corrupted or empty signature drawing.');
                }

                $imageInfo = @getimagesizefromstring($decoded);
                if ($imageInfo === false) {
                    return back()->with('error', 'The drawn signature could not be verified as a valid image.');
                }

                $filename = 'sig_' . Str::random(24) . '.png';
                $path = 'signatures/' . $filename;

                Storage::disk('public')->put($path, $decoded);
            }

            if ($path && Storage::disk('public')->exists($path)) {
                $oldValues = $user->toArray();
                $oldSignature = $user->signature;

                // Safely delete previous physical signature file if it exists
                if ($oldSignature && !str_starts_with($oldSignature, 'data:') && Storage::disk('public')->exists($oldSignature)) {
                    Storage::disk('public')->delete($oldSignature);
                }

                $user->update(['signature' => $path]);

                \App\Models\AuditTrail::log("Signature Updated via Profile", "users/{$user->id}", $oldValues, $user->fresh()->toArray());

                return back()->with('success', 'Digital signature updated successfully!');
            }

            return back()->with('error', 'No signature data received or image could not be processed.');

        } catch (\Illuminate\Validation\ValidationException $ve) {
            return back()->withErrors($ve->errors())->withInput()->with('error', $ve->validator->errors()->first());
        } catch (\Exception $e) {
            Log::error('Signature Update Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update signature: ' . $e->getMessage());
        }
    }
}