<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Department;
use App\Models\Office;
use App\Models\ActivityLog;
use App\Models\AuditTrail;
use Illuminate\Support\Facades\{Hash, Auth, Storage, DB, Mail, Log};

class UserController extends Controller
{
    /**
     * Handle user login with role-based session enforcement and 2FA support.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username or email is required.',
            'password.required' => 'Password is required.',
        ]);

        $input = $request->input('username');
        $password = $request->input('password');
        $ip = $request->ip() ?? '127.0.0.1';
        
        // Find user by email or username
        $user = User::where('email', $input)->orWhere('username', $input)->first();

        // 1. Check account lockout
        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $diff = $user->locked_until->diffInMinutes(now()) + 1;
            Log::channel('authentication')->warning("Login blocked for locked user {$input} from IP {$ip}");
            return back()->with('error', "This account is locked due to multiple failed login attempts. Please try again in {$diff} minute(s).");
        }

        // 2. Count failed logins for this IP to enforce Google reCAPTCHA (if enabled)
        if (env('RECAPTCHA_ENABLED', false)) {
            $failedAttemptsCount = DB::table('login_failures')
                ->where('ip_address', $ip)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();

            if ($failedAttemptsCount >= 3) {
                if (!$this->validateReCaptcha($request->input('g-recaptcha-response'))) {
                    return back()->with('error', 'reCAPTCHA verification failed. Please try again.')->withInput($request->only('username'));
                }
            }
        }

        // Determine credential field based on input format
        $credentials = [];
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $credentials = ['email' => $input, 'password' => $password];
        } else {
            $credentials = ['username' => $input, 'password' => $password];
        }

        // Perform standard Auth::attempt()
        $attemptSuccess = Auth::attempt($credentials);

        // Temporarily log authentication details
        Log::channel('authentication')->info("Login Auth attempt logs:", [
            'email_or_username' => $input,
            'user_found' => !is_null($user),
            'auth_attempt_result' => $attemptSuccess ? 'SUCCESS' : 'FAILURE',
        ]);

        if ($attemptSuccess) {
            $user = Auth::user();

            // Reset failed login attempts
            $user->update([
                'failed_login_attempts' => 0,
                'locked_until' => null
            ]);

            // Save details to the session for OTP redirect
            session([
                'temp_otp_user_id' => $user->id,
                'temp_otp_login_time' => now()
            ]);

            // Generate secure random 6-digit OTP
            $otp = (string) random_int(100000, 999999);
            
            DB::transaction(function () use ($user, $otp) {
                // Delete or invalidate previous OTP when generating a new one
                \App\Models\Otp::where('user_id', $user->id)->delete();

                // Save OTP in the database
                $record = \App\Models\Otp::create([
                    'user_id' => $user->id,
                    'otp' => Hash::make($otp),
                    'expires_at' => now()->addMinutes(5),
                    'is_used' => false,
                    'attempts' => 0,
                ]);

                Log::channel('email')->info('OTP Generated and Stored (Login):', [
                    'generated_otp' => $otp,
                    'record_id' => $record->id,
                    'user_id' => $user->id,
                ]);
            });

            // Send OTP email using the configured Email API
            $sent = false;
            $sendError = null;
            try {
                $emailService = new \App\Services\EmailService();
                $subject = "Login Verification Code";
                $body = "Hello {$user->name},\n\n"
                      . "Your verification code is:\n\n"
                      . "{$otp}\n\n"
                      . "This code expires in 5 minutes.\n\n"
                      . "If you did not request this login, you may safely ignore this email.\n\n"
                      . "Regards,\n"
                      . "NAAP Document Routing System";

                $sent = $emailService->send($user->email, $subject, $body);
                if ($sent) {
                    Log::channel('email')->info("Login OTP email successfully sent/logged for {$user->email}");
                } else {
                    $sendError = 'Provider returned failure status.';
                    Log::channel('email')->error("Failed to send login OTP email to {$user->email}: {$sendError}");
                }
            } catch (\Exception $e) {
                $sendError = $e->getMessage();
                Log::channel('email')->error("Failed to send login OTP email to {$user->email}: " . $sendError);
            }

            if (!$sent) {
                // Logout the user from Auth session if sending OTP fails
                Auth::logout();
                $displayError = config('app.debug') || env('APP_DEBUG', false)
                    ? 'Failed to send verification code. Error: ' . $sendError
                    : 'Failed to send verification code. Please check configuration/logs.';
                return back()->with('error', $displayError)->withInput($request->only('username'));
            }

            return redirect()->route('login.otp.verify');
        }

        // Increment failed attempts for user
        if ($user) {
            $user->increment('failed_login_attempts');
            if ($user->failed_login_attempts >= 5) {
                $user->update([
                    'locked_until' => now()->addMinutes(15),
                    'failed_login_attempts' => 0
                ]);
                
                AuditTrail::log("User Account Locked: {$user->email}", "users/{$user->id}");
                Log::channel('security')->critical("Account locked out for user {$user->email} due to consecutive failed attempts from IP {$ip}");

                try {
                    Mail::to($user->email)->send(new \App\Mail\SecurityMail(
                        "🛡️ Security Alert: Account Temporarily Locked",
                        $user->name,
                        "<p>Your NAAP Routing account has been temporarily locked for 15 minutes due to 5 consecutive failed login attempts.</p>"
                        . "<p><strong>Attempt details:</strong></p>"
                        . "<ul>"
                        . "<li><strong>IP Address:</strong> {$ip}</li>"
                        . "<li><strong>Timestamp:</strong> " . now()->format('Y-m-d H:i:s') . "</li>"
                        . "</ul>"
                        . "<p>If this was not you, please contact your administrator immediately.</p>"
                    ));
                } catch (\Exception $e) {
                    Log::channel('email')->error("Failed to send lockout alert to {$user->email}: " . $e->getMessage());
                }

                return back()->with('error', 'This account has been locked for 15 minutes due to 5 consecutive failed login attempts.');
            }
        }

        // Save failed attempt history
        $userAgent = $request->header('User-Agent', '');
        [$browser, $os, $device] = AuditTrail::parseUserAgentExtended($userAgent);
        DB::table('login_failures')->insert([
            'ip_address' => $ip,
            'username' => $input,
            'user_agent' => $userAgent,
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        Log::channel('authentication')->info("Failed login attempt for username '{$input}' from IP {$ip}");

        return back()->with('error', 'Invalid credentials. Please try again.')->withInput($request->only('username'));
    }

    /**
     * Show 2FA verification screen
     */
    public function show2FAVerify()
    {
        if (!session('temp_authenticated')) {
            return redirect()->route('login');
        }

        return view('2fa-verify');
    }

