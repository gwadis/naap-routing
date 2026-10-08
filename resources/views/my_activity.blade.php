@extends('layouts.app')

@section('title', 'My Activity')

@section('content')
<style>
    :root {
        --primary-bg: var(--panel, #FFFFFF);
        --secondary-bg: var(--bg, #F8FAFC);
        --border-color: var(--panel-border, #E2E8F0);
        --text-primary: inherit;
        --text-secondary: var(--text-dim, #64748B);
        --text-muted: var(--text-dim, #64748B);
    }

    .filter-panel {
        background: var(--primary-bg);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg, 12px);
        padding: 20px 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
    }

    .filter-input {
        background: var(--primary-bg) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-main) !important;
        border-radius: var(--radius-md, 8px);
        padding: 8px 12px;
        height: 40px;
        font-size: 13px;
    }
    .filter-input:focus {
        border-color: var(--accent-cyan) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12) !important;
        outline: none;
    }

    .activity-table {
        background: var(--primary-bg) !important;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg, 12px);
        overflow: hidden;
        width: 100%;
        --bs-table-bg: var(--primary-bg) !important;
        --bs-table-hover-bg: rgba(59, 130, 246, 0.03) !important;
    }

    .activity-table thead {
        background: var(--secondary-bg) !important;
    }

    .activity-table th {
        color: var(--text-secondary) !important;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.72rem;
        letter-spacing: 0.05em;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color) !important;
        background: var(--secondary-bg) !important;
        white-space: nowrap;
    }

    .activity-table td {
        color: var(--text-main) !important;
        padding: 13px 16px;
        border-bottom: 1px solid var(--border-color) !important;
        background: transparent !important;
        vertical-align: middle;
        font-size: 13px;
    }

    /* --- PAGINATION STYLING --- */
    .pagination nav svg {
        width: 1.25rem !important;
        height: 1.25rem !important;
    }
    
    .pagination .flex.justify-between.flex-1 {
        display: none !important;
    }

    .pagination .page-link {
        background: var(--primary-bg) !important;
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        border-radius: var(--radius-md, 8px) !important;
        margin: 0 3px;
        padding: 7px 14px;
        font-size: 13px;
        transition: 0.15s ease;
    }

    .pagination .page-link:hover {
        background: var(--secondary-bg);
        color: var(--text-primary);
    }

    .pagination .page-item.active .page-link {
        background: var(--accent-navy);
        border-color: var(--accent-navy);
        color: #FFFFFF !important;
    }

    .btn-search {
        background: var(--accent-navy, #0F172A) !important;
        border: 1px solid var(--accent-navy, #0F172A) !important;
        color: #FFFFFF !important;
        font-weight: 600;
        border-radius: var(--radius-md, 8px);
        height: 40px;
        padding: 8px 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease;
        font-size: 13px;
    }
    .btn-search * {
        color: #FFFFFF !important;
    }
    .btn-search:hover {
        background: #1D4ED8 !important;
        border-color: #1D4ED8 !important;
        color: #FFFFFF !important;
        box-shadow: 0 2px 8px rgba(30, 58, 138, 0.2);
    }
    .btn-reset {
        background: var(--primary-bg) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-secondary) !important;
        font-weight: 500;
        border-radius: var(--radius-md, 8px);
        height: 40px;
        padding: 8px 18px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
        font-size: 13px;
    }
    .btn-reset:hover {
        background: var(--secondary-bg) !important;
        color: var(--text-primary) !important;
    }

    .doc-title { color: var(--accent-cyan); text-decoration: none; font-weight: 500; }
    .doc-title:hover { text-decoration: underline; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title mb-1">My Activity</h1>
        <p class="text-secondary small mb-0">Your personal operational audit trail and actions.</p>
    </div>
</div>

<div class="filter-panel">
    <form method="GET" action="{{ route('activity.my') }}" class="row g-3 align-items-end">
        <div class="col-12 col-md-4 col-lg-3 text-start">
            <label class="form-label text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">Search</label>
            <input type="text" name="search" class="form-control filter-input" placeholder="Document title, user, or action..." value="{{ request('search') }}">
        </div>
        
        <div class="col-6 col-md-2 text-start">
            <label class="form-label text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">From Date</label>
            <input type="date" name="from_date" class="form-control filter-input" value="{{ request('from_date') }}">
        </div>
        
        <div class="col-6 col-md-2 text-start">
            <label class="form-label text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">To Date</label>
            <input type="date" name="to_date" class="form-control filter-input" value="{{ request('to_date') }}">
        </div>

        <div class="col-12 col-md-2 text-start">
            <label class="form-label text-uppercase fw-bold" style="font-size: 11px; letter-spacing: 0.5px;">Action</label>
            <select name="action" class="form-select filter-input">
                <option value="">All Actions</option>
                <option value="Document Created" {{ request('action') === 'Document Created' ? 'selected' : '' }}>Created</option>
                <option value="Document Routed" {{ request('action') === 'Document Routed' ? 'selected' : '' }}>Routed</option>
                <option value="QR Scanned" {{ request('action') === 'QR Scanned' ? 'selected' : '' }}>Scanned</option>
                <option value="Document Viewed" {{ request('action') === 'Document Viewed' ? 'selected' : '' }}>Viewed</option>
                <option value="Password Reset" {{ request('action') === 'Password Reset' ? 'selected' : '' }}>Password Reset</option>
            </select>
        </div>

        <div class="col-12 col-md-auto d-flex gap-2 text-start">
            <button type="submit" class="btn btn-search">
                <i class="bi bi-search me-1"></i>Search
            </button>
            <a href="{{ route('activity.my') }}" class="btn btn-reset">
                <i class="bi bi-arrow-clockwise me-1"></i>Reset
            </a>
        </div>
    </form>
</div>

<div class="table-responsive shadow-sm" style="border-radius: 12px;">
    <table class="table table-hover mb-0 activity-table">
        <thead>
            <tr>
                <th style="min-width: 220px;">Action</th>
                <th>User</th>
                <th>Document</th>
                <th>From</th>
                <th>To</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr>
                <td class="align-middle">
                    @php
                        $actionLower = strtolower($log->action);
                        $iconClass = 'bi-record-circle';
                        $iconColor = '#1D4ED8';
                        if (str_contains($actionLower, 'created')) {
                            $iconClass = 'bi-plus-circle-fill';
                            $iconColor = '#1D4ED8';
                        } elseif (str_contains($actionLower, 'completed')) {
                            $iconClass = 'bi-check-circle-fill';
                            $iconColor = '#059669';
                        } elseif (str_contains($actionLower, 'scan')) {
                            $iconClass = 'bi-qr-code-scan';
                            $iconColor = '#1D4ED8';
                        } elseif (str_contains($actionLower, 'viewed')) {
                            $iconClass = 'bi-eye-fill';
                            $iconColor = '#64748B';
                        } elseif (str_contains($actionLower, 'password') || str_contains($actionLower, 'security') || str_contains($actionLower, 'login')) {
                            $iconClass = 'bi-shield-lock-fill';
                            $iconColor = '#1D4ED8';
                        } elseif (str_contains($actionLower, 'routed') || str_contains($actionLower, 'forward')) {
                            $iconClass = 'bi-arrow-right-circle-fill';
                            $iconColor = '#1D4ED8';
                        }
                    @endphp
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi {{ $iconClass }}" style="color: {{ $iconColor }}; font-size: 14px; flex-shrink: 0;"></i>
                        <span class="action-text fw-medium" style="color: var(--text-main); font-size: 13px; line-height: 1.4;">
                            {{ $log->action }}
                        </span>
                    </div>
                </td>
                <td class="align-middle">
                    <span style="color: var(--text-secondary); font-weight: 500;">{{ $log->user }}</span>
                </td>
                <td class="align-middle">
                    @if($log->document)
                        <a href="{{ route('track.detail', $log->document->id) }}" class="doc-title">{{ $log->document->title }}</a>
                    @else
                        <span style="color: var(--text-muted);">System</span>
                    @endif
                </td>
                <td class="align-middle" style="color: var(--text-secondary);">
                    {{ $log->document?->originOffice?->name ?? 'N/A' }}
                </td>
                <td class="align-middle" style="color: var(--text-secondary);">
                    {{ $log->document?->destinationOffice?->name ?? 'N/A' }}
                </td>
                <td class="align-middle">
                    <span style="color: var(--text-secondary); font-size: 0.875rem; white-space: nowrap;">
                        @if(is_string($log->created_at))
                            {{ $log->created_at }}
                        @else
                            {{ str_contains(strtolower($log->action), 'viewed') ? $log->created_at->format('M d, Y • h:i A') : $log->created_at->format('M j, Y H:i') }}
                        @endif
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center py-5" style="color: var(--text-muted);">
                    <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.5;"></i>
                    <p class="mt-2 mb-0">No activity logs found matching the selected criteria.</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4 pagination d-flex justify-content-center">
    @if(method_exists($logs, 'links'))
        {{ $logs->links('pagination::bootstrap-4') }}
    @endif
</div>
@endsection
