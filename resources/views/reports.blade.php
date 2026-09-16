@extends('layouts.app')
@section('title', 'Reports & Analytics')

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
</style>
@endsection

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title mb-0">Reports & Analytics</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="btn btn-export rounded-pill px-3" style="height: 40px !important; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem;">
                <i class="bi bi-filetype-csv me-2"></i>Export CSV
            </a>
            <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="btn btn-success text-white rounded-pill px-3" style="height: 40px !important; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; border: none;">
                <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
            </a>
            <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" target="_blank" class="btn btn-danger text-white rounded-pill px-3" style="height: 40px !important; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; border: none; background: #dc2626 !important;">
                <i class="bi bi-file-pdf me-2"></i>Print PDF
            </a>
        </div>
    </div>

    <!-- Reports & Analytics Filter Panel -->
    <div class="glass-card mb-4">
        <h5 class="fw-bold mb-3" style="color: var(--accent-navy) !important;"><i class="bi bi-funnel me-2"></i>Report Parameter Panel</h5>
        <form method="GET" action="{{ route('reports.index') }}" class="row g-3">
            <div class="col-md-3 text-start">
                <label class="form-label text-uppercase small" style="font-weight: 700;">Department</label>
                <select name="department_id" class="form-select">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small" style="font-weight: 700;">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach(['Pending', 'Received', 'Under Review', 'Approved', 'Rejected', 'Completed', 'Cancelled', 'Archived'] as $st)
                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small" style="font-weight: 700;">QR Status</label>
                <select name="qr_status" class="form-select">
                    <option value="">All QR Statuses</option>
                    @foreach(['Not Scanned', 'Scanned', 'Verified', 'Accessed'] as $qrs)
                        <option value="{{ $qrs }}" {{ request('qr_status') === $qrs ? 'selected' : '' }}>{{ $qrs }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 text-start">
                <label class="form-label text-uppercase small" style="font-weight: 700;">Uploader User</label>
                <select name="user_id" class="form-select">
                    <option value="">All Users</option>
                    @foreach($users as $us)
                        <option value="{{ $us->id }}" {{ request('user_id') == $us->id ? 'selected' : '' }}>{{ $us->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small" style="font-weight: 700;">From Date</label>
                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
            </div>

            <div class="col-md-2 text-start">
                <label class="form-label text-uppercase small" style="font-weight: 700;">To Date</label>
                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
            </div>

            <div class="col-12 d-flex gap-2 pt-3">
                <button type="submit" class="btn btn-primary px-4 fw-bold" style="border-radius: 8px;">
                    <i class="bi bi-search me-2"></i>Apply Filters
                </button>
                <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary px-4 fw-bold" style="border-radius: 8px;">
                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                </a>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="glass-card text-center">
                <div class="stat-label">Total Documents</div>
                <div class="stat-value">{{ number_format($summary['total_processed']) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card text-center">
                <div class="stat-label">Avg Processing Time</div>
                <div class="stat-value" style="color: var(--accent-cyan) !important;">{{ $summary['avg_time'] }}h</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card text-center">
                <div class="stat-label">Most Active Office</div>
                <div class="stat-value" style="color: var(--accent-purple) !important;">{{ $summary['most_active'] }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card text-center">
                <div class="stat-label">System QR Scans</div>
                <div class="stat-value">{{ number_format($summary['qr_scans']) }}</div>
            </div>
        </div>
    </div>

    <div class="glass-card mb-4">
        <h5 class="fw-bold mb-3" style="color: var(--accent-navy) !important;"><i class="bi bi-graph-up me-2 text-primary"></i>Weekly Volume Flow</h5>
        <div style="height:220px;"><canvas id="flowChart"></canvas></div>
    </div>

    <!-- ARTA SLA & Compliance Audit Table -->
    <div class="glass-card mb-4 text-start">
        <h5 class="fw-bold mb-3" style="color: var(--accent-navy) !important;">
            <i class="bi bi-shield-check me-2 text-success"></i>Office SLA & Compliance Audit (ARTA Compliance)
        </h5>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead style="background: rgba(30, 58, 138, 0.05);">
                    <tr style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700;" class="text-secondary">
                        <th>Office Name</th>
                        <th class="text-center">Total Steps</th>
                        <th class="text-center">Completed Steps</th>
                        <th class="text-center">Delays / Breaches</th>
                        <th class="text-center">Avg Processing Time</th>
                        <th class="text-center">SLA Compliance Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($officeSlaStats as $stat)
                        <tr style="font-size: 0.9rem;">
                            <td class="fw-bold text-dark">{{ $stat->office_name }}</td>
                            <td class="text-center text-secondary">{{ number_format($stat->total_steps) }}</td>
                            <td class="text-center text-secondary">{{ number_format($stat->completed_steps) }}</td>
                            <td class="text-center">
                                @if($stat->delayed_steps > 0)
                                    <span class="badge bg-danger">{{ number_format($stat->delayed_steps) }} delay(s)</span>
                                @else
                                    <span class="badge bg-success">No Delays</span>
                                @endif
                            </td>
                            <td class="text-center fw-semibold" style="color: var(--accent-purple);">
                                {{ $stat->avg_processing_hours ? $stat->avg_processing_hours . ' hrs' : 'N/A' }}
                            </td>
                            <td class="text-center">
                                @php
                                    $rate = $stat->compliance_rate;
                                    $badgeColor = $rate >= 90 ? 'bg-success' : ($rate >= 75 ? 'bg-warning text-dark' : 'bg-danger');
                                @endphp
                                <span class="badge {{ $badgeColor }} px-2 py-1" style="font-size: 0.85rem; font-weight: 600;">
                                    {{ number_format($rate, 1) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted text-center py-4">No routing logs available for SLA auditing.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="glass-card h-100">
                <h6 class="fw-bold mb-3" style="color: var(--accent-navy) !important;">Documents per Office</h6>
                <div style="height:250px;"><canvas id="reportBar"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="glass-card h-100">
                <h6 class="fw-bold mb-3" style="color: var(--accent-navy) !important;">Daily Scan Activity</h6>
                <div style="height:250px;"><canvas id="reportLine"></canvas></div>
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

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(0,0,0,0.05)' }, beginAtZero: true },
            x: { grid: { display: false } }
        }
    };

    // 1. Flow Chart
    new Chart(document.getElementById('flowChart'), {
        type: 'line',
        data: {
            labels: @json($flowLabels),
            datasets: [{
                data: @json($flowData),
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.08)',
                fill: true,
                tension: 0.4
            }]
        },
        options: commonOptions
    });

    // 2. Bar Chart
    new Chart(document.getElementById('reportBar'), {
        type: 'bar',
        data: {
            labels: @json($officeNames),
            datasets: [{
                data: @json($processingTimes),
                backgroundColor: '#1E3A8A',
                borderRadius: 8
            }]
        },
        options: commonOptions
    });

    // 3. Line Chart
    new Chart(document.getElementById('reportLine'), {
        type: 'line',
        data: {
            labels: @json($days),
            datasets: [{
                data: @json($scanCounts),
                borderColor: '#4F46E5',
                tension: 0.4,
                pointRadius: 5
            }]
        },
        options: commonOptions
    });
};
</script>
@endsection