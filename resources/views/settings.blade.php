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
        width: 120px;
        text-align: center;
    }

    .form-control-dark:focus {
        background: var(--panel);
        border-color: var(--accent-cyan);
        color: inherit;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
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

    .save-bar {
        position: sticky;
        bottom: 20px;
        background: var(--panel);
        padding: 15px 25px;
        border-radius: 12px;
        border: 1px solid var(--panel-border);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        z-index: 100;
        box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.03), 0 4px 6px -1px rgba(0, 0, 0, 0.05);
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
                    <h5 class="m-0 fw-bold">Notifications</h5>
                    <small class="text-secondary">Email and system alerts</small>
                </div>
            </div>

            <div class="form-group-custom">
                <div>
                    <div class="form-label-custom">Email Notifications</div>
                    <small class="text-secondary">Receive email alerts for document updates</small>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="email_notif" checked>
                </div>
            </div>
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

        <div class="save-bar">
            <button type="button" class="btn btn-link text-decoration-none fw-bold" onclick="window.location.reload()" style="color: var(--text-dim) !important; height: 44px !important;">Cancel</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold" style="height: 44px !important;">Save Changes</button>
        </div>
    </form>
</div>
@endsection