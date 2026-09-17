@extends('layouts.app')

@section('title', 'Document Details')

@section('head')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script>
<style>
    .tracking-map {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px;
        background: #F8FAFC;
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        margin: 20px 0;
    }
    .tracking-office {
        text-align: center;
        flex: 1;
        opacity: 0.65;
        transition: opacity 0.2s ease;
    }
    .tracking-office.active {
        opacity: 1;
    }
    .tracking-office-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-weight: bold;
        color: var(--text-dim);
        background: var(--panel);
        border: 2px solid var(--panel-border);
        transition: all 0.2s ease;
    }
    .tracking-office.active .tracking-office-circle {
        background: var(--accent-cyan);
        border-color: var(--accent-cyan);
        color: #ffffff !important;
        box-shadow: 0 0 18px rgba(59, 130, 246, 0.35);
    }
    .tracking-office-name {
        font-size: 0.9rem;
        margin-bottom: 5px;
        color: var(--text-dim);
        transition: color 0.2s ease;
    }
    .tracking-office.active .tracking-office-name {
        color: var(--text-main);
        font-weight: 700;
    }
    .tracking-office-type {
        font-size: 0.75rem;
        color: var(--text-dim);
    }
    .tracking-arrow {
        flex: 0.5;
        text-align: center;
        color: var(--accent-cyan);
        font-size: 24px;
    }
    .sla-card {
        background: var(--bg);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .sla-field {
        margin-bottom: 10px;
    }
    .sla-field-label {
        font-size: 0.8rem;
        color: var(--text-dim);
        text-transform: uppercase;
        display: block;
    }
    .sla-field-value {
        color: var(--text-main);
        font-weight: 600;
        margin-top: 3px;
    }
</style>
@endsection

@section('content')
@if(isset($isLocked) && $isLocked)
<div class="container-fluid p-4">
    <div class="row justify-content-center">
        <div class="col-md-6 text-start">
            <div class="card shadow p-4 text-center" style="border-radius: 16px; border: 1px solid var(--panel-border); background: var(--panel) !important;">
                <div class="mb-4">
                    <i class="bi bi-lock-fill text-danger" style="font-size: 4.5rem; filter: drop-shadow(0 4px 10px rgba(30, 58, 138, 0.15));"></i>
                </div>
                <h4 class="fw-bold">Document Access Locked</h4>
                <p class="text-secondary">This document is locked for security. You must scan the QR code to verify and access this document.</p>
                
                <div class="mt-4">
                    <a href="{{ route('qr.index', ['document_id' => $document->id]) }}" class="btn btn-outline-secondary w-100 py-3" style="border-radius: 8px; height: 48px !important;">
                        <i class="bi bi-qr-code-scan me-1"></i> Go to QR Scanner
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@else
@php
    $originId = optional($document->originOffice)->id;
    $destinationId = optional($document->destinationOffice)->id;
    $currentId = optional($document->currentOffice)->id;
    $statusKey = strtolower(str_replace(' ', '_', $document->status ?? ''));
    $originActive = $currentId && $currentId === $originId && $statusKey !== 'in_transit';
    $destinationActive = $currentId && $currentId === $destinationId && $statusKey !== 'in_transit';
    $currentActive = !$originActive && !$destinationActive;
    $currentLabel = $document->currentOffice?->name ?? 'In Transit';
@endphp
<div class="container-fluid p-4">
    @if(session('error'))
        <div class="alert alert-danger border-0 mb-4" style="border-radius: 12px; background: rgba(239, 68, 68, 0.2); color: #f87171;">
            {{ session('error') }}
        </div>
    @endif

    @if($document->is_confidential)
        <div class="alert alert-warning border-0 d-flex justify-content-between align-items-center mb-4" style="border-radius: 12px; background: rgba(245, 158, 11, 0.15); border-left: 4px solid #f59e0b; color: #d97706;">
            <div>
                <strong><i class="bi bi-shield-lock-fill me-1"></i> Confidential Document</strong>
                <div class="small">Access is restricted. Receivers require a PIN code to view details.</div>
            </div>
            @if(session('user_role') === 'ADMIN')
                <form action="{{ route('documents.regeneratePin', $document->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm fw-bold"><i class="bi bi-arrow-clockwise me-1"></i> Regenerate PIN</button>
                </form>
            @endif
        </div>
    @endif

    @php
        $canAct = $canPerformWorkflowAction ?? ($document->receiver_user_id === (auth()->id() ?? session('user_id')));
    @endphp
    @if($canAct && !in_array($document->status, ['Accepted', 'Completed', 'Archived', 'Rejected']))
    <div class="card shadow-sm border-0 p-4 mb-4" style="border-radius: 12px; background: rgba(59, 130, 246, 0.04); border: 1px solid rgba(59, 130, 246, 0.2) !important;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="text-start">
                <h6 class="fw-bold mb-1" style="color: var(--accent-navy);"><i class="bi bi-shield-check text-primary me-2"></i>Awaiting Your Acceptance</h6>
                <p class="text-muted small mb-0">You are the designated receiver of this document. Please verify, review, and formally accept it to continue routing.</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary fw-bold px-4 py-2" data-bs-toggle="modal" data-bs-target="#acceptDocumentModal" style="border-radius: 8px;">
                    <i class="bi bi-pencil-square me-1"></i> Accept Document
                </button>
            </div>
        </div>
    </div>
    @endif

    <div class="card bg-white shadow-sm" style="border-radius: 12px; border: 1px solid var(--panel-border); color: var(--text-main);">
        <div class="card-header d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0; background: #F8FAFC; border-bottom: 1px solid var(--panel-border);">
            <h5 class="mb-0 fw-bold" style="color: var(--accent-navy);"><i class="bi bi-box-seam me-2"></i>{{ $document->title }}</h5>
            <div>
                <span class="badge me-2 text-white" style="background: var(--accent-cyan); font-weight: 600;">{{ $document->status }}</span>
            </div>
        </div>
        <div class="card-body">
            <!-- SLA & Due Date Analytics Card -->
            @php
                $estOfficeMap = [
                    'Registrar' => 1,
                    'Academic Dean' => 2,
                    'Finance Office' => 3,
                    'HR Office' => 2,
                    'VPAA' => 1
                ];
                $totalEstDays = 0;
                foreach ($document->destination_offices ?? [] as $hop) {
                    $office = \App\Models\Office::find($hop['office_id'] ?? 0);
                    if ($office) {
                        $totalEstDays += $estOfficeMap[$office->name] ?? 2;
                    }
                }
                if ($totalEstDays === 0) $totalEstDays = 2;

                $percent = 100;
                $remainingDaysText = '';
                $overdueDaysText = '';
                $overdueDaysVal = 0;
                $slaColorClass = 'text-success';
                $isOverdue = false;
                if ($document->due_date) {
                    $isOverdue = now()->greaterThan($document->due_date);
                    $diffInSeconds = now()->diffInSeconds($document->due_date, false);
                    $diffInDays = $diffInSeconds / 86400.0;
                    
                    if ($isOverdue) {
                        $absDays = abs($diffInDays);
                        $overdueDaysVal = round($absDays, 1);
                        $val = ($overdueDaysVal == round($overdueDaysVal)) ? (int)$overdueDaysVal : $overdueDaysVal;
                        
                        $overdueDaysText = "{$val} " . ($val == 1 ? "day" : "days") . " overdue";
                        $slaColorClass = 'text-danger';
                    } else {
                        if (round($diffInDays, 4) == 0) {
                            $remainingDaysText = "Due today";
                        } elseif ($diffInDays < 1) {
                            $hours = $diffInDays * 24.0;
                            $roundedHours = round($hours, 1);
                            $val = ($roundedHours == round($roundedHours)) ? (int)$roundedHours : $roundedHours;
                            if ($val <= 0) {
                                $remainingDaysText = "Due today";
                            } else {
                                $remainingDaysText = "{$val} " . ($val == 1 ? "hour" : "hours") . " remaining";
                            }
                        } else {
                            $rounded = round($diffInDays, 1);
                            $val = ($rounded == round($rounded)) ? (int)$rounded : $rounded;
                            $remainingDaysText = "{$val} " . ($val == 1 ? "day" : "days") . " remaining";
                        }
                        $slaColorClass = 'text-success';
                    }

                    $totalSecs = $document->created_at->diffInSeconds($document->due_date);
                    $elapsedSecs = $document->created_at->diffInSeconds(now());
                    if ($totalSecs > 0) {
                        $percent = max(0, min(100, round((($totalSecs - $elapsedSecs) / $totalSecs) * 100)));
                    }
                }
            @endphp
            <div class="sla-card p-4 rounded-3 border mb-4 bg-light">
                <h6 class="fw-bold text-uppercase text-secondary mb-3 small" style="letter-spacing: 0.05em;"><i class="bi bi-clock-history me-2"></i>Due Date & SLA Analytics</h6>
                <div class="row g-3 text-start">
                    <div class="col-md-3">
                        <span class="sla-field-label small text-muted text-uppercase" style="font-size:0.75rem;">Expected Completion</span>
                        <div class="h6 fw-bold mt-1 text-dark">{{ $document->due_date ? $document->due_date->format('M d, Y h:i A') : 'N/A' }}</div>
                    </div>
                    <div class="col-md-3">
                        <span class="sla-field-label small text-muted text-uppercase" style="font-size:0.75rem;">SLA Type / Status</span>
                        <div class="mt-1">
                            <span class="badge bg-secondary text-uppercase">{{ $document->sla ?? 'Standard' }}</span>
                            <span class="badge {{ $document->sla_status_class }} text-uppercase">{{ $document->sla_status_label }}</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <span class="sla-field-label small text-muted text-uppercase" style="font-size:0.75rem;">Remaining / Overdue Days</span>
                        <div class="h6 fw-bold mt-1 {{ $slaColorClass }}">
                            @if($isOverdue)
                                {{ $overdueDaysText }}
                            @else
                                {{ $remainingDaysText }}
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3">
                        <span class="sla-field-label small text-muted text-uppercase" style="font-size:0.75rem;">Est. Processing / Current Delay</span>
                        <div class="h6 fw-bold mt-1 text-dark">
                            Est: {{ $totalEstDays }} Day(s)
                            @if($isOverdue)
                                | Delay: <span class="text-danger fw-bold">{{ ($overdueDaysVal == round($overdueDaysVal)) ? (int)$overdueDaysVal : $overdueDaysVal }} {{ (($overdueDaysVal == round($overdueDaysVal)) ? (int)$overdueDaysVal : $overdueDaysVal) == 1 ? 'Day' : 'Days' }}</span>
                            @else
                                | On Track
                            @endif
                        </div>
                    </div>
                </div>
                
                @if($document->due_date)
                    <div class="mt-3 text-start">
                        <div class="d-flex justify-content-between mb-1 small text-muted">
                            <span>SLA Target Limit Timeline</span>
                            <span class="fw-bold">{{ $percent }}% time remaining</span>
                        </div>
                        <div class="progress" style="height: 12px; border-radius: 6px;">
                            <div class="progress-bar progress-bar-striped {{ $isOverdue ? 'bg-danger' : ($percent < 25 ? 'bg-warning text-dark' : 'bg-success') }}" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Tracking Map -->
            <h6 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill text-primary me-1"></i> Document Route</h6>
            <div class="tracking-map">
                <!-- Origin Office -->
                <div class="tracking-office {{ $originActive ? 'active' : '' }}">
                    <div class="tracking-office-circle">
                        <i class="bi bi-box-arrow-up"></i>
                    </div>
                    <div class="tracking-office-name">{{ $document->originOffice->name ?? 'N/A' }}</div>
                    <div class="tracking-office-type">Origin</div>
                </div>

                <!-- Arrow -->
                <div class="tracking-arrow">→</div>

                <!-- Current Office -->
                <div class="tracking-office {{ $currentActive ? 'active' : '' }}">
                    <div class="tracking-office-circle">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div class="tracking-office-name">{{ $currentLabel }}</div>
                    <div class="tracking-office-type">Current</div>
                </div>

                <!-- Arrow -->
                <div class="tracking-arrow">→</div>

                <!-- Destination Office -->
                <div class="tracking-office {{ $destinationActive ? 'active' : '' }}">
                    <div class="tracking-office-circle">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                    <div class="tracking-office-name">{{ $document->destinationOffice->name ?? 'N/A' }}</div>
                    <div class="tracking-office-type">Destination</div>
                </div>
            </div>

            <!-- Basic Information -->
            <hr class="border-secondary">
            <div class="row mb-4">
                <div class="col-md-3">
                    <small class="text-muted d-block"><i class="bi bi-pin-angle-fill me-1"></i> Receiver</small>
                    @if($document->receiverUsers && $document->receiverUsers->isNotEmpty())
                        <div class="d-flex flex-column gap-1">
                            @foreach($document->receiverUsers as $receiver)
                                <div>
                                    <strong>{{ $receiver->name }}</strong>
                                    <div class="small text-muted">{{ optional($receiver->department)->name ?? 'No department' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <strong>{{ optional($document->receiverUser)->name ?? 'Unassigned' }}</strong>
                        <div class="small text-muted">{{ optional(optional($document->receiverUser)->department)->name ?? 'No department' }}</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block"><i class="bi bi-file-earmark-text me-1"></i> Document Type</small>
                    <strong>{{ $document->type }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block"><i class="bi bi-tag-fill me-1"></i> Category / Subject</small>
                    <strong>{{ $document->category ?? 'N/A' }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block"><i class="bi bi-person-fill me-1"></i> Uploaded By</small>
                    <strong>{{ optional($document->uploader)->name ?? 'System' }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block"><i class="bi bi-calendar-event me-1"></i> Created</small>
                    <strong>{{ $document->created_at->format('M d, Y') }}</strong>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <small class="text-muted d-block"><i class="bi bi-pencil-square me-1"></i> Description</small>
                    <p class="mb-0 bg-transparent text-dark border-0 py-1" style="font-size: 0.95rem;">
                        {{ $document->description }}
                    </p>
                </div>
            </div>

            <!-- Live SLA Timer & Seen Status -->
            <div class="row g-3 mb-4">
                <div class="col-md-6 text-start">
                    <div class="p-3 rounded h-100" style="background: rgba(0,0,0,0.02); border: 1px solid var(--panel-border);">
                        <small class="text-muted d-block fw-bold text-uppercase mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;"><i class="bi bi-hourglass-split me-1"></i> SLA & Office Holding Time</small>
                        @php
                            $activeStep = $document->routings->where('status', 'Pending')->first();
                        @endphp
                        @if($activeStep)
                            <div class="mb-1" style="font-size: 0.85rem;"><strong>Time Received:</strong> {{ $activeStep->pending_at ? $activeStep->pending_at->format('M d, Y h:i A') : $activeStep->created_at->format('M d, Y h:i A') }}</div>
                            <div class="mb-2" style="font-size: 0.85rem;"><strong>Current Time:</strong> <span id="current-live-time" class="text-dark"></span></div>
                            <div class="d-flex align-items-center gap-2">
                                <span id="holding-duration-badge" class="badge bg-warning text-dark px-2.5 py-1.5 fs-6" style="border-radius:6px; font-weight:600;">Calculating...</span>
                                @if($activeStep->sla_due_at)
                                    <span class="small text-muted" style="font-size: 0.78rem;">Limit: {{ $activeStep->sla_due_at->format('M d, Y h:i A') }}</span>
                                @endif
                            </div>
                        @else
                            <span class="text-muted small">No active pending office holding this document.</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6 text-start">
                    <div class="p-3 rounded h-100" style="background: rgba(0,0,0,0.02); border: 1px solid var(--panel-border);">
                        <small class="text-muted d-block fw-bold text-uppercase mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;"><i class="bi bi-eye me-1"></i> Seen / Viewed Receipt</small>
                        @php
                            $activeReceiverId = $document->receiver_user_id;
                            $hasActiveReceiverViewed = isset($views) && $views->where('user_id', $activeReceiverId)->isNotEmpty();
                        @endphp
                        @if($activeReceiverId)
                            <div class="mb-2" style="font-size: 0.85rem;"><strong>Active Receiver:</strong> {{ optional($document->receiverUser)->name }}</div>
                            <div>
                                @if($hasActiveReceiverViewed)
                                    <span class="badge bg-success px-2.5 py-1.5 fs-6" style="border-radius:6px; font-weight:600;"><i class="bi bi-eye-fill me-1"></i> Seen / Viewed</span>
                                @else
                                    <span class="badge bg-secondary px-2.5 py-1.5 fs-6" style="border-radius:6px; font-weight:600;"><i class="bi bi-clock me-1"></i> Not Yet Viewed</span>
                                @endif
                            </div>
                        @else
                            <div class="text-muted small">No current active receiver assigned.</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Viewed History (Multiple Viewers) -->
            <div class="card shadow-sm border-0 p-4 mb-4" style="border-radius: 12px; background: var(--panel); border: 1px solid var(--panel-border) !important;">
                <h6 class="fw-bold mb-3 text-start" style="color: var(--accent-navy);"><i class="bi bi-people me-1 text-primary"></i> Document Viewed History</h6>
                @if(isset($views) && $views->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--panel-border); font-size: 0.75rem; text-transform: uppercase;" class="text-secondary text-start">
                                    <th>Viewer</th>
                                    <th>Office / Department</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($views as $v)
                                    <tr class="text-start" style="font-size: 0.85rem;">
                                        <td class="py-2 text-dark font-medium"><i class="bi bi-person-check me-1 text-success"></i>{{ $v->user->name }}</td>
                                        <td class="py-2 text-secondary">{{ $v->office->name ?? 'N/A' }}</td>
                                        <td class="py-2 text-secondary">{{ $v->viewed_at->format('M d, Y h:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-muted text-center py-2" style="font-size: 0.85rem;">No read receipts (views) recorded yet.</div>
                @endif
            </div>

            <!-- Workflow Execution Actions Card -->
            @php
                $showWorkflowCard = $canViewWorkflow ?? true;
                $canExecuteAction = $canPerformWorkflowAction ?? (session('user_role') === 'ADMIN' || ($document->receiver_user_id === (auth()->id() ?? session('user_id')) && !in_array($document->status, ['Completed', 'Archived', 'Rejected'])));
            @endphp
            @if($showWorkflowCard)
            <div class="card shadow-sm border-0 p-4 mb-4" style="border-radius: 12px; background: var(--panel); border: 1px solid var(--panel-border) !important;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0" style="color: var(--accent-cyan);"><i class="bi bi-gear-fill me-2"></i>Workflow Execution Actions</h5>
                    @if(!$canExecuteAction)
                        <span class="badge bg-secondary text-white px-2 py-1" style="font-size: 0.75rem; font-weight: 600;">
                            <i class="bi bi-eye me-1"></i> View Only
                        </span>
                    @endif
                </div>

                @if(!$canExecuteAction)
                    <div class="alert alert-light border small text-muted mb-3 py-2 px-3" style="background: rgba(0,0,0,0.02); font-size: 13px;">
                        @if(in_array($document->status, ['Completed', 'Archived', 'Cancelled']))
                            <i class="bi bi-check-circle-fill text-success me-1"></i> This document's workflow has reached a terminal status (<strong>{{ $document->status }}</strong>).
                        @else
                            <i class="bi bi-info-circle text-primary me-1"></i> You are viewing this workflow in read-only mode. Only the designated receiver, assigned office handler, or an administrator can execute workflow actions.
                        @endif
                    </div>
                @endif

                <form action="{{ route('documents.workflowAction', $document->id) }}" method="POST" enctype="multipart/form-data" id="workflowActionForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Update Status</label>
                        <select name="status" class="form-select" required @disabled(!$canExecuteAction)>
                            <option value="Pending" {{ $document->status === 'Pending' ? 'selected' : '' }}>Pending</option>
                            <option value="Received" {{ $document->status === 'Received' ? 'selected' : '' }}>Received</option>
                            <option value="Under Review" {{ $document->status === 'Under Review' ? 'selected' : '' }}>Under Review</option>
                            <option value="Approved">Approved / Forward to Next Step</option>
                            <option value="Accepted">Accept / Route to Next Office</option>
                            <option value="Endorsed">Endorse Document</option>
                            <option value="Returned">Returned for Missing Signature</option>
                            <option value="Reverted">Revert Back to Previous Office</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Completed" {{ $document->status === 'Completed' ? 'selected' : '' }}>Completed</option>
                            <option value="Cancelled" {{ $document->status === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                            <option value="Archived" {{ $document->status === 'Archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Execution Remarks / Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Enter remarks or approval notes..." @disabled(!$canExecuteAction)></textarea>
                    </div>

                    @if($canExecuteAction)
                    <!-- Signature Section -->
                    <div id="workflow-signature-section" style="display: none;" class="mb-3 text-start">
                        <label class="form-label small fw-bold text-secondary">Digital Signature</label>
                        
                        <div class="d-flex gap-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="signature_option" id="sigOptProfile" value="profile" checked>
                                <label class="form-check-label small" for="sigOptProfile">
                                    Use Profile Signature
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="signature_option" id="sigOptDraw" value="draw">
                                <label class="form-check-label small" for="sigOptDraw">
                                    Draw Signature
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="signature_option" id="sigOptUpload" value="upload">
                                <label class="form-check-label small" for="sigOptUpload">
                                    Upload Signature
                                </label>
                            </div>
                        </div>

                        <!-- 1. Profile Signature Preview -->
                        <div id="sigProfileContainer" class="p-3 bg-light rounded text-center border">
                            @if(auth()->user() && auth()->user()->signature)
                                <img src="{{ asset('storage/' . auth()->user()->signature) }}" alt="Profile Signature" style="max-height: 80px; background:white; padding:4px; border:1px solid #ddd; border-radius:4px;">
                                <div class="small text-muted mt-1">Using your saved profile signature.</div>
                            @else
                                <span class="text-danger small">No saved profile signature. Please choose another option or save one in profile.</span>
                            @endif
                        </div>

                        <!-- 2. Draw Signature Canvas -->
                        <div id="sigDrawContainer" class="text-center" style="display: none;">
                            <div style="border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff;">
                                <canvas id="workflowSigPad" width="400" height="150" style="width: 100%; height: 150px; background:#fff; cursor:crosshair;"></canvas>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="clearWorkflowSigBtn" style="font-size:11px; font-weight:600;"><i class="bi bi-eraser me-1"></i>Clear Pad</button>
                            <input type="hidden" name="signature_data" id="workflowSigData">
                        </div>

                        <!-- 3. Upload Signature File -->
                        <div id="sigUploadContainer" style="display: none;">
                            <input type="file" name="signature_file" class="form-control" accept="image/*">
                            <div class="small text-muted mt-1">Upload an image of your signature (Max 2MB).</div>
                        </div>

                        <!-- Save to profile checkbox -->
                        <div class="form-check mt-3" id="saveToProfileWrapper" style="display: none;">
                            <input class="form-check-input" type="checkbox" name="save_to_profile" id="saveToProfileCheck" value="1">
                            <label class="form-check-label small text-secondary" for="saveToProfileCheck">
                                Save this signature to my profile for future use
                            </label>
                        </div>
                    </div>
                    @endif

                    @if($canExecuteAction)
                        <button type="submit" class="btn btn-primary fw-bold px-4 py-2 w-100" style="border-radius: 8px;">Update State</button>
                    @else
                        <button type="button" class="btn btn-secondary fw-bold px-4 py-2 w-100" style="border-radius: 8px; opacity: 0.65; cursor: not-allowed;" disabled>Action Restricted (View Only)</button>
                    @endif
                </form>
            </div>

            <!-- Forward Document Action -->
            @if($canExecuteAction)
            <div class="card shadow-sm border-0 p-4 mb-4" style="border-radius: 12px; background: var(--panel); border: 1px solid var(--panel-border) !important;">
                <h5 class="fw-bold mb-3" style="color: var(--accent-cyan);"><i class="bi bi-arrow-right-short"></i> Forward Document</h5>
                <p class="small text-muted mb-3" style="margin-top:-8px;">Delegate or forward this document to another recipient if you are unavailable.</p>
                <form action="{{ route('documents.forward', $document->id) }}" method="POST">
                    @csrf
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold text-secondary">Select Office</label>
                        <select id="forwardOfficeSelect" class="form-select" required>
                            <option value="">-- Choose Office --</option>
                            @foreach(\App\Models\Office::orderBy('name', 'asc')->get() as $office)
                                <option value="{{ $office->id }}">{{ $office->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold text-secondary">Select New Receiver</label>
                        <select name="new_receiver_user_id" id="forwardReceiverSelect" class="form-select" required disabled>
                            <option value="">-- Select Office First --</option>
                        </select>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold text-secondary">Reason for Forwarding</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="Describe why this document is being forwarded..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-primary fw-bold w-100 py-2" style="border-radius: 8px;">Forward Document</button>
                </form>
            </div>
            @endif
            @endif

            <div class="row mb-4">
                <div class="col-md-12">
                    <small class="text-secondary d-block mb-2" style="font-weight: 700;"><i class="bi bi-shield-lock-fill me-1"></i> Secure Download</small>
                    <a href="{{ route('documents.download', $document->id) }}" class="btn btn-outline-primary px-4 py-2" style="border-radius: 8px; text-decoration: none; height: auto !important;">
                        <i class="bi bi-download me-1"></i> Download File ({{ $document->type }})
                    </a>
                </div>
            </div>

            @if($document->qr_code)
            <div class="mb-4 text-start">
                <small class="text-secondary d-block mb-2" style="font-weight: 700;">Dynamic QR Code (Scan to View)</small>
                @php
                    $qrValue = $document->qr_code;
                    $qrUrl = '';
                    if (str_contains($qrValue, 'qr_codes/')) {
                        $qrUrl = asset('storage/' . $qrValue);
                    } else {
                        try {
                            $qrObj = new \Endroid\QrCode\QrCode(route('documents.show', $document->id), size: 300);
                            $writer = new \Endroid\QrCode\Writer\PngWriter();
                            $result = $writer->write($qrObj);
                            $qrUrl = 'data:image/png;base64,' . base64_encode($result->getString());
                        } catch (\Exception $e) {
                            $qrUrl = '';
                        }
                    }
                @endphp
                @if($qrUrl)
                    <img src="{{ $qrUrl }}" alt="QR Code" class="img-fluid" style="max-width: 150px; border-radius: 8px; border: 1px solid var(--panel-border); padding: 8px; background: #FFFFFF;">
                @else
                    <div style="font-size:0.9rem; color:var(--text-muted);"><i class="bi bi-qr-code"></i> Code: {{ $document->qr_code }}</div>
                @endif

                <div class="mt-2 text-start">
                    <span class="small text-secondary d-block fw-bold mb-1">QR Code Status</span>
                    @php
                        $qrStatusClass = match($document->qr_status ?? 'Not Scanned') {
                            'Scanned' => 'bg-info text-dark',
                            'Verified' => 'bg-primary text-white',
                            'Accessed' => 'bg-success text-white',
                            default => 'bg-secondary text-white',
                        };
                    @endphp
                    <span class="badge {{ $qrStatusClass }}" style="font-weight: 600; font-size: 0.85rem;">{{ $document->qr_status ?? 'Not Scanned' }}</span>
                </div>

                <div class="mt-3">
                    <a href="{{ route('documents.qr-label', $document->id) }}?autoprint=1" target="_blank" class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-2" style="font-weight: 600; border-radius: 8px;">
                        <i class="bi bi-printer"></i> Print QR Routing Label
                    </a>
                </div>
            </div>
            @endif

            {{-- Visual Routing Timeline (Courier-Style) --}}
            <h6 class="fw-bold mb-3 mt-4" style="color: var(--accent-navy);"><i class="bi bi-diagram-3 me-2 text-primary"></i>Document Journey Timeline</h6>
            <div class="mb-4">
                @php
                    $routings = $document->routings()->with(['fromOffice','toOffice','receiverUser','senderUser','forwardedFromUser'])->orderBy('sort_order','asc')->orderBy('id','asc')->get();
                    $lastIdx  = $routings->count() - 1;
                @endphp

                @if($routings->isEmpty())
                    <div class="text-muted text-center py-4" style="font-size:0.9rem;">No routing steps recorded yet.</div>
                @else
                <div class="position-relative" style="padding-left: 48px;">
                    {{-- Vertical line --}}
                    <div style="position:absolute; left:15px; top:0; bottom:0; width:2px; background: linear-gradient(to bottom, var(--accent-cyan) 0%, #e2e8f0 100%);"></div>

                    @foreach($routings as $index => $routing)
                        @php
                            $isEnd     = ($index === $lastIdx);
                            $stepNum   = $index + 1;
                            $rStatus   = strtolower($routing->status);
                            $slaStatus = $routing->computed_sla_status ?? 'on_time';

                            $stepDotColor = match($rStatus) {
                                'approved','completed' => '#10b981',
                                'pending'             => '#3b82f6',
                                'waiting'             => '#94a3b8',
                                'returned','rejected' => '#ef4444',
                                'forwarded'           => '#8b5cf6',
                                default               => '#64748b',
                            };

                            $statusBadge = match($rStatus) {
                                'approved','completed' => 'bg-success text-white',
                                'pending'             => 'bg-primary text-white',
                                'waiting'             => 'bg-light border text-muted',
                                'returned','rejected' => 'bg-danger text-white',
                                'forwarded'           => 'bg-info text-dark',
                                default               => 'bg-secondary text-white',
                            };

                            $slaBadge = match($slaStatus) {
                                'overdue'           => ['class' => 'bg-danger text-white',    'label' => 'Overdue'],
                                'near_due'          => ['class' => 'bg-warning text-dark',    'label' => 'Near Due'],
                                'completed_overdue' => ['class' => 'bg-secondary text-white', 'label' => 'Completed Late'],
                                'completed_on_time' => ['class' => 'bg-success text-white',   'label' => '✓ On Time'],
                                default             => ['class' => 'bg-success text-white',   'label' => '✓ On Time'],
                            };
                        @endphp

                        <div class="mb-4 position-relative">
                            {{-- Step dot --}}
                            <div style="position:absolute; left:-33px; top:4px; width:20px; height:20px; border-radius:50%; background:{{ $stepDotColor }}; border:3px solid #fff; box-shadow:0 0 0 2px {{ $stepDotColor }};"></div>

                            {{-- Step Card --}}
                            <div class="card" style="border-radius:10px; border:1px solid var(--panel-border); background:var(--panel);">
                                {{-- Card Header --}}
                                <div class="card-header d-flex justify-content-between align-items-center" style="border-radius:10px 10px 0 0; background: var(--bg); border-bottom:1px solid var(--panel-border); padding:12px 16px;">
                                    <div class="d-flex align-items-center gap-2">
                                        @if($isEnd)
                                            <span><i class="bi bi-flag-fill text-success"></i></span>
                                            <strong style="color:var(--accent-navy); font-size:0.9rem;">Final Destination: {{ $routing->toOffice?->name ?? 'N/A' }}</strong>
                                        @else
                                            <span class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:20px;height:20px;font-size:10px;">{{ $stepNum }}</span>
                                            <strong style="color:var(--accent-navy); font-size:0.9rem;">{{ $routing->toOffice?->name ?? 'N/A' }}</strong>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {{ $slaBadge['class'] }}" style="font-size:0.65rem;">{{ $slaBadge['label'] }}</span>
                                        <span class="badge {{ $statusBadge }}">{{ ucfirst($routing->status) }}</span>
                                    </div>
                                </div>

                                {{-- Card Body: Tracking Grid --}}
                                <div class="card-body" style="padding:14px 16px;">
                                    <div class="row g-3">

                                        {{-- Column 1: People --}}
                                        <div class="col-md-4">
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Sender</small>
                                                <span style="font-size:0.82rem; font-weight:600; color:var(--text-main);">
                                                    {{ $routing->senderUser?->name ?? ($routing->forwardedFromUser?->name ?? ($index === 0 ? ($document->uploader?->name ?? 'System') : 'N/A')) }}
                                                </span>
                                            </div>
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Receiver</small>
                                                <span style="font-size:0.82rem; font-weight:600; color:var(--text-main);">{{ $routing->receiverUser?->name ?? 'Unassigned' }}</span>
                                            </div>
                                            @if($routing->forwardedFromUser)
                                            <div>
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Delegated From</small>
                                                <span style="font-size:0.82rem; color:#8b5cf6;"><i class="bi bi-reply-fill"></i> {{ $routing->forwardedFromUser->name }}</span>
                                            </div>
                                            @endif
                                        </div>

                                        {{-- Column 2: Offices --}}
                                        <div class="col-md-4">
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">From Office</small>
                                                <span style="font-size:0.82rem; font-weight:600; color:var(--text-main);">{{ $routing->fromOffice?->name ?? 'N/A' }}</span>
                                            </div>
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Destination Office</small>
                                                <span style="font-size:0.82rem; font-weight:600; color:var(--text-main);">{{ $routing->toOffice?->name ?? 'N/A' }}</span>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Action Label</small>
                                                <span style="font-size:0.82rem; color:var(--text-dim);">{{ $routing->action_label ?? ucfirst($routing->status) }}</span>
                                            </div>
                                        </div>

                                        {{-- Column 3: Timestamps & Duration --}}
                                        <div class="col-md-4">
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Assigned / Sent</small>
                                                <span style="font-size:0.78rem; color:var(--text-main);">{{ $routing->created_at->format('M d, Y') }}<br><span style="color:var(--text-dim);">{{ $routing->created_at->format('h:i A') }}</span></span>
                                            </div>
                                            @if($routing->received_at)
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Date / Time Received</small>
                                                <span style="font-size:0.78rem; color:#10b981; font-weight:600;">{{ $routing->received_at->format('M d, Y') }}<br>{{ $routing->received_at->format('h:i A') }}</span>
                                            </div>
                                            @endif
                                            @if($routing->released_at)
                                            <div class="mb-2">
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Date / Time Released</small>
                                                <span style="font-size:0.78rem; color:#8b5cf6; font-weight:600;">{{ $routing->released_at->format('M d, Y') }}<br>{{ $routing->released_at->format('h:i A') }}</span>
                                            </div>
                                            @endif
                                            @if($routing->processing_duration)
                                            <div>
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Processing Duration</small>
                                                <span style="font-size:0.78rem; color:var(--text-main); font-weight:600;"><i class="bi bi-clock me-1"></i> {{ $routing->processing_duration }}</span>
                                            </div>
                                            @elseif($routing->sla_remaining)
                                            <div>
                                                <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">SLA Remaining</small>
                                                @php $slaIsOverdue = str_starts_with($routing->sla_remaining, '-'); @endphp
                                                <span style="font-size:0.78rem; font-weight:600; color: {{ $slaIsOverdue ? '#ef4444' : '#10b981' }};">{{ $routing->sla_remaining }}</span>
                                            </div>
                                            @endif
                                        </div>

                                    </div>{{-- /row --}}

                                    {{-- Remarks & Signature --}}
                                    @if($routing->notes)
                                    <div class="mt-3 pt-3" style="border-top: 1px dashed var(--panel-border);">
                                        <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Remarks</small>
                                        <p class="mb-0" style="font-size:0.82rem; color:var(--text-main); font-style:italic;">"{{ $routing->notes }}"</p>
                                    </div>
                                    @endif

                                    @if($routing->signature)
                                    <div class="mt-2">
                                        <small class="text-muted d-block" style="font-size:0.65rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Signature Proof</small>
                                        <img src="{{ str_contains($routing->signature, 'data:image') ? $routing->signature : asset('storage/' . $routing->signature) }}" style="max-height:45px; border-radius:4px; background:white; padding:2px; border:1px solid var(--panel-border);">
                                    </div>
                                    @endif
                                </div>{{-- /card-body --}}
                            </div>{{-- /card --}}
                        </div>{{-- /step item --}}
                    @endforeach
                </div>{{-- /timeline wrapper --}}
                @endif
            </div>


            <!-- Activity Timeline -->
            <h6 class="fw-bold mb-4 mt-4" style="color: var(--accent-navy);"><i class="bi bi-list-task me-2 text-primary"></i>Document Lifecycle Audit Trail</h6>
            <div class="position-relative text-start border-start border-2 border-primary ps-4 ms-2">
                @forelse($document->activityLogs()->orderBy('id', 'desc')->get() as $log)
                    <div class="mb-4 position-relative">
                        <!-- Icon indicator on the timeline border -->
                        <span class="position-absolute" style="left: -33px; top: 0; background: var(--panel); border: 2px solid var(--accent-cyan); width: 16px; height: 16px; border-radius: 50%; display: inline-block;"></span>
                        
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark" style="font-size: 0.95rem;">
                                @if(str_contains(strtolower($log->action), 'created'))
                                    <i class="bi bi-cloud-arrow-up text-primary me-2"></i> Document Uploaded / Initialized
                                @elseif(str_contains(strtolower($log->action), 'viewed'))
                                    <i class="bi bi-eye-fill text-info me-2"></i> DOCUMENT VIEWED
                                @elseif(str_contains(strtolower($log->action), 'scanned') || str_contains(strtolower($log->action), 'opened'))
                                    <i class="bi bi-qr-code-scan text-secondary me-2"></i> Document Opened / QR Scanned
                                @elseif(str_contains(strtolower($log->action), 'accepted'))
                                    <i class="bi bi-shield-check text-success me-2"></i> Document Accepted
                                @elseif(str_contains(strtolower($log->action), 'received'))
                                    <i class="bi bi-envelope-open text-success me-2"></i> Received
                                @elseif(str_contains(strtolower($log->action), 'approved'))
                                    <i class="bi bi-check-circle-fill text-success me-2"></i> Approved & Forwarded to Next Hop
                                @elseif(str_contains(strtolower($log->action), 'completed'))
                                    <i class="bi bi-check-all text-success me-2"></i> Completed & Archived
                                @elseif(str_contains(strtolower($log->action), 'rejected'))
                                    <i class="bi bi-x-circle-fill text-danger me-2"></i> Rejected
                                @else
                                    <i class="bi bi-arrow-repeat text-secondary me-2"></i> {{ $log->action }}
                                @endif
                            </span>
                            <small class="text-secondary" style="font-size: 0.75rem;">{{ $log->created_at->format('M d, Y h:i A') }}</small>
                        </div>
                        
                        <div class="small text-secondary" style="font-size: 0.8rem; line-height: 1.5;">
                            @if(str_contains(strtolower($log->action), 'viewed'))
                                <strong>User:</strong> {{ $log->user }} <br>
                                <strong>Document:</strong> {{ $document->title }} <br>
                                <strong>Office:</strong> {{ $log->meta['office'] ?? ($log->meta['department'] ?? 'System') }} <br>
                                <strong>Viewed On:</strong> <br>
                                {{ $log->created_at->format('M d, Y') }} • {{ $log->created_at->format('h:i A') }}
                            @elseif(str_contains(strtolower($log->action), 'accepted'))
                                <strong>Accepted By:</strong> {{ $log->user }} <br>
                                <strong>Office:</strong> {{ $log->meta['department'] ?? 'System' }} <br>
                                <strong>Date:</strong> {{ $log->created_at->format('M d, Y') }} <br>
                                <strong>Time:</strong> {{ $log->created_at->format('h:i A') }} <br>
                                <strong>Verification:</strong> <span class="text-success fw-bold"><i class="bi bi-patch-check-fill"></i> Digital Signature Verified</span>
                                @if(isset($log->meta['signature']) && $log->meta['signature'])
                                    <br><strong>Signature Proof:</strong> <br>
                                    <img src="{{ str_contains($log->meta['signature'], 'data:image') ? $log->meta['signature'] : asset('storage/' . $log->meta['signature']) }}" style="max-height:40px; border-radius:4px; background:white; padding:2px; border:1px solid var(--panel-border); margin-top:4px;">
                                @endif
                                @if(isset($log->meta['notes']) && $log->meta['notes'])
                                    <br><strong>Remarks:</strong> <span class="fst-italic text-dark">"{{ $log->meta['notes'] }}"</span>
                                @endif
                            @else
                                <strong>User:</strong> {{ $log->user }} <br>
                                <strong>Department:</strong> {{ $log->meta['department'] ?? 'System' }} <br>
                                @if(isset($log->meta['signature']) && $log->meta['signature'])
                                    <br><strong>Signature Proof:</strong> <br>
                                    <img src="{{ str_contains($log->meta['signature'], 'data:image') ? $log->meta['signature'] : asset('storage/' . $log->meta['signature']) }}" style="max-height:40px; border-radius:4px; background:white; padding:2px; border:1px solid var(--panel-border); margin-top:4px;">
                                @endif
                                @if(isset($log->meta['notes']) && $log->meta['notes'])
                                    <br><strong>Remarks:</strong> <span class="fst-italic text-dark">"{{ $log->meta['notes'] }}"</span>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-muted py-3">No lifecycle audit trails recorded.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const pendingAtStr = "{{ $activeStep ? ($activeStep->pending_at ? $activeStep->pending_at->toIso8601String() : $activeStep->created_at->toIso8601String()) : '' }}";
        const slaDueAtStr = "{{ $activeStep && $activeStep->sla_due_at ? $activeStep->sla_due_at->toIso8601String() : '' }}";
        
        if (pendingAtStr) {
            const pendingAt = new Date(pendingAtStr);
            const slaDueAt = slaDueAtStr ? new Date(slaDueAtStr) : null;
            
            function updateTimer() {
                const now = new Date();
                
                // Update live clock
                const timeString = now.toLocaleDateString('en-US', {
                    month: 'short', day: 'numeric', year: 'numeric'
                }) + ' ' + now.toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
                });
                const liveClockEl = document.getElementById('current-live-time');
                if (liveClockEl) liveClockEl.textContent = timeString;
                
                // Calculate elapsed time (holding duration)
                const diffMs = now - pendingAt;
                if (diffMs < 0) return;
                
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);
                
                let durationText = '';
                if (diffDays >= 1) {
                    durationText = `Held for ${diffDays} day` + (diffDays > 1 ? 's' : '');
                } else if (diffHours >= 1) {
                    durationText = `Held for ${diffHours} hour` + (diffHours > 1 ? 's' : '');
                } else {
                    durationText = `Held for ${diffMins} minute` + (diffMins !== 1 ? 's' : '');
                }
                
                const badgeEl = document.getElementById('holding-duration-badge');
                if (badgeEl) {
                    badgeEl.textContent = durationText;
                    
                    if (slaDueAt && now > slaDueAt) {
                        badgeEl.className = 'badge bg-danger px-2.5 py-1.5 fs-6';
                    } else {
                        badgeEl.className = 'badge bg-warning text-dark px-2.5 py-1.5 fs-6';
                    }
                }
            }
            
            updateTimer();
            setInterval(updateTimer, 60000);
        }

        // Dynamic receiver loading for Forward Document
        const forwardOfficeSelect = document.getElementById('forwardOfficeSelect');
        const forwardReceiverSelect = document.getElementById('forwardReceiverSelect');
        if (forwardOfficeSelect && forwardReceiverSelect) {
            forwardOfficeSelect.addEventListener('change', async function() {
                const officeId = this.value;
                forwardReceiverSelect.innerHTML = '<option value="">-- Loading Staff --</option>';
                forwardReceiverSelect.disabled = true;
                
                if (!officeId) {
                    forwardReceiverSelect.innerHTML = '<option value="">-- Select Office First --</option>';
                    return;
                }
                
                try {
                    const response = await fetch(`/api/offices/${officeId}/staff`);
                    const data = await response.json();
                    const staff = data.staff || [];
                    
                    if (staff.length === 0) {
                        forwardReceiverSelect.innerHTML = '<option value="">No available receivers for this office.</option>';
                    } else {
                        forwardReceiverSelect.innerHTML = '<option value="">-- Choose Recipient --</option>';
                        staff.forEach(user => {
                            if (user.id != "{{ session('user_id') }}") {
                                const opt = document.createElement('option');
                                opt.value = user.id;
                                opt.textContent = `${user.name} (${user.role})`;
                                forwardReceiverSelect.appendChild(opt);
                            }
                        });
                        forwardReceiverSelect.disabled = false;
                    }
                } catch(e) {
                    console.error(e);
                    forwardReceiverSelect.innerHTML = '<option value="">Error loading staff</option>';
                }
            });
        }

        // Signature options canvas handling
        const statusSelect = document.querySelector('select[name="status"]');
        const signatureSection = document.getElementById('workflow-signature-section');
        const sigOptProfile = document.getElementById('sigOptProfile');
        const sigOptDraw = document.getElementById('sigOptDraw');
        const sigOptUpload = document.getElementById('sigOptUpload');
        
        const sigProfileContainer = document.getElementById('sigProfileContainer');
        const sigDrawContainer = document.getElementById('sigDrawContainer');
        const sigUploadContainer = document.getElementById('sigUploadContainer');
        const saveToProfileWrapper = document.getElementById('saveToProfileWrapper');

        const signatureRequired = {{ ($activeStep && $activeStep->signature_required) ? 'true' : 'false' }};

        function toggleSignatureSection() {
            if (!statusSelect || !signatureSection) return;
            const status = statusSelect.value;
            const needsSig = ['Approved', 'Accepted', 'Endorsed', 'Completed'].includes(status);
            
            if (signatureRequired && needsSig) {
                signatureSection.style.display = 'block';
            } else {
                signatureSection.style.display = 'none';
            }
        }

        if (statusSelect) {
            statusSelect.addEventListener('change', toggleSignatureSection);
            toggleSignatureSection();
        }

        // Handle radio selection change
        document.querySelectorAll('input[name="signature_option"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const opt = this.value;
                if (sigProfileContainer) sigProfileContainer.style.display = opt === 'profile' ? 'block' : 'none';
                if (sigDrawContainer) sigDrawContainer.style.display = opt === 'draw' ? 'block' : 'none';
                if (sigUploadContainer) sigUploadContainer.style.display = opt === 'upload' ? 'block' : 'none';
                
                if (saveToProfileWrapper) saveToProfileWrapper.style.display = (opt === 'draw' || opt === 'upload') ? 'block' : 'none';

                if (opt === 'draw') {
                    resizeWorkflowCanvas();
                }
            });
        });

        // Initialize Signature Pad for workflow
        const workflowCanvas = document.getElementById('workflowSigPad');
        let workflowSigPad = null;

        function resizeWorkflowCanvas() {
            if (!workflowCanvas) return;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            workflowCanvas.width = workflowCanvas.offsetWidth * ratio;
            workflowCanvas.height = workflowCanvas.offsetHeight * ratio;
            workflowCanvas.getContext("2d").scale(ratio, ratio);
            if (workflowSigPad) workflowSigPad.clear();
        }

        if (workflowCanvas && typeof SignaturePad !== 'undefined') {
            workflowSigPad = new SignaturePad(workflowCanvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)'
            });

            document.getElementById('clearWorkflowSigBtn')?.addEventListener('click', function() {
                workflowSigPad.clear();
            });

            // Form Submit validation
            const workflowForm = document.getElementById('workflowActionForm');
            workflowForm?.addEventListener('submit', function(e) {
                const status = statusSelect.value;
                const needsSig = ['Approved', 'Accepted', 'Endorsed', 'Completed'].includes(status);
                
                if (signatureRequired && needsSig) {
                    const selectedOpt = document.querySelector('input[name="signature_option"]:checked')?.value;
                    if (selectedOpt === 'draw') {
                        if (workflowSigPad.isEmpty()) {
                            e.preventDefault();
                            alert("Digital signature is required. Please sign on the canvas.");
                            return false;
                        }
                        document.getElementById('workflowSigData').value = workflowSigPad.toDataURL();
                    } else if (selectedOpt === 'upload') {
                        const fileInput = document.querySelector('input[name="signature_file"]');
                        if (!fileInput.files || fileInput.files.length === 0) {
                            e.preventDefault();
                            alert("Digital signature file is required. Please upload an image.");
                            return false;
                        }
                    }
                }
            });

            window.addEventListener("resize", resizeWorkflowCanvas);
        }

        // Modal Signature Canvas Handling
        const modalSigOptProfile = document.getElementById('modalSigOptProfile');
        const modalSigOptDraw = document.getElementById('modalSigOptDraw');
        const modalSigOptUpload = document.getElementById('modalSigOptUpload');

        const modalSigProfileContainer = document.getElementById('modalSigProfileContainer');
        const modalSigDrawContainer = document.getElementById('modalSigDrawContainer');
        const modalSigUploadContainer = document.getElementById('modalSigUploadContainer');
        const modalSaveToProfileWrapper = document.getElementById('modalSaveToProfileWrapper');

        document.querySelectorAll('input[name="modal_signature_option"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const opt = this.value;
                if (modalSigProfileContainer) modalSigProfileContainer.style.display = opt === 'profile' ? 'block' : 'none';
                if (modalSigDrawContainer) modalSigDrawContainer.style.display = opt === 'draw' ? 'block' : 'none';
                if (modalSigUploadContainer) modalSigUploadContainer.style.display = opt === 'upload' ? 'block' : 'none';
                if (modalSaveToProfileWrapper) modalSaveToProfileWrapper.style.display = (opt === 'draw' || opt === 'upload') ? 'block' : 'none';

                if (opt === 'draw') {
                    resizeModalCanvas();
                }
            });
        });

        const modalCanvas = document.getElementById('modalSigCanvas');
        let modalSigPad = null;

        function resizeModalCanvas() {
            if (!modalCanvas) return;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            modalCanvas.width = modalCanvas.offsetWidth * ratio;
            modalCanvas.height = modalCanvas.offsetHeight * ratio;
            modalCanvas.getContext("2d").scale(ratio, ratio);
            if (modalSigPad) modalSigPad.clear();
        }

        if (modalCanvas && typeof SignaturePad !== 'undefined') {
            modalSigPad = new SignaturePad(modalCanvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)'
            });

            document.getElementById('clearModalSigBtn')?.addEventListener('click', function() {
                modalSigPad.clear();
            });

            const acceptModalEl = document.getElementById('acceptDocumentModal');
            acceptModalEl?.addEventListener('shown.bs.modal', function () {
                resizeModalCanvas();
            });

            const acceptForm = document.getElementById('acceptDocumentForm');
            acceptForm?.addEventListener('submit', function(e) {
                const selectedOpt = document.querySelector('input[name="modal_signature_option"]:checked')?.value;
                if (selectedOpt === 'profile') {
                    const hasProfileSig = {{ (auth()->user() && auth()->user()->signature) ? 'true' : 'false' }};
                    if (!hasProfileSig) {
                        e.preventDefault();
                        alert("No profile signature found. Please choose another signature option.");
                        return false;
                    }
                    const hiddenOpt = document.createElement('input');
                    hiddenOpt.type = 'hidden';
                    hiddenOpt.name = 'signature_option';
                    hiddenOpt.value = 'profile';
                    acceptForm.appendChild(hiddenOpt);
                } else if (selectedOpt === 'draw') {
                    if (modalSigPad.isEmpty()) {
                        e.preventDefault();
                        alert("Drawn signature is required. Please sign on the canvas.");
                        return false;
                    }
                    document.getElementById('modalSigData').value = modalSigPad.toDataURL();
                    
                    const hiddenOpt = document.createElement('input');
                    hiddenOpt.type = 'hidden';
                    hiddenOpt.name = 'signature_option';
                    hiddenOpt.value = 'draw';
                    acceptForm.appendChild(hiddenOpt);
                } else if (selectedOpt === 'upload') {
                    const fileInput = document.querySelector('#modalSigUploadContainer input[name="signature_file"]');
                    if (!fileInput.files || fileInput.files.length === 0) {
                        e.preventDefault();
                        alert("Digital signature file is required. Please upload an image.");
                        return false;
                    }
                    
                    const hiddenOpt = document.createElement('input');
                    hiddenOpt.type = 'hidden';
                    hiddenOpt.name = 'signature_option';
                    hiddenOpt.value = 'upload';
                    acceptForm.appendChild(hiddenOpt);
                }
            });
        }
    });
