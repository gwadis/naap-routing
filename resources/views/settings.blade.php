@extends('layouts.app')

@section('title', 'Settings')

@section('head')
<style>
    .settings-card {
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
        gap: 15px;
        margin-bottom: 25px;
    }

    .icon-box {
        width: 45px;
        height: 45px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        font-size: 1.4rem;
    }

    /* Section Specific Colors */
    .bg-security { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .bg-qr { background: rgba(59, 130, 246, 0.1); color: var(--accent-cyan); }
    .bg-notif { background: rgba(79, 70, 229, 0.1); color: var(--accent-purple); }
    .bg-logs { background: rgba(16, 185, 129, 0.1); color: #059669; }

    .form-group-custom {
        background: var(--bg);
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .form-label-custom {
        margin-bottom: 0;
        font-weight: 600;
        color: inherit;
    }

    .form-control-dark {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        color: inherit;
        border-radius: 8px;
        padding: 8px 12px;
        width: 110px;
        text-align: center;
    }

    .form-control-dark:focus {
        background: var(--panel);
        border-color: var(--accent-cyan);
        color: inherit;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    /* Full-width responsive form controls */
    .form-control-custom {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        color: inherit;
        border-radius: 8px;
        padding: 9px 13px;
        width: 100%;
        font-size: 0.88rem;
        box-sizing: border-box;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-control-custom:focus {
        background: var(--panel);
        border-color: var(--accent-cyan);
        color: inherit;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        outline: none;
    }

    .form-select-custom {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        color: inherit;
        border-radius: 8px;
        padding: 9px 36px 9px 13px;
        width: 100%;
        font-size: 0.88rem;
        box-sizing: border-box;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 14px;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-select-custom:focus {
        background: var(--panel);
        border-color: var(--accent-cyan);
        color: inherit;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        outline: none;
    }

    .form-select-custom option {
        background: var(--panel);
        color: var(--text-main);
    }

    textarea.form-control-custom {
        min-height: 96px;
        resize: vertical;
        width: 100%;
    }

    /* Toggle Switch Styling */
    .form-check-input {
        width: 3em;
        height: 1.5em;
        cursor: pointer;
    }
    
    .form-check-input:checked {
        background-color: var(--accent-cyan);
        border-color: var(--accent-cyan);
    }

    /* Normal-flow Save Bar (prevents overlapping System Logs or other cards) */
    .save-bar {
        position: static;
        background: var(--panel);
        padding: 16px 24px;
        border-radius: 12px;
        border: 1px solid var(--panel-border);
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 12px;
        margin-top: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    }

    @media (max-width: 576px) {
        .save-bar {
            flex-direction: column-reverse;
            padding: 14px 18px;
        }
        .save-bar button {
            width: 100% !important;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid pb-5">
    <div class="mb-4 text-start">
        <h1 class="page-title mb-2">Settings</h1>
        <p class="text-secondary small mb-0">System configuration and preferences</p>
    </div>

    <form action="{{ route('settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="settings-card text-start">
            <div class="section-header">
                <div class="icon-box bg-security">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <div>
                    <h5 class="m-0 fw-bold">Security Settings</h5>
                    <small class="text-secondary">Password and authentication</small>
                </div>
            </div>

            <div class="form-group-custom">
                <div>
                    <div class="form-label-custom">Two-Factor Authentication</div>
                    <small class="text-secondary">Add an extra layer of security</small>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="2fa_enabled" checked>
                </div>
            </div>

            <div class="form-group-custom flex-column align-items-start">
                <div class="w-100 d-flex justify-content-between align-items-center mb-2">
                    <div class="form-label-custom">Minimum Password Length</div>
                    <input type="number" class="form-control-dark" name="min_password" value="12">
                </div>
                <small class="text-secondary">Recommended: 12 characters minimum</small>
            </div>

            <div class="form-group-custom">
                <div>
                    <div class="form-label-custom">Session Timeout (minutes)</div>
                </div>
                <input type="number" class="form-control-dark" name="session_timeout" value="30">
            </div>
        </div>

        <div class="settings-card text-start">
            <div class="section-header">
                <div class="icon-box bg-qr">
                    <i class="bi bi-qr-code"></i>
                </div>
                <div>
                    <h5 class="m-0 fw-bold">QR Code Settings</h5>
                    <small class="text-secondary">Generation and sizing preferences</small>
                </div>
            </div>

            <div class="form-group-custom">
                <div>
                    <div class="form-label-custom">Auto-Generate QR Codes</div>
                    <small class="text-secondary">Automatically create QR codes for new documents</small>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="auto_qr">
                </div>
            </div>

            <div class="form-group-custom">
                <div>
                    <div class="form-label-custom">QR Code Size (pixels)</div>
                </div>
                <input type="number" class="form-control-dark" name="qr_size" value="256">
            </div>
        </div>

        <div class="settings-card text-start">
            <div class="section-header">
                <div class="icon-box bg-notif">
                    <i class="bi bi-bell"></i>
                </div>
                <div>
                    <h5 class="m-0 fw-bold">Notification Channels</h5>
                    <small class="text-secondary">Core channels & optional emergency fallback</small>
                </div>
            </div>

            <div class="form-group-custom">
                <div>
                    <div class="form-label-custom d-flex align-items-center gap-2">
                        <span>In-App Notifications</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill" style="font-size: 0.72rem;">Active</span>
                    </div>
                    <small class="text-secondary">Real-time alerts and document routing tracking inside the dashboard</small>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="inapp_notif" checked disabled>
                </div>
            </div>

            <div class="form-group-custom">
                <div>
                    <div class="form-label-custom d-flex align-items-center gap-2">
                        <span>Email Notifications</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill" style="font-size: 0.72rem;">Active</span>
                    </div>
                    <small class="text-secondary">Dispatch email alerts for routing actions, approvals, and 5-min OTP verification</small>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="email_notif" checked>
                </div>
            </div>

            <div class="form-group-custom">
                <div class="me-3">
                    <div class="form-label-custom d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span>Telegram Notifications</span>
                        @if(app(\App\Services\TelegramService::class)->isConfigured())
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill" style="font-size: 0.72rem;">Available</span>
                        @else
                            <span class="badge bg-secondary text-light px-2 py-1 rounded-pill" style="font-size: 0.72rem;">Zero-Config Ready</span>
                        @endif
                    </div>
                    <small class="text-secondary d-block">Optional channel. Users freely connect their personal Telegram account in their Profile for instant announcements & alerts.</small>
                </div>
                <div class="form-check form-switch flex-shrink-0 m-0">
                    <input class="form-check-input" type="checkbox" checked disabled>
                </div>
            </div>

            <div class="form-group-custom flex-column align-items-start">
                <div class="w-100 d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <div class="form-label-custom">Broadcast System Announcement</div>
                        <small class="text-secondary">Send announcement across In-App, Email, and Telegram connected accounts</small>
                    </div>
                </div>
                <div class="w-100 mt-3">
                    <div class="row g-3">
                        <div class="col-12 col-md-8 text-start">
                            <label for="announceTitle" class="form-label small text-secondary mb-1 fw-semibold">Announcement Title / Subject</label>
                            <input type="text" id="announceTitle" class="form-control-custom" placeholder="e.g. Scheduled System Maintenance">
                        </div>
                        <div class="col-12 col-md-4 text-start">
                            <label for="announceAudience" class="form-label small text-secondary mb-1 fw-semibold">Audience</label>
                            <select id="announceAudience" class="form-select-custom">
                                <option value="all">Audience: All Users</option>
                                <option value="staff">Audience: Staff & Office Heads</option>
                                <option value="admins">Audience: Administrators Only</option>
                            </select>
                        </div>
                        <div class="col-12 text-start">
                            <label for="announceMessage" class="form-label small text-secondary mb-1 fw-semibold">Announcement Message</label>
                            <textarea id="announceMessage" class="form-control-custom" rows="3" placeholder="Announcement details, maintenance timeframes, or system issues..."></textarea>
                        </div>
                        <div class="col-12 text-start">
                            <button type="button" id="btnBroadcastAnnounce" class="btn btn-outline-primary btn-sm px-3 fw-bold" style="height: 38px;" onclick="sendBroadcastAnnouncement()">
                                <i class="bi bi-megaphone me-1"></i> Send Announcement
                            </button>
                            <div id="announceStatus" class="mt-2 small fw-semibold" style="display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group-custom">
                <div class="me-3">
                    <div class="form-label-custom d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span>SMS Emergency Fallback</span>
                        @if(config('services.sms.enabled') && app(\App\Services\SmsService::class)->isConfigured())
                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 rounded-pill" style="font-size: 0.72rem;">Configured</span>
                        @else
                            <span class="badge bg-secondary text-light px-2 py-1 rounded-pill" style="font-size: 0.72rem;">Unavailable</span>
                        @endif
                    </div>
                    @if(config('services.sms.enabled') && app(\App\Services\SmsService::class)->isConfigured())
                        <small class="text-secondary d-block">Active external cellular SMS provider configured for emergency alerts.</small>
                    @else
                        <small class="text-secondary d-block">SMS emergency fallback is currently unavailable. Core routing runs on Email and In-App notifications.</small>
                    @endif
                </div>
                <div class="form-check form-switch flex-shrink-0 m-0">
                    <input class="form-check-input" type="checkbox" name="sms_notif" {{ (config('services.sms.enabled') && app(\App\Services\SmsService::class)->isConfigured()) ? 'checked' : 'disabled' }}>
                </div>
            </div>

            <div class="form-group-custom flex-column align-items-start">
                <div class="w-100 d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <div class="form-label-custom">Emergency SMS Fallback Action</div>
                        <small class="text-secondary">Check provider status or create device SMS draft (Zero Cost)</small>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 w-100 mt-2 align-items-center">
                    <input type="text" id="testSmsPhone" class="form-control-custom" value="09690222557" placeholder="e.g. 09690222557" style="max-width: 260px; min-width: 180px;">
                    <button type="button" id="btnTestSms" class="btn btn-outline-secondary btn-sm px-3 fw-bold" style="height: 38px;" onclick="sendTestSmsNow()">
                        <i class="bi bi-chat-dots me-1"></i> Check / Draft SMS
                    </button>
                </div>
                <div id="testSmsStatus" class="mt-2 small fw-semibold" style="display:none;"></div>
                <div id="testSmsActionWrap" class="mt-2" style="display:none;">
                    <a id="btnOpenDeviceSms" href="#" class="btn btn-primary btn-sm px-3 fw-bold text-white text-decoration-none" style="height: 38px; display: inline-flex; align-items: center;">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Open SMS
                    </a>
                    <small class="text-secondary ms-2">SMS draft created — click to open your device's native messaging application.</small>
                </div>
            </div>
        </div>

        <div class="save-bar my-4">
            <button type="button" class="btn btn-link text-decoration-none fw-bold" onclick="window.location.reload()" style="color: var(--text-dim) !important; height: 44px !important;">Cancel</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold" style="height: 44px !important;">Save Changes</button>
        </div>

        <div class="settings-card text-start">
            <div class="section-header">
                <div class="icon-box bg-logs">
                    <i class="bi bi-database"></i>
                </div>
                <div>
                    <h5 class="m-0 fw-bold">System Logs</h5>
                    <small class="text-secondary">Activity logging settings</small>
                </div>
            </div>

            <div class="form-group-custom flex-column align-items-start">
                <div class="w-100 d-flex justify-content-between align-items-center mb-2">
                    <div class="form-label-custom">Log Retention Period (days)</div>
                    <input type="number" class="form-control-dark" name="log_retention" value="90">
                </div>
                <small class="text-secondary">Logs older than this will be automatically deleted</small>
            </div>
        </div>
    </form>
</div>

<script>
function sendTestSmsNow() {
    const phoneInput = document.getElementById('testSmsPhone');
    const statusDiv = document.getElementById('testSmsStatus');
    const actionWrap = document.getElementById('testSmsActionWrap');
    const openSmsBtn = document.getElementById('btnOpenDeviceSms');
    const btn = document.getElementById('btnTestSms');
    const phone = phoneInput ? phoneInput.value.trim() : '09690222557';

    if (!phone) {
        statusDiv.style.display = 'block';
        statusDiv.className = 'mt-2 small fw-semibold text-danger';
        statusDiv.innerText = 'Please enter a valid phone number.';
        if (actionWrap) actionWrap.style.display = 'none';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking...';
    statusDiv.style.display = 'block';
    statusDiv.className = 'mt-2 small fw-semibold text-info';
    statusDiv.innerText = 'Checking SMS emergency fallback status for ' + phone + '...';
    if (actionWrap) actionWrap.style.display = 'none';

    fetch('{{ route("settings.test-sms") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ phone: phone })
    })
    .then(res => res.json().catch(() => ({ success: false, status: 'SMS_UNAVAILABLE', info: 'SMS provider not configured.' })))
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-chat-dots me-1"></i> Check / Draft SMS';
        statusDiv.style.display = 'block';

        if (data.status === 'SMS_SENT' && data.success) {
            statusDiv.className = 'mt-2 small fw-semibold text-success';
            statusDiv.innerText = '✔ SMS SENT: Verified cellular delivery confirmed by provider.';
            if (actionWrap) actionWrap.style.display = 'none';
        } else {
            // Never report SMS Sent if no provider delivered it
            statusDiv.className = 'mt-2 small fw-semibold text-warning';
            statusDiv.innerText = data.info || 'SMS unavailable — no SMS provider configured.';

            if (data.device_sms_uri && openSmsBtn && actionWrap) {
                openSmsBtn.href = data.device_sms_uri;
                actionWrap.style.display = 'block';
            }
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-chat-dots me-1"></i> Check / Draft SMS';
        statusDiv.style.display = 'block';
        statusDiv.className = 'mt-2 small fw-semibold text-secondary';
        statusDiv.innerText = 'SMS unavailable — no SMS provider configured.';
    });
}

function sendBroadcastAnnouncement() {
    const titleInput = document.getElementById('announceTitle');
    const messageInput = document.getElementById('announceMessage');
    const audienceInput = document.getElementById('announceAudience');
    const statusDiv = document.getElementById('announceStatus');
    const btn = document.getElementById('btnBroadcastAnnounce');

    const title = titleInput ? titleInput.value.trim() : '';
    const message = messageInput ? messageInput.value.trim() : '';
    const audience = audienceInput ? audienceInput.value : 'all';

    if (!title || !message) {
        statusDiv.style.display = 'block';
        statusDiv.className = 'mt-2 small fw-semibold text-danger';
        statusDiv.innerText = 'Please provide both an announcement title and message.';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Broadcasting...';
    statusDiv.style.display = 'block';
    statusDiv.className = 'mt-2 small fw-semibold text-info';
    statusDiv.innerText = 'Dispatching announcement across channels...';

    fetch('{{ route("telegram.announcement") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            title: title,
            message: message,
            audience: audience
        })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-megaphone me-1"></i> Send Announcement';
        statusDiv.style.display = 'block';

        if (data.success) {
            statusDiv.className = 'mt-2 small fw-semibold text-success';
            statusDiv.innerText = '✔ ' + data.message;
            if (titleInput) titleInput.value = '';
            if (messageInput) messageInput.value = '';
        } else {
            statusDiv.className = 'mt-2 small fw-semibold text-danger';
            statusDiv.innerText = '✖ ' + (data.error || 'Failed to dispatch announcement.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-megaphone me-1"></i> Send Announcement';
        statusDiv.style.display = 'block';
        statusDiv.className = 'mt-2 small fw-semibold text-danger';
        statusDiv.innerText = 'Broadcast request error. Please try again.';
    });
}
</script>
@endsection