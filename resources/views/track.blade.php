@extends('layouts.app')

@section('title','Track Documents')

@section('content')
<style>
    .filter-toolbar {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        padding: 16px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    
    .toolbar-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
    }

    .filter-input {
        background: var(--panel) !important;
        border: 1px solid var(--panel-border) !important;
        color: var(--text-main) !important;
        border-radius: var(--radius-md) !important;
        height: 38px !important;
        font-size: 13px !important;
        padding: 8px 12px !important;
    }
    
    .filter-input:focus {
        border-color: var(--accent-cyan) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        outline: none;
    }

    .btn-toolbar {
        height: 38px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        padding: 8px 16px !important;
        border-radius: var(--radius-md) !important;
    }

    .btn-search {
        background: var(--accent-cyan) !important;
        border: 1px solid var(--accent-cyan) !important;
        color: #FFFFFF !important;
    }
    
    .btn-search:hover {
        background: #1D4ED8 !important;
        border-color: #1D4ED8 !important;
    }

    .btn-reset {
        background: var(--panel) !important;
        border: 1px solid var(--panel-border) !important;
        color: var(--text-dim) !important;
        text-decoration: none;
    }
    
    .btn-reset:hover {
        background: var(--bg) !important;
        color: var(--text-main) !important;
    }

    .advanced-filters-panel {
        border-top: 1px solid var(--panel-border);
        padding-top: 16px;
        margin-top: 16px;
    }

    /* Progress bar container */
    .progress-bar-container { 
        width: 100px; 
        height: 5px; 
        background: var(--panel-border); 
        border-radius: var(--radius-sm); 
        overflow: hidden; 
    }
    .progress-fill { 
        height: 100%; 
        background: var(--accent-cyan); 
        border-radius: var(--radius-sm);
        transition: width 0.3s ease; 
    }

    /* Fixed table styling */
    .pagination nav svg {
        width: 1.25rem;
        height: 1.25rem;
    }
    .pagination .flex.justify-between.flex-1 {
        display: none !important;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title mb-1">Document Tracking</h1>
        <p class="text-secondary small mb-0" style="color: var(--text-dim) !important;">Monitor real-time location, progress logs, and transit records across the system.</p>
    </div>
</div>

<div class="filter-toolbar">
    <form method="GET" action="{{ route('track.index') }}">
        <!-- Top Toolbar Row -->
        <div class="toolbar-row justify-content-between">
            <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
                <!-- Search -->
                <div style="min-width: 240px;" class="flex-grow-1">
                    <input type="text" name="search" class="form-control filter-input" placeholder="Search by title, category, tracking number..." value="{{ request('search') }}">
                </div>
                
                <!-- Status -->
                <div style="min-width: 140px;">
                    <select name="status" class="form-select filter-input">
                        <option value="">All Statuses</option>
                        @foreach(['Pending', 'Received', 'Under Review', 'Approved', 'Rejected', 'Completed', 'Cancelled', 'Archived'] as $st)
                            <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- QR Status -->
                <div style="min-width: 140px;">
                    <select name="qr_status" class="form-select filter-input">
                        <option value="">All QR Statuses</option>
                        @foreach(['Not Scanned', 'Scanned', 'Verified', 'Accessed'] as $qrs)
                            <option value="{{ $qrs }}" {{ request('qr_status') === $qrs ? 'selected' : '' }}>{{ $qrs }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-toolbar" id="toggleAdvancedFiltersBtn">
                    <i class="bi bi-sliders2 me-1"></i> Filters
                </button>
                <a href="{{ route('track.index') }}" class="btn btn-reset btn-toolbar">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
                <button type="submit" class="btn btn-search btn-toolbar">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
        </div>

        <!-- Collapsible Advanced Filters Section -->
        <div id="advancedFiltersSection" class="advanced-filters-panel" style="display: {{ request()->hasAny(['department_id', 'uploader_id', 'receiver_id', 'from_date', 'to_date']) ? 'block' : 'none' }};">
            <div class="row g-3">
                <!-- Office -->
                <div class="col-md-3 text-start">
                    <label class="form-label text-uppercase text-slate-500 font-bold" style="font-size: 11px;">Office Location</label>
                    <select name="department_id" class="form-select filter-input">
                        <option value="">All Offices</option>
                        @foreach($offices ?? [] as $off)
                            <option value="{{ $off->id }}" {{ request('department_id') == $off->id ? 'selected' : '' }}>{{ $off->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Uploader -->
                <div class="col-md-3 text-start">
                    <label class="form-label text-uppercase text-slate-500 font-bold" style="font-size: 11px;">Uploaded By</label>
                    <select name="uploader_id" class="form-select filter-input">
                        <option value="">All Uploaders</option>
                        @foreach($users ?? [] as $us)
                            <option value="{{ $us->id }}" {{ request('uploader_id') == $us->id ? 'selected' : '' }}>{{ $us->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Receiver -->
                <div class="col-md-3 text-start">
                    <label class="form-label text-uppercase text-slate-500 font-bold" style="font-size: 11px;">Active Receiver</label>
                    <select name="receiver_id" class="form-select filter-input">
                        <option value="">All Receivers</option>
                        @foreach($users ?? [] as $us)
                            <option value="{{ $us->id }}" {{ request('receiver_id') == $us->id ? 'selected' : '' }}>{{ $us->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Filter -->
                <div class="col-md-3 text-start">
                    <label class="form-label text-uppercase text-slate-500 font-bold" style="font-size: 11px;">Date Range</label>
                    <div class="d-flex gap-2">
                        <input type="date" name="from_date" class="form-control filter-input w-50" value="{{ request('from_date') }}" placeholder="From">
                        <input type="date" name="to_date" class="form-control filter-input w-50" value="{{ request('to_date') }}" placeholder="To">
                    </div>
                </div>

                <!-- Sorting parameters -->
                <div class="col-md-3 text-start">
                    <label class="form-label text-uppercase text-slate-500 font-bold" style="font-size: 11px;">Sort By</label>
                    <select name="sort_by" class="form-select filter-input">
                        <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>Date Created</option>
                        <option value="due_date" {{ request('sort_by') === 'due_date' ? 'selected' : '' }}>Due Date</option>
                        <option value="title" {{ request('sort_by') === 'title' ? 'selected' : '' }}>Title</option>
                        <option value="status" {{ request('sort_by') === 'status' ? 'selected' : '' }}>Status</option>
                        <option value="qr_status" {{ request('sort_by') === 'qr_status' ? 'selected' : '' }}>QR Status</option>
                    </select>
                </div>

                <div class="col-md-3 text-start">
                    <label class="form-label text-uppercase text-slate-500 font-bold" style="font-size: 11px;">Sort Order</label>
                    <select name="sort_order" class="form-select filter-input">
                        <option value="desc" {{ request('sort_order') === 'desc' ? 'selected' : '' }}>Newest First</option>
                        <option value="asc" {{ request('sort_order') === 'asc' ? 'selected' : '' }}>Oldest First</option>
                    </select>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Document Details</th>
                <th>Routing Path</th>
                <th>Current Status</th>
                <th>Progress SLA</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($documents as $doc)
            @php
                $status = strtolower($doc->status);
                $badgeClass = 'badge-pending';
                if($status == 'completed' || $status == 'approved') $badgeClass = 'badge-success';
                if(in_array($status, ['in_transit', 'in transit', 'under review', 'received'])) $badgeClass = 'badge-info';
                if($status == 'rejected' || $status == 'cancelled') $badgeClass = 'badge-danger';
                
                $progress = 20;
                if(in_array($status, ['received', 'under review'])) $progress = 50;
                if(in_array($status, ['in_transit', 'in transit'])) $progress = 75;
                if($status == 'completed') $progress = 100;
            @endphp
            <tr>
                <td>
                    <strong style="color: var(--text-main) !important; font-size:13.5px;">{{ $doc->title }}</strong>
                    <div style="font-size: 11.5px; color: var(--text-dim); margin-top:2px;">
                        ID: <code style="font-size: 11px;">{{ $doc->tracking_number ?? $doc->qr_id }}</code>
                    </div>
                    <div style="font-size: 11px; color: var(--text-dim); margin-top:4px;">
                        @php
                            $latestView = $doc->views->sortByDesc('viewed_at')->first();
                        @endphp
                        @if($latestView)
                            <span title="Viewed by {{ $latestView->user->name }}"><i class="bi bi-eye me-1"></i> Last Viewed: {{ $latestView->viewed_at->format('M d, Y • h:i A') }}</span>
                        @else
                            Never Viewed
                        @endif
                    </div>
                </td>
                <td>
                    <div style="font-size: 12.5px;">
                        <span class="text-slate-500">{{ $doc->originOffice?->name ?? 'Uploader' }}</span>
                        <i class="bi bi-arrow-right text-slate-400 mx-1"></i>
                        <span class="text-slate-800 fw-bold">{{ $doc->destinationOffice?->name ?? 'Recipient' }}</span>
                    </div>
                    <div style="font-size:11px; color:var(--text-dim); margin-top:2px;">
                        Location: {{ $doc->currentOffice?->name ?? 'In Transit' }}
                    </div>
                </td>
                <td>
                    <span class="badge {{ $badgeClass }}">
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </span>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress-bar-container">
                            <div class="progress-fill" style="width: {{ $progress }}%"></div>
                        </div>
                        <span style="font-size: 11.5px; font-weight:600; color:var(--text-dim);">{{ $progress }}%</span>
                    </div>
                </td>
                <td>
                    <a href="{{ route('track.detail', $doc->id) }}" class="btn btn-outline-secondary btn-sm" style="height: 30px !important; padding:4px 10px !important; font-size:12px !important;">
                        <i class="bi bi-geo-alt"></i> Track Hops
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center py-5">
                    <i class="bi bi-folder-x text-slate-300" style="font-size: 36px; display:block; margin-bottom:12px;"></i>
                    <p class="mb-0 text-slate-500" style="font-size:13px;">No documents match your query filters.</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($documents, 'links'))
    <div class="d-flex justify-content-center mt-4">
        {{ $documents->links('pagination::bootstrap-4') }}
    </div>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('toggleAdvancedFiltersBtn');
        const advancedPanel = document.getElementById('advancedFiltersSection');
        
        if (toggleBtn && advancedPanel) {
            toggleBtn.addEventListener('click', function() {
                if (advancedPanel.style.display === 'none') {
                    advancedPanel.style.display = 'block';
                    toggleBtn.classList.add('active');
                    toggleBtn.style.background = 'var(--bg)';
                } else {
                    advancedPanel.style.display = 'none';
                    toggleBtn.classList.remove('active');
                    toggleBtn.style.background = 'transparent';
                }
            });
        }
    });
</script>
@endsection