    /**
     * Verify 2FA code
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            '2fa_code' => 'required|numeric|digits:6',
        ]);

        $userId = session('temp_user_id');
        if (!$userId) {
            return redirect()->route('login')->with('error', '2FA session expired.');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login')->with('error', 'User not found.');
        }

        // Verify the TOTP code
        if ($this->verify2FACode($user, $request->input('2fa_code'))) {
            Auth::login($user);
            $request->session()->forget(['temp_user_id', 'temp_authenticated']);
            $request->session()->regenerate();

            session([
                'authenticated' => true,
                'user_id'       => $user->id,
                'user_name'     => $user->name,
                'user_email'    => $user->email,
                'user_role'     => $user->role,
                'department_id' => $user->department_id,
            ]);

            return redirect()->route('dashboard')->with('success', 'Successfully logged in!');
        }

        return back()->withErrors([
            '2fa_code' => 'Invalid 2FA code. Please try again.',
        ]);
    }

    /**
     * Verify TOTP code against user's secret
     */
    private function verify2FACode($user, $code)
    {
        if (!$user->two_factor_secret) {
            return false;
        }

        // Verify using TOTP algorithm
        for ($i = -1; $i <= 1; $i++) {
            $timeWindow = floor(time() / 30) + $i;
            $hash = hash_hmac('SHA1', pack('N*', 0, $timeWindow), base64_decode($user->two_factor_secret), true);
            $offset = ord($hash[strlen($hash) - 1]) & 0xf;
            $code_check = (unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff) % 1000000;
            
            if ($code_check == $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redirect the named register route to the user management page.
     */
    public function redirectToUsers()
    {
        return redirect()->route('users.index');
    }

    /**
     * Display a listing of the users (Admin View).
     */
    public function index()
    {
        $this->authorizeAdmin();

        $this->ensureDefaultDepartments();

        $users = User::with(['department', 'office'])->latest()->get();
        $departments = Department::all(); 
        $offices = Office::orderBy('name', 'asc')->get();
        
        return view('users', compact('users', 'departments', 'offices'));
    }

    /**
     * Show the user registration page.
     */
    public function create()
    {
        $this->authorizeAdmin();

        $this->ensureDefaultDepartments();

        $departments = Department::all();
        $offices = Office::orderBy('name', 'asc')->get();
        return view('users.create', compact('departments', 'offices'));
    }

    protected function ensureDefaultDepartments(): void
    {
        $defaultDepartments = ['ILAS', 'INET', 'ICS'];

        foreach ($defaultDepartments as $name) {
            Department::firstOrCreate([
                'name' => $name,
            ], [
                'description' => null,
                'status' => 'active',
            ]);
        }
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'employee_id'   => 'nullable|string|max:255|unique:users,employee_id',
            'position'      => 'nullable|string|max:255',
            'password'      => 'required|string|min:10|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9])[a-zA-Z0-9!@#$%^&*()\-_+=\[\]{}:;<>,.?]+$/',
            'role'          => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'office_id'     => 'nullable|exists:offices,id',
            'status'        => 'nullable|string|in:active,inactive',
        ], [
            'password.min' => 'Password must be at least 10 characters long.',
            'password.regex' => 'Password must contain uppercase, lowercase, numbers, and a special character.',
        ]);

        $user = User::create([
            'name'                  => $validated['name'],
            'email'                 => $validated['email'],
            'employee_id'           => $validated['employee_id'] ?? null,
            'position'              => $validated['position'] ?? null,
            'role'                  => $validated['role'],
            'department_id'         => $validated['department_id'],
            'office_id'             => $validated['office_id'] ?? null,
            'status'                => $validated['status'] ?? 'active',
            'password'              => Hash::make($validated['password']),
            'needs_password_change' => true, // Enforce password reset on first login
        ]);

        AuditTrail::log("User Account Created: {$user->email}", "users/{$user->id}", null, $user->toArray());
        ActivityLog::log('New user created: ' . $user->name, null, [
            'email' => $user->email,
            'role' => $user->role,
            'employee_id' => $user->employee_id
        ]);

        // Send welcome email
        try {
            Mail::to($user->email)->send(new \App\Mail\WelcomeUserMail($user, $validated['password']));
            ActivityLog::log('Welcome Email Sent to ' . $user->email, null, ['email' => $user->email]);
            Log::channel('email')->info("Welcome email sent to {$user->email}");
        } catch (\Exception $e) {
            \Log::error('Failed to send welcome email to ' . $user->email . ': ' . $e->getMessage());
            ActivityLog::log('Welcome Email Sending Failed to ' . $user->email, null, ['error' => $e->getMessage()]);
            Log::channel('email')->error("Failed to send welcome email to {$user->email}: " . $e->getMessage());
        }

        return redirect()->route('users.index')->with('success', 'User account created successfully!');
    }

    /**
     * Update user details and roles.
     */
    public function update(Request $request, $id)
    {
        $this->authorizeAdmin();
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'employee_id'   => 'nullable|string|max:255|unique:users,employee_id,'.$user->id,
            'position'      => 'nullable|string|max:255',
            'password'      => 'nullable|string|min:10|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9])[a-zA-Z0-9!@#$%^&*()\-_+=\[\]{}:;<>,.?]+$/',
            'role'          => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'office_id'     => 'nullable|exists:offices,id',
            'status'        => 'nullable|string|in:active,inactive',
        ], [
            'password.min' => 'Password must be at least 10 characters long.',
            'password.regex' => 'Password must contain uppercase, lowercase, numbers, and a special character.',
        ]);