</script>

<!-- Accept Document Signature Modal -->
<div class="modal fade" id="acceptDocumentModal" tabindex="-1" aria-labelledby="acceptDocumentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel) !important; border: 1px solid var(--panel-border); border-radius: 16px; color: var(--text-main);">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="acceptDocumentModalLabel" style="color: var(--accent-navy);"><i class="bi bi-shield-check text-success me-2"></i>Accept & Acknowledge Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('documents.workflowAction', $document->id) }}" method="POST" enctype="multipart/form-data" id="acceptDocumentForm">
                @csrf
                <input type="hidden" name="status" value="Accepted">
                <div class="modal-body pt-3 text-start">
                    <p class="small text-muted mb-3" style="font-size: 13px; line-height: 1.5;">
                        To acknowledge custody and officially accept this document, please select a signature option and confirm.
                    </p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Acknowledge Remarks / Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Enter remarks..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary d-block">Signature Option</label>
                        <div class="d-flex gap-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modal_signature_option" id="modalSigOptProfile" value="profile" checked>
                                <label class="form-check-label small" for="modalSigOptProfile">Profile</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modal_signature_option" id="modalSigOptDraw" value="draw">
                                <label class="form-check-label small" for="modalSigOptDraw">Draw</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modal_signature_option" id="modalSigOptUpload" value="upload">
                                <label class="form-check-label small" for="modalSigOptUpload">Upload</label>
                            </div>
                        </div>

                        <!-- 1. Profile Signature Container -->
                        <div id="modalSigProfileContainer" class="p-3 bg-light rounded text-center border">
                            @if(auth()->user() && auth()->user()->signature)
                                <img src="{{ asset('storage/' . auth()->user()->signature) }}" alt="Profile Signature" style="max-height: 60px; background:white; padding:4px; border:1px solid #ddd; border-radius:4px;">
                                <div class="small text-muted mt-1">Using your saved profile signature.</div>
                            @else
                                <span class="text-danger small">No saved profile signature. Please choose another option.</span>
                            @endif
                        </div>

                        <!-- 2. Draw Signature Canvas -->
                        <div id="modalSigDrawContainer" class="text-center" style="display: none;">
                            <div style="border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff;">
                                <canvas id="modalSigCanvas" width="400" height="150" style="width: 100%; height: 150px; background:#fff; cursor:crosshair;"></canvas>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="clearModalSigBtn" style="font-size:11px; font-weight:600;"><i class="bi bi-eraser me-1"></i>Clear Pad</button>
                            <input type="hidden" name="signature_data" id="modalSigData">
                        </div>

                        <!-- 3. Upload Signature File -->
                        <div id="modalSigUploadContainer" style="display: none;">
                            <input type="file" name="signature_file" class="form-control" accept="image/*">
                            <div class="small text-muted mt-1">Upload your signature image (Max 2MB).</div>
                        </div>

                        <!-- Save to profile checkbox -->
                        <div class="form-check mt-3" id="modalSaveToProfileWrapper" style="display: none;">
                            <input class="form-check-input" type="checkbox" name="save_to_profile" id="modalSaveToProfileCheck" value="1">
                            <label class="form-check-label small text-secondary" for="modalSaveToProfileCheck">
                                Save signature to my profile
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-size:12px; font-weight:600; border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-success" style="font-size:12px; font-weight:600; border-radius: 8px;">Confirm Acceptance</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection