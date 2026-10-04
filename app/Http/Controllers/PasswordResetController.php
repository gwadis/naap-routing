<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\AuditTrail;
use App\Models\ActivityLog;
use App\Mail\SecurityMail;

class PasswordResetController extends Controller
{
    /**
     * Display the form to request a password reset link.
     */
    public function showForgotPassword()
    {
        if (session()->has('user_id') || Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('forgot-password');
    }

    /**
     * Send a reset link to the given user.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
        ], [
            'email.required' => 'Please enter your email address or username.',
        ]);

        $input = trim($request->input('email'));

        // Lookup user by either email (case-insensitive) or username to match NAAP login system
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($input)])
            ->orWhere('username', $input)
            ->first();

        // If user exists, is active, and has a valid designated email, send the password reset notification
        if ($user && (!isset($user->status) || $user->status !== 'deactivated') && !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            try {
                $status = Password::broker()->sendResetLink(['email' => $user->email]);

                if ($status === Password::RESET_THROTTLED) {
                    return back()->withErrors([
                        'email' => 'Too many requests. Please wait a moment before requesting another reset link.'
                    ])->withInput();
                }

                if (class_exists(AuditTrail::class)) {
                    AuditTrail::log("Password reset link requested for {$user->email}", "users/{$user->id}");
                }
            } catch (\Throwable $e) {
                Log::channel('email')->error("Failed to send password reset email to {$user->email}: " . $e->getMessage());
                return back()->withErrors([
                    'email' => 'Unable to send password reset email at this time. Please try again later.'
                ])->withInput();
            }
        }

        // Generic response prevents account enumeration vulnerabilities
        return back()->with('status', 'If an account with that email or username exists, a password reset link has been sent to the associated email address.');
    }

    /**
     * Display the password reset view for the given token.
     */
    public function showResetPassword(Request $request, $token)
    {
        if (session()->has('user_id') || Auth::check()) {
            return redirect()->route('dashboard');
        }

        $email = $request->query('email');

        if ($email) {
            $user = User::where('email', $email)->first();
            if (!$user || !Password::broker()->tokenExists($user, $token)) {
                return redirect()->route('password.request')->withErrors([
                    'email' => 'This password reset link is invalid or has expired. Please request a new one.'
                ]);
            }
        }

        return view('reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Reset the given user's password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:12',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
                'confirmed',
            ],
        ], [
            'password.min' => 'The password must be at least 12 characters.',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $response = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                    'needs_password_change' => false,
                ])->save();
            }
        );

        if ($response === Password::PASSWORD_RESET) {
            $user = User::where('email', $request->email)->first();
            if ($user) {
                if (class_exists(AuditTrail::class)) {
                    AuditTrail::log("Password reset completed successfully for {$user->email}", "users/{$user->id}");
                }
                if (class_exists(ActivityLog::class)) {
                    ActivityLog::log("Password reset completed for user: {$user->name}", null, ['email' => $user->email]);
                }

                try {
                    Mail::to($user->email)->send(new SecurityMail(
                        "🔐 Security Notice: Password Reset Successfully",
                        $user->name ?? $user->username,
                        "<p>This email confirms that your password for NAAP Routing was successfully reset.</p>"
                        . "<p>If you did not make this change, please contact your administrator immediately.</p>"
                    ));
                } catch (\Throwable $e) {
                    Log::channel('email')->error("Failed to send password reset confirmation email: " . $e->getMessage());
                }
            }

            return redirect()->route('login')->with('status', 'Your password has been reset successfully! Please sign in with your new password.');
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => trans($response) ?: 'This password reset token is invalid or has expired.']);
    }
}
