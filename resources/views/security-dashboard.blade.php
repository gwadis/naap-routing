@extends('layouts.app')

@section('title', 'Security Dashboard')

@section('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .stat-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        font-size: 1.5rem;
    }
    .bg-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .bg-red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .bg-yellow { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .bg-green { background: rgba(16, 185, 129, 0.1); color: #10b981; }

    .dashboard-panel {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .list-item-custom {
        border-bottom: 1px solid var(--panel-border);
        padding: 12px 0;
    }
    .list-item-custom:last-child {
        border-bottom: none;
    }
</style>
@endsection

@section('content')
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0" style="color: var(--accent-navy);"><i class="bi bi-shield-fill-check me-2" style="color: #10b981;"></i>Security Dashboard</h3>
        <span class="badge bg-secondary p-2"><i class="bi bi-shield-fill-check me-1"></i> System Integrity Verified</span>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-blue"><i class="bi bi-person-check-fill"></i></div>
                <div class="text-start">
                    <span class="text-muted small text-uppercase fw-bold">Today's Logins</span>
                    <h4 class="fw-bold mb-0 mt-1 text-dark">{{ $todayLogins }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-red"><i class="bi bi-person-x-fill"></i></div>
                <div class="text-start">
                    <span class="text-muted small text-uppercase fw-bold">Failed Logins</span>
                    <h4 class="fw-bold mb-0 mt-1 text-dark">{{ $todayFailed }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-yellow"><i class="bi bi-lock-fill"></i></div>
                <div class="text-start">
                    <span class="text-muted small text-uppercase fw-bold">Locked Accounts</span>
                    <h4 class="fw-bold mb-0 mt-1 text-dark">{{ $lockedAccounts }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-green"><i class="bi bi-people-fill"></i></div>
                <div class="text-start">
                    <span class="text-muted small text-uppercase fw-bold">Online Users</span>
                    <h4 class="fw-bold mb-0 mt-1 text-dark">{{ $onlineUsers }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Panel -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="dashboard-panel">
                <h5 class="fw-bold mb-4 text-start"><i class="bi bi-graph-up me-2 text-primary"></i>Login Trends (Last 7 Days)</h5>
                <div style="height: 300px; position: relative;">
                    <canvas id="loginTrendsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dashboard-panel" style="height: calc(100% - 24px); overflow-y: auto;">
                <h5 class="fw-bold mb-4 text-start"><i class="bi bi-bell-fill me-2 text-danger"></i>Recent Security Alerts</h5>
                <div class="text-start">
                    @forelse($recentAlerts as $alert)
                        <div class="list-item-custom">
                            <small class="text-danger fw-bold d-block"><i class="bi bi-exclamation-triangle-fill me-1"></i>Security Event</small>
                            <span class="small d-block text-dark">{{ $alert['message'] }}</span>
                            <small class="text-muted" style="font-size:0.75rem;">{{ $alert['time'] }}</small>
                        </div>
                    @empty
                        <div class="text-muted text-center py-4">No recent security alerts recorded.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Lists Panel -->
    <div class="row">
        <div class="col-lg-6">
            <div class="dashboard-panel">
                <h5 class="fw-bold mb-4 text-start"><i class="bi bi-list-check me-2 text-success"></i>Recent Successful Logins</h5>
                <div class="table-responsive text-start">
                    <table class="table table-borderless align-middle mb-0" style="font-size: 0.85rem;">
                        <thead>
                            <tr class="border-bottom text-muted">
                                <th>User</th>
                                <th>Device</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogins as $login)
                                <tr class="border-bottom">
                                    <td class="fw-bold text-dark">{{ $login->email }}</td>
                                    <td>{{ $login->device ?? 'N/A' }}</td>
                                    <td class="text-muted">{{ $login->login_time }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted text-center py-3">No login logs.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="dashboard-panel">
                <h5 class="fw-bold mb-4 text-start"><i class="bi bi-shield-exclamation me-2 text-warning"></i>Suspicious Activities & Lockouts</h5>
                <div class="table-responsive text-start">
                    <table class="table table-borderless align-middle mb-0" style="font-size: 0.85rem;">
                        <thead>
                            <tr class="border-bottom text-muted">
                                <th>Actor</th>
                                <th>Alert Action</th>
                                <th>IP Address</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($suspiciousActivities as $activity)
                                <tr class="border-bottom">
                                    <td class="fw-bold text-dark">{{ $activity->user }}</td>
                                    <td class="text-danger fw-bold">{{ $activity->action }}</td>
                                    <td><code>{{ $activity->ip_address }}</code></td>
                                    <td class="text-muted">{{ $activity->created_at }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-muted text-center py-3">No suspicious activities logged.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
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
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35
                    },
                    {
                        label: 'Failed Attempts',
                        data: {!! json_encode($chartFailed) !!},
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    });
</script>
@endsection
