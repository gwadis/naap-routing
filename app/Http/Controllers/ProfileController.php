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
            $user = User::where('email', session('user_email'))->first();

            if (!$user) {
                return redirect()->route('home')->with('error', 'Please log in again.');
            }

            $departments = \App\Models\Department::all();

            return view('profile', compact('user', 'departments'));

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
            $user = User::where('email', session('user_email'))->first();

            if (!$user) {
                return back()->with('error', 'Session expired.');
            }

            $path = null;

            // FILE UPLOAD
            if ($request->hasFile('sig_file')) {
                $path = $request->file('sig_file')->store('signatures', 'public');
            }

            // BASE64 SIGNATURE
            elseif ($request->signature_data) {

                $imageName = 'sig_' . Str::random(10) . '.png';

                $data = $request->signature_data;
                $data = str_replace('data:image/png;base64,', '', $data);
                $data = str_replace(' ', '+', $data);

                Storage::disk('public')->put(
                    'signatures/' . $imageName,
                    base64_decode($data)
                );

                $path = 'signatures/' . $imageName;
            }

            if ($path) {
                $oldValues = $user->toArray();

                // delete old signature safely
                if ($user->signature) {
                    Storage::disk('public')->delete($user->signature);
                }

                $user->update(['signature' => $path]);

                \App\Models\AuditTrail::log("Signature Updated via Profile", "users/{$user->id}", $oldValues, $user->toArray());

                return back()->with('success', 'Digital signature updated!');
            }

            return back()->with('error', 'No signature data received.');

        } catch (\Exception $e) {
            Log::error('Signature Update Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update signature.');
        }
    }
}