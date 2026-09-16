@extends('layouts.app')

@section('title', 'Security Settings')

@section('head')
<style>
    .security-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    }
    .section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }
    .icon-box {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        font-size: 1.25rem;
    }
    .bg-security { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .bg-2fa { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .bg-email { background: rgba(59, 130, 246, 0.1); color: var(--accent-cyan); }
    .bg-sessions { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    .session-item {
        background: var(--bg);
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .strength-meter {
        height: 5px;
        background-color: rgba(0,0,0,0.1);
        border-radius: 2.5px;
        margin-top: 6px;
        overflow: hidden;
    }
    .strength-bar {
        width: 0%;
        height: 100%;
        transition: width 0.3s, background-color 0.3s;
    }
    .strength-text {
        font-size: 0.78rem;
        margin-top: 4px;
        font-weight: 600;
    }
    .policy-list {
        list-style: none;
        padding-left: 0;
        font-size: 0.8rem;
        margin-top: 8px;
    }
    .policy-item {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 3px;
        color: var(--text-dim);
    }
    .policy-item.valid { color: #10b981; }
    .policy-item.invalid { color: #ef4444; }
</style>
@endsection

@section('content')
<div class="container-fluid p-4">
    <h3 class="fw-bold mb-4" style="color: var(--accent-navy);"><i class="bi bi-shield-lock-fill me-2"></i>Account Security Settings</h3>

    @if(session('success'))
        <div class="alert alert-success border-0 mb-4" style="border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 mb-4" style="border-radius: 8px; background: rgba(239, 68, 68, 0.15); color: #ef4444;">
            {{ session('error') }}
        </div>
    @endif

    <div class="row">
        <!-- Left Column: Settings -->
        <div class="col-lg-6">
            <!-- Change Password -->
            <div class="security-card">
                <div class="section-header">
                    <div class="icon-box bg-security"><i class="bi bi-key-fill"></i></div>
                    <h5 class="fw-bold mb-0">Change Password</h5>
                </div>
                <form action="{{ route('security.settings.password') }}" method="POST">
                    @csrf
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="••••••••••" required>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold">New Password</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="••••••••••" required autocomplete="off">
                        <div class="strength-meter">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="strength-text" id="strengthText">Strength: Weak</span>
                        </div>
                        <ul class="policy-list">
                            <li class="policy-item invalid" id="ruleLength"><i class="bi bi-x-circle-fill"></i> Min 10 chars</li>
                            <li class="policy-item invalid" id="ruleUpper"><i class="bi bi-x-circle-fill"></i> Uppercase</li>
                            <li class="policy-item invalid" id="ruleLower"><i class="bi bi-x-circle-fill"></i> Lowercase</li>
                            <li class="policy-item invalid" id="ruleNumber"><i class="bi bi-x-circle-fill"></i> Number</li>
                            <li class="policy-item invalid" id="ruleSpecial"><i class="bi bi-x-circle-fill"></i> Special character</li>
                        </ul>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold">Confirm New Password</label>
                        <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control" placeholder="••••••••••" required autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-danger w-100 py-2 fw-bold" id="submitBtn" disabled>Update Password</button>
                </form>
            </div>

            <!-- Two-Factor Authentication Toggle -->
            <div class="security-card">
                <div class="section-header">
                    <div class="icon-box bg-2fa"><i class="bi bi-shield-check"></i></div>
                    <h5 class="fw-bold mb-0">Two-Factor Authentication (2FA)</h5>
                </div>
                <p class="small text-muted text-start">Two-factor authentication adds an extra layer of protection to your account by requiring an authenticator code on login.</p>
                <form action="{{ route('security.settings.2fa') }}" method="POST">
                    @csrf
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded border mb-3">
                        <span class="fw-bold">Enable App-based 2FA</span>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="enable_2fa" value="1" onchange="this.form.submit()" {{ $user->two_factor_confirmed_at ? 'checked' : '' }}>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Sessions & History -->
        <div class="col-lg-6">
            <!-- Recovery Email & Export -->
            <div class="security-card">
                <div class="section-header">
                    <div class="icon-box bg-email"><i class="bi bi-envelope-shield"></i></div>
                    <h5 class="fw-bold mb-0">Account Recovery & Actions</h5>
                </div>
                <form action="{{ route('security.settings.recovery-email') }}" method="POST" class="mb-4">
                    @csrf
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold">Recovery Email Address</label>
                        <div class="input-group">
                            <input type="email" name="recovery_email" class="form-control" placeholder="recovery@example.com" value="{{ old('recovery_email', $user->recovery_email) }}" required>
                            <button type="submit" class="btn btn-primary px-3">Save</button>
                        </div>
                    </div>
                </form>

                <hr class="border-light">

                <div class="text-start mt-3">
                    <label class="form-label small fw-bold d-block">Backup & History Logs</label>
                    <a href="{{ route('security.settings.activity.download') }}" class="btn btn-outline-secondary w-100 py-2 fw-bold text-decoration-none" style="height: auto !important;">
                        <i class="bi bi-download me-2"></i> Download Activity History (CSV)
                    </a>
                </div>
            </div>

            <!-- Active Sessions -->
            <div class="security-card">
                <div class="section-header">
                    <div class="icon-box bg-sessions"><i class="bi bi-laptop"></i></div>
                    <h5 class="fw-bold mb-0">Active Sessions</h5>
                </div>
                <p class="small text-muted text-start mb-3">Monitor active devices and terminate other active browser sessions remotely.</p>

                <div class="sessions-list text-start">
                    @foreach($sessionsList as $session)
                        <div class="session-item" id="session-{{ $session['id'] }}">
                            <div>
                                <strong style="color: var(--accent-navy);">
                                    @if($session['device'] === 'Mobile') <i class="bi bi-phone-fill me-1"></i> Mobile @elseif($session['device'] === 'Tablet') <i class="bi bi-tablet-fill me-1"></i> Tablet @else <i class="bi bi-laptop-fill me-1"></i> Desktop @endif
                                </strong> 
                                <span class="badge bg-secondary ms-2">{{ $session['browser'] }} on {{ $session['os'] }}</span>
                                <div class="small text-muted mt-1">
                                    IP: {{ $session['ip_address'] }} | Active: {{ $session['last_active'] }}
                                </div>
                            </div>
                            <div>
                                @if($session['is_current'])
                                    <span class="badge bg-success">Current Session</span>
                                @else
                                    <button class="btn btn-sm btn-outline-danger py-1 px-2 terminate-btn" onclick="terminateSession('{{ $session['id'] }}')">Terminate</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('new_password_confirmation');
    const submitBtn = document.getElementById('submitBtn');

    const ruleLength = document.getElementById('ruleLength');
    const ruleUpper = document.getElementById('ruleUpper');
    const ruleLower = document.getElementById('ruleLower');
    const ruleNumber = document.getElementById('ruleNumber');
    const ruleSpecial = document.getElementById('ruleSpecial');

    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    if(newPassword) {
        newPassword.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);
    }

    function validatePassword() {
        const pwd = newPassword.value;
        const conf = confirmPassword.value;

        const hasLength = pwd.length >= 10;
        const hasUpper = /[A-Z]/.test(pwd);
        const hasLower = /[a-z]/.test(pwd);
        const hasNumber = /[0-9]/.test(pwd);
        const hasSpecial = /[^A-Za-z0-9]/.test(pwd);

        toggleRule(ruleLength, hasLength);
        toggleRule(ruleUpper, hasUpper);
        toggleRule(ruleLower, hasLower);
        toggleRule(ruleNumber, hasNumber);
        toggleRule(ruleSpecial, hasSpecial);

        let score = 0;
        if (hasLength) score++;
        if (hasUpper) score++;
        if (hasLower) score++;
        if (hasNumber) score++;
        if (hasSpecial) score++;

        let strength = 'Weak';
        let color = '#ef4444';
        let width = '20%';

        if (score >= 5) {
            strength = 'Strong (Excellent)';
            color = '#10b981';
            width = '100%';
        } else if (score >= 4) {
            strength = 'Good';
            color = '#3b82f6';
            width = '80%';
        } else if (score >= 3) {
            strength = 'Fair';
            color = '#f59e0b';
            width = '60%';
        } else if (score >= 2) {
            strength = 'Weak';
            color = '#ef4444';
            width = '40%';
        }

        strengthBar.style.width = width;
        strengthBar.style.backgroundColor = color;
        strengthText.textContent = 'Strength: ' + strength;
        strengthText.style.color = color;

        const valid = hasLength && hasUpper && hasLower && hasNumber && hasSpecial && pwd === conf && pwd.length > 0;
        submitBtn.disabled = !valid;
    }

    function toggleRule(element, isValid) {
        if (isValid) {
            element.className = 'policy-item valid';
            element.querySelector('i').className = 'bi bi-check-circle-fill';
        } else {
            element.className = 'policy-item invalid';
            element.querySelector('i').className = 'bi bi-x-circle-fill';
        }
    }

    function terminateSession(sessionId) {
        if (!confirm('Are you sure you want to terminate this active login session?')) return;

        fetch('{{ route("security.settings.session.terminate") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ session_id: sessionId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                const row = document.getElementById('session-' + sessionId);
                if (row) row.remove();
            } else {
                showNotification(data.message, 'danger');
            }
        })
        .catch(err => {
            showNotification('Error terminating session.', 'danger');
        });
    }
</script>
@endsection
