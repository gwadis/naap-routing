@extends('layouts.app')

@section('title', 'Document Routing')

@section('content')
<style>
    /* Header Styling */
    .page-header { margin-bottom: 30px; }
    .page-header h2 { font-weight: 700; color: var(--text-main); margin: 0; }
    .page-header p { color: var(--text-dim); margin: 5px 0 0 0; }

    /* Routing Card Styling */
    .routing-card { 
        background: var(--panel); 
        border: 1px solid var(--panel-border); 
        border-radius: 24px; 
        padding: 28px; 
        margin-bottom: 25px; 
        transition: 0.2s;
    }
    .routing-card:hover { border-color: rgba(34, 211, 238, 0.2); }

    /* The Tracker */
    .tracker-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 40px 0;
        position: relative;
        padding: 0 10px;
    }
    
    .tracker-line-bg {
        position: absolute;
        top: 15px; left: 0; right: 0;
        height: 4px;
        background: var(--panel-border);
        z-index: 1;
        border-radius: 10px;
    }

    .tracker-line-fill {
        position: absolute;
        top: 15px; left: 0;
        height: 4px;
        background: linear-gradient(90deg, #a855f7, #22d3ee);
        z-index: 2;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(34, 211, 238, 0.3);
    }

    .step-node {
        position: relative;
        z-index: 3;
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 120px;
    }

    .step-dot {
        width: 32px; height: 32px;
        border-radius: 50%;
        background: var(--bg);
        border: 3px solid var(--panel-border);
        display: grid; place-items: center;
        font-size: 0.8rem; font-weight: 700;
        transition: 0.3s;
        color: var(--text-dim);
    }

    .step-node.completed .step-dot {
        background: #a855f7;
        border-color: #a855f7;
        color: white;
    }

    .step-node.active .step-dot {
        background: #22d3ee;
        border-color: #22d3ee;
        color: #000;
        box-shadow: 0 0 20px rgba(34, 211, 238, 0.4);
    }

    .step-label {
        margin-top: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-dim);
        text-align: center;
    }
    .active .step-label, .completed .step-label { color: var(--text-main); }

    /* Form Controls */
    .form-glass {
        background: var(--bg);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        color: var(--text-main) !important;
        padding: 12px 15px;
    }
    .form-glass:focus { border-color: #22d3ee; box-shadow: none; }

    .btn-route {
        background: linear-gradient(135deg, #22d3ee, #a855f7);
        border: none; color: white; font-weight: 700;
        padding: 12px 25px; border-radius: 12px;
        transition: 0.2s;
    }
    .btn-route:hover { opacity: 0.9; transform: scale(1.02); }
</style>

<div class="page-header">
    <h2>Active Routing</h2>
    <p>Monitor and update document progress across offices.</p>
</div>

@if(session('success'))
    <div class="alert alert-success bg-success text-white border-0 mb-4" style="border-radius: 15px;">
        {{ session('success') }}
    </div>
@endif

@foreach($documents as $doc)
<div class="routing-card">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
            <span class="badge mb-2" style="background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.2);">
                {{ $doc->priority }} Priority
            </span>
            <h4 class="mb-1">{{ $doc->title }}</h4>
            <div class="text-muted small">Tracking ID: <span class="text-info">#{{ str_pad($doc->id, 6, '0', STR_PAD_LEFT) }}</span></div>
        </div>
        <div class="text-end">
            <div class="small text-muted">Receiver</div>
            <div class="small fw-bold">{{ optional($doc->receiverUser)->name ?? 'Unassigned' }}</div>
            <div class="small text-muted">{{ optional(optional($doc->receiverUser)->department)->name ?? 'No department' }}</div>
            <div class="small text-muted mt-2">Last Update</div>
            <div class="small fw-bold">{{ optional($doc->updated_at)->diffForHumans() ?? 'Unknown' }}</div>
        </div>
    </div>

    @php
        // Logic to determine tracker width and step status
        $currentOfficeId = $doc->current_office_id;
        $originOfficeId = $doc->origin_office_id;
        $destinationOfficeId = $doc->destination_office_id;
        $isAtOrigin = $currentOfficeId === $originOfficeId;
        $isAtDestination = $currentOfficeId === $destinationOfficeId;
        $isInTransit = !$isAtOrigin && !$isAtDestination;
        $progressWidth = $isAtDestination ? '100%' : ($isAtOrigin ? '20%' : '60%');
    @endphp

    <div class="tracker-container">
        <div class="tracker-line-bg"></div>
        <div class="tracker-line-fill" style="width: {{ $progressWidth }};"></div> 
        
        <div class="step-node {{ $isAtOrigin ? 'active' : 'completed' }}">
            <div class="step-dot">{{ $isAtOrigin ? '●' : '✓' }}</div>
            <div class="step-label text-truncate" style="max-width: 100px;">{{ optional($doc->originOffice)->name ?? 'Origin' }}</div>
        </div>

        <div class="step-node {{ $isInTransit ? 'active' : ($isAtDestination ? 'completed' : '') }}">
            <div class="step-dot">{{ $isInTransit ? '●' : ($isAtDestination ? '✓' : '2') }}</div>
            <div class="step-label text-truncate" style="max-width: 100px;">{{ optional($doc->currentOffice)->name ?? 'Current' }}</div>
        </div>

        <div class="step-node {{ $isAtDestination ? 'active completed' : '' }}">
            <div class="step-dot">{{ $isAtDestination ? '✓' : '3' }}</div>
            <div class="step-label text-truncate" style="max-width: 100px;">{{ optional($doc->destinationOffice)->name ?? 'Destination' }}</div>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-md-4">
            <small class="text-muted">Receiver</small>
            <div class="fw-bold">{{ optional($doc->receiverUser)->name ?? 'Unassigned' }}</div>
            <small class="text-muted">{{ optional(optional($doc->receiverUser)->department)->name ?? 'No department' }}</small>
        </div>
        <div class="col-md-4">
            <small class="text-muted">SLA</small>
            <div class="fw-bold">{{ $doc->sla ?? 'Standard' }}</div>
        </div>
        <div class="col-md-4">
            <small class="text-muted">Status</small>
            <div class="fw-bold">{{ $doc->status }}</div>
        </div>
    </div>

    <hr style="border-color: var(--panel-border); margin: 25px 0;">

    <div class="row mb-4">
        <div class="col-md-12">
            <h6 class="text-info mb-3">Current Document Location</h6>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-3">
            <div class="card p-3" style="background: rgba(34, 211, 238, 0.05); border: 1px solid rgba(34, 211, 238, 0.2); border-radius: 10px;">
                <small class="text-muted"><i class="bi bi-geo-alt-fill text-info me-1"></i> Current Location</small>
                <div class="fw-bold mt-2">{{ $doc->currentOffice->name ?? 'In Transit' }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3" style="background: rgba(168, 85, 247, 0.05); border: 1px solid rgba(168, 85, 247, 0.2); border-radius: 10px;">
                <small class="text-muted"><i class="bi bi-arrow-repeat text-purple me-1"></i> Status</small>
                <div class="fw-bold mt-2">{{ $doc->status }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3" style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 10px;">
                <small class="text-muted"><i class="bi bi-clock-fill text-warning me-1"></i> Due Date</small>
                <div class="fw-bold mt-2">
                    @if($doc->due_date)
                        {{ $doc->due_date->format('M d, Y') }}
                    @else
                        N/A
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3" style="background: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 10px;">
                <small class="text-muted"><i class="bi bi-person-fill text-success me-1"></i> Current Holder</small>
                <div class="fw-bold mt-2 small">{{ optional($doc->receiverUser)->name ?? 'Unassigned' }}</div>
            </div>
        </div>
    </div>

    <div class="row mt-4 g-3">
        <div class="col-md-6">
            <div class="card p-4" style="background: var(--bg) !important; border: 1px solid var(--panel-border) !important; border-radius: 10px;">
                <h6 class="text-info mb-3">Journey So Far</h6>
                <div class="small">
                    <div class="mb-2">
                        <span class="badge bg-success me-2">✓</span>
                        <span>Started at: {{ $doc->originOffice->name ?? 'Origin' }}</span>
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-primary me-2">●</span>
                        <span>Currently at: {{ $doc->currentOffice->name ?? 'In Transit' }}</span>
                    </div>
                    <div>
                        <span class="badge bg-warning me-2">→</span>
                        <span>Heading to: {{ $doc->destinationOffice->name ?? 'Final Destination' }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-4" style="background: var(--bg) !important; border: 1px solid var(--panel-border) !important; border-radius: 10px;">
                <h6 class="text-info mb-3">Document Details</h6>
                <div class="small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">QR Code:</span>
                        <span class="fw-bold">{{ $doc->qr_id ?? 'N/A' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">File Type:</span>
                        <span class="fw-bold">{{ $doc->type ?? 'Unknown' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Last Update:</span>
                        <span class="fw-bold">{{ $doc->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach

<div class="d-flex justify-content-center mt-4">
    {{ $documents->links() }}
</div>
@endsection