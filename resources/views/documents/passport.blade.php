@extends('layouts.app')

@section('title', 'Document Passport - ' . ($document->tracking_number ?? 'NAAP'))

@section('head')
<style>
    .passport-container {
        max-width: 1300px;
        margin: 0 auto;
    }
    .passport-badge-header {
        background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        border-radius: var(--radius-xl);
        padding: 24px 28px;
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
    }
    .passport-meta-box {
        background: #F8FAFC;
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        padding: 16px;
    }
    .passport-meta-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-dim);
        margin-bottom: 4px;
    }
    .passport-meta-val {
        font-size: 13.5px;
        font-weight: 600;
        color: var(--text-main);
    }
    .journey-timeline {
        position: relative;
        padding-left: 32px;
    }
    .journey-timeline::before {
        content: '';
        position: absolute;
        left: 11px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: var(--panel-border);
    }
    .journey-item {
        position: relative;
        margin-bottom: 24px;
    }
    .journey-item:last-child {
        margin-bottom: 0;
    }
    .journey-dot {
        position: absolute;
        left: -32px;
        top: 2px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #FFFFFF;
        border: 2px solid var(--accent-cyan);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: var(--accent-cyan);
        z-index: 2;
    }
    .journey-card {
        background: #FFFFFF;
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-md);
        padding: 14px 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .completion-certificate {
        background: #FFFFFF;
        border: 2px solid #0F172A;
        border-radius: var(--radius-lg);
        padding: 28px;
        position: relative;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .completion-watermark {
        position: absolute;
        right: 24px;
        top: 24px;
        opacity: 0.08;
        font-size: 8rem;
        color: #0F172A;
        pointer-events: none;
    }

    @media print {
        body {
            background: #FFFFFF !important;
            color: #000000 !important;
        }
        .sidebar, .navbar, .top-header, .btn-print, .no-print {
            display: none !important;
        }
        .passport-container {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .passport-badge-header {
            background: #F8FAFC !important;
            color: #000000 !important;
            border: 1px solid #000000 !important;
        }
        .passport-badge-header h2, .passport-badge-header span, .passport-badge-header div {
            color: #000000 !important;
        }
        .card, .passport-meta-box, .completion-certificate {
            box-shadow: none !important;
            border: 1px solid #CCCCCC !important;
            page-break-inside: avoid;
        }
        @page {
            size: auto;
            margin: 12mm;
        }
    }
</style>
@endsection

@section('content')
<div class="passport-container p-3 p-md-4">
    <!-- Header Controls -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
        <a href="{{ route('documents.show', $document->id) }}" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
            <i class="bi bi-arrow-left me-1"></i> Back to Document Details
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-primary btn-sm btn-print" style="border-radius: 8px;">
                <i class="bi bi-printer me-1"></i> Print Passport
            </button>
            <a href="{{ route('documents.qr-label', $document->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                <i class="bi bi-qr-code me-1"></i> QR Label
            </a>
        </div>
    </div>

    <!-- Official NAAP Document Passport Banner -->
    <div class="passport-badge-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 56px; height: 56px; border-radius: 12px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 28px;">
                    <i class="bi bi-journal-bookmark-fill text-white"></i>
                </div>
                <div>
                    <div class="text-uppercase small tracking-wide" style="color: #94A3B8; font-size: 11px; letter-spacing: 0.1em; font-weight: 700;">
                        National Aviation Academy of the Philippines • Official Traceability Passport
                    </div>
                    <h2 class="mb-0 fw-bold text-white" style="letter-spacing: -0.02em;">{{ $document->title }}</h2>
                    <div class="d-flex align-items-center gap-3 mt-1 small" style="color: #CBD5E1;">
                        <span><i class="bi bi-hash"></i> <strong>{{ $document->tracking_number }}</strong></span>
                        <span>•</span>
                        <span><i class="bi bi-clock"></i> Registered: {{ $document->created_at ? $document->created_at->format('M d, Y • h:i A') : 'N/A' }}</span>
                    </div>
                </div>
            </div>
            <div>
                @php
                    $statusBadge = match(strtolower($document->status ?? '')) {
                        'completed' => 'bg-success text-white',
                        'pending' => 'bg-warning text-dark',
                        'in transit', 'in_transit' => 'bg-info text-dark',
                        'received' => 'bg-primary text-white',
                        'approved', 'accepted' => 'bg-success text-white',
                        'reverted', 'returned', 'rejected' => 'bg-danger text-white',
                        default => 'bg-secondary text-white'
                    };
                @endphp
                <span class="badge {{ $statusBadge }} px-3 py-2 fs-6 shadow-sm" style="border-radius: 8px;">
                    {{ $document->status }}
                </span>
            </div>
        </div>
    </div>

    <!-- Section A & D: Document Identity & QR Traceability Grid -->
    <div class="row g-3 mb-4">
        <!-- Identity Summary -->
        <div class="col-lg-8">
            <div class="card shadow-sm h-100" style="border-radius: var(--radius-lg); border: 1px solid var(--panel-border);">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold" style="color: var(--accent-navy);"><i class="bi bi-card-heading text-primary me-2"></i>Document Passport Identity</h6>
                    <span class="badge bg-light text-dark border">{{ $document->category ?? 'General' }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6 col-sm-6">
                            <div class="passport-meta-box">
                                <div class="passport-meta-label">Origin Office</div>
                                <div class="passport-meta-val"><i class="bi bi-building text-muted me-1"></i>{{ $document->originOffice->name ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6">
                            <div class="passport-meta-box">
                                <div class="passport-meta-label">Current Location</div>
                                <div class="passport-meta-val text-primary"><i class="bi bi-geo-alt-fill text-primary me-1"></i>{{ $document->currentOffice->name ?? 'In Transit' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6">
                            <div class="passport-meta-box">
                                <div class="passport-meta-label">Final Destination</div>
                                <div class="passport-meta-val"><i class="bi bi-flag-fill text-muted me-1"></i>{{ $document->destinationOffice->name ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6">
                            <div class="passport-meta-box">
                                <div class="passport-meta-label">Active Receiver</div>
                                <div class="passport-meta-val"><i class="bi bi-person-badge text-muted me-1"></i>{{ $document->receiverUser->name ?? 'Unassigned / Office Pool' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="passport-meta-box">
                                <div class="passport-meta-label">SLA Target</div>
                                <div class="passport-meta-val">{{ $document->sla ?: 'Standard Processing' }}</div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="passport-meta-box">
                                <div class="passport-meta-label">Security Classification</div>
                                <div class="passport-meta-val">
                                    @if($document->is_confidential)
                                        <span class="badge bg-danger text-white"><i class="bi bi-lock-fill me-1"></i>Confidential / OTP Protected</span>
                                    @else
                                        <span class="badge bg-success text-white"><i class="bi bi-unlock-fill me-1"></i>Non-Confidential</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <div class="passport-meta-box">
                                <div class="passport-meta-label">Created By</div>
                                <div class="passport-meta-val">{{ $document->uploader->name ?? 'System' }}</div>
                            </div>
                        </div>
                    </div>
                    @if($document->description)
                        <div class="mt-3 p-3 bg-light rounded border text-muted small">
                            <strong>Description:</strong> {{ $document->description }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- QR Verification Card -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100 text-center" style="border-radius: var(--radius-lg); border: 1px solid var(--panel-border);">
                <div class="card-header bg-white py-3 border-bottom text-start">
                    <h6 class="mb-0 fw-bold" style="color: var(--accent-navy);"><i class="bi bi-qr-code-scan text-primary me-2"></i>Physical QR Identity</h6>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    @if($qrCodeData)
                        <div class="p-2 border rounded bg-white shadow-sm mb-3">
                            <img src="{{ $qrCodeData }}" alt="Document Passport QR" style="width: 160px; height: 160px; object-fit: contain;">
                        </div>
                    @else
                        <div class="text-muted py-4"><i class="bi bi-qr-code" style="font-size: 3rem;"></i><br>QR Code Available on Label</div>
                    @endif
                    <div class="small fw-bold text-dark">{{ $document->tracking_number }}</div>
                    <div class="text-muted small mt-1">
                        QR Status: 
                        <span class="badge {{ $document->qr_status === 'Verified' ? 'bg-success' : 'bg-primary' }} text-white">
                            {{ $document->qr_status ?: 'Active Trace' }}
                        </span>
                    </div>
                    <div class="small text-muted mt-2">
                        Last Scan: {{ $document->qr_scanned_at ? $document->qr_scanned_at->format('M d, Y • h:i A') : 'Not yet scanned' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section E: Document Completion Record (Internal Certificate of Traceability) -->
    @if($completionRecord)
    <div class="completion-certificate mb-4">
        <i class="bi bi-patch-check-fill completion-watermark"></i>
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3 flex-wrap gap-2">
            <div>
                <span class="badge bg-success text-white mb-1"><i class="bi bi-award me-1"></i>Verified Internal Traceability Certificate</span>
                <h4 class="fw-bold mb-0" style="color: var(--accent-navy);">NAAP Document Completion Record</h4>
                <div class="small text-muted">Certificate ID: <code>{{ $completionRecord['certificate_id'] }}</code></div>
            </div>
            <div class="text-end">
                <div class="fw-bold text-dark">Completed: {{ $completionRecord['completed_at']->format('F d, Y • h:i A') }}</div>
                <div class="small text-muted">Terminal Office: {{ $completionRecord['final_office'] }}</div>
            </div>
        </div>

        <div class="row g-3 text-start mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="small text-muted text-uppercase fw-bold">Signer / Authority</div>
                <div class="fw-bold text-dark">{{ $completionRecord['signer_name'] }}</div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small text-muted text-uppercase fw-bold">Completed Handoffs</div>
                <div class="fw-bold text-dark">{{ $completionRecord['total_hops'] }} Routing Hops</div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small text-muted text-uppercase fw-bold">Verified QR Scans</div>
                <div class="fw-bold text-dark">{{ $completionRecord['verified_scans'] }} Recorded Scans</div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small text-muted text-uppercase fw-bold">Integrity Fingerprint</div>
                <div class="small font-monospace text-truncate text-secondary">{{ $completionRecord['verification_hash'] }}</div>
            </div>
        </div>

        <div class="p-3 bg-light rounded border text-muted small">
            <i class="bi bi-info-circle me-1"></i> This internal certificate authenticates that all configured office routing handoffs, review stages, and signature approvals for document <strong>{{ $document->tracking_number }}</strong> were legitimately completed within NAAP Document Tracking System.
        </div>
    </div>
    @endif

    <!-- Section F: Stage & Delay Analysis / Route Deviations -->
    @if(!empty($delayBreakdown) || !empty($routeDeviations))
    <div class="row g-3 mb-4">
        @if(!empty($delayBreakdown))
        <div class="col-lg-{{ !empty($routeDeviations) ? '7' : '12' }}">
            <div class="card shadow-sm h-100" style="border-radius: var(--radius-lg); border: 1px solid var(--panel-border);">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold" style="color: var(--accent-navy);"><i class="bi bi-hourglass-split text-primary me-2"></i>Processing Stage & Delay Breakdown</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach($delayBreakdown as $stageName => $stageData)
                        <div class="col-md-4 col-sm-6">
                            <div class="p-3 bg-light rounded border h-100">
                                <div class="small text-muted fw-bold text-uppercase" style="font-size: 11px;">{{ $stageName }}</div>
                                <div class="h4 fw-bold text-primary my-1">{{ $stageData['hours'] }}h</div>
                                <div class="text-muted" style="font-size: 11px;">{{ $stageData['desc'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(!empty($routeDeviations))
        <div class="col-lg-5">
            <div class="card shadow-sm h-100 border-warning" style="border-radius: var(--radius-lg);">
                <div class="card-header bg-white py-3 border-bottom text-warning d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Route Exceptions & Deviations</h6>
                    <span class="badge bg-warning text-dark">{{ count($routeDeviations) }} Recorded</span>
                </div>
                <div class="card-body">
                    @foreach($routeDeviations as $dev)
                        <div class="p-2 mb-2 bg-light rounded border-start border-warning border-3">
                            <div class="fw-bold small text-dark">{{ $dev['type'] }} at {{ $dev['office'] }}</div>
                            <div class="text-muted" style="font-size: 11.5px;">{{ $dev['notes'] }}</div>
                            <div class="text-muted" style="font-size: 10px;">{{ \Carbon\Carbon::parse($dev['date'])->format('M d, Y • h:i A') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- Section B: Complete Document Journey (Chronological Timeline) -->
    <div class="card shadow-sm mb-4" style="border-radius: var(--radius-lg); border: 1px solid var(--panel-border);">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--accent-navy);"><i class="bi bi-bezier2 text-primary me-2"></i>Complete Document Journey</h6>
            <span class="badge bg-light text-dark border">{{ $journeyEvents->count() }} Total Events Recorded</span>
        </div>
        <div class="card-body p-4">
            <div class="journey-timeline">
                @forelse($journeyEvents as $event)
                    <div class="journey-item">
                        <div class="journey-dot">
                            <i class="bi {{ $event['icon'] }}"></i>
                        </div>
                        <div class="journey-card">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                                <div>
                                    <span class="badge {{ $event['badge_class'] }} me-2">{{ $event['badge'] }}</span>
                                    <strong class="text-dark">{{ $event['action'] }}</strong>
                                </div>
                                <span class="small text-muted">
                                    <i class="bi bi-calendar3 me-1"></i>{{ $event['timestamp'] instanceof \Carbon\Carbon ? $event['timestamp']->format('M d, Y • h:i A') : $event['timestamp'] }}
                                </span>
                            </div>
                            <div class="small text-muted">
                                <i class="bi bi-person me-1"></i><strong>{{ $event['user'] }}</strong> 
                                @if($event['office'])
                                    • <i class="bi bi-building me-1"></i>{{ $event['office'] }}
                                @endif
                            </div>
                            <div class="small text-dark mt-1">
                                {{ $event['details'] }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4 text-muted">No journey events recorded.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Section C: Office Routing Handoffs Table -->
    <div class="card shadow-sm mb-4" style="border-radius: var(--radius-lg); border: 1px solid var(--panel-border);">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold" style="color: var(--accent-navy);"><i class="bi bi-diagram-3 text-primary me-2"></i>Configured & Recorded Routing Handoffs</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead class="table-light text-uppercase" style="font-size: 11px; letter-spacing: 0.05em;">
                    <tr>
                        <th>Step</th>
                        <th>From Office</th>
                        <th>To Office</th>
                        <th>Sender</th>
                        <th>Receiver</th>
                        <th>Dispatched</th>
                        <th>Acknowledged</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($document->routings as $route)
                    <tr>
                        <td class="fw-bold">#{{ $route->sort_order }}</td>
                        <td>{{ $route->fromOffice->name ?? 'Origin' }}</td>
                        <td class="fw-semibold text-primary">{{ $route->toOffice->name ?? 'Destination' }}</td>
                        <td>{{ $route->senderUser->name ?? 'System' }}</td>
                        <td>{{ $route->receiverUser->name ?? 'Office Staff' }}</td>
                        <td class="text-muted small">{{ $route->pending_at ? $route->pending_at->format('M d, Y • h:i A') : 'N/A' }}</td>
                        <td class="text-muted small">{{ $route->received_at ? $route->received_at->format('M d, Y • h:i A') : 'Pending' }}</td>
                        <td>
                            @php
                                $rBadge = match($route->status) {
                                    'Approved', 'Completed', 'Accepted', 'Endorsed' => 'bg-success text-white',
                                    'Pending' => 'bg-warning text-dark',
                                    'Waiting' => 'bg-secondary text-white',
                                    'Returned', 'Reverted' => 'bg-danger text-white',
                                    default => 'bg-info text-white'
                                };
                            @endphp
                            <span class="badge {{ $rBadge }}">{{ $route->status }}</span>
                        </td>
                        <td class="small text-muted">{{ $route->notes ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No routing handoffs configured for this document.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
