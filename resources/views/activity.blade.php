@extends('layouts.app')

@section('title', 'Activity Logs')

@section('content')
<style>
    :root {
        --primary-bg: var(--panel);
        --secondary-bg: var(--bg);
        --accent-cyan: var(--accent-cyan);
        --accent-success: #10b981;
        --border-color: var(--panel-border);
        --text-primary: inherit;
        --text-secondary: var(--text-dim);
        --text-muted: var(--text-dim);
        --accent-navy: var(--accent-navy);
    }

    .filter-panel {
        background: var(--primary-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 25px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    }

    .filter-input {
        background: var(--primary-bg) !important;
        border: 1px solid var(--border-color) !important;
        color: inherit !important;
        border-radius: 8px;
        padding: 10px 12px;
        height: 44px;
    }
    .filter-input:focus {
        border-color: var(--accent-cyan) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        outline: none;
    }

    .activity-table {
        background: var(--primary-bg) !important;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        --bs-table-bg: var(--primary-bg) !important;
        --bs-table-hover-bg: rgba(59, 130, 246, 0.05) !important;
    }

    .activity-table thead {
        background: var(--secondary-bg) !important;
    }

    .activity-table th {
        color: var(--text-secondary) !important;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        padding: 14px 16px;
        border-color: var(--border-color) !important;
        background: var(--secondary-bg) !important;
    }

    .activity-table td {
        color: var(--text-primary) !important;
        padding: 14px 16px;
        border-color: var(--border-color) !important;
        background: transparent !important;
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
        border-radius: 8px !important;
        margin: 0 3px;
        padding: 8px 16px;
        transition: 0.2s;
    }

    .pagination .page-link:hover {
        background: var(--secondary-bg);
        color: var(--text-primary);
    }

    .pagination .page-item.active .page-link {
        background: var(--accent-navy);
        border-color: var(--accent-navy);
        color: #FFFFFF;
    }

    .btn-search {
        background: var(--accent-navy) !important;
        border: none;
        color: #FFFFFF !important;
        font-weight: 600;
        border-radius: 8px;
        height: 44px;
        padding: 10px 24px;
        transition: 0.2s;
    }
    .btn-search:hover {
        background: #1D4ED8 !important;
        box-shadow: 0 4px 12px rgba(30, 58, 138, 0.15);
    }
    .btn-reset {
        background: var(--primary-bg) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-secondary) !important;
        font-weight: 500;
        border-radius: 8px;
        height: 44px;
        padding: 10px 20px;
        text-decoration: none;
        transition: 0.2s;
    }
    .btn-reset:hover {
        background: var(--secondary-bg) !important;
        color: var(--text-primary) !important;
    }

    .activity-badge {
        padding: 6px 14px;
        border-radius: 24px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
        border: 1px solid;
    }

    .activity-badge.create { background: rgba(16, 185, 129, 0.1); color: #059669; border-color: rgba(16, 185, 129, 0.2); }
    .activity-badge.routed { background: rgba(59, 130, 246, 0.1); color: #2563EB; border-color: rgba(59, 130, 246, 0.2); }
    .activity-badge.completed { background: rgba(16, 185, 129, 0.1); color: #059669; border-color: rgba(16, 185, 129, 0.2); }
    .activity-badge.scan { background: rgba(245, 158, 11, 0.1); color: #D97706; border-color: rgba(245, 158, 11, 0.2); }
    .activity-badge.viewed { background: rgba(139, 92, 246, 0.1); color: #7C3AED; border-color: rgba(139, 92, 246, 0.2); }

    .doc-title { color: var(--accent-cyan); text-decoration: none; font-weight: 500; }
    .doc-title:hover { text-decoration: underline; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title mb-2">Activity Logs</h1>
        <p class="text-secondary small mb-0">Audit entries for user and document actions.</p>
    </div>
</div>

<div class="filter-panel">
    <form method="GET" action="{{ route('activity.index') }}" class="row g-3">
        <div class="col-md-3 text-start">
            <label class="form-label text-uppercase" style="font-weight: 700;">Search</label>
            <input type="text" name="search" class="form-control filter-input" placeholder="Document or user..." value="{{ request('search') }}">
        </div>
        
        <div class="col-md-2 text-start">
            <label class="form-label text-uppercase" style="font-weight: 700;">From Date</label>
            <input type="date" name="from_date" class="form-control filter-input" value="{{ request('from_date') }}">
        </div>
        
        <div class="col-md-2 text-start">
            <label class="form-label text-uppercase" style="font-weight: 700;">To Date</label>
            <input type="date" name="to_date" class="form-control filter-input" value="{{ request('to_date') }}">
        </div>

        <div class="col-md-2 text-start">
            <label class="form-label text-uppercase" style="font-weight: 700;">Action</label>
            <select name="action" class="form-control filter-input">
                <option value="">All Actions</option>
                <option value="Document Created" {{ request('action') === 'Document Created' ? 'selected' : '' }}>Created</option>
                <option value="Document Routed" {{ request('action') === 'Document Routed' ? 'selected' : '' }}>Routed</option>
                <option value="QR Scanned" {{ request('action') === 'QR Scanned' ? 'selected' : '' }}>Scanned</option>
            </select>
        </div>

        <div class="col-md-12 d-flex gap-2 pt-3">
            <button type="submit" class="btn btn-search">
                <i class="bi bi-search me-2"></i>Search
            </button>
            <a href="{{ route('activity.index') }}" class="btn btn-reset">
                <i class="bi bi-arrow-clockwise me-2"></i>Reset
            </a>
        </div>
    </form>
</div>

<div class="table-responsive shadow-lg" style="border-radius: 12px;">
    <table class="table table-hover mb-0 activity-table">
        <thead>
            <tr>
                <th>Action</th>
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
                        $action = strtolower($log->action);
                        $badgeClass = 'routed';
                        if (str_contains($action, 'created')) $badgeClass = 'create';
                        elseif (str_contains($action, 'completed')) $badgeClass = 'completed';
                        elseif (str_contains($action, 'scan')) $badgeClass = 'scan';
                        elseif (str_contains($action, 'viewed')) $badgeClass = 'viewed';
                    @endphp
                    <span class="activity-badge {{ $badgeClass }}">
                        {{ $log->action }}
                    </span>
                </td>
                <td class="align-middle">
                    <span style="color: var(--text-secondary);">{{ $log->user }}</span>
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
                    <span style="color: var(--text-secondary); font-size: 0.9rem;">
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
                    <p class="mt-2">No activity logs found.</p>
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