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
            <div class="chart-card">
                <h5><i class="bi bi-shield-lock-fill text-warning"></i> System Security & Access</h5>
                <div class="canvas-container">
                    <canvas id="securityChart"></canvas>
                </div>
            </div>
        </div>
    @endif

    <!-- Analytics & Activity Charts Section -->
    <div class="charts-main-grid">
        <!-- 1. Document Activity Calendar Widget -->
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-calendar3 text-primary"></i> Document Activity Calendar</h5>
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
                <!-- Dynamically populated via JS from actual system records -->
            </div>

            <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top small" style="border-color: var(--panel-border) !important; font-size: 11px; color: var(--text-dim);">
                <span class="d-inline-flex align-items-center gap-1">
                    <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#2563eb;"></span> Upload / Route Activity
                </span>
                <span class="d-inline-flex align-items-center gap-1">
                    <span style="display:inline-block; width:10px; height:10px; border-radius:2px; border:1.5px solid #2563eb;"></span> Today's Date
                </span>
            </div>
        </div>

        <!-- 2. Selected Date Activity Statistics -->
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-calendar-check text-info"></i> Activity for <span id="selectedDateTitle" class="text-primary">{{ now()->format('M d, Y') }}</span></h5>
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

                <div class="stat-metric-row" style="background: rgba(168, 85, 247, 0.06); border: 1px solid rgba(168, 85, 247, 0.15);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-left-right fs-5" style="color: #a855f7;"></i>
                        <span style="font-size: 13px; font-weight: 600; color: var(--text-main);">Total Routed Documents</span>
                    </div>
                    <span class="badge fs-6 px-3 py-1 font-monospace text-white" style="background: #a855f7;" id="statRoutedDocs">{{ $selectedDateStats['routed'] ?? 0 }}</span>
                </div>

                <div class="stat-metric-row" style="background: rgba(16, 185, 129, 0.06); border: 1px solid rgba(16, 185, 129, 0.15);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-circle text-success fs-5"></i>
                        <span style="font-size: 13px; font-weight: 600; color: var(--text-main);">Total Approved Documents</span>
                    </div>
                    <span class="badge bg-success fs-6 px-3 py-1 font-monospace" id="statApprovedDocs">{{ $selectedDateStats['approved'] ?? 0 }}</span>
                </div>

                <div class="stat-metric-row" style="background: rgba(13, 148, 136, 0.06); border: 1px solid rgba(13, 148, 136, 0.15);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-patch-check fs-5" style="color: #0d9488;"></i>
                        <span style="font-size: 13px; font-weight: 600; color: var(--text-main);">Total Completed Documents</span>
                    </div>
                    <span class="badge fs-6 px-3 py-1 font-monospace text-white" style="background: #0d9488;" id="statCompletedDocs">{{ $selectedDateStats['completed'] ?? 0 }}</span>
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

        <!-- 3. Active Office Workloads -->
        <div class="chart-card">
            <h5><i class="bi bi-building"></i> Active Office Workloads</h5>
            <div class="canvas-container">
                <canvas id="officeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Activity Logs / My Recent Uploads (Placed below analytics/charts section) -->
    <div class="chart-card mb-4 mt-2">
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

    // 1. Workload Chart (Offices)
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

    // 2. QR Scan Trend Chart & Security Chart (Admin Only)
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

    const secCanvas = document.getElementById('securityChart');
    if (secCanvas) {
        new Chart(secCanvas, {
            type: 'bar',
            data: {
                labels: ['Document File Views', 'Approval Events', 'Routing & Transit Movements'],
                datasets: [{
                    data: [
                        @json($documentViews ?? 0),
                        @json($approvalActivities ?? 0),
                        @json($routingActivities ?? 0)
                    ],
                    backgroundColor: [
                        '#3b82f6',
                        '#10b981',
                        '#8b5cf6'
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                ...baseOptions,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.parsed.y + ' recorded';
                            }
                        }
                    }
                }
            }
        });
    }
    @endif

    // --- Calendar Analytics Widget Logic ---
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

        // If activeDateKey is in this month, update details
        if (data.activity[activeDateKey]) {
            selectCalendarDate(activeDateKey, data.activity[activeDateKey]);
        } else {
            const firstDateKey = `${data.year}-${String(data.month).padStart(2, '0')}-01`;
            if (data.activity[firstDateKey]) {
                selectCalendarDate(firstDateKey, data.activity[firstDateKey]);
            }
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
</script>
@endsection