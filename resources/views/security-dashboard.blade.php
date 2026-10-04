@extends('layouts.app')

@section('title', 'Security Dashboard')

@section('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    /* Security Dashboard Enterprise Theme */
    .stat-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg, 12px);
        padding: 18px 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .stat-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }
    .bg-blue { background: rgba(37, 99, 235, 0.08); color: #2563eb; }
    .bg-red { background: rgba(239, 68, 68, 0.08); color: #ef4444; }
    .bg-yellow { background: rgba(245, 158, 11, 0.08); color: #d97706; }
    .bg-green { background: rgba(16, 185, 129, 0.08); color: #059669; }

    .dashboard-panel {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg, 12px);
        padding: 22px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }
    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .panel-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--accent-navy, #0f172a);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Security Posture Header Banner */
    .posture-header-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg, 12px);
        padding: 20px 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }
    .posture-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 0.825rem;
        font-weight: 600;
        letter-spacing: 0.01em;
    }
    .posture-status-pill.healthy {
        background-color: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .posture-status-pill.attention {
        background-color: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .status-pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .status-pulse-dot.pulse {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: status-pulse 2s infinite;
    }
    @keyframes status-pulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    /* Subsystem Health Bar */
    .subsystem-health-bar {
        background-color: #f8fafc;
        border: 1px solid var(--panel-border);
        border-radius: 10px;
        padding: 14px 18px;
    }
    .subsystem-item {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .subsystem-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #ffffff;
        border: 1px solid var(--panel-border);
        display: grid;
        place-items: center;
        font-size: 1rem;
        color: #475569;
        flex-shrink: 0;
    }
    .subsystem-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        margin-bottom: 2px;
    }
    .subsystem-status-chip {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .chip-healthy {
        background-color: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .chip-attention {
        background-color: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    /* Metrics Grid inside Cards */
    .metric-box {
        background: #f8fafc;
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        padding: 12px 14px;
        height: 100%;
    }
    .metric-box-label {
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.03em;
        margin-bottom: 4px;
    }
    .metric-box-val {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--accent-navy, #0f172a);
        line-height: 1.1;
    }
    .metric-box-sub {
        font-size: 0.75rem;
        color: #94a3b8;
        margin-top: 3px;
    }

    /* Professional Empty States */
    .empty-security-state {
        padding: 32px 16px;
        text-align: center;
    }
    .empty-state-icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        margin: 0 auto 12px;
        font-size: 1.35rem;
    }

    /* Activity Stream */
    .security-activity-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .security-activity-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .activity-icon-badge {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        font-size: 0.95rem;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .activity-icon-badge.badge-success { background-color: #ecfdf5; color: #059669; }
    .activity-icon-badge.badge-danger { background-color: #fef2f2; color: #dc2626; }
    .activity-icon-badge.badge-warning { background-color: #fffbeb; color: #d97706; }
    .activity-icon-badge.badge-info { background-color: #eff6ff; color: #2563eb; }
    .activity-icon-badge.badge-primary { background-color: #eef2ff; color: #4f46e5; }

    /* Alert Items */
    .alert-card-item {
        background-color: #fef2f2;
        border: 1px solid #fecaca;
        border-left: 4px solid #ef4444;
        border-radius: 8px;
        padding: 12px 14px;
        margin-bottom: 10px;
    }
    .alert-card-item:last-child {
        margin-bottom: 0;
    }

    /* Security Console Table Container & Sizing Fixes */
    .security-console-col {
        min-width: 0 !important;
        max-width: 100% !important;
    }

    @media (max-width: 1199.98px) {
        .security-console-col {
            flex: 0 0 100% !important;
            max-width: 100% !important;
            width: 100% !important;
        }
    }

    .security-console-card {
        min-width: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        padding: 20px 16px !important;
    }

    .security-table-container {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: hidden !important;
        overflow-y: hidden !important;
        border: 1px solid var(--panel-border) !important;
        border-radius: var(--radius-lg, 12px) !important;
        background: var(--panel, #ffffff) !important;
    }

    /* Table styles */
    .enterprise-sec-table {
        margin-bottom: 0 !important;
        font-size: 0.8rem !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        table-layout: fixed !important;
    }
    .enterprise-sec-table thead th {
        font-size: 0.68rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.03em !important;
        color: #64748b !important;
        background-color: #f8fafc !important;
        border-bottom: 1px solid var(--panel-border) !important;
        padding: 9px 5px !important;
        height: auto !important;
        white-space: normal !important;
        word-break: normal !important;
        overflow-wrap: break-word !important;
        vertical-align: middle !important;
        line-height: 1.2 !important;
        overflow: hidden;
    }
    .enterprise-sec-table thead th:first-child,
    .enterprise-sec-table tbody td:first-child {
        padding-left: 9px !important;
    }
    .enterprise-sec-table thead th:last-child,
    .enterprise-sec-table tbody td:last-child {
        padding-right: 9px !important;
    }
    .enterprise-sec-table tbody td {
        padding: 10px 5px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #f1f5f9 !important;
        height: auto !important;
        color: var(--text-main, #0f172a);
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.25 !important;
        overflow: hidden;
    }
    .enterprise-sec-table tbody tr:last-child td {
        border-bottom: none !important;
    }

    /* Column-specific sizing & wrapping rules */
    .table-logins {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        table-layout: fixed !important;
    }
    .table-logins .col-user {
        width: 24% !important;
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    .table-logins .col-role {
        width: 22% !important;
        word-break: normal;
        overflow-wrap: break-word;
    }
    .table-logins .col-device {
        width: 16% !important;
        white-space: nowrap !important;
        overflow-wrap: normal;
    }
    .table-logins .col-time {
        width: 20% !important;
        word-break: normal;
        overflow-wrap: break-word;
        white-space: normal !important;
    }
    .table-logins .col-status {
        width: 18% !important;
        text-align: right !important;
        white-space: normal !important;
    }

    .table-suspicious {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        table-layout: fixed !important;
    }
    .table-suspicious .col-event {
        width: 31% !important;
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    .table-suspicious .col-user {
        width: 25% !important;
        word-break: break-all;
        overflow-wrap: anywhere;
    }
    .table-suspicious .col-time {
        width: 21% !important;
        word-break: normal;
        overflow-wrap: break-word;
        white-space: normal !important;
    }
    .table-suspicious .col-status {
        width: 23% !important;
        text-align: right !important;
        white-space: normal !important;
    }

    /* Badges & Pills inside security tables */
    .enterprise-sec-table .badge {
        font-size: 0.69rem !important;
        font-weight: 600 !important;
        padding: 3px 5px !important;
        white-space: normal !important;
        word-break: normal !important;
        overflow-wrap: break-word !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 3px !important;
        border-radius: var(--radius-sm, 6px) !important;
        line-height: 1.2 !important;
        text-transform: capitalize !important;
        max-width: 100% !important;
        text-align: center !important;
    }

    /* Role Pill */
    .role-badge {
        font-size: 0.67rem !important;
        font-weight: 600 !important;
        padding: 2px 5px !important;
        border-radius: 4px !important;
        background: #f1f5f9 !important;
        color: #334155 !important;
        border: 1px solid #e2e8f0 !important;
        white-space: nowrap !important;
        display: inline-block !important;
        line-height: 1.2 !important;
        max-width: 100% !important;
        text-align: center !important;
    }

    @media (max-width: 768px) {
        .stat-card { padding: 14px; }
        .dashboard-panel { padding: 16px; }
        .posture-header-card { padding: 16px; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid p-3 p-md-4">

    {{-- ================================================== --}}
    {{-- 1. SECURITY POSTURE HEADER                         --}}
    {{-- ================================================== --}}
    <div class="posture-header-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.08em;">
                    Enterprise Access & Security Control
                </div>
                <h3 class="fw-bold mb-1" style="color: var(--accent-navy, #0f172a);">
                    <i class="bi bi-shield-lock-fill me-2 text-primary"></i>Security Dashboard
                </h3>
                <p class="text-secondary small mb-0">
                    Real-time operational monitoring for authentication, sensitive document access control, and verification integrity.
                </p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if($systemIntegrityStatus === 'Healthy')
                    <div class="posture-status-pill healthy">
                        <span class="status-pulse-dot bg-success pulse"></span>
                        <span>System Integrity Verified</span>
                    </div>
                @else
                    <div class="posture-status-pill attention">
                        <span class="status-pulse-dot bg-danger"></span>
                        <span>Security Attention Required</span>
                    </div>
                @endif

                <a href="{{ route('security.settings.activity.download') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm px-3" style="height: 34px; font-weight: 600; font-size: 0.8rem;">
                    <i class="bi bi-download"></i>
                    <span>Download Audit Log</span>
                </a>
            </div>
        </div>

        {{-- Subsystem Health Status Matrix (Real State) --}}
        <div class="subsystem-health-bar mt-3">
            <div class="row g-3 align-items-center">
                <div class="col-6 col-md-3">
                    <div class="subsystem-item">
                        <div class="subsystem-icon"><i class="bi bi-person-badge"></i></div>
                        <div>
                            <div class="subsystem-label">Authentication</div>
                            <div>
                                @if($authHealth === 'Healthy')
                                    <span class="subsystem-status-chip chip-healthy"><i class="bi bi-check-circle-fill"></i> Operational</span>
                                @else
                                    <span class="subsystem-status-chip chip-attention"><i class="bi bi-exclamation-triangle-fill"></i> Attention Required</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="subsystem-item">
                        <div class="subsystem-icon"><i class="bi bi-file-earmark-lock2"></i></div>
                        <div>
                            <div class="subsystem-label">Protected Document Access</div>
                            <div>
                                @if($docAccessHealth === 'Healthy')
                                    <span class="subsystem-status-chip chip-healthy"><i class="bi bi-check-circle-fill"></i> Operational</span>
                                @else
                                    <span class="subsystem-status-chip chip-attention"><i class="bi bi-exclamation-triangle-fill"></i> Attention Required</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="subsystem-item">
                        <div class="subsystem-icon"><i class="bi bi-qr-code-scan"></i></div>
                        <div>
                            <div class="subsystem-label">QR Verification</div>
                            <div>
                                @if($qrHealth === 'Healthy')
                                    <span class="subsystem-status-chip chip-healthy"><i class="bi bi-check-circle-fill"></i> Operational</span>
                                @else
                                    <span class="subsystem-status-chip chip-attention"><i class="bi bi-exclamation-triangle-fill"></i> Attention Required</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="subsystem-item">
                        <div class="subsystem-icon"><i class="bi bi-shield-check"></i></div>
                        <div>
                            <div class="subsystem-label">OTP Verification</div>
                            <div>
                                @if($otpHealth === 'Healthy')
                                    <span class="subsystem-status-chip chip-healthy"><i class="bi bi-check-circle-fill"></i> Operational</span>
                                @else
                                    <span class="subsystem-status-chip chip-attention"><i class="bi bi-exclamation-triangle-fill"></i> Attention Required</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- 2. PRIMARY SECURITY KPI CARDS                      --}}
    {{-- ================================================== --}}
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-blue"><i class="bi bi-person-check-fill"></i></div>
                <div class="text-start">
                    <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.03em;">Today's Logins</span>
                    <h4 class="fw-bold mb-0 mt-1 text-dark" style="font-size: 1.4rem;">{{ $todayLogins }}</h4>
                    <span class="text-secondary" style="font-size: 0.75rem;">Successful authentication events today</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-red"><i class="bi bi-person-x-fill"></i></div>
                <div class="text-start">
                    <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.03em;">Failed Logins</span>
                    <h4 class="fw-bold mb-0 mt-1 text-dark" style="font-size: 1.4rem;">{{ $todayFailed }}</h4>
                    <span class="text-secondary" style="font-size: 0.75rem;">Authentication failures today</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-yellow"><i class="bi bi-lock-fill"></i></div>
                <div class="text-start">
                    <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.03em;">Locked Accounts</span>
                    <h4 class="fw-bold mb-0 mt-1 text-dark" style="font-size: 1.4rem;">{{ $lockedAccounts }}</h4>
                    <span class="text-secondary" style="font-size: 0.75rem;">Accounts currently locked</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-green"><i class="bi bi-people-fill"></i></div>
                <div class="text-start">
                    <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.03em;">Online Users</span>
                    <h4 class="fw-bold mb-0 mt-1 text-dark" style="font-size: 1.4rem;">{{ $onlineUsers }}</h4>
                    <span class="text-secondary" style="font-size: 0.75rem;">Currently active users</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- 3 & 4. LOGIN TRENDS & SECURITY ALERTS              --}}
    {{-- ================================================== --}}
    <div class="row g-4 mb-4">
        {{-- Login Trends --}}
        <div class="col-lg-8">
            <div class="dashboard-panel mb-0 h-100">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="bi bi-graph-up text-primary"></i>
                        <span>Login Trends (Last 7 Days)</span>
                    </h5>
                    <div class="d-flex align-items-center gap-3">
                        <span class="small d-flex align-items-center gap-1 text-muted" style="font-size: 0.78rem;">
                            <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: #10b981;"></span> Successful Logins
                        </span>
                        <span class="small d-flex align-items-center gap-1 text-muted" style="font-size: 0.78rem;">
                            <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: #ef4444;"></span> Failed Attempts
                        </span>
                    </div>
                </div>
                <div style="height: 270px; position: relative;">
                    <canvas id="loginTrendsChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Recent Security Alerts --}}
        <div class="col-lg-4">
            <div class="dashboard-panel mb-0 h-100 d-flex flex-column">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="bi bi-bell-fill text-danger"></i>
                        <span>Recent Security Alerts</span>
                    </h5>
                    @if($recentAlerts->isNotEmpty())
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $recentAlerts->count() }} Active</span>
                    @endif
                </div>

                <div class="flex-grow-1 overflow-y-auto" style="max-height: 270px;">
                    @forelse($recentAlerts as $alert)
                        <div class="alert-card-item">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="badge {{ $alert['severity'] === 'HIGH' ? 'bg-danger' : 'bg-warning text-dark' }} px-2 py-0" style="font-size: 0.7rem; font-weight: 700;">
                                    {{ $alert['severity'] }}
                                </span>
                                <span class="text-muted" style="font-size: 0.72rem;">{{ $alert['time'] }}</span>
                            </div>
                            <div class="fw-bold text-dark small">{{ $alert['event'] }}</div>
                            <div class="text-muted small mt-1" style="font-size: 0.75rem;">Account: {{ $alert['user'] }}</div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top border-danger-subtle">
                                <span class="text-secondary" style="font-size: 0.7rem;">Status</span>
                                <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">{{ $alert['status'] }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="empty-security-state my-auto">
                            <div class="empty-state-icon-circle bg-success-subtle text-success">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">✓ No active security alerts</h6>
                            <p class="text-muted small mb-0">No suspicious security events require attention at this time.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- 6, 7 & 8. PROTECTED DOCUMENT ACCESS, QR & OTP     --}}
    {{-- ================================================== --}}
    <div class="row g-4 mb-4">
        {{-- Protected Document Access Security --}}
        <div class="col-lg-6">
            <div class="dashboard-panel mb-0 h-100">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="bi bi-file-earmark-lock2-fill text-primary"></i>
                        <span>Protected Document Access</span>
                    </h5>
                    @if($docAccessHealth === 'Healthy')
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.75rem;">
                            <i class="bi bi-check-circle-fill me-1"></i>Access Controls Active
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.75rem;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Attention Required
                        </span>
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="metric-box">
                            <div class="metric-box-label">Confidential Documents</div>
                            <div class="metric-box-val">{{ $confidentialDocsCount }}</div>
                            <div class="metric-box-sub">Secured with PIN/OTP restrictions</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="metric-box">
                            <div class="metric-box-label">PINs Issued Today</div>
                            <div class="metric-box-val">{{ $pinsIssuedToday }}</div>
                            <div class="metric-box-sub">Confidential access passes generated</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="metric-box">
                            <div class="metric-box-label">Successful Verifications</div>
                            <div class="metric-box-val text-success">{{ $pinsVerifiedToday }} <span class="text-muted fw-normal" style="font-size: 0.85rem;">/ {{ $totalPinsVerified }}</span></div>
                            <div class="metric-box-sub">Today / Total authorized clearances</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="metric-box">
                            <div class="metric-box-label">Failed Access Attempts</div>
                            <div class="metric-box-val {{ $pinsFailedToday > 0 ? 'text-danger' : 'text-dark' }}">{{ $pinsFailedToday }} <span class="text-muted fw-normal" style="font-size: 0.85rem;">/ {{ $pinsFailedTotal }}</span></div>
                            <div class="metric-box-sub">Today / Total unauthorized attempts</div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 p-2 px-3 bg-light rounded-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 text-muted small" style="font-size: 0.75rem;">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Confidential files require recipient verification via QR scan and one-time PIN before decrypting content.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- QR & OTP Verification Security --}}
        <div class="col-lg-6">
            <div class="dashboard-panel mb-0 h-100">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="bi bi-qr-code-scan text-primary"></i>
                        <span>QR & OTP Verification Security</span>
                    </h5>
                    <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.72rem;">
                        <i class="bi bi-lock-fill me-1 text-primary"></i>Cryptographic Verification
                    </span>
                </div>

                {{-- QR Verification Metrics --}}
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold small text-dark"><i class="bi bi-qr-code me-1 text-muted"></i>QR Verification Security</span>
                        @if($qrHealth === 'Healthy')
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.7rem;">✓ Normal Operation</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem;">● Scans Failing</span>
                        @endif
                    </div>
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="metric-box py-2">
                                <div class="metric-box-label" style="font-size: 0.68rem;">Scans Today</div>
                                <div class="metric-box-val" style="font-size: 1.15rem;">{{ $qrScannedToday }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="metric-box py-2">
                                <div class="metric-box-label" style="font-size: 0.68rem;">Total Verified</div>
                                <div class="metric-box-val text-success" style="font-size: 1.15rem;">{{ $qrTotalVerified }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="metric-box py-2">
                                <div class="metric-box-label" style="font-size: 0.68rem;">Failed Scans</div>
                                <div class="metric-box-val {{ $qrTotalFailed > 0 ? 'text-danger' : 'text-dark' }}" style="font-size: 1.15rem;">{{ $qrTotalFailed }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- OTP Security Metrics --}}
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold small text-dark"><i class="bi bi-shield-check me-1 text-muted"></i>OTP Security Monitoring</span>
                        @if($otpHealth === 'Healthy')
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.7rem;">✓ Healthy</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem;">● Failures Detected</span>
                        @endif
                    </div>
                    <div class="row g-2">
                        <div class="col-3">
                            <div class="metric-box py-2">
                                <div class="metric-box-label" style="font-size: 0.68rem;">Requests</div>
                                <div class="metric-box-val" style="font-size: 1.15rem;">{{ $otpRequestsToday }}</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-box py-2">
                                <div class="metric-box-label" style="font-size: 0.68rem;">Verified</div>
                                <div class="metric-box-val text-success" style="font-size: 1.15rem;">{{ $otpSuccessToday }}</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-box py-2">
                                <div class="metric-box-label" style="font-size: 0.68rem;">Failed</div>
                                <div class="metric-box-val {{ $otpFailedToday > 0 ? 'text-danger' : 'text-dark' }}" style="font-size: 1.15rem;">{{ $otpFailedToday }}</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-box py-2">
                                <div class="metric-box-label" style="font-size: 0.68rem;">Expired</div>
                                <div class="metric-box-val" style="font-size: 1.15rem;">{{ $otpExpiredToday }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-muted small" style="font-size: 0.72rem;">
                    <i class="bi bi-shield-lock text-success me-1"></i>Plaintext OTP tokens and security keys are cryptographically hashed and never stored or displayed in the UI.
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- 9 & 10. RECENT SUCCESSFUL LOGINS & SUSPICIOUS ACTS --}}
    {{-- ================================================== --}}
    <div class="row g-4 mb-4">
        {{-- Recent Successful Logins (NO IP ADDRESSES) --}}
        <div class="col-lg-6 security-console-col d-flex flex-column">
            <div class="dashboard-panel security-console-card mb-0 h-100 d-flex flex-column">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="bi bi-person-check-fill text-success"></i>
                        <span>Recent Successful Logins</span>
                    </h5>
                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">Active Telemetry</span>
                </div>

                <div class="table-responsive security-table-container flex-grow-1">
                    <table class="table enterprise-sec-table table-logins align-middle">
                        <thead>
                            <tr>
                                <th class="col-user">User Account</th>
                                <th class="col-role">Role</th>
                                <th class="col-device">Device</th>
                                <th class="col-time">Timestamp</th>
                                <th class="col-status text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogins as $login)
                                <tr>
                                    <td class="col-user">
                                        <div class="fw-bold text-dark">{{ $login['user_name'] }}</div>
                                        <div class="text-muted small" style="font-size: 0.72rem;">{{ $login['email'] }}</div>
                                    </td>
                                    <td class="col-role"><span class="role-badge">{{ $login['role'] }}</span></td>
                                    <td class="col-device"><span class="text-secondary small">{{ $login['device'] }}</span></td>
                                    <td class="col-time"><span class="text-muted small">{{ $login['time'] }}</span></td>
                                    <td class="col-status text-end">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-check2"></i> Successful
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted text-center py-4">No recent successful logins recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Suspicious Activities & Account Lockouts (NO IP ADDRESSES) --}}
        <div class="col-lg-6 security-console-col d-flex flex-column">
            <div class="dashboard-panel security-console-card mb-0 h-100 d-flex flex-column">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="bi bi-shield-exclamation text-danger"></i>
                        <span>Suspicious Activities & Lockouts</span>
                    </h5>
                    @if($suspiciousActivities->isNotEmpty())
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.72rem;">
                            {{ $suspiciousActivities->count() }} Events Recorded
                        </span>
                    @endif
                </div>

                <div class="table-responsive security-table-container flex-grow-1">
                    <table class="table enterprise-sec-table table-suspicious align-middle">
                        <thead>
                            <tr>
                                <th class="col-event">Event</th>
                                <th class="col-user">Account / User</th>
                                <th class="col-time">Timestamp</th>
                                <th class="col-status text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($suspiciousActivities as $act)
                                <tr>
                                    <td class="col-event">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi {{ $act['icon'] }} flex-shrink-0"></i>
                                            <span class="fw-semibold text-dark">{{ $act['event'] }}</span>
                                        </div>
                                    </td>
                                    <td class="col-user"><span class="text-dark small">{{ $act['user'] }}</span></td>
                                    <td class="col-time"><span class="text-muted small">{{ $act['time'] }}</span></td>
                                    <td class="col-status text-end">
                                        <span class="badge {{ $act['badge_class'] }} px-2 py-1" style="font-size: 0.72rem;">
                                            {{ $act['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-security-state py-4 my-2">
                                            <div class="empty-state-icon-circle bg-success-subtle text-success">
                                                <i class="bi bi-shield-check"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">✓ No suspicious activities detected</h6>
                                            <p class="text-muted small mb-0">No unauthorized access attempts or account lockouts recorded.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- 5 & 12. SECURITY ACTIVITY (OPERATIONAL EVENT FEED) --}}
    {{-- ================================================== --}}
    <div class="dashboard-panel mb-4">
        <div class="panel-header">
            <div>
                <h5 class="panel-title">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span>Security Activity</span>
                </h5>
                <span class="text-muted small">Compact operational log of real-time authentication, access control, and credential events.</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('security.settings.activity.download') }}" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 shadow-sm px-3" style="font-size: 0.8rem; font-weight: 600;">
                    <i class="bi bi-shield-check"></i>
                    <span>View All Security Events</span>
                </a>
            </div>
        </div>

        <div class="security-activity-stream">
            @forelse($securityActivity as $event)
                <div class="security-activity-item">
                    <div class="activity-icon-badge badge-{{ $event['type'] }}">
                        <i class="bi {{ $event['icon'] }}"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span class="fw-bold text-dark small">{{ $event['event'] }}</span>
                            <span class="text-muted" style="font-size: 0.75rem;">{{ $event['time'] }}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-1">
                            <span class="text-secondary small" style="font-size: 0.8rem;">
                                Initiated by: <strong class="text-dark">{{ $event['user'] }}</strong>
                            </span>
                            @if($event['result'] === 'Successful' || $event['result'] === 'Completed' || $event['result'] === 'Verified')
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.7rem;">
                                    ✓ {{ $event['result'] }}
                                </span>
                            @elseif($event['result'] === 'Failed' || $event['result'] === 'Locked')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem;">
                                    ✕ {{ $event['result'] }}
                                </span>
                            @else
                                <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">
                                    {{ $event['result'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-security-state py-4">
                    <div class="empty-state-icon-circle bg-light text-muted">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">No recent security events recorded</h6>
                    <p class="text-muted small mb-0">System operational security events will be streamed here as they occur.</p>
                </div>
            @endforelse
        </div>
    </div>

</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const ctx = document.getElementById('loginTrendsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [
                    {
                        label: 'Successful Logins',
                        data: {!! json_encode($chartSuccess) !!},
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.08)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Failed Attempts',
                        data: {!! json_encode($chartFailed) !!},
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.08)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#64748b'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9'
                        },
                        ticks: {
                            stepSize: 1,
                            font: { size: 11 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
    });
</script>
@endsection
