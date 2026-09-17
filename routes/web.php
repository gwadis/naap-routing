<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\QRController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SecuritySettingsController;
use App\Http\Controllers\SecurityDashboardController;

// --- Public Routes ---
Route::get('/', function() {
    if (session()->has('user_id')) {
        return redirect()->route('dashboard');
    }
    $showRecaptcha = false;
    if (env('RECAPTCHA_ENABLED', false)) {
        $ip = request()->ip() ?? '127.0.0.1';
        $failedAttemptsCount = \Illuminate\Support\Facades\DB::table('login_failures')
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();
        $showRecaptcha = $failedAttemptsCount >= 3;
    }
    return view('login', compact('showRecaptcha'));
})->name('home');

Route::get('/login', function() {
    if (session()->has('user_id')) {
        return redirect()->route('dashboard');
    }
    $showRecaptcha = false;
    if (env('RECAPTCHA_ENABLED', false)) {
        $ip = request()->ip() ?? '127.0.0.1';
        $failedAttemptsCount = \Illuminate\Support\Facades\DB::table('login_failures')
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();
        $showRecaptcha = $failedAttemptsCount >= 3;
    }
    return view('login', compact('showRecaptcha'));
})->name('login');

Route::post('/login', [UserController::class, 'login'])->name('login.submit');

// OTP verification routes
Route::get('/login/otp-verify', [UserController::class, 'showOtpVerify'])->name('login.otp.verify');
Route::post('/login/otp-verify', [UserController::class, 'verifyOtp'])->name('login.otp.verify.submit');
Route::post('/login/otp-resend', [UserController::class, 'resendOtp'])->name('login.otp.resend');

// Forced password reset on first login
Route::get('/login/password-reset', [UserController::class, 'showForcePasswordReset'])->name('login.password.reset');
Route::post('/login/password-reset', [UserController::class, 'forcePasswordReset'])->name('login.password.update');

// 2FA Routes
Route::get('/2fa/verify', [UserController::class, 'show2FAVerify'])->name('2fa.verify');
Route::post('/2fa/verify', [UserController::class, 'verify2FA'])->name('2fa.verify.submit');

// --- Authenticated Admin Routes ---
Route::middleware([\App\Http\Middleware\EnsureAuthenticated::class, \App\Http\Middleware\VerifyPasswordChange::class])->group(function () {

    Route::get('/logout', function () {
        session()->flush();
        Auth::logout(); // Ensure Laravel Auth is also cleared
        return redirect()->route('home')->with('success', 'Logged out successfully.');
    })->name('logout');

    Route::get('/register', [UserController::class, 'create'])->name('register');

    // --- Core Admin Dashboard ---
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/notifications', [DashboardController::class, 'notifications'])->name('api.notifications');
    Route::post('/api/notifications/mark-read', [DashboardController::class, 'markAllRead'])->name('api.notifications.markRead');

    // --- Resource Management (Users & Offices) ---
    Route::resource('users', UserController::class);
    Route::resource('offices', OfficeController::class);

    // --- API Endpoints for Departments ---
    Route::get('/api/departments/{name}/offices', [OfficeController::class, 'getDepartmentOffices']);
    Route::get('/api/departments/{name}/users', [OfficeController::class, 'getDepartmentUsers']);
    Route::post('/api/departments/rename', [OfficeController::class, 'renameDepartment']);
    Route::get('/api/offices/{id}/staff', [OfficeController::class, 'getOfficeStaff']);
    Route::get('/api/departments/{department}/staff', [OfficeController::class, 'getDepartmentStaff']);
    Route::get('/api/categories/suggest', [DocumentController::class, 'suggestCategory'])->name('api.categories.suggest');

    // --- Document Management & Tracking ---
    Route::resource('documents', DocumentController::class);
    Route::post('/documents/{id}/regenerate-pin', [DocumentController::class, 'regeneratePin'])->name('documents.regeneratePin');
    Route::post('/documents/{id}/workflow', [DocumentController::class, 'workflowAction'])->name('documents.workflowAction');
    Route::post('/documents/{id}/forward', [DocumentController::class, 'forwardDocument'])->name('documents.forward');
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{id}/qr-label', [DocumentController::class, 'qrLabel'])->name('documents.qr-label');
    Route::get('/track', [DocumentController::class, 'trackIndex'])->name('track.index');
    Route::get('/track/{id}', [DocumentController::class, 'show'])->name('track.detail');
    Route::get('/api/documents/{id}/status', [DocumentController::class, 'checkStatus'])->name('documents.status');
    Route::get('/activity', [DocumentController::class, 'activityIndex'])->name('activity.index');

    // --- Notifications ---
    Route::get('/notifications', [DashboardController::class, 'notificationsPage'])->name('notifications.index');
    Route::match(['get', 'post'], '/notifications/{id}/read', [DashboardController::class, 'markSingleRead'])->name('notifications.markRead');
    Route::delete('/notifications/{id}', [DashboardController::class, 'deleteNotification'])->name('notifications.delete');
    Route::post('/notifications/mark-all-read', [DashboardController::class, 'markAllRead'])->name('notifications.markAllRead');

    // --- Routing & QR System ---
    Route::get('/routing', [RoutingController::class, 'index'])->name('routing.index');
    Route::post('/routing/update/{id}', [RoutingController::class, 'routeDocument'])->name('routing.route');
    Route::get('/scan-qr', [QRController::class, 'index'])->name('qr.index');
    Route::post('/scan-qr/process', [QRController::class, 'scan'])->name('qr.scan');
    Route::post('/scan-qr/store', [QRController::class, 'store'])->name('qr.store');

    // --- Reports & Analytics ---
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    // --- System Settings ---
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/update', [SettingsController::class, 'update'])->name('settings.update');

    // --- Profile Management ---
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::post('/profile/update', [ProfileController::class, 'updateInfo'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/signature', [ProfileController::class, 'updateSignature'])->name('profile.signature');

    // --- User Security Settings ---
    Route::get('/security/settings', [SecuritySettingsController::class, 'index'])->name('security.settings');
    Route::post('/security/settings/password', [SecuritySettingsController::class, 'updatePassword'])->name('security.settings.password');
    Route::post('/security/settings/2fa', [SecuritySettingsController::class, 'toggle2FA'])->name('security.settings.2fa');
    Route::post('/security/settings/recovery-email', [SecuritySettingsController::class, 'updateRecoveryEmail'])->name('security.settings.recovery-email');
    Route::post('/security/settings/session/terminate', [SecuritySettingsController::class, 'terminateSession'])->name('security.settings.session.terminate');
    Route::get('/security/settings/activity/download', [SecuritySettingsController::class, 'downloadActivity'])->name('security.settings.activity.download');

    // --- Security Dashboard (Admin Only) ---
    Route::get('/security/dashboard', [SecurityDashboardController::class, 'index'])->name('security.dashboard');
});