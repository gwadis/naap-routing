@extends('layouts.app')
@section('title', 'Document Operations Analytics Center')

@section('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
    :root {
        --glass-bg: var(--panel);
        --glass-border: var(--panel-border);
        --accent-cyan: var(--accent-cyan);
        --accent-purple: var(--accent-purple);
    }
    .glass-card {
        background: var(--panel);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    }
    .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--text-main); }
    .stat-label { font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; }
    .btn-export {
        background: var(--accent-navy) !important;
        border: none;
        color: #FFFFFF !important;
        transition: 0.2s;
    }
    .btn-export:hover { 
        background: #1D4ED8 !important; 
        color: #FFFFFF !important;
        box-shadow: 0 4px 12px rgba(30, 58, 138, 0.15);
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
        max-height: 34px;
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
    .stat-metric-row {
        padding: 8px 12px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Fixed Chart Wrapper - Strictly breaks any circular canvas-parent height loop */
    .report-chart-wrapper,
    .report-chart-container,
    .chart-wrapper {
        position: relative !important;
        width: 100% !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
        flex: none !important; /* CRITICAL: Never allow flex-grow to expand wrapper */
    }

    .report-chart-wrapper canvas,
    .report-chart-container canvas,
    .chart-wrapper canvas {
        display: block !important;
        box-sizing: border-box !important;
        max-width: 100% !important;
        max-height: 100% !important;
    }

    /* ============================================================
       PRINT & EXPORT VIEW SPECIFIC SIZING & LAYOUT
       Preserves normal screen view while enforcing compact 280px
       chart heights and clean page breaks during print/export.
       ============================================================ */
    @media print {
        @page {
            size: auto;
            margin: 12mm 15mm;
        }

        body {
            background: #FFFFFF !important;
            color: #0F172A !important;
            font-size: 10pt !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* 1. Hide interactive non-report controls */
        .sidebar, #sidebar, #sidebarOverlay,
        header, .breadcrumb-container, .search-container,
        .dropdown, #notifDropdown, #hamburgerMenu,
        .d-print-none,
        form, .pagination, nav[aria-label="Pagination Navigation"],
        #calPrevMonth, #calNextMonth,
        .btn-close, .alert {
            display: none !important;
        }

        /* 2. Full Width Printable Main Layout */
        .main-container {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 0 !important;
        }

        main, .container-fluid {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        /* 3. Prevent Flex and Grid Parent Stretching */
        .row {
            display: flex !important;
            flex-wrap: wrap !important;
        }

        .col-lg-5, .col-lg-6, .col-lg-7, .col-12 {
            height: auto !important;
            min-height: 0 !important;
        }

        .h-100 {
            height: auto !important;
            min-height: 0 !important;
        }

        .d-flex.flex-column {
            height: auto !important;
            min-height: 0 !important;
        }

        /* 4. Page Break & Card Formatting */
        .glass-card, .card, .report-card {
            background: #FFFFFF !important;
            border: 1px solid #CBD5E1 !important;
            border-radius: 8px !important;
            box-shadow: none !important;
            padding: 14px 18px !important;
            margin-bottom: 16px !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
            height: auto !important;
            min-height: 0 !important;
        }

        /* 5. DEDICATED PRINT/EXPORT CHART SIZING BEHAVIOR */
        .report-chart-wrapper,
        .report-chart-container,
        .chart-wrapper,
        .chart-container {
            height: 280px !important;
            max-height: 280px !important;
            min-height: 280px !important;
            width: 100% !important;
            flex: none !important; /* Neutralizes flex-grow so canvas cannot stretch */
            position: relative !important;
            overflow: hidden !important;
            display: block !important;
            box-sizing: border-box !important;
            margin: 0 auto !important;
        }

        .report-chart-wrapper canvas,
        .report-chart-container canvas,
        .chart-wrapper canvas,
        .chart-container canvas {
            height: 280px !important;
            max-height: 280px !important;
            min-height: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            display: block !important;
            box-sizing: border-box !important;
            object-fit: contain !important;
        }

        /* 6. Clean Table Print Layout */
        .table-responsive {
            overflow: visible !important;
            border: 1px solid #CBD5E1 !important;
        }

        table {
            width: 100% !important;
            page-break-inside: auto !important;
        }

        tr {
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        th, td {
            font-size: 8.5pt !important;
            padding: 5px 6px !important;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid py-3">
    <!-- Dedicated Print Header (Only visible in Print / PDF export) -->
    <div class="d-none d-print-block mb-3 border-bottom pb-2">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold mb-0" style="color: #0F172A; letter-spacing: -0.5px;">NAAP DOCUMENT ROUTING SYSTEM</h3>
                <p class="text-secondary small mb-0 font-monospace">Document Operations Analytics & Business Intelligence Report</p>
            </div>
            <div class="text-end text-muted small" style="font-size: 8.5pt;">
                <div>Generated: <strong>{{ now()->format('F d, Y h:i A') }}</strong></div>
                @if(request('from_date') || request('to_date'))
                    <div>Period: <strong>{{ request('from_date', 'Start') }}</strong> to <strong>{{ request('to_date', 'Present') }}</strong></div>
                @endif
                @if(request('office_id'))
                    <div>Office Filter: <strong>{{ $allOffices->firstWhere('id', request('office_id'))?->name }}</strong></div>
                @endif
            </div>
        </div>
    </div>

    <!-- Header with Export Bar (Part 20) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="page-title mb-1">Document Operations Analytics Center</h2>
            <p class="text-secondary small mb-0">
                Detailed operational analytics, office performance, SLAs, and organizational business intelligence.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="btn btn-export rounded-pill px-3" style="height: 38px !important; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem;">
                <i class="bi bi-filetype-csv me-2"></i>Export CSV
            </a>
            <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="btn btn-success text-white rounded-pill px-3" style="height: 38px !important; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; border: none;">
                <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
            </a>
            <button type="button" onclick="window.print()" class="btn btn-primary text-white rounded-pill px-3" style="height: 38px !important; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; border: none; background: #1D4ED8 !important;">
                <i class="bi bi-printer me-2"></i>Print Report
            </button>
            <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" target="_blank" class="btn btn-outline-secondary rounded-pill px-3" style="height: 38px !important; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem;" title="Export Tabular Documents Ledger as PDF">
                <i class="bi bi-file-earmark-pdf me-2"></i>Table PDF
            </a>
        </div>
    </div>

    <!-- Reports Filter Bar (Part 5) -->
    <div class="glass-card mb-4 d-print-none">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                <i class="bi bi-funnel me-2 text-primary"></i>Operational Report Filter Panel
            </h5>
            <span class="text-muted small">All analytics and charts reflect the selected period & parameters</span>
        </div>
        <form method="GET" action="{{ route('reports.index') }}" class="row g-3">
            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small fw-bold">Date From</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}">
            </div>

            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small fw-bold">Date To</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}">
            </div>

            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small fw-bold">Office</label>
                <select name="office_id" class="form-select form-select-sm">
                    <option value="">All Offices</option>
                    @foreach($allOffices as $off)
                        <option value="{{ $off->id }}" {{ request('office_id') == $off->id ? 'selected' : '' }}>{{ $off->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small fw-bold">Document Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach(['Pending', 'Received', 'In Process', 'Under Review', 'For Approval', 'Approved', 'Completed', 'Overdue', 'Cancelled', 'Archived'] as $st)
                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small fw-bold">Document Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small fw-bold">Processing / SLA</label>
                <select name="sla_category" class="form-select form-select-sm">
                    <option value="">All SLA Categories</option>
                    <option value="Simple Transaction (3 Working Days)" {{ request('sla_category') === 'Simple Transaction (3 Working Days)' ? 'selected' : '' }}>Simple (3 Working Days)</option>
                    <option value="Complex Transaction (7 Working Days)" {{ request('sla_category') === 'Complex Transaction (7 Working Days)' ? 'selected' : '' }}>Complex (7 Working Days)</option>
                    <option value="Highly Technical Transaction (20 Working Days)" {{ request('sla_category') === 'Highly Technical Transaction (20 Working Days)' ? 'selected' : '' }}>Highly Technical (20 Days)</option>
                    <option value="within_sla" {{ request('sla_category') === 'within_sla' ? 'selected' : '' }}>Completed Within SLA</option>
                    <option value="beyond_sla" {{ request('sla_category') === 'beyond_sla' ? 'selected' : '' }}>Completed Beyond SLA</option>
                    <option value="overdue" {{ request('sla_category') === 'overdue' ? 'selected' : '' }}>Currently Overdue</option>
                    <option value="nearing_sla" {{ request('sla_category') === 'nearing_sla' ? 'selected' : '' }}>Nearing SLA (&le; 48h)</option>
                </select>
            </div>

            <div class="col-12 d-flex justify-content-between align-items-center pt-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" style="border-radius: 6px;">
                        <i class="bi bi-funnel-fill me-1"></i>Apply Filters
                    </button>
                    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm px-4 fw-bold" style="border-radius: 6px;">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
                @if(request()->anyFilled(['from_date', 'to_date', 'office_id', 'status', 'category', 'sla_category', 'search']))
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1">
                        <i class="bi bi-check-circle me-1"></i>Active Filters Applied ({{ $summary['total_processed'] }} Matching Documents)
                    </span>
                @endif
            </div>
        </form>
    </div>

    <!-- Reports Overview (Part 6: Values based on selected reporting period) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="glass-card text-center h-100 d-flex flex-column justify-content-center p-3">
                <div class="stat-label">Total Documents</div>
                <div class="stat-value">{{ number_format($summary['total_processed']) }}</div>
                <small class="text-muted" style="font-size: 11px;">Reporting Period</small>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="glass-card text-center h-100 d-flex flex-column justify-content-center p-3">
                <div class="stat-label">Completed</div>
                <div class="stat-value text-success">{{ number_format($summary['completed']) }}</div>
                <small class="text-muted" style="font-size: 11px;">Finished Steps</small>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="glass-card text-center h-100 d-flex flex-column justify-content-center p-3">
                <div class="stat-label">In Process</div>
                <div class="stat-value" style="color: #2563EB;">{{ number_format($summary['in_process']) }}</div>
                <small class="text-muted" style="font-size: 11px;">Active Workflows</small>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="glass-card text-center h-100 d-flex flex-column justify-content-center p-3">
                <div class="stat-label">Overdue</div>
                <div class="stat-value text-danger">{{ number_format($summary['overdue']) }}</div>
                <small class="text-muted" style="font-size: 11px;">Action Required</small>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="glass-card text-center h-100 d-flex flex-column justify-content-center p-3">
                <div class="stat-label">Avg Processing Time</div>
                <div class="stat-value" style="color: var(--text-main);">{{ is_numeric($summary['avg_time']) ? $summary['avg_time'] . 'h' : 'N/A' }}</div>
                <small class="text-muted" style="font-size: 11px;">Median: {{ is_numeric($summary['median_time']) ? $summary['median_time'] . 'h' : 'N/A' }}</small>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="glass-card text-center h-100 d-flex flex-column justify-content-center p-3">
                <div class="stat-label">Completed SLA Rate</div>
                <div class="stat-value" style="color: #059669;">{{ $summary['sla_compliance'] !== 'N/A' ? $summary['sla_compliance'] . '%' : 'N/A' }}</div>
                <small class="text-muted" style="font-size: 11px;">Overall: {{ $summary['overall_sla'] !== 'N/A' ? $summary['overall_sla'] . '%' : 'N/A' }}</small>
            </div>
        </div>
    </div>

    <!-- Document Volume Analytics (Part 7) -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="glass-card h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                        <i class="bi bi-graph-up text-primary me-2"></i>Weekly Volume Flow
                    </h5>
                    <span class="badge bg-light text-secondary border">Monday – Sunday</span>
                </div>
                <div class="report-chart-wrapper" style="height: 280px; min-height: 280px; max-height: 280px; position: relative; width: 100%; overflow: hidden;">
                    <canvas id="flowChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="glass-card h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                        <i class="bi bi-layers text-primary me-2"></i>Workflow Event Breakdown
                    </h5>
                    <span class="badge bg-light text-secondary border">Period Events</span>
                </div>
                <div class="report-chart-wrapper" style="height: 220px; min-height: 220px; max-height: 220px; position: relative; width: 100%; overflow: hidden;">
                    <canvas id="volumeBreakdownChart"></canvas>
                </div>
                <div class="d-flex flex-wrap gap-2 pt-3 mt-2 border-top justify-content-between small">
                    @foreach($volumeBreakdown as $event => $count)
                        <span class="badge bg-light text-dark border px-2 py-1">
                            {{ $event }}: <strong class="text-primary">{{ number_format($count) }}</strong>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Office Workload & Processing Analysis (Part 8 & 13) -->
    <div class="glass-card mb-4 text-start">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: var(--accent-navy) !important;">
                    <i class="bi bi-buildings text-primary me-2"></i>Office Workload & Processing Analysis
                </h5>
                <p class="text-muted small mb-0">Detailed comparative workload, processing velocity, queue times, and SLA compliance per office.</p>
            </div>
            <span class="badge bg-light text-secondary border">Operational Intelligence</span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0" style="font-size: 12.5px;">
                <thead style="background: rgba(30, 58, 138, 0.05);">
                    <tr style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700;" class="text-secondary">
                        <th>Office</th>
                        <th class="text-center">Received</th>
                        <th class="text-center">Processed</th>
                        <th class="text-center">Pending Workload</th>
                        <th class="text-center">Completed</th>
                        <th class="text-center">Overdue</th>
                        <th class="text-center">Avg Processing Time</th>
                        <th class="text-center">Median Time</th>
                        <th class="text-center">SLA Compliance</th>
                        <th class="text-center">Avg Queue Time</th>
                        <th class="text-end">Inspect</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($officeStats as $stat)
                        <tr>
                            <td class="fw-bold text-dark">{{ $stat['name'] }}</td>
                            <td class="text-center text-secondary">{{ number_format($stat['received']) }}</td>
                            <td class="text-center text-success fw-bold">{{ number_format($stat['processed']) }}</td>
                            <td class="text-center">
                                <span class="badge {{ $stat['pending'] > 5 ? 'bg-danger' : ($stat['pending'] > 0 ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                    {{ $stat['pending'] }}
                                </span>
                            </td>
                            <td class="text-center text-muted">{{ number_format($stat['completed']) }}</td>
                            <td class="text-center">
                                <span class="badge {{ $stat['overdue'] > 0 ? 'bg-danger' : 'bg-light text-muted border' }}">
                                    {{ $stat['overdue'] }}
                                </span>
                            </td>
                            <td class="text-center fw-semibold" style="color: var(--accent-purple);">
                                {{ $stat['avg_processing_time'] }}
                            </td>
                            <td class="text-center text-secondary">{{ $stat['median_processing_time'] }}</td>
                            <td class="text-center">
                                @if($stat['sla_compliance'] !== 'N/A')
                                    @php
                                        $rate = $stat['sla_compliance'];
                                        $badgeColor = $rate >= 90 ? 'bg-success' : ($rate >= 75 ? 'bg-warning text-dark' : 'bg-danger');
                                    @endphp
                                    <span class="badge {{ $badgeColor }} px-2 py-1">
                                        {{ number_format($rate, 1) }}%
                                    </span>
                                @else
                                    <span class="badge bg-secondary px-2 py-1">N/A</span>
                                @endif
                            </td>
                            <td class="text-center text-secondary">{{ $stat['avg_time_before_processing'] }}</td>
                            <td class="text-end">
                                <a href="{{ $stat['inspect_url'] }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                                    Inspect ➔
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-muted text-center py-4">No office records available for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Processing Time & SLA Analysis (Part 9 & 10) -->
    <div class="row g-4 mb-4">
        <!-- Processing Time Analysis (Part 9) -->
        <div class="col-lg-6">
            <div class="glass-card h-100 d-flex flex-column text-start">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                        <i class="bi bi-clock-history text-primary me-2"></i>Processing Time Velocity
                    </h5>
                    <span class="badge bg-light text-secondary border">Strict Chronological Validation</span>
                </div>
                
                <div class="row g-2 mb-3">
                    <div class="col-3">
                        <div class="p-2 border rounded text-center bg-light">
                            <div class="text-muted small" style="font-size: 10px;">AVERAGE</div>
                            <div class="fw-bold text-dark">{{ is_numeric($summary['avg_time']) ? $summary['avg_time'] . 'h' : 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded text-center bg-light">
                            <div class="text-muted small" style="font-size: 10px;">MEDIAN</div>
                            <div class="fw-bold text-dark">{{ is_numeric($summary['median_time']) ? $summary['median_time'] . 'h' : 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded text-center bg-light">
                            <div class="text-muted small" style="font-size: 10px;">FASTEST</div>
                            <div class="fw-bold text-success">{{ is_numeric($summary['fastest_time']) ? $summary['fastest_time'] . 'h' : 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded text-center bg-light">
                            <div class="text-muted small" style="font-size: 10px;">LONGEST</div>
                            <div class="fw-bold text-danger">{{ is_numeric($summary['longest_time']) ? $summary['longest_time'] . 'h' : 'N/A' }}</div>
                        </div>
                    </div>
                </div>

                <div class="report-chart-wrapper" style="height: 240px; min-height: 240px; max-height: 240px; position: relative; width: 100%; overflow: hidden;">
                    <canvas id="procTimeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- SLA Performance Analysis (Part 10) -->
        <div class="col-lg-6">
            <div class="glass-card h-100 d-flex flex-column text-start">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                        <i class="bi bi-shield-check text-success me-2"></i>SLA Performance & ARTA Compliance
                    </h5>
                    <span class="badge bg-light text-secondary border">Compliance Trend</span>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-3">
                        <div class="p-2 border rounded text-center bg-light">
                            <div class="text-muted small" style="font-size: 10px;">WITHIN SLA</div>
                            <div class="fw-bold text-success">{{ $summary['within_sla'] }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded text-center bg-light">
                            <div class="text-muted small" style="font-size: 10px;">BEYOND SLA</div>
                            <div class="fw-bold text-warning">{{ $summary['beyond_sla'] }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded text-center bg-light">
                            <div class="text-muted small" style="font-size: 10px;">OVERDUE</div>
                            <div class="fw-bold text-danger">{{ $summary['overdue'] }}</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded text-center bg-light">
                            <div class="text-muted small" style="font-size: 10px;">AVG OVERDUE</div>
                            <div class="fw-bold text-dark">{{ $summary['avg_overdue_days'] !== 'N/A' ? $summary['avg_overdue_days'] . 'd' : 'N/A' }}</div>
                        </div>
                    </div>
                </div>

                <div class="report-chart-wrapper" style="height: 240px; min-height: 240px; max-height: 240px; position: relative; width: 100%; overflow: hidden;">
                    <canvas id="slaTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Aging & Document Lifecycle Analysis (Part 11 & 12 - Moved from Dashboard) -->
    <div class="row g-4 mb-4">
        <!-- Document Aging Analysis (Part 11) -->
        <div class="col-lg-6">
            <div class="glass-card h-100 d-flex flex-column text-start">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                        <i class="bi bi-hourglass-split text-warning me-2"></i>Active Document Aging Analysis
                    </h5>
                    <span class="text-muted small" style="font-size: 11px;">Click category to inspect</span>
                </div>
                <div class="report-chart-wrapper" style="height: 240px; min-height: 240px; max-height: 240px; position: relative; width: 100%; overflow: hidden;">
                    <canvas id="agingChart"></canvas>
                </div>
                <div class="d-flex flex-wrap gap-1 mt-2 pt-2 border-top">
                    @foreach($reportAging as $range => $cnt)
                        @php
                            $param = match($range) {
                                '0–1 hour' => '0_1h',
                                '1–4 hours' => '1_4h',
                                '4–8 hours' => '4_8h',
                                '8–24 hours' => '8_24h',
                                '1–3 days' => '1_3d',
                                '3+ days' => '3plus',
                                default => 'all'
                            };
                        @endphp
                        <a href="{{ route('documents.index', ['aging' => $param]) }}" class="badge bg-light text-dark border text-decoration-none py-1 px-2" style="font-size: 11px;" title="Click to view {{ $range }} documents">
                            {{ $range }}: <strong class="text-primary">{{ $cnt }}</strong>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Document Lifecycle Analysis (Part 12) -->
        <div class="col-lg-6">
            <div class="glass-card h-100 d-flex flex-column text-start">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>Document Lifecycle Analysis
                    </h5>
                    <span class="badge bg-light text-secondary border">Workflow Funnel</span>
                </div>
                <div class="report-chart-wrapper" style="height: 240px; min-height: 240px; max-height: 240px; position: relative; width: 100%; overflow: hidden;">
                    <canvas id="lifecycleChart"></canvas>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2 pt-2 border-top justify-content-between small">
                    @foreach($reportLifecycle['stages'] as $stage => $data)
                        <span class="badge bg-light text-dark border px-2 py-1">
                            {{ $stage }}: <strong class="text-primary">{{ $data['count'] }}</strong>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed QR Analytics (Part 14 - Moved from Dashboard & Expanded) -->
    <div class="glass-card mb-4 text-start">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: var(--accent-navy) !important;">
                    <i class="bi bi-qr-code-scan text-primary me-2"></i>QR Code Analytics & Physical Traceability
                </h5>
                <p class="text-muted small mb-0">System QR issuance, scan logs, unique scanning personnel, and tamper-evident tracking metrics.</p>
            </div>
            <span class="badge bg-light text-secondary border">Physical Routing Security</span>
        </div>

        <!-- QR Metrics Row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 border rounded text-center bg-light">
                    <div class="stat-label">Total QR Generated</div>
                    <div class="fs-4 fw-bold text-primary">{{ number_format($totalQrGenerated) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 border rounded text-center bg-light">
                    <div class="stat-label">Total Scans</div>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($summary['qr_scans']) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 border rounded text-center bg-light">
                    <div class="stat-label">Successful Scans</div>
                    <div class="fs-4 fw-bold text-success">{{ number_format($qrSuccessfulScans) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 border rounded text-center bg-light">
                    <div class="stat-label">Failed Scans</div>
                    <div class="fs-4 fw-bold text-danger">{{ number_format($qrFailedScans) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 border rounded text-center bg-light">
                    <div class="stat-label">Unique Scanners</div>
                    <div class="fs-4 fw-bold" style="color: #7C3AED;">{{ number_format($uniqueUsersScanning) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 border rounded text-center bg-light">
                    <div class="stat-label">QR Accessed Docs</div>
                    <div class="fs-4 fw-bold text-primary">{{ number_format($qrDocumentsAccessed) }}</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <h6 class="fw-bold mb-3 text-secondary" style="font-size: 13px;">7-Day Scanning Trend & Verifications</h6>
                <div class="report-chart-wrapper" style="height: 220px; min-height: 220px; max-height: 220px; position: relative; width: 100%; overflow: hidden;">
                    <canvas id="qrTrendChart"></canvas>
                </div>
            </div>
            <div class="col-lg-5">
                <h6 class="fw-bold mb-3 text-secondary" style="font-size: 13px;">Most Frequently Scanned Documents</h6>
                <div class="list-group list-group-flush border rounded">
                    @forelse($topScannedDocuments as $tsd)
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div class="text-truncate me-2">
                                <span class="font-monospace text-primary fw-bold" style="font-size: 12px;">{{ $tsd['tracking_number'] }}</span>
                                <div class="text-dark small text-truncate" style="max-width: 240px;">{{ $tsd['title'] }}</div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                <span class="badge bg-primary rounded-pill">{{ $tsd['scans_count'] }} scans</span>
                                <a href="{{ $tsd['passport_url'] }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px;">Passport</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">No document scan records available for this period.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Routing Analysis & Operational Deviations (Part 15) -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="glass-card h-100 text-start">
                <h5 class="fw-bold mb-3" style="color: var(--accent-navy) !important;">
                    <i class="bi bi-signpost-split text-primary me-2"></i>Most Common Routing Pathways
                </h5>
                <div class="list-group list-group-flush">
                    @forelse($topRoutes as $route)
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-bottom">
                            <div>
                                <span class="fw-semibold text-dark">{{ $route['from'] }}</span>
                                <i class="bi bi-arrow-right mx-2 text-primary"></i>
                                <span class="fw-semibold text-dark">{{ $route['to'] }}</span>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-monospace">
                                {{ $route['count'] }} documents routed
                            </span>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">No completed routing pathways recorded.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="glass-card h-100 text-start">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                        <i class="bi bi-exclamation-triangle text-warning me-2"></i>Route Exceptions & Deviations
                    </h5>
                    <span class="badge bg-warning text-dark">{{ $reportRouteExceptions->count() }} Recorded</span>
                </div>
                <div class="list-group list-group-flush" style="max-height: 280px; overflow-y: auto;">
                    @forelse($reportRouteExceptions as $exc)
                        <div class="list-group-item px-0 py-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-dark small text-truncate" style="max-width: 250px;">{{ $exc->document?->title ?? 'Document' }}</strong>
                                <span class="badge bg-warning text-dark" style="font-size: 10px;">{{ $exc->status }} (Route Deviation)</span>
                            </div>
                            <div class="text-muted small mt-1" style="font-size: 11px;">
                                {{ $exc->fromOffice?->name ?? 'Origin' }} ➔ {{ $exc->toOffice?->name ?? 'Destination' }}
                                @if($exc->forwarded_reason) &bull; Note: {{ $exc->forwarded_reason }} @endif
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="text-secondary font-monospace" style="font-size: 10.5px;">{{ $exc->document?->tracking_number ?? 'N/A' }}</span>
                                @if($exc->document_id)
                                    <a href="{{ route('documents.passport', $exc->document_id) }}" class="text-primary text-decoration-none small fw-bold" style="font-size: 11px;">
                                        Passport ➔
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">
                            <i class="bi bi-check-circle text-success d-block mb-1 fs-5"></i>
                            No operational route deviations recorded. All documents on intended paths.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Document Category Performance Analysis (Part 17) -->
    <div class="glass-card mb-4 text-start">
        <h5 class="fw-bold mb-3" style="color: var(--accent-navy) !important;">
            <i class="bi bi-tags text-primary me-2"></i>Document Category Performance Analysis
        </h5>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0" style="font-size: 12.5px;">
                <thead style="background: rgba(30, 58, 138, 0.05);">
                    <tr style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700;" class="text-secondary">
                        <th>Category</th>
                        <th class="text-center">Total Volume</th>
                        <th class="text-center">Completed</th>
                        <th class="text-center">In Process</th>
                        <th class="text-center">Overdue</th>
                        <th class="text-center">Avg Processing Time</th>
                        <th class="text-center">SLA Compliance Rate</th>
                        <th class="text-end">Drill-Down</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categoryStats as $cStat)
                        @if($cStat['volume'] > 0 || in_array($cStat['name'], ['Class Schedule', 'Faculty Workload', 'Operational Plans', 'Endorsements', 'Payrolls']))
                        <tr>
                            <td class="fw-bold text-dark">{{ $cStat['name'] }}</td>
                            <td class="text-center font-monospace">{{ number_format($cStat['volume']) }}</td>
                            <td class="text-center text-success fw-bold">{{ number_format($cStat['completed']) }}</td>
                            <td class="text-center text-primary">{{ number_format($cStat['in_process']) }}</td>
                            <td class="text-center">
                                <span class="badge {{ $cStat['overdue'] > 0 ? 'bg-danger' : 'bg-light text-muted border' }}">
                                    {{ $cStat['overdue'] }}
                                </span>
                            </td>
                            <td class="text-center text-secondary font-monospace">{{ $cStat['avg_time'] }}</td>
                            <td class="text-center">
                                <span class="badge {{ $cStat['sla_compliance'] !== 'N/A' && (float)$cStat['sla_compliance'] >= 90 ? 'bg-success' : ($cStat['sla_compliance'] !== 'N/A' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                    {{ $cStat['sla_compliance'] }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('documents.index', ['category' => $cStat['name']]) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                                    View Docs ➔
                                </a>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="text-muted text-center py-4">No categories recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detailed Document Records Ledger Table (Part 18 & 19) -->
    <div class="glass-card mb-4 text-start">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: var(--accent-navy) !important;">
                    <i class="bi bi-table text-primary me-2"></i>Comprehensive Document Ledger
                </h5>
                <p class="text-muted small mb-0">Complete historical records matching active reporting parameters.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="{{ route('reports.index') }}" class="d-flex gap-2">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search tracking #, title..." value="{{ request('search') }}" style="width: 220px;">
                    <button type="submit" class="btn btn-sm btn-primary px-3">Search</button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 12.5px;">
                <thead style="background: rgba(30, 58, 138, 0.05);">
                    <tr style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700;" class="text-secondary">
                        <th>Tracking No</th>
                        <th>Document Title</th>
                        <th>Category</th>
                        <th>Origin Office</th>
                        <th>Current Location</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Completed</th>
                        <th>Processing Time</th>
                        <th>SLA Category</th>
                        <th>SLA Result</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportDocuments as $doc)
                        <tr>
                            <td>
                                <a href="{{ route('documents.passport', $doc->id) }}" class="font-monospace text-primary fw-bold text-decoration-none hover-primary">
                                    {{ $doc->tracking_number ?? ('DOC-' . $doc->id) }}
                                </a>
                            </td>
                            <td class="fw-bold text-dark text-truncate" style="max-width: 200px;" title="{{ $doc->title }}">{{ $doc->title }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $doc->category ?? 'General' }}</span></td>
                            <td class="text-muted small text-truncate" style="max-width: 140px;">{{ $doc->originOffice?->name ?? 'N/A' }}</td>
                            <td class="text-muted small text-truncate" style="max-width: 140px;">{{ $doc->currentOffice?->name ?? 'In Transit' }}</td>
                            <td><span class="badge bg-light text-secondary border">{{ $doc->status }}</span></td>
                            <td class="text-muted small font-monospace">{{ $doc->created_at ? $doc->created_at->format('M d, Y') : 'N/A' }}</td>
                            <td class="text-muted small font-monospace">{{ $doc->completed_at ? $doc->completed_at->format('M d, Y') : ($doc->received_at && $doc->status === 'Completed' ? $doc->received_at->format('M d, Y') : 'N/A') }}</td>
                            <td class="font-monospace text-secondary">{{ $doc->processing_time_hours }}</td>
                            <td class="small text-truncate" style="max-width: 130px;">{{ $doc->sla ?? 'Standard' }}</td>
                            <td>
                                <span class="badge {{ $doc->sla_result_badge ?? 'bg-secondary' }}">
                                    {{ $doc->sla_result ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('documents.passport', $doc->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                                    Passport ➔
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No document records matching the selected parameters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
            <span class="text-muted small">
                Showing {{ $reportDocuments->firstItem() ?? 0 }} to {{ $reportDocuments->lastItem() ?? 0 }} of {{ $reportDocuments->total() }} documents
            </span>
            <div>
                {{ $reportDocuments->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <!-- Document Activity Calendar Widget & Statistics (Part 16) -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="glass-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                        <i class="bi bi-calendar3 me-2 text-primary"></i>Historical Document Activity Calendar
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

                <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top small" style="border-color: var(--glass-border) !important; font-size: 11px; color: var(--text-dim);">
                    <span class="d-inline-flex align-items-center gap-1">
                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#2563eb;"></span> Upload / Route Activity
                    </span>
                    <span class="d-inline-flex align-items-center gap-1">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:2px; border:1.5px solid #2563eb;"></span> Today's Date
                    </span>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="glass-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0" style="color: var(--accent-navy) !important;">
                        <i class="bi bi-calendar-check me-2 text-primary"></i>Activity for <span id="selectedDateTitle" class="text-primary">{{ now()->format('M d, Y') }}</span>
                    </h6>
                    <span class="badge bg-light text-secondary border px-2 py-1" id="selectedDateStatusBadge" style="font-size: 11px;">Selected Date</span>
                </div>

                <div class="d-flex flex-column gap-2" style="min-height: 220px; justify-content: space-between;">
                    <div class="stat-metric-row" style="background: rgba(37, 99, 235, 0.06); border: 1px solid rgba(37, 99, 235, 0.15);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-cloud-arrow-up text-primary fs-5"></i>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-main);">Total Uploaded Documents</span>
                        </div>
                        <span class="badge bg-primary fs-6 px-3 py-1 font-monospace" id="statUploadedDocs">{{ $selectedDateStats['uploaded'] ?? 0 }}</span>
                    </div>

                    <div class="stat-metric-row" style="background: rgba(37, 99, 235, 0.06); border: 1px solid rgba(37, 99, 235, 0.15);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-arrow-left-right text-primary fs-5"></i>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-main);">Total Routed Documents</span>
                        </div>
                        <span class="badge bg-primary fs-6 px-3 py-1 font-monospace text-white" id="statRoutedDocs">{{ $selectedDateStats['routed'] ?? 0 }}</span>
                    </div>

                    <div class="stat-metric-row" style="background: rgba(16, 185, 129, 0.06); border: 1px solid rgba(16, 185, 129, 0.15);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check2-circle text-success fs-5"></i>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-main);">Total Approved Documents</span>
                        </div>
                        <span class="badge bg-success fs-6 px-3 py-1 font-monospace" id="statApprovedDocs">{{ $selectedDateStats['approved'] ?? 0 }}</span>
                    </div>

                    <div class="stat-metric-row" style="background: rgba(16, 185, 129, 0.06); border: 1px solid rgba(16, 185, 129, 0.15);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-patch-check text-success fs-5"></i>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-main);">Total Completed Documents</span>
                        </div>
                        <span class="badge bg-success fs-6 px-3 py-1 font-monospace text-white" id="statCompletedDocs">{{ $selectedDateStats['completed'] ?? 0 }}</span>
                    </div>

                    <div class="stat-metric-row" style="background: rgba(245, 158, 11, 0.06); border: 1px solid rgba(245, 158, 11, 0.15);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-hourglass-split text-warning fs-5"></i>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-main);">Total Pending Documents</span>
                        </div>
                        <span class="badge bg-warning text-dark fs-6 px-3 py-1 font-monospace" id="statPendingDocs">{{ $selectedDateStats['pending'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
window.onload = function () {
    Chart.defaults.color = '#475569';
    Chart.defaults.font.family = "'Inter', sans-serif";

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(0,0,0,0.05)' }, beginAtZero: true },
            x: { grid: { display: false } }
        }
    };

    // Helper to safely instantiate a Chart ensuring only one instance exists per canvas
    function createSafeChart(elementId, config) {
        const el = document.getElementById(elementId);
        if (!el) return null;
        const existing = Chart.getChart(el);
        if (existing) {
            existing.destroy();
        }
        return new Chart(el, config);
    }

    // 1. Flow Chart (Weekly Volume Flow with explicit Monday–Sunday labels)
    createSafeChart('flowChart', {
        type: 'line',
        data: {
            labels: @json($flowLabels),
            datasets: [{
                label: 'Documents',
                data: @json($flowData),
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 4
            }]
        },
        options: commonOptions
    });

    // 2. Volume Breakdown Chart
    createSafeChart('volumeBreakdownChart', {
        type: 'bar',
        data: {
            labels: @json(array_keys($volumeBreakdown)),
            datasets: [{
                data: @json(array_values($volumeBreakdown)),
                backgroundColor: ['#3b82f6', '#8b5cf6', '#0284c7', '#10b981', '#059669', '#f59e0b'],
                borderRadius: 6
            }]
        },
        options: commonOptions
    });

    // 3. Processing Time Chart (By Office)
    createSafeChart('procTimeChart', {
        type: 'bar',
        data: {
            labels: @json($procTimeOfficeNames),
            datasets: [{
                label: 'Avg Hours',
                data: @json($procTimeOfficeHours),
                backgroundColor: '#6366f1',
                borderRadius: 6
            }]
        },
        options: commonOptions
    });

    // 4. SLA Trend Chart
    createSafeChart('slaTrendChart', {
        type: 'line',
        data: {
            labels: @json($slaTrendLabels),
            datasets: [{
                label: 'SLA Compliance %',
                data: @json($slaTrendRates),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                fill: true,
                tension: 0.35,
                pointRadius: 4
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                y: { grid: { color: 'rgba(0,0,0,0.05)' }, beginAtZero: true, max: 100 }
            }
        }
    });

    // 5. Active Document Aging Analysis (with clickable drill-down!)
    const agingCanvas = document.getElementById('agingChart');
    if (agingCanvas) {
        const agingMap = {
            '0–1 hour': '0_1h',
            '1–4 hours': '1_4h',
            '4–8 hours': '4_8h',
            '8–24 hours': '8_24h',
            '1–3 days': '1_3d',
            '3+ days': '3plus'
        };

        const existingAging = Chart.getChart(agingCanvas);
        if (existingAging) existingAging.destroy();

        const agingChartInstance = new Chart(agingCanvas, {
            type: 'bar',
            data: {
                labels: @json(array_keys($reportAging)),
                datasets: [{
                    data: @json(array_values($reportAging)),
                    backgroundColor: ['#10b981', '#06b6d4', '#3b82f6', '#f59e0b', '#f97316', '#ef4444'],
                    borderRadius: 6
                }]
            },
            options: {
                ...commonOptions,
                onClick: (evt, activeElements) => {
                    if (activeElements && activeElements.length > 0) {
                        const index = activeElements[0].index;
                        const label = agingChartInstance.data.labels[index];
                        const param = agingMap[label] || 'all';
                        window.location.href = "{{ route('documents.index') }}?aging=" + encodeURIComponent(param);
                    }
                }
            }
        });
    }

    // 6. Document Lifecycle Funnel
    createSafeChart('lifecycleChart', {
        type: 'bar',
        data: {
            labels: @json(array_keys($reportLifecycle['stages'])),
            datasets: [{
                data: @json(array_map(fn($s) => $s['count'], $reportLifecycle['stages'])),
                backgroundColor: ['#3b82f6', '#8b5cf6', '#0284c7', '#0ea5e9', '#f59e0b', '#10b981', '#059669', '#64748b'],
                borderRadius: 6
            }]
        },
        options: commonOptions
    });

    // 7. QR Trend Chart
    createSafeChart('qrTrendChart', {
        type: 'line',
        data: {
            labels: @json($qrTrendDays),
            datasets: [
                {
                    label: 'Attempts',
                    data: @json($qrTrendAttempts),
                    borderColor: '#f59e0b',
                    tension: 0.35,
                    pointRadius: 4
                },
                {
                    label: 'Successful',
                    data: @json($qrTrendSuccess),
                    borderColor: '#10b981',
                    tension: 0.35,
                    pointRadius: 4
                },
                {
                    label: 'Accessed',
                    data: @json($qrTrendAccess),
                    borderColor: '#3b82f6',
                    tension: 0.35,
                    pointRadius: 4
                }
            ]
        },
        options: {
            ...commonOptions,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { boxWidth: 10, padding: 8, font: { size: 10.5, weight: '600' } }
                }
            }
        }
    });

    // 8. Document Activity Calendar Widget Logic
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
            const stats = data.activity[dateKey] || { uploaded: 0, routed: 0, approved: 0, completed: 0, pending: 0, has_activity: false };

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
            if (stats.has_activity) {
                const dot = document.createElement('span');
                dot.className = 'cal-dot';
                cell.appendChild(dot);
            }

            cell.addEventListener('click', function() {
                selectCalendarDate(dateKey, stats);
            });

            gridBody.appendChild(cell);
        }

        // Select initial date
        if (data.activity[activeDateKey]) {
            selectCalendarDate(activeDateKey, data.activity[activeDateKey]);
        }
    }

    function selectCalendarDate(dateKey, stats) {
        activeDateKey = dateKey;

        document.querySelectorAll('.cal-day-cell').forEach(el => {
            el.classList.toggle('cal-active', el.dataset.date === dateKey);
        });

        try {
            const parts = dateKey.split('-');
            const dObj = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
            const formatted = dObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const titleEl = document.getElementById('selectedDateTitle');
            if (titleEl) titleEl.textContent = formatted;
        } catch (e) {
            const titleEl = document.getElementById('selectedDateTitle');
            if (titleEl) titleEl.textContent = dateKey;
        }

        document.getElementById('statUploadedDocs').textContent = stats.uploaded ?? 0;
        document.getElementById('statRoutedDocs').textContent = stats.routed ?? 0;
        document.getElementById('statApprovedDocs').textContent = stats.approved ?? 0;
        document.getElementById('statCompletedDocs').textContent = stats.completed ?? 0;
        document.getElementById('statPendingDocs').textContent = stats.pending ?? 0;

        const badge = document.getElementById('selectedDateStatusBadge');
        if (badge) {
            const total = (stats.uploaded || 0) + (stats.routed || 0) + (stats.approved || 0) + (stats.completed || 0) + (stats.pending || 0);
            if (total > 0) {
                badge.textContent = `${total} Document Events`;
                badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1';
            } else {
                badge.textContent = 'No Activity';
                badge.className = 'badge bg-light text-secondary border px-2 py-1';
            }
        }
    }

    function changeMonth(delta) {
        let newMonth = currentCalMonth + delta;
        let newYear = currentCalYear;
        if (newMonth < 1) {
            newMonth = 12;
            newYear--;
        } else if (newMonth > 12) {
            newMonth = 1;
            newYear++;
        }

        fetch(`{{ route('api.dashboard.calendarActivity') }}?year=${newYear}&month=${newMonth}`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    renderCalendar(res.data);
                }
            })
            .catch(err => console.error('Failed to change calendar month:', err));
    }

    document.getElementById('calPrevMonth')?.addEventListener('click', () => changeMonth(-1));
    document.getElementById('calNextMonth')?.addEventListener('click', () => changeMonth(1));

    // Initial render
    renderCalendar(currentCalData);
};
</script>
@endsection