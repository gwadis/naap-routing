@extends('layouts.app')

@section('title')
    {{ session('user_role') === 'ADMIN' ? 'System Analytics - Admin' : 'My Dashboard' }}
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
    
    /* Charts main grid */
    .charts-main-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); 
        gap: 20px; 
        margin-bottom: 24px; 
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
        max-height: 260px; 
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
        color: var(--accent-cyan);
        min-width: 75px;
        flex-shrink: 0;
    }

    .activity-details {
        font-size: 12.5px;
        color: var(--text-main);
    }
</style>

<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title mb-1">{{ $isAdmin ? 'System Analytics Dashboard' : 'User Execution Dashboard' }}</h1>
            <p class="text-secondary small mb-0" style="color: var(--text-dim) !important;">
                Welcome back! Workflow overview for <strong>{{ $departmentName }}</strong> as of {{ now()->format('F d, Y') }}.
            </p>
        </div>

        @if($urgentDocs > 0)
            <div class="badge bg-danger p-2 shadow-sm text-white" style="font-size: 11.5px; font-weight: 600;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $urgentDocs }} Urgent Tasks
            </div>
        @endif
    </div>

    <!-- KPI Summary Grid -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div>
                <div class="label">Total Scope Documents</div>
                <h3>{{ $totalDocs }}</h3>
            </div>
            <div class="kpi-trend trend-up">
                <i class="bi bi-arrow-up-short"></i> +{{ $docsToday }} uploaded today
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="label">Pending Review</div>
                <h3 style="color: #D97706 !important;">{{ $pendingDocs }}</h3>
            </div>
            <div class="kpi-trend trend-neutral">
                <i class="bi bi-clock-history"></i> Awaiting signatures
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="label">Completed / Archived</div>
                <h3 style="color: #059669 !important;">{{ $completedDocs }}</h3>
            </div>
            <div class="kpi-trend trend-up">
                <i class="bi bi-check-all"></i> {{ $archivedDocs }} moved to archive
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="label">Overdue Items</div>
                <h3 style="color: #DC2626 !important;">{{ $overdueDocs }}</h3>
            </div>
            <div class="kpi-trend trend-down">
                <i class="bi bi-exclamation-circle"></i> Action required
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="label">Avg Processing SLA</div>
                <h3 style="color: #4F46E5 !important;">{{ $avgProcessingHours }}h</h3>
            </div>
            <div class="kpi-trend trend-neutral">
                <i class="bi bi-lightning-charge"></i> Target: < 24 hours
            </div>
        </div>
    </div>

    <!-- Charts & Tables Grid -->
    <div class="charts-main-grid">
        <div class="chart-card">
            <h5><i class="bi bi-graph-up"></i> 7-Day Upload Volume Flow</h5>
            <div class="canvas-container">
                <canvas id="flowChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h5><i class="bi bi-bar-chart-steps"></i> Monthly Upload vs Routing Activity</h5>
            <div class="canvas-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h5><i class="bi bi-building"></i> Active Office Workloads</h5>
            <div class="canvas-container">
                <canvas id="officeChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h5><i class="bi bi-clock-history"></i> {{ $isAdmin ? 'Recent Activity Logs' : 'My Recent Uploads' }}</h5>
            <div class="activity-feed">
                @if($isAdmin)
                    @forelse($recentActivities ?? [] as $log)
                        <div class="activity-item">
                            <div class="activity-time">{{ $log->created_at ? $log->created_at->diffForHumans() : 'N/A' }}</div>
                            <div class="activity-details">
                                <strong>{{ $log->user }}</strong> — {{ $log->action }}
                                <br><small class="text-secondary" style="font-size: 11px; opacity:0.8;">Browser: {{ $log->browser ?? 'Unknown' }} | Device: {{ $log->device ?? 'Desktop' }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary small text-center mt-5">No activity logs recorded.</p>
                    @endforelse
                @else
                    @forelse($recentUploads ?? [] as $doc)
                        @php
                            $durationText = '';
                            if ($doc->status === 'Completed' && $doc->received_at) {
                                $durationText = 'Completed in ' . $doc->created_at->diffForHumans($doc->received_at, true);
                            } else {
                                $activeStep = $doc->routings->where('status', 'Pending')->first();
                                if ($activeStep) {
                                    $start = $activeStep->pending_at ?? $activeStep->created_at;
                                    $durationText = 'Held for ' . $start->diffForHumans(null, true);
                                }
                            }

                            $status = strtolower($doc->status);
                            $badgeClass = 'bg-warning';
                            if ($status === 'completed' || $status === 'approved') {
                                $badgeClass = 'bg-success';
                            } elseif (in_array($status, ['in_transit', 'in transit', 'under review', 'received', 'on process', 'for approval'])) {
                                $badgeClass = 'bg-info';
                            } elseif (in_array($status, ['rejected', 'cancelled', 'held'])) {
                                $badgeClass = 'bg-danger';
                            }
                        @endphp
                        <div class="activity-item">
                            <div class="activity-time">{{ $doc->created_at ? $doc->created_at->diffForHumans() : 'N/A' }}</div>
                            <div class="activity-details">
                                <strong>{{ $doc->title }}</strong>
                                <br><small class="text-secondary" style="font-size: 11px; opacity:0.8;">Status: <span class="badge {{ $badgeClass }}" style="font-size:10px; padding:2px 6px;">{{ $doc->status }}</span> | ID: {{ $doc->tracking_number ?? $doc->qr_id }} @if($durationText) | <span class="text-warning">{{ $durationText }}</span> @endif</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary small text-center mt-5">No uploads recorded yet.</p>
                    @endforelse
                @endif
            </div>
        </div>
    </div>

    @if($isAdmin)
        <h4 class="fw-bold mb-3 mt-4 text-start"><i class="bi bi-qr-code-scan text-primary me-2"></i>QR Code Scan & OTP Activity Monitoring</h4>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div>
                    <div class="label">QR Scans Today</div>
                    <h3>{{ $qrScansToday }}</h3>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="bi bi-calendar-check"></i> Scans today
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="label">Weekly QR Scans</div>
                    <h3>{{ $qrScansWeek }}</h3>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="bi bi-graph-up-arrow"></i> Past 7 days
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="label">Unique Users Scanning</div>
                    <h3>{{ $uniqueUsersScanning }}</h3>
                </div>
                <div class="kpi-trend trend-neutral">
                    <i class="bi bi-people"></i> Distinct accounts
                </div>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="label">OTP Verifications</div>
                    <h3>{{ $otpVerifications }}</h3>
                </div>
                <div class="kpi-trend trend-up">
                    <i class="bi bi-shield-check-fill"></i> PIN verifications
                </div>
            </div>
        </div>

        <div class="charts-main-grid mb-4">
            <div class="chart-card">
                <h5><i class="bi bi-clock-history"></i> 7-Day QR Code Scanning Trend</h5>
                <div class="canvas-container">
                    <canvas id="qrTrendChart"></canvas>
                </div>
            </div>
            <div class="chart-card text-start">
                <h5><i class="bi bi-shield-lock-fill text-warning"></i> System Security & Access Counters</h5>
                <div class="p-3 bg-light rounded border mb-2 small" style="line-height: 1.8; color: var(--text-main); background: rgba(0,0,0,0.02) !important; border-color: var(--panel-border) !important;">
                    <div class="mb-2"><span class="text-secondary">Document File Views:</span> <strong class="text-dark">{{ $documentViews }} views</strong></div>
                    <div class="mb-2"><span class="text-secondary">Active OTP/PIN Verifications:</span> <strong class="text-dark">{{ $otpVerifications }} verifications</strong></div>
                    <div class="mb-2"><span class="text-secondary">Approval Events (Signatures bound):</span> <strong class="text-dark">{{ $approvalActivities }} approvals</strong></div>
                    <div><span class="text-secondary">Routing & Transit Movements:</span> <strong class="text-dark">{{ $routingActivities }} transits</strong></div>
                </div>
            </div>
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

    // 1. Flow Chart (7 Days)
    new Chart(document.getElementById('flowChart'), {
        type: 'line',
        data: {
            labels: @json($days ?? []),
            datasets: [{
                data: @json($flowData ?? []),
                borderColor: '#22d3ee',
                backgroundColor: 'rgba(34, 211, 238, 0.08)',
                fill: true,
                tension: 0.4
            }]
        },
        options: baseOptions
    });

    // 2. Monthly Upload vs Routing
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                {
                    label: 'Uploaded Documents',
                    data: @json($monthlyUploads ?? []),
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                },
                {
                    label: 'Routed Hops',
                    data: @json($monthlyRouting ?? []),
                    backgroundColor: '#a855f7',
                    borderRadius: 4
                }
            ]
        },
        options: {
            ...baseOptions,
            plugins: { legend: { display: true, position: 'top' } }
        }
    });

    // 3. Workload Chart (Offices)
    new Chart(document.getElementById('officeChart'), {
        type: 'bar',
        data: {
            labels: @json($offices->pluck('name') ?? []),
            datasets: [{
                data: @json($offices->pluck('documents_count') ?? []),
                backgroundColor: '#10b981',
                borderRadius: 6
            }]
        },
        options: baseOptions
    });

    // 4. QR Scan Trend Chart (Admin Only)
    @if($isAdmin)
    const qrCanvas = document.getElementById('qrTrendChart');
    if (qrCanvas) {
        new Chart(qrCanvas, {
            type: 'line',
            data: {
                labels: @json($qrTrendDays ?? []),
                datasets: [{
                    data: @json($qrTrendData ?? []),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.08)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: baseOptions
        });
    }
    @endif
</script>
@endsection