        $oldValues = $user->toArray();

        $user->fill([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'employee_id'   => $validated['employee_id'] ?? null,
            'position'      => $validated['position'] ?? null,
            'role'          => $validated['role'],
            'department_id' => $validated['department_id'],
            'office_id'     => $validated['office_id'] ?? null,
            'status'        => $validated['status'] ?? 'active',
        ]);

        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if (Auth::id() == $user->id) {
            session([
                'user_name'     => $user->name,
                'user_email'    => $user->email,
                'user_role'     => $user->role,
                'department_id' => $user->department_id,
            ]);
        }

        AuditTrail::log("User Account Updated: {$user->email}", "users/{$user->id}", $oldValues, $user->toArray());
        ActivityLog::log('User details updated: ' . $user->name, null, [
            'email' => $user->email,
            'role' => $user->role,
            'employee_id' => $user->employee_id
        ]);

        return redirect()->route('users.index')->with('success', 'User updated successfully!');
    }

    /**
     * Remove the user.
     */
    public function destroy($id)
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($id);
        
        if (Auth::id() == $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $oldValues = $user->toArray();
        $user->delete();

        AuditTrail::log("User Account Deleted: {$user->email}", "users/{$id}", $oldValues, null);
        return redirect()->route('users.index')->with('success', 'User removed from the system.');
    }

    protected function authorizeAdmin()
    {
        $role = session('user_role') ?? auth()->user()?->role;
        $user = auth()->user() ?? User::find(session('user_id'));
        $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($role);

        if (!$isAdmin) {
            abort(403, 'Administrator privileges are required to perform this action.');
        }
    }

    // --- Upgraded Security Helpers & Methods ---

    private function validateReCaptcha($responseToken)
    {
        if (!env('RECAPTCHA_ENABLED', false)) {
            return true;
        }

        $secret = env('RECAPTCHA_SECRET_KEY', '6LeIxAcTAAAAAGG-vFI1Tn5cfdCwCxC1TvpaA1Yb');
        if (!$responseToken) {
            return false;
        }

        // Global Google reCAPTCHA test keys only work on 'localhost' and '127.0.0.1'.
        // If testing on a custom local test domain (e.g. naaprouting_system.test) in local environment,
        // we bypass the remote API call to avoid Google domain-lock mismatch failures.
        if (app()->environment('local') && $secret === '6LeIxAcTAAAAAGG-vFI1Tn5cfdCwCxC1TvpaA1Yb') {
            return true;
        }

        try {
            $client = new \GuzzleHttp\Client();
            $res = $client->post('https://www.google.com/recaptcha/api/siteverify', [
                'form_params' => [
                    'secret' => $secret,
                    'response' => $responseToken,
                    'remoteip' => request()->ip(),
                ]
            ]);
            $body = json_decode($res->getBody(), true);
            return $body['success'] ?? false;
        } catch (\Exception $e) {
            Log::channel('security')->error('reCAPTCHA Validation Exception: ' . $e->getMessage());
            return true; // Fallback to pass in development
        }
    }

    public function showOtpVerify()
    {
        if (!session('temp_otp_user_id')) {
            return redirect()->route('login');
        }
        return view('otp-verify');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|numeric|digits:6',
        ]);

        $userId = session('temp_otp_user_id') ?: Auth::id();
        if (!$userId) {
            return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login')->with('error', 'User not found.');
        }

        // Fetch the latest OTP record for this user
        $otpRecord = \App\Models\Otp::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->first();

        // 1. Check if OTP exists
        if (!$otpRecord) {
            return back()->with('error', 'Invalid OTP');
        }

        // 2. Check maximum 5 verification attempts
        if ($otpRecord->attempts >= 5) {
            // Invalidate the OTP to prevent reuse
            $otpRecord->update(['is_used' => true]);
            return back()->with('error', 'Too many attempts');
        }

        // 3. Check if already used
        if ($otpRecord->is_used) {
            return back()->with('error', 'OTP already used');
        }

        // 4. Validate expiration before authentication
        if ($otpRecord->expires_at->isPast()) {
            return back()->with('error', 'OTP expired');
        }

        // 5. Check if OTP is correct
        $inputOtp = $request->input('otp');
        $checkResult = Hash::check($inputOtp, $otpRecord->otp);
        Log::channel('email')->info('OTP Verification Attempt:', [
            'input_otp' => $inputOtp,
            'record_id' => $otpRecord->id,
            'user_id' => $user->id,
            'stored_otp_hash' => $otpRecord->otp,
            'check_result' => $checkResult ? 'TRUE' : 'FALSE',
        ]);

        if (!$checkResult) {
            // Increment attempts
            $otpRecord->increment('attempts');
            
            // If they just hit 5 attempts, invalidate and return "Too many attempts"
            if ($otpRecord->attempts >= 5) {
                $otpRecord->update(['is_used' => true]);
                return back()->with('error', 'Too many attempts');
            }

            return back()->with('error', 'Invalid OTP');
        }

        // 6. Mark OTP as used
        $otpRecord->update(['is_used' => true]);

        session()->forget(['temp_otp_user_id', 'temp_otp_login_time']);

        // Check forced password change
        if ($user->needs_password_change) {
            session(['change_password_user_id' => $user->id]);
            return redirect()->route('login.password.reset')->with('info', 'You must change your password before continuing.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        session([
            'authenticated' => true,
            'user_id'       => $user->id,
            'user_name'     => $user->name,
            'user_email'    => $user->email,
            'user_role'     => $user->role,
            'department_id' => $user->department_id,
        ]);

        $ip = $request->ip() ?? '127.0.0.1';
        $userAgent = $request->header('User-Agent', '');
        [$browser, $os, $device] = AuditTrail::parseUserAgentExtended($userAgent);

        DB::table('login_histories')->insert([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $ip,
            'device' => $device,
            'user_agent' => $userAgent,
            'login_time' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        \App\Services\SuspiciousLoginDetector::detect($user, $request);

        Log::channel('authentication')->info("Successful OTP login verified for user {$user->email} from IP {$ip}");
        AuditTrail::log("User Logged In", "users/{$user->id}");

        return redirect()->route('dashboard')->with('success', 'Welcome back, ' . $user->name . '.');
    }

    public function resendOtp(Request $request)
    {
        $userId = session('temp_otp_user_id') ?: Auth::id();
        if (!$userId) {
            return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login')->with('error', 'User not found.');
        }

        // Check if there is an existing OTP that was created within 60 seconds
        $lastOtp = \App\Models\Otp::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOtp && $lastOtp->created_at->addSeconds(60)->isFuture()) {
            return back()->with('error', 'Too many resend requests');
        }

        // Generate a new OTP
        $otp = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $otp) {
            // Invalidate the previous OTPs
            \App\Models\Otp::where('user_id', $user->id)->delete();

            // Save the new OTP
            $record = \App\Models\Otp::create([
                'user_id' => $user->id,
                'otp' => Hash::make($otp),
                'expires_at' => now()->addMinutes(5),
                'is_used' => false,
                'attempts' => 0,
            ]);

            Log::channel('email')->info('OTP Generated and Stored (Resend):', [
                'generated_otp' => $otp,
                'record_id' => $record->id,
                'user_id' => $user->id,
            ]);
        });

        // Send through the configured Email API
        $sent = false;
        $sendError = null;
        try {
            $emailService = new \App\Services\EmailService();
            $subject = "Login Verification Code";
            $body = "Hello {$user->name},\n\n"
                  . "Your verification code is:\n\n"
                  . "{$otp}\n\n"
                  . "This code expires in 5 minutes.\n\n"
                  . "If you did not request this login, simply ignore this email.\n\n"
                  . "Regards,\n"
                  . "NAAP Document Routing System";

            $sent = $emailService->send($user->email, $subject, $body);
            if ($sent) {
                Log::channel('email')->info("Login OTP email successfully sent/logged (Resent) for {$user->email}");
            } else {
                $sendError = 'Provider returned failure status.';
                Log::channel('email')->error("Failed to resend login OTP email to {$user->email}: {$sendError}");
            }
        } catch (\Exception $e) {
            $sendError = $e->getMessage();
            Log::channel('email')->error("Failed to resend login OTP email: " . $sendError);
        }

        if (!$sent) {
            $displayError = config('app.debug') || env('APP_DEBUG', false)
                ? 'Failed to send verification code. Error: ' . $sendError
                : 'Failed to send verification code. Please check configuration/logs.';
            return back()->with('error', $displayError);
        }

        return back()->with('success', 'A new verification code has been sent to your email.');
    }

    public function showForcePasswordReset()
    {
        if (!session('change_password_user_id')) {
            return redirect()->route('login');
        }
        return view('force-password');
    }

    public function forcePasswordReset(Request $request)
    {
        $userId = session('change_password_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $request->validate([
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

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $user->update([
            'password' => $request->input('new_password'),
            'needs_password_change' => false
        ]);

        session()->forget('change_password_user_id');

        Auth::login($user);
        $request->session()->regenerate();

        session([
            'authenticated' => true,
            'user_id'       => $user->id,
            'user_name'     => $user->name,
            'user_email'    => $user->email,
            'user_role'     => $user->role,
            'department_id' => $user->department_id,
        ]);

        try {
            Mail::to($user->email)->send(new \App\Mail\SecurityMail(
                "🔐 Security Notice: Password Updated Successfully",
                $user->name,
                "<p>This email confirms that your password for NAAP Routing was updated successfully.</p>"
                . "<p>If you did not perform this change, please notify your administrator immediately.</p>"
            ));
        } catch (\Exception $e) {
            Log::channel('email')->error("Failed to send password reset success email: " . $e->getMessage());
        }

        AuditTrail::log("Forced Password Reset Successfully Completed", "users/{$user->id}");

        return redirect()->route('dashboard')->with('success', 'Password updated successfully! Welcome to your dashboard.');
    }
}