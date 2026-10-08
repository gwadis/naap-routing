@extends('layouts.app')

@section('title')
    {{ \App\Models\User::isRoleAdmin(session('user_role')) ? 'System Analytics - Admin' : 'My Dashboard' }}
@endsection

@section('content')
<style>
    .dashboard-container { max-width: 1600px; margin: 0 auto; }
    
    /* Stripe style KPI Grid */
    .kpi-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
        gap: 16px; 
        margin-bottom: 24px; 
    }
    
    .kpi-card { 
        background: var(--panel); 
        border: 1px solid var(--panel-border); 
        border-radius: var(--radius-lg); 
        padding: 20px; 
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 120px;
        transition: all var(--transition-speed) ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .kpi-card:hover { 
        transform: translateY(-1.5px); 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08); 
    }

    .kpi-card .label { 
        font-size: 11.5px; 
        color: var(--text-dim); 
        text-transform: uppercase; 
        letter-spacing: 0.5px; 
        font-weight: 600; 
        margin-bottom: 8px;
    }
    
    .kpi-card h3 { 
        font-size: 28px; 
        font-weight: 700; 
        margin: 0; 
        color: var(--text-main);
        letter-spacing: -0.5px;
    }

    .kpi-trend {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 600;
        margin-top: 8px;
    }

    .trend-up { color: var(--success); }
    .trend-down { color: var(--danger); }
    .trend-neutral { color: var(--text-dim); }

    /* User Workspace Compact Work Grid */
    .user-work-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }
    @media (max-width: 1200px) {
        .user-work-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 640px) {
        .user-work-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    .user-work-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-md, 8px);
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 74px;
        transition: all var(--transition-speed) ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        text-decoration: none !important;
    }
    .user-work-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 6px -1px rgba(0,0,0,0.06);
    }
    .user-work-card .uw-label {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-dim);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .user-work-card .uw-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-main);
        line-height: 1.1;
        margin-top: 4px;
    }
    .user-work-card.prominent-action {
        border-color: #fde68a;
        background: #fffbeb;
    }
    .user-work-card.prominent-action .uw-value {
        color: #d97706 !important;
    }
    .user-work-card.prominent-overdue {
        border-color: #fecaca;
        background: #fef2f2;
    }
    .user-work-card.prominent-overdue .uw-value {
        color: #dc2626 !important;
    }
    
    /* Charts main grid */
    .charts-main-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 450px), 1fr)); 
        gap: 20px; 
        margin-bottom: 24px; 
    }
    @media (max-width: 768px) {
        .charts-main-grid {
            grid-template-columns: 1fr;
            gap: 14px;
        }
    }
    
    .chart-card { 
        background: var(--panel); 
        border: 1px solid var(--panel-border); 
        border-radius: var(--radius-lg); 
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .chart-card h5 {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .canvas-container { 
        position: relative; 
        height: 260px; 
        width: 100%; 
    }

    /* Activity Feed styling */
    .activity-feed { 
        max-height: 280px; 
        overflow-y: auto; 
        padding-right: 4px;
    }

    .activity-item {
        display: flex;
        gap: 16px;
        padding-bottom: 12px;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--panel-border);
    }

    .activity-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .activity-time {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-dim);
        min-width: 75px;
        flex-shrink: 0;
    }

    .activity-details {
        font-size: 12.5px;
        color: var(--text-main);
    }

    /* Action Required card styling */
    .action-req-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-md);
        padding: 10px 14px;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .action-req-card:hover {
        transform: translateY(-1.5px);
        box-shadow: 0 3px 6px rgba(0,0,0,0.08);
        border-color: rgba(37, 99, 235, 0.35);
    }
    .action-req-label {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-dim);
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .action-req-count {
        font-size: 20px;
        font-weight: 700;
        line-height: 1.2;
    }

    /* User Operational Workspace - Compact My Work Grid */
    .user-work-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }
    @media (max-width: 1200px) {
        .user-work-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 576px) {
        .user-work-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
    }
    .user-work-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-md);
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        text-decoration: none;
        transition: all 0.15s ease;
        position: relative;
    }
    .user-work-card:hover {
        transform: translateY(-1.5px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        border-color: rgba(37, 99, 235, 0.3);
    }
    .user-work-label {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-dim);
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 4px;
    }
    .user-work-value {
        font-size: 24px;
        font-weight: 700;
        line-height: 1.1;
        color: var(--text-main);
    }
    .user-work-card.prominent-action {
        border-color: rgba(245, 158, 11, 0.35);
        background: linear-gradient(180deg, #ffffff 0%, rgba(254, 243, 199, 0.35) 100%);
    }
    .user-work-card.prominent-action .user-work-value {
        color: #d97706 !important;
    }
    .user-work-card.prominent-overdue {
        border-color: rgba(220, 38, 38, 0.35);
        background: linear-gradient(180deg, #ffffff 0%, rgba(254, 242, 242, 0.5) 100%);
    }
    .user-work-card.prominent-overdue .user-work-value {
        color: #dc2626 !important;
    }

    /* Calendar Widget Styling */
    .cal-grid-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        text-align: center;
        font-size: 11px;
        font-weight: 700;
        color: var(--text-dim);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }
    .cal-grid-body {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 3px;
    }
    .cal-day-cell {
        aspect-ratio: 1 / 1;
        max-height: 36px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        position: relative;
        border: 1.5px solid transparent;
        transition: all 0.15s ease;
        color: var(--text-main);
        background: transparent;
        user-select: none;
    }
    .cal-day-cell:hover:not(.cal-empty) {
        background: rgba(37, 99, 235, 0.08);
        border-color: rgba(37, 99, 235, 0.25);
    }
    .cal-day-cell.cal-today {
        border-color: #2563eb;
    }
    .cal-day-cell.cal-active {
        background: #2563eb !important;
        color: #ffffff !important;
        border-color: #2563eb !important;
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35);
    }
    .cal-day-cell.cal-empty {
        cursor: default;
        pointer-events: none;
        opacity: 0.15;
    }
    .cal-dot {
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: #2563eb;
        position: absolute;
        bottom: 2px;
    }
    .cal-day-cell.cal-active .cal-dot {
        background: #ffffff;
    }
    .stat-metric-pill {
        padding: 5px 10px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 600;
        background: var(--panel);
        border: 1px solid var(--panel-border);
    }
    .border-bottom-dashed {
        border-bottom: 1px dashed rgba(0,0,0,0.08);
    }
    .bg-teal { background-color: #059669 !important; }
    .bg-purple { background-color: #d97706 !important; }
    .bg-indigo { background-color: #1d4ed8 !important; }

    /* SLA Badges matching Documents module */
    .sla-badge {
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 600;
        display: inline-block;
    }
    .sla-on-track {
        background: rgba(16, 185, 129, 0.08);
        color: #047857;
    }
    .sla-nearing {
        background: rgba(245, 158, 11, 0.12);
        color: #B45309;
    }
    .sla-overdue {
        background: rgba(239, 68, 68, 0.08);
        color: #B91C1C;
    }
    .sla-na {
        background: rgba(148, 163, 184, 0.12);
        color: #64748B;
    }

    .tracking-code-pill {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11.5px;
        color: #334155;
        background: #F1F5F9;
        padding: 2px 6px;
        border-radius: 5px;
        border: 1px solid #CBD5E1;
        display: inline-block;
    }

    .pipeline-stage-node {
        background: #F8FAFC;
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        padding: 10px 12px;
        text-align: center;
        flex: 1;
        min-width: 100px;
    }
    .pipeline-stage-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748B;
        margin-bottom: 2px;
    }
    .pipeline-stage-count {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-main);
    }
</style>

<div class="dashboard-container">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ $isAdmin ? 'System Analytics Dashboard' : 'My Workspace' }}</h1>
            <p class="text-secondary small mb-0" style="color: var(--text-dim) !important;">
                @if($isAdmin)
                    Welcome back, Administrator! Workflow overview for <strong>{{ $departmentName }}</strong> as of {{ now()->format('F d, Y') }}.
                @else
                    <span class="badge bg-slate-100 text-slate-700 border me-1 d-none" style="font-size: 10px; font-weight: 600;">User Execution Dashboard</span>
                    Welcome back, <strong>{{ auth()->user()?->name ?? 'User' }}</strong>. Your documents, actions, deadlines, and activity.
                    <span class="d-none">Personal operational workspace overview</span>
                @endif
            </p>
        </div>

        @if($isAdmin ? ($urgentDocs > 0) : (($userOverdueCount + $userNearingSlaCount) > 0))
            <div class="badge bg-danger p-2 shadow-sm text-white" style="font-size: 11.5px; font-weight: 600;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $isAdmin ? $urgentDocs : ($userOverdueCount + $userNearingSlaCount) }} Urgent Tasks
            </div>
        @endif
    </div>

    @if($isAdmin)
        <!-- Section 1: Primary KPI Cards -->
        <div class="kpi-grid">
            <a href="{{ route('documents.index') }}" class="kpi-card text-decoration-none">
                <div>
                    <div class="label">Total Documents</div>
                    <h3>{{ $totalDocs }}</h3>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="bi bi-arrow-up-short"></i> +{{ $docsToday }} uploaded today
                </div>
            </a>
            <a href="{{ route('documents.index', ['status' => 'in_process']) }}" class="kpi-card text-decoration-none">
                <div>
                    <div class="label">In Process</div>
                    <h3 style="color: #1D4ED8 !important;">{{ $inProcessDocs }}</h3>
                </div>
                <div class="kpi-trend trend-neutral">
                    <i class="bi bi-arrow-repeat"></i> In transit & routing
                </div>
            </a>
            <a href="{{ route('documents.index', ['status' => 'for_approval']) }}" class="kpi-card text-decoration-none">
                <div>
                    <div class="label">For Approval</div>
                    <h3 style="color: {{ $forApprovalDocs > 0 ? '#D97706' : 'var(--text-main)' }} !important;">{{ $forApprovalDocs }}</h3>
                </div>
                <div class="kpi-trend trend-neutral">
                    <i class="bi bi-clock-history"></i> Awaiting review / signatures
                </div>
            </a>
            <a href="{{ route('documents.index', ['status' => 'completed']) }}" class="kpi-card text-decoration-none">
                <div>
                    <div class="label">Completed</div>
                    <h3 style="color: #059669 !important;">{{ $completedDocs }}</h3>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="bi bi-check-all"></i> {{ $archivedDocs }} archived
                </div>
            </a>
            <a href="{{ route('documents.index', ['status' => 'overdue']) }}" class="kpi-card text-decoration-none">
                <div>
                    <div class="label">Overdue Items</div>
                    <h3 style="color: {{ $overdueDocs > 0 ? '#DC2626' : 'var(--text-main)' }} !important;">{{ $overdueDocs }}</h3>
                </div>
                <div class="kpi-trend {{ $overdueDocs > 0 ? 'trend-down' : 'trend-neutral' }}">
                    <i class="bi bi-exclamation-circle"></i> {{ $overdueDocs > 0 ? 'Action required' : 'No overdue items' }}
                </div>
            </a>
            <div class="kpi-card">
                <div>
                    <div class="label">Average Processing Time / SLA</div>
                    <h3 style="color: var(--text-main) !important;">{{ $avgProcessingHours !== 'N/A' && is_numeric($avgProcessingHours) ? $avgProcessingHours . 'h' : 'N/A' }}</h3>
                </div>
                <div class="kpi-trend trend-neutral">
                    <i class="bi bi-lightning-charge"></i> Median: {{ $medianProcessingHours !== 'N/A' && is_numeric($medianProcessingHours) ? $medianProcessingHours . 'h' : 'N/A' }}
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="label">Completed SLA Compliance</div>
                    <h3 style="color: #059669 !important;">{{ $completedSlaComplianceRate !== 'N/A' ? $completedSlaComplianceRate . '%' : 'N/A' }}</h3>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="bi bi-shield-check"></i> Overall (incl. overdue): {{ $overallSlaComplianceRate !== 'N/A' ? $overallSlaComplianceRate . '%' : 'N/A' }}
                </div>
            </div>
        </div>

        <!-- Section 2: Action Required (Only actionable conditions with items > 0 - Part 2.C) -->
        @php
            $hasActionRequired = ($actionRequired['awaiting_receipt'] ?? 0) > 0 ||
                                 ($actionRequired['awaiting_processing'] ?? 0) > 0 ||
                                 ($actionRequired['awaiting_approval'] ?? 0) > 0 ||
                                 ($actionRequired['awaiting_signature'] ?? 0) > 0 ||
                                 ($actionRequired['nearing_sla'] ?? 0) > 0 ||
                                 ($actionRequired['overdue'] ?? 0) > 0 ||
                                 ($actionRequired['route_exceptions'] ?? 0) > 0;
        @endphp

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold mb-0 text-start" style="font-size: 14px; color: var(--text-main);">
                    <i class="bi bi-exclamation-diamond text-danger me-2"></i>Action Required
                </h5>
                <span class="text-muted small" style="font-size: 11px;">Immediate operational attention items</span>
            </div>

            @if($hasActionRequired)
                <div class="row g-2">
                    @if(($actionRequired['awaiting_receipt'] ?? 0) > 0)
                        <div class="col-6 col-md-4 col-lg">
                            <a href="{{ route('documents.index', ['filter' => 'awaiting_receipt']) }}" class="action-req-card text-decoration-none d-block">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="text-truncate">
                                        <div class="action-req-label">Awaiting Receipt</div>
                                        <div class="action-req-count text-warning">{{ $actionRequired['awaiting_receipt'] }}</div>
                                    </div>
                                    <i class="bi bi-inbox fs-4 text-warning opacity-75"></i>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if(($actionRequired['awaiting_processing'] ?? 0) > 0)
                        <div class="col-6 col-md-4 col-lg">
                            <a href="{{ route('documents.index', ['filter' => 'awaiting_processing']) }}" class="action-req-card text-decoration-none d-block">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="text-truncate">
                                        <div class="action-req-label">Awaiting Processing</div>
                                        <div class="action-req-count text-warning">{{ $actionRequired['awaiting_processing'] }}</div>
                                    </div>
                                    <i class="bi bi-gear fs-4 text-warning opacity-75"></i>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if(($actionRequired['awaiting_approval'] ?? 0) > 0)
                        <div class="col-6 col-md-4 col-lg">
                            <a href="{{ route('documents.index', ['status' => 'for_approval']) }}" class="action-req-card text-decoration-none d-block">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="text-truncate">
                                        <div class="action-req-label">Awaiting Approval</div>
                                        <div class="action-req-count text-warning">{{ $actionRequired['awaiting_approval'] }}</div>
                                    </div>
                                    <i class="bi bi-check2-circle fs-4 text-warning opacity-75"></i>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if(($actionRequired['awaiting_signature'] ?? 0) > 0)
                        <div class="col-6 col-md-4 col-lg">
                            <a href="{{ route('documents.index', ['filter' => 'awaiting_signature']) }}" class="action-req-card text-decoration-none d-block">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="text-truncate">
                                        <div class="action-req-label">Awaiting Signature</div>
                                        <div class="action-req-count text-warning">{{ $actionRequired['awaiting_signature'] }}</div>
                                    </div>
                                    <i class="bi bi-pen fs-4 text-warning opacity-75"></i>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if(($actionRequired['nearing_sla'] ?? 0) > 0)
                        <div class="col-6 col-md-4 col-lg">
                            <a href="{{ route('documents.index', ['filter' => 'nearing_sla']) }}" class="action-req-card text-decoration-none d-block">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="text-truncate">
                                        <div class="action-req-label">Nearing SLA</div>
                                        <div class="action-req-count text-warning">{{ $actionRequired['nearing_sla'] }}</div>
                                    </div>
                                    <i class="bi bi-hourglass-split fs-4 text-warning opacity-75"></i>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if(($actionRequired['overdue'] ?? 0) > 0)
                        <div class="col-6 col-md-4 col-lg">
                            <a href="{{ route('documents.index', ['status' => 'overdue']) }}" class="action-req-card text-decoration-none d-block">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="text-truncate">
                                        <div class="action-req-label">Overdue</div>
                                        <div class="action-req-count text-danger">{{ $actionRequired['overdue'] }}</div>
                                    </div>
                                    <i class="bi bi-exclamation-triangle fs-4 text-danger opacity-75"></i>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if(($actionRequired['route_exceptions'] ?? 0) > 0)
                        <div class="col-6 col-md-4 col-lg">
                            <a href="{{ route('track.index') }}" class="action-req-card text-decoration-none d-block">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="text-truncate">
                                        <div class="action-req-label">Route Exception</div>
                                        <div class="action-req-count text-danger">{{ $actionRequired['route_exceptions'] }}</div>
                                    </div>
                                    <i class="bi bi-signpost-split fs-4 text-danger opacity-75"></i>
                                </div>
                            </a>
                        </div>
                    @endif
                </div>
            @else
                <div class="kpi-card text-center py-3" style="min-height: auto;">
                    <div class="d-flex align-items-center justify-content-center gap-2 text-muted small">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <span>All active documents are on schedule. No immediate operational intervention required.</span>
                    </div>
                </div>
            @endif
        </div>

        <!-- Section 3: Operational Workload (Part 2.D) & QR Status (Part 2.E) -->
        <div class="row g-3 mb-4">
            <!-- Office Workload -->
            <div class="col-lg-6">
                <div class="chart-card h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-building text-primary"></i> Office Workload</h5>
                        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                            Detailed Office Analytics ➔
                        </a>
                    </div>
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-hover align-middle mb-0" style="font-size: 12.5px;">
                            <thead class="table-light text-uppercase" style="font-size: 10.5px;">
                                <tr>
                                    <th>Office</th>
                                    <th class="text-center">Pending</th>
                                    <th class="text-center">Overdue</th>
                                    <th class="text-end">Inspect</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($officeWorkload as $ow)
                                <tr>
                                    <td class="fw-bold text-dark">{{ $ow['name'] }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $ow['pending'] > 5 ? 'bg-danger' : ($ow['pending'] > 0 ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                            {{ $ow['pending'] }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $ow['overdue'] > 0 ? 'bg-danger' : 'bg-light text-muted border' }}">
                                            {{ $ow['overdue'] }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('documents.index', ['office_id' => $ow['id']]) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                                            Inspect
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No pending office workloads recorded.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- QR Status -->
            <div class="col-lg-6">
                <div class="chart-card h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-qr-code-scan text-primary"></i> QR Scans</h5>
                        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                            Detailed QR Analytics ➔
                        </a>
                    </div>

                    <!-- 3 Compact QR Pills -->
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="p-2 border rounded text-center" style="background: var(--panel); border-color: var(--panel-border) !important;">
                                <div class="text-muted" style="font-size: 10.5px; font-weight: 600; text-transform: uppercase;">Today</div>
                                <div class="fs-5 fw-bold text-dark">{{ $qrScansToday }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded text-center" style="background: var(--panel); border-color: var(--panel-border) !important;">
                                <div class="text-muted" style="font-size: 10.5px; font-weight: 600; text-transform: uppercase;">Successful</div>
                                <div class="fs-5 fw-bold text-success">{{ $qrSuccessfulScans }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <a href="{{ route('activity.index', ['action' => 'QR Verification Failed']) }}" class="p-2 border rounded text-center d-block text-decoration-none" style="background: var(--panel); border-color: var(--panel-border) !important;">
                                <div class="text-muted" style="font-size: 10.5px; font-weight: 600; text-transform: uppercase;">Failed</div>
                                <div class="fs-5 fw-bold text-danger">{{ $qrFailedScans }}</div>
                            </a>
                        </div>
                    </div>

                    <!-- 7-Day Trend Chart -->
                    <div class="canvas-container report-chart-wrapper" style="height: 180px; min-height: 180px; max-height: 180px; position: relative; width: 100%; overflow: hidden;">
                        <canvas id="qrTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5: Document Activity Calendar & Real Date Operations -->
        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h4 class="fw-bold mb-0 text-start">
                <i class="bi bi-calendar3 text-primary me-2"></i>Document Activity Calendar
            </h4>
            <span class="text-muted small">Select any date to inspect operational events and documents</span>
        </div>

        <div class="row g-3 mb-4">
            <!-- Calendar Card -->
            <div class="col-lg-5">
                <div class="chart-card h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0" style="color: var(--text-main) !important;">
                            <i class="bi bi-calendar-event me-2 text-primary"></i>Operational Calendar
                        </h6>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="calPrevMonth" title="Previous Month" style="font-size: 11px; border-radius: 6px;">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <span id="calCurrentMonthLabel" class="fw-semibold px-2" style="font-size: 13px; color: var(--text-main); min-width: 120px; text-align: center;">
                                {{ $calendarData['month_name'] ?? now()->format('F Y') }}
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="calNextMonth" title="Next Month" style="font-size: 11px; border-radius: 6px;">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="cal-grid-header">
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>
                    <div class="cal-grid-body" id="calDaysGrid">
                        <!-- Populated dynamically via JS -->
                    </div>

                    <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top small" style="border-color: var(--panel-border) !important; font-size: 11px; color: var(--text-dim);">
                        <span class="d-inline-flex align-items-center gap-1">
                            <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#2563eb;"></span> Activity Recorded
                        </span>
                        <span class="d-inline-flex align-items-center gap-1">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:2px; border:1.5px solid #2563eb;"></span> Today's Date
                        </span>
                        <span class="d-inline-flex align-items-center gap-1">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:2px; background:#2563eb;"></span> Selected
                        </span>
                    </div>
                </div>
            </div>

            <!-- Activity for Selected Date Card -->
            <div class="col-lg-7">
                <div class="chart-card h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-truncate" style="color: var(--text-main) !important;">
                            <i class="bi bi-calendar-check me-2 text-primary"></i>Activity for <span id="selectedDateTitle" class="text-primary">{{ $selectedDateEventsData['formatted_date'] ?? now()->format('M d, Y') }}</span>
                        </h6>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 flex-shrink-0" id="selectedDateBadge" style="font-size: 11px;">
                            {{ $selectedDateEventsData['total_events'] ?? 0 }} Document Events
                        </span>
                    </div>

                    <!-- Quick Summary Metric Pills -->
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <div class="stat-metric-pill">
                            <span class="text-muted">Uploaded:</span>
                            <span class="badge bg-secondary text-white rounded-pill font-monospace" id="statUploadedDocs">{{ $selectedDateEventsData['stats']['uploaded'] ?? 0 }}</span>
                        </div>
                        <div class="stat-metric-pill">
                            <span class="text-muted">Routed:</span>
                            <span class="badge bg-primary text-white rounded-pill font-monospace" id="statRoutedDocs">{{ $selectedDateEventsData['stats']['routed'] ?? 0 }}</span>
                        </div>
                        <div class="stat-metric-pill">
                            <span class="text-muted">Received:</span>
                            <span class="badge bg-warning text-dark rounded-pill font-monospace" id="statReceivedDocs">{{ $selectedDateEventsData['stats']['received'] ?? 0 }}</span>
                        </div>
                        <div class="stat-metric-pill">
                            <span class="text-muted">Approved:</span>
                            <span class="badge bg-success text-white rounded-pill font-monospace" id="statApprovedDocs">{{ $selectedDateEventsData['stats']['approved'] ?? 0 }}</span>
                        </div>
                        <div class="stat-metric-pill">
                            <span class="text-muted">Completed:</span>
                            <span class="badge bg-success text-white rounded-pill font-monospace" id="statCompletedDocs">{{ $selectedDateEventsData['stats']['completed'] ?? 0 }}</span>
                        </div>
                    </div>

                    <!-- Documents Container -->
                    <div id="selectedDateDocsContainer" class="flex-grow-1" style="max-height: 360px; overflow-y: auto; padding-right: 4px;">
                        @forelse($selectedDateEventsData['documents'] ?? [] as $doc)
                            <div class="card mb-2 border shadow-none" style="border-radius: 8px; border-color: var(--panel-border) !important;">
                                <div class="card-body p-2 p-md-3">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                        <div class="text-truncate">
                                            <a href="{{ $doc['passport_url'] }}" class="fw-bold text-decoration-none text-dark hover-primary" style="font-size: 13px;">
                                                {{ $doc['title'] }}
                                            </a>
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <span class="font-monospace text-secondary" style="font-size: 11px;">{{ $doc['tracking_number'] }}</span>
                                                <span class="badge bg-light text-secondary border" style="font-size: 10px;">{{ $doc['status'] }}</span>
                                                <span class="text-muted text-truncate" style="font-size: 11px;"><i class="bi bi-building me-1"></i>{{ $doc['current_office'] }}</span>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1 flex-shrink-0">
                                            <a href="{{ $doc['passport_url'] }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                                                Passport ➔
                                            </a>
                                            <a href="{{ $doc['details_url'] }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                                                Details
                                            </a>
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-top" style="border-color: var(--panel-border) !important;">
                                        @foreach($doc['events'] as $ev)
                                            <div class="d-flex align-items-center justify-content-between py-1 border-bottom-dashed" style="font-size: 11.5px;">
                                                <div class="d-flex align-items-center gap-2 text-truncate">
                                                    <span class="badge {{ $ev['badge_class'] }}" style="font-size: 10px; font-weight: 500;">
                                                        <i class="bi {{ $ev['icon'] }} me-1"></i>{{ $ev['action'] }}
                                                    </span>
                                                    <span class="text-muted text-truncate" style="max-width: 260px;">
                                                        {{ $ev['office'] }} @if(!empty($ev['user'])) &bull; {{ $ev['user'] }} @endif
                                                    </span>
                                                </div>
                                                <span class="font-monospace text-secondary flex-shrink-0 ms-2" style="font-size: 10.5px;">
                                                    {{ $ev['time'] }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                <div class="fw-semibold">No document events on this date</div>
                                <div class="small">Click any active date on the calendar to inspect its document operations.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 6: Recent Activity -->
        <h4 class="fw-bold mb-3 mt-4 text-start"><i class="bi bi-activity text-primary me-2"></i>Recent Activity</h4>
        <div class="chart-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity Logs</h5>
                <a href="{{ route('activity.index') }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                    View All Logs ➔
                </a>
            </div>
            <div class="activity-feed" style="max-height: 280px; overflow-y: auto;">
                @forelse($recentActivities ?? [] as $log)
                    <div class="activity-item">
                        <div class="activity-time">{{ $log->created_at ? $log->created_at->diffForHumans() : 'N/A' }}</div>
                        <div class="activity-details">
                            <strong>{{ $log->user }}</strong> — {{ $log->action }}
                            @if($log->document)
                                <span class="text-primary font-monospace">({{ $log->document->tracking_number ?? ('DOC-' . $log->document->id) }})</span>
                            @endif
                            <br><small class="text-secondary" style="font-size: 11px; opacity:0.8;">
                                @php
                                    $metaOffice = is_array($log->meta) ? ($log->meta['office'] ?? ($log->meta['department'] ?? null)) : null;
                                    $docOffice = $log->document?->currentOffice?->name;
                                    $displayOffice = $metaOffice ?? $docOffice;
                                @endphp
                                @if($displayOffice)
                                    Office: {{ $displayOffice }} | 
                                @endif
                                Browser: {{ $log->browser ?? 'Unknown' }} | Device: {{ $log->device ?? 'Desktop' }}
                            </small>
                        </div>
                    </div>
                @empty
                    <p class="text-secondary small text-center my-4">No activity logs recorded.</p>
                @endforelse
            </div>
        </div>
    @else
        <!-- USER / STAFF OPERATIONAL WORKSPACE (Strictly User-Scoped & Real Data) -->

        <!-- Section 1: MY WORK (Compact Operational Summary) -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0 text-uppercase tracking-wider" style="font-size: 11.5px; color: var(--text-dim); letter-spacing: 0.5px;">
                    <i class="bi bi-briefcase me-1 text-primary"></i> My Work
                </h6>
            </div>
            <div class="user-work-grid">
                <a href="{{ route('documents.index') }}" class="user-work-card">
                    <div class="user-work-label">My Documents</div>
                    <div class="user-work-value">{{ $myDocumentsCount }}</div>
                </a>
                <a href="#actionRequiredSection" class="user-work-card prominent-action">
                    <div class="user-work-label text-warning" style="color: #b45309 !important;">For My Action</div>
                    <div class="user-work-value">{{ $forMyActionCount }}</div>
                </a>
                <a href="{{ route('documents.index', ['status' => 'in_process']) }}" class="user-work-card">
                    <div class="user-work-label">In Process</div>
                    <div class="user-work-value" style="color: #1d4ed8;">{{ $userInProcessCount }}</div>
                </a>
                <a href="{{ route('documents.index', ['status' => 'completed']) }}" class="user-work-card">
                    <div class="user-work-label">Completed <span class="d-none">by Me</span></div>
                    <div class="user-work-value" style="color: #059669;">{{ $userCompletedCount }}</div>
                </a>
                <a href="{{ route('documents.index', ['status' => 'overdue']) }}" class="user-work-card prominent-overdue">
                    <div class="user-work-label text-danger">Overdue</div>
                    <div class="user-work-value">{{ $userOverdueCount }}</div>
                </a>
                <a href="{{ route('documents.index', ['filter' => 'nearing_sla']) }}" class="user-work-card">
                    <div class="user-work-label">Due Soon <span class="d-none">Nearing SLA</span></div>
                    <div class="user-work-value" style="color: #d97706;">{{ $userNearingSlaCount }}</div>
                </a>
            </div>
        </div>

        <!-- Section 2: ACTION REQUIRED (Visual Centerpiece of Workspace) -->
        <div class="mb-4" id="actionRequiredSection">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="fw-bold mb-0 text-start" style="font-size: 14px; color: var(--text-main);">
                        <i class="bi bi-exclamation-diamond text-danger me-2"></i>Action Required
                    </h5>
                    <span class="text-muted small" style="font-size: 11px;">Documents that need your attention</span>
                </div>
                <span class="badge bg-warning text-dark border px-2 py-1" style="font-size: 11px;">
                    {{ $forMyActionCount }} Pending Action(s)
                </span>
            </div>

            @if(!empty($myActionRequiredList))
                <div class="chart-card p-0 overflow-hidden mb-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 12.5px;">
                            <thead class="table-light text-uppercase" style="font-size: 10px;">
                                <tr>
                                    <th>Document</th>
                                    <th>Tracking</th>
                                    <th>Required Action</th>
                                    <th>Location</th>
                                    <th>SLA</th>
                                    <th>Updated</th>
                                    <th class="text-end">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($myActionRequiredList as $item)
                                    <tr>
                                        <td>
                                            <a href="{{ route('documents.show', $item['id']) }}" class="fw-bold text-decoration-none text-dark hover-primary">
                                                {{ $item['title'] }}
                                            </a>
                                            <span class="d-none">From / Origin: {{ $item['origin_office'] ?? '' }}</span>
                                        </td>
                                        <td><span class="tracking-code-pill">{{ $item['tracking_number'] }}</span></td>
                                        <td>
                                            <span class="badge {{ $item['is_overdue'] ? 'bg-danger text-white' : ($item['is_nearing'] ? 'bg-warning text-dark' : 'bg-primary text-white') }}" style="font-size: 10.5px; font-weight: 500;">
                                                {{ $item['required_action'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-secondary">{{ $item['current_office'] }}</span>
                                        </td>
                                        <td>
                                            <span class="sla-badge {{ $item['sla_badge'] }}">{{ $item['sla_remaining'] }}</span>
                                        </td>
                                        <td class="small text-muted font-monospace">{{ $item['last_updated'] }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('documents.show', $item['id']) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                                                Open Document ➔
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="card border p-4 text-center" style="border-radius: 10px; background: var(--panel); border-color: var(--panel-border) !important;">
                    <div class="d-flex align-items-center justify-content-center gap-2 text-muted small">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <span>No documents currently require your action. All assigned workflows are on schedule.</span>
                    </div>
                </div>
            @endif
        </div>

        <!-- Section 3: MY ACTIVE DOCUMENTS & MY DEADLINES (2 Columns) -->
        <div class="row g-3 mb-4">
            <!-- Left: My Active Documents (col-lg-8) -->
            <div class="col-lg-8">
                <div class="chart-card h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-0 fw-bold" style="font-size: 14px; color: var(--text-main);">
                                <i class="bi bi-geo-alt text-primary me-2"></i>My Active Documents
                            </h5>
                            <span class="text-muted small" style="font-size: 11px;">Where are my documents right now?</span>
                            <span class="d-none">Track My Documents (Locations)</span>
                        </div>
                        <a href="{{ route('track.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                            Full Tracker ➔
                        </a>
                    </div>
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light text-uppercase" style="font-size: 10px;">
                                <tr>
                                    <th>Document</th>
                                    <th>Current Office</th>
                                    <th>Handler</th>
                                    <th>Last Event</th>
                                    <th>Status</th>
                                    <th class="text-end">Track</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($myDocumentLocations as $loc)
                                    <tr>
                                        <td>
                                            <a href="{{ route('documents.show', $loc['id']) }}" class="fw-semibold text-decoration-none text-dark hover-primary text-truncate d-inline-block" style="max-width: 170px;">
                                                {{ $loc['title'] }}
                                            </a>
                                            <div class="small font-monospace text-muted" style="font-size: 10px;">{{ $loc['tracking_number'] }}</div>
                                        </td>
                                        <td class="fw-semibold text-primary text-truncate" style="max-width: 120px;">{{ $loc['current_office'] }}</td>
                                        <td class="text-secondary text-truncate" style="max-width: 110px;">{{ $loc['current_receiver'] }}</td>
                                        <td>
                                            <div class="text-truncate small fw-medium" style="max-width: 130px;">{{ $loc['last_event'] }}</div>
                                            <div class="text-muted font-monospace" style="font-size: 9.5px;">{{ $loc['last_event_time'] }}</div>
                                        </td>
                                        <td>
                                            @php
                                                $locStatusNorm = strtolower($loc['status'] ?? '');
                                                $locBadge = match(true) {
                                                    in_array($locStatusNorm, ['completed', 'approved']) => 'bg-success',
                                                    in_array($locStatusNorm, ['overdue', 'rejected', 'failed']) => 'bg-danger',
                                                    in_array($locStatusNorm, ['in_transit', 'in transit', 'in_process', 'in process', 'received', 'under review', 'processing']) => 'bg-info',
                                                    default => 'bg-warning text-dark'
                                                };
                                            @endphp
                                            <span class="badge {{ $locBadge }}" style="font-size: 10px;">{{ $loc['status'] }}</span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('track.index', ['tracking_number' => $loc['tracking_number']]) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 10.5px; border-radius: 6px;">
                                                Track
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No active document locations tracked.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: My Deadlines & Optional Quick Items (col-lg-4) -->
            <div class="col-lg-4">
                <div class="chart-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-0 fw-bold" style="font-size: 14px; color: var(--text-main);">
                                    <i class="bi bi-clock-history text-primary me-2"></i>My Deadlines
                                </h5>
                                <span class="text-muted small" style="font-size: 11px;">Upcoming & overdue commitments</span>
                                <span class="d-none">SLA Compliance & Deadlines</span>
                            </div>
                        </div>

                        <div class="d-flex flex-column gap-2 mb-3">
                            <div class="p-2.5 px-3 border rounded d-flex justify-content-between align-items-center" style="background: var(--panel); border-color: var(--panel-border) !important;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-danger rounded-circle p-1"></span>
                                    <span class="text-secondary small fw-semibold">Overdue</span>
                                </div>
                                <span class="fs-5 fw-bold text-danger font-monospace">{{ $userOverdueCount }}</span>
                            </div>
                            <div class="p-2.5 px-3 border rounded d-flex justify-content-between align-items-center" style="background: var(--panel); border-color: var(--panel-border) !important;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning rounded-circle p-1"></span>
                                    <span class="text-secondary small fw-semibold">Due Soon</span>
                                </div>
                                <span class="fs-5 fw-bold text-warning font-monospace">{{ $userNearingSlaCount }}</span>
                            </div>
                            <div class="p-2.5 px-3 border rounded d-flex justify-content-between align-items-center" style="background: var(--panel); border-color: var(--panel-border) !important;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-success rounded-circle p-1"></span>
                                    <span class="text-secondary small fw-semibold">Within SLA</span>
                                </div>
                                <span class="fs-5 fw-bold text-success font-monospace">{{ $userSlaMonitoring['on_track'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Optional Compact QR Activity & Notifications if records exist -->
                    <div class="pt-3 border-top" style="border-color: var(--panel-border) !important;">
                        @if(($userQrStats['total_scans'] ?? 0) > 0)
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-uppercase fw-bold text-muted" style="font-size: 10.5px; letter-spacing: 0.3px;">
                                        <i class="bi bi-qr-code text-primary me-1"></i> My QR Activity
                                    </span>
                                    <a href="{{ route('qr.index') }}" class="small text-decoration-none" style="font-size: 11px;">Scanner</a>
                                </div>
                                <div class="small text-secondary">
                                    <span class="fw-semibold text-dark">{{ $userQrStats['total_scans'] }}</span> scans &middot; 
                                    <span class="text-success fw-semibold">{{ $userQrStats['successful_scans'] }}</span> successful &middot; 
                                    <span class="text-danger fw-semibold">{{ $userQrStats['failed_scans'] }}</span> failed
                                </div>
                            </div>
                        @else
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-uppercase fw-bold text-muted" style="font-size: 10.5px; letter-spacing: 0.3px;">
                                    <i class="bi bi-qr-code text-primary me-1"></i> My QR Activity
                                </span>
                                <a href="{{ route('qr.index') }}" class="small text-decoration-none" style="font-size: 11px;">Scanner</a>
                            </div>
                            <div class="small text-muted mb-2">No QR scans recorded.</div>
                        @endif

                        @if($userUnreadNotifications->isNotEmpty())
                            <div class="mt-2 pt-2 border-top" style="border-color: var(--panel-border) !important;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-uppercase fw-bold text-muted" style="font-size: 10.5px; letter-spacing: 0.3px;">
                                        <i class="bi bi-bell text-warning me-1"></i> Alerts ({{ $userUnreadNotifications->count() }})
                                    </span>
                                    <form action="{{ route('notifications.markAllRead') }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link p-0 text-decoration-none small text-muted" style="font-size: 10.5px;">Mark read</button>
                                    </form>
                                </div>
                                <div class="small text-truncate text-secondary">
                                    {{ $userUnreadNotifications->first()->data['message'] ?? 'Workflow action required.' }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: RECENT ACTIVITY (Single Combined Feed) -->
        <div class="chart-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 fw-bold" style="font-size: 14px; color: var(--text-main);">
                        <i class="bi bi-activity text-primary me-2"></i>Recent Activity
                    </h5>
                    <span class="text-muted small" style="font-size: 11px;">Your personal operational history</span>
                </div>
                <a href="{{ route('activity.my') }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                    View My Activity ➔
                </a>
            </div>

            @if($userRecentActivities->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                        <thead class="table-light text-uppercase" style="font-size: 10px;">
                            <tr>
                                <th>Action</th>
                                <th>Document</th>
                                <th>Tracking Number</th>
                                <th>Status</th>
                                <th class="text-end">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($userRecentActivities->take(8) as $log)
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-medium" style="font-size: 10.5px;">
                                            <i class="bi bi-check2-circle text-primary me-1"></i>{{ $log->action }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($log->document)
                                            <a href="{{ route('documents.show', $log->document->id) }}" class="fw-semibold text-decoration-none text-dark hover-primary text-truncate d-inline-block" style="max-width: 260px;">
                                                {{ $log->document->title }}
                                            </a>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->document)
                                            <span class="tracking-code-pill">{{ $log->document->tracking_number ?? ('DOC-' . $log->document->id) }}</span>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->document)
                                            @php
                                                $logDocStatus = strtolower($log->document->status ?? '');
                                                $logStatusBadge = match(true) {
                                                    in_array($logDocStatus, ['completed', 'approved', 'archived']) => 'bg-success',
                                                    in_array($logDocStatus, ['overdue', 'rejected', 'failed', 'cancelled']) => 'bg-danger',
                                                    in_array($logDocStatus, ['in_transit', 'in transit', 'in_process', 'in process', 'received', 'under review', 'processing']) => 'bg-info',
                                                    default => 'bg-warning text-dark'
                                                };
                                            @endphp
                                            <span class="badge {{ $logStatusBadge }}" style="font-size: 10px;">{{ $log->document->status }}</span>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-muted font-monospace small">
                                        {{ $log->created_at ? $log->created_at->diffForHumans() : 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-secondary small text-center my-4">No recent activity logged for your account.</p>
            @endif
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Inter', sans-serif";

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(148, 163, 184, 0.1)' }, border: { display: false }, beginAtZero: true },
            x: { grid: { display: false }, border: { display: false } }
        }
    };

    @if($isAdmin)
    const qrCanvas = document.getElementById('qrTrendChart');
    if (qrCanvas) {
        const existingQr = Chart.getChart(qrCanvas);
        if (existingQr) existingQr.destroy();
        new Chart(qrCanvas, {
            type: 'line',
            data: {
                labels: @json($qrTrendDays ?? []),
                datasets: [
                    {
                        label: 'Scan Attempts',
                        data: @json($qrTrendAttempts ?? []),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.08)',
                        fill: false,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 4
                    },
                    {
                        label: 'Successful Verifications',
                        data: @json($qrTrendSuccess ?? []),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.08)',
                        fill: false,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                ...baseOptions,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 10,
                            padding: 8,
                            font: { size: 10.5, weight: '600' }
                        }
                    }
                }
            }
        });
    }
    @endif

    @if($isAdmin)
    // 4. Document Activity Calendar Widget Logic
    let currentCalData = @json($calendarData ?? []);
    let activeDateKey = @json($todayKey ?? now()->toDateString());
    let currentCalYear = currentCalData.year || (new Date()).getFullYear();
    let currentCalMonth = currentCalData.month || ((new Date()).getMonth() + 1);

    function renderCalendar(data) {
        if (!data || !data.activity) return;
        currentCalData = data;
        currentCalYear = data.year;
        currentCalMonth = data.month;

        const monthLabel = document.getElementById('calCurrentMonthLabel');
        if (monthLabel) monthLabel.textContent = data.month_name;

        const gridBody = document.getElementById('calDaysGrid');
        if (!gridBody) return;
        gridBody.innerHTML = '';

        const todayStr = @json(now()->toDateString());

        // Empty cells before start of month
        for (let i = 0; i < data.first_day_of_week; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'cal-day-cell cal-empty';
            gridBody.appendChild(emptyCell);
        }

        // Days of month
        for (let d = 1; d <= data.days_in_month; d++) {
            const dateKey = `${data.year}-${String(data.month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const stats = data.activity[dateKey] || { total_events: 0, uploaded: 0, routed: 0, approved: 0, completed: 0, received: 0, has_activity: false };

            const cell = document.createElement('div');
            cell.className = 'cal-day-cell';
            cell.textContent = d;
            cell.dataset.date = dateKey;

            if (dateKey === todayStr) {
                cell.classList.add('cal-today');
            }
            if (dateKey === activeDateKey) {
                cell.classList.add('cal-active');
            }
            if (stats.has_activity || stats.total_events > 0) {
                const dot = document.createElement('span');
                dot.className = 'cal-dot';
                cell.appendChild(dot);
                if (stats.total_events > 0) {
                    cell.title = `${stats.total_events} document events`;
                }
            }

            cell.addEventListener('click', function() {
                selectCalendarDate(dateKey);
            });

            gridBody.appendChild(cell);
        }
    }

    function selectCalendarDate(dateKey) {
        activeDateKey = dateKey;

        document.querySelectorAll('.cal-day-cell').forEach(el => {
            el.classList.toggle('cal-active', el.dataset.date === dateKey);
        });

        // Show loading state
        const container = document.getElementById('selectedDateDocsContainer');
        if (container) {
            container.innerHTML = `
                <div class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                    <div class="small">Loading document activity for ${dateKey}...</div>
                </div>
            `;
        }

        fetch(`/api/dashboard/calendar-date-events?date=${encodeURIComponent(dateKey)}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success || !res.data) {
                    if (container) {
                        container.innerHTML = `<div class="text-center text-danger py-4 small">Unable to load activity for this date.</div>`;
                    }
                    return;
                }
                const d = res.data;

                // Update Header
                const titleEl = document.getElementById('selectedDateTitle');
                if (titleEl) titleEl.textContent = d.formatted_date;

                const badgeEl = document.getElementById('selectedDateBadge');
                if (badgeEl) {
                    badgeEl.textContent = `${d.total_events} Document Events`;
                }

                // Update Stat counters
                const st = d.stats || {};
                const upEl = document.getElementById('statUploadedDocs');
                if (upEl) upEl.textContent = st.uploaded || 0;
                const rtEl = document.getElementById('statRoutedDocs');
                if (rtEl) rtEl.textContent = st.routed || 0;
                const rcEl = document.getElementById('statReceivedDocs');
                if (rcEl) rcEl.textContent = st.received || 0;
                const apEl = document.getElementById('statApprovedDocs');
                if (apEl) apEl.textContent = st.approved || 0;
                const cpEl = document.getElementById('statCompletedDocs');
                if (cpEl) cpEl.textContent = st.completed || 0;

                // Render Documents
                if (!container) return;
                const docs = d.documents || [];
                if (docs.length === 0) {
                    container.innerHTML = `
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary opacity-50"></i>
                            <div class="fw-semibold">No document events on this date</div>
                            <div class="small">Click any active date on the calendar to inspect its document operations.</div>
                        </div>
                    `;
                    return;
                }

                let html = '';
                docs.forEach(doc => {
                    let evHtml = '';
                    (doc.events || []).forEach(ev => {
                        evHtml += `
                            <div class="d-flex align-items-center justify-content-between py-1 border-bottom-dashed" style="font-size: 11.5px;">
                                <div class="d-flex align-items-center gap-2 text-truncate">
                                    <span class="badge ${ev.badge_class}" style="font-size: 10px; font-weight: 500;">
                                        <i class="bi ${ev.icon} me-1"></i>${escapeHtml(ev.action)}
                                    </span>
                                    <span class="text-muted text-truncate" style="max-width: 260px;">
                                        ${escapeHtml(ev.office)} ${ev.user ? `&bull; ${escapeHtml(ev.user)}` : ''}
                                    </span>
                                </div>
                                <span class="font-monospace text-secondary flex-shrink-0 ms-2" style="font-size: 10.5px;">
                                    ${escapeHtml(ev.time)}
                                </span>
                            </div>
                        `;
                    });

                    html += `
                        <div class="card mb-2 border shadow-none" style="border-radius: 8px; border-color: var(--panel-border) !important;">
                            <div class="card-body p-2 p-md-3">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                    <div class="text-truncate">
                                        <a href="${doc.passport_url}" class="fw-bold text-decoration-none text-dark hover-primary" style="font-size: 13px;">
                                            ${escapeHtml(doc.title)}
                                        </a>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <span class="font-monospace text-secondary" style="font-size: 11px;">${escapeHtml(doc.tracking_number)}</span>
                                            <span class="badge bg-light text-secondary border" style="font-size: 10px;">${escapeHtml(doc.status)}</span>
                                            <span class="text-muted text-truncate" style="font-size: 11px;"><i class="bi bi-building me-1"></i>${escapeHtml(doc.current_office)}</span>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <a href="${doc.passport_url}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                                            Passport ➔
                                        </a>
                                        <a href="${doc.details_url}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                                            Details
                                        </a>
                                    </div>
                                </div>
                                <div class="mt-2 pt-2 border-top" style="border-color: var(--panel-border) !important;">
                                    ${evHtml}
                                </div>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            })
            .catch(err => {
                if (container) {
                    container.innerHTML = `<div class="text-center text-danger py-4 small">Error loading date events: ${escapeHtml(err.message)}</div>`;
                }
            });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Month Navigation Listeners
    const prevBtn = document.getElementById('calPrevMonth');
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            let m = currentCalMonth - 1;
            let y = currentCalYear;
            if (m < 1) { m = 12; y--; }
            fetch(`/api/dashboard/calendar-activity?year=${y}&month=${m}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data) {
                        renderCalendar(res.data);
                    }
                });
        });
    }

    const nextBtn = document.getElementById('calNextMonth');
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            let m = currentCalMonth + 1;
            let y = currentCalYear;
            if (m > 12) { m = 1; y++; }
            fetch(`/api/dashboard/calendar-activity?year=${y}&month=${m}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data) {
                        renderCalendar(res.data);
                    }
                });
        });
    }

    // Initial render of calendar
    renderCalendar(currentCalData);
    @endif
</script>
@endsection