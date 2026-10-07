@extends('layouts.app')

@section('title', 'Document Details - ' . ($document->tracking_number ?? $document->title))

@section('head')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script>
<style>
    /* NAAP Enterprise Documents Module Consistency */
    .doc-details-workspace {
        color: var(--text-main, #0F172A);
        font-family: inherit;
    }

    .detail-card {
        background: var(--panel, #FFFFFF);
        border: 1px solid var(--panel-border, #E2E8F0);
        border-radius: var(--radius-lg, 12px);
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .detail-card-header {
        background: #F8FAFC;
        border-bottom: 1px solid var(--panel-border, #E2E8F0);
        padding: 14px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .detail-card-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--accent-navy, #0F172A);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .detail-card-body {
        padding: 20px;
    }

    /* Meta grid items */
    .meta-item-box {
        background: #F8FAFC;
        border: 1px solid var(--panel-border, #E2E8F0);
        border-radius: 8px;
        padding: 12px 14px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .meta-item-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748B;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .meta-item-value {
        font-size: 13.5px;
        font-weight: 600;
        color: #0F172A;
        line-height: 1.35;
        word-break: break-word;
    }

    .meta-item-sub {
        font-size: 11px;
        color: #64748B;
        margin-top: 2px;
    }

    /* Tracking Code Badge */
    .tracking-code-pill {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 12px;
        color: #334155;
        background: #F1F5F9;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #CBD5E1;
        letter-spacing: -0.01em;
        display: inline-block;
    }

    /* File Type Icons matching Documents page */
    .file-type-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 11px;
        color: #ffffff;
        text-transform: uppercase;
        flex-shrink: 0;
    }
    .file-pdf { background: #EF4444; }
    .file-docx, .file-doc { background: #2563EB; }
    .file-xlsx, .file-xls { background: #10B981; }
    .file-zip, .file-rar { background: #F59E0B; }
    .file-png, .file-jpg, .file-jpeg { background: #8B5CF6; }
    .file-default { background: #64748B; }

    /* SLA Badges matching Documents page */
    .sla-badge {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
        display: inline-block;
    }
    .sla-on-track {
        background: rgba(16, 185, 129, 0.08);
        color: #047857;
    }
    .sla-nearing {
        background: rgba(245, 158, 11, 0.12);
        color: #B45309;
    }
    .sla-overdue {
        background: rgba(239, 68, 68, 0.08);
        color: #B91C1C;
    }
    .sla-na {
        background: rgba(148, 163, 184, 0.12);
        color: #64748B;
    }

    /* Priority Indicator */
    .priority-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 4px;
        background: #F1F5F9;
    }
    .priority-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
    }

    /* Courier Route Steps */
    .tracking-steps-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        overflow-x: auto;
        padding: 16px 8px;
    }

    .tracking-step-node {
        flex: 1;
        min-width: 140px;
        text-align: center;
        position: relative;
    }

    .tracking-step-circle {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        margin: 0 auto 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 700;
        background: #F8FAFC;
        border: 2px solid #CBD5E1;
        color: #64748B;
        transition: all 0.2s ease;
    }

    .tracking-step-node.completed .tracking-step-circle {
        background: #10B981;
        border-color: #10B981;
        color: #FFFFFF;
    }

    .tracking-step-node.active .tracking-step-circle {
        background: #2563EB;
        border-color: #2563EB;
        color: #FFFFFF;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.18);
    }

    .tracking-step-title {
        font-size: 12px;
        font-weight: 700;
        color: #0F172A;
        margin-bottom: 2px;
        line-height: 1.25;
    }

    .tracking-step-sub {
        font-size: 11px;
        color: #64748B;
    }

    .tracking-step-arrow {
        flex: 0 0 24px;
        text-align: center;
        color: #94A3B8;
        font-size: 18px;
    }

    /* Enterprise Table for Routing & Activities */
    .details-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }

    .details-table th {
        background: #F8FAFC;
        padding: 10px 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748B;
        border-bottom: 1px solid var(--panel-border, #E2E8F0);
        letter-spacing: 0.05em;
        white-space: nowrap;
    }

    .details-table td {
        padding: 12px 12px;
        border-bottom: 1px solid var(--panel-border, #E2E8F0);
        color: #0F172A;
        vertical-align: middle;
    }

    .details-table tbody tr:hover {
        background: #F8FAFC;
    }

    /* Timeline styling */
    .audit-timeline {
        position: relative;
        padding-left: 28px;
    }
    .audit-timeline::before {
        content: '';
        position: absolute;
        left: 9px;
        top: 6px;
        bottom: 6px;
        width: 2px;
        background: #E2E8F0;
    }
    .audit-timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    .audit-timeline-item:last-child {
        margin-bottom: 0;
    }
    .audit-timeline-dot {
        position: absolute;
        left: -28px;
        top: 2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #FFFFFF;
        border: 2px solid #2563EB;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: #2563EB;
        z-index: 2;
    }
</style>
@endsection

@section('content')
@php
    // Extension & File Type
    $ext = strtolower($document->type ?? '');
    $iconClass = match($ext) {
        'pdf' => 'file-pdf',
        'docx', 'doc' => 'file-docx',
        'xlsx', 'xls' => 'file-xlsx',
        'zip', 'rar' => 'file-zip',
        'png', 'jpg', 'jpeg' => 'file-png',
        default => 'file-default'
    };

    // Status Key & Badge Styling
    $statusKey = strtolower(str_replace(' ', '_', $document->status ?? 'pending'));
    $statusBadgeClass = match($statusKey) {
        'completed' => 'bg-success text-white',
        'pending' => 'bg-warning text-dark',
        'in_transit', 'in transit' => 'bg-info text-dark',
        'received' => 'bg-primary text-white',
        'approved', 'accepted', 'endorsed' => 'bg-success text-white',
        'processing', 'under_review', 'under review', 'on process' => 'bg-info text-white',
        'for_approval', 'for approval' => 'bg-warning text-dark',
        'reverted', 'returned', 'rejected' => 'bg-danger text-white',
        'archived' => 'bg-secondary text-white',
        default => 'bg-secondary text-white'
    };

    // Strict Chronological Validation: created <= received <= processed <= completed
    $createdTs   = $document->created_at ? $document->created_at->timestamp : null;
    $receivedTs  = $document->received_at ? $document->received_at->timestamp : null;
    $processedTs = $document->processed_at ? $document->processed_at->timestamp : null;
    $completedTs = $document->completed_at ? $document->completed_at->timestamp : null;

    $timestampsValid = true;
    if ($createdTs && $receivedTs && $receivedTs < $createdTs) {
        $timestampsValid = false;
    }
    if ($receivedTs && $processedTs && $processedTs < $receivedTs) {
        $timestampsValid = false;
    }
    if ($createdTs && $completedTs && $completedTs < $createdTs) {
        $timestampsValid = false;
    }

    // Uniform SLA Calculation matching Documents, Dashboard, and Reports
    $isCompleted = in_array($statusKey, ['completed', 'archived']);
    $slaRemainingText = 'N/A';
    $slaStatusClass = 'sla-na';
    $slaStatusLabel = 'N/A';
    $isOverdue = false;
    $overdueDuration = null;
    $slaPercentRemaining = 100;

    if (!$document->due_date || !$timestampsValid) {
        $slaRemainingText = 'N/A';
        $slaStatusClass = 'sla-na';
        $slaStatusLabel = 'N/A';
    } elseif ($isCompleted) {
        if ($completedTs && $document->due_date && $completedTs > $document->due_date->timestamp) {
            $slaRemainingText = 'Overdue at Completion';
            $slaStatusClass = 'sla-overdue';
            $slaStatusLabel = 'Completed Beyond SLA';
            $isOverdue = true;
        } else {
            $slaRemainingText = 'Completed Within Limit';
            $slaStatusClass = 'sla-on-track';
            $slaStatusLabel = 'Completed Within SLA';
        }
    } else {
        if ($document->due_date->isPast()) {
            $isOverdue = true;
            $diffHours = (int) now()->diffInHours($document->due_date, false);
            $diffDays = (int) now()->diffInDays($document->due_date, false);
            $absDays = abs($diffDays);
            $overdueDuration = $absDays > 0 ? "{$absDays}d overdue" : abs($diffHours) . "h overdue";
            $slaRemainingText = $overdueDuration;
            $slaStatusClass = 'sla-overdue';
            $slaStatusLabel = 'Overdue';
            $slaPercentRemaining = 0;
        } elseif (now()->diffInHours($document->due_date, false) <= 48) {
            $diffH = (int) now()->diffInHours($document->due_date, false);
            $diffM = abs((int) (now()->diffInMinutes($document->due_date, false) % 60));
            $slaRemainingText = $diffH > 0 ? "{$diffH}h {$diffM}m" : "{$diffM}m";
            $slaStatusClass = 'sla-nearing';
            $slaStatusLabel = 'Nearing SLA';

            $totalSecs = $document->created_at ? $document->created_at->diffInSeconds($document->due_date) : 0;
            $remainingSecs = now()->diffInSeconds($document->due_date, false);
            if ($totalSecs > 0 && $remainingSecs > 0) {
                $slaPercentRemaining = max(0, min(100, round(($remainingSecs / $totalSecs) * 100)));
            }
        } else {
            $diffD = (int) now()->diffInDays($document->due_date, false);
            $slaRemainingText = $diffD > 0 ? "{$diffD} days left" : "On Track";
            $slaStatusClass = 'sla-on-track';
            $slaStatusLabel = 'On Track';

            $totalSecs = $document->created_at ? $document->created_at->diffInSeconds($document->due_date) : 0;
            $remainingSecs = now()->diffInSeconds($document->due_date, false);
            if ($totalSecs > 0 && $remainingSecs > 0) {
                $slaPercentRemaining = max(0, min(100, round(($remainingSecs / $totalSecs) * 100)));
            }
        }
    }

    // Priority color
    $priorityColor = match(strtolower($document->priority ?? 'normal')) {
        'urgent' => '#EF4444',
        'high' => '#F59E0B',
        'low' => '#10B981',
        default => '#3B82F6'
    };

    // User permissions & workflow capability
    $user = auth()->user() ?? \App\Models\User::find(session('user_id'));
    $userRole = strtoupper($user?->role ?? session('user_role') ?? '');
    $isAdmin = in_array($userRole, ['ADMIN', 'ADMINISTRATOR', 'SUPER ADMINISTRATOR']);
    
    // Resolve active routing step from database records
    $activeStep = $activeRouting ?? $document->routings->where('status', 'Pending')->first();
    $latestStep = $document->routings->sortByDesc('id')->first();

    // Actual Current Location (office and receiver)
    $currentOfficeName = $document->currentOffice?->name ?? ($activeStep?->toOffice?->name ?? 'Unassigned');
    $currentReceiverName = $document->receiverUser?->name ?? ($activeStep?->receiverUser?->name ?? 'Unassigned');
    $currentReceiverDept = $document->receiverUser?->department?->name ?? ($activeStep?->receiverUser?->department?->name ?? null);

    // Next Destination
    $nextDestination = $document->destinationOffice?->name ?? 'None';
    if ($activeStep && $activeStep->toOffice && $activeStep->toOffice->name !== $currentOfficeName) {
        $nextDestination = $activeStep->toOffice->name;
    } elseif ($isCompleted) {
        $nextDestination = 'Final Destination Reached';
    }

    // Next Action Required
    $nextActionRequired = match($statusKey) {
        'completed' => 'Workflow Completed & Archived',
        'rejected' => 'Document Rejected',
        'archived' => 'Document Archived',
        'received' => 'Awaiting Processing / Review',
        'under_review', 'under review' => 'Awaiting Approval or Endorsement',
        'pending' => ($activeStep && $activeStep->receiver_user_id) ? 'Awaiting Acceptance / Receipt' : 'Awaiting Receiver Assignment',
        default => 'Action in Progress'
    };

    // Last Known Event from real Activity Logs or Routing records
    $lastLog = $document->activityLogs->first();
    $lastEventName = $lastLog ? $lastLog->action : ($latestStep ? "Step {$latestStep->status}" : 'Document Created');
    $lastEventTime = $lastLog ? $lastLog->created_at : ($latestStep ? $latestStep->updated_at : $document->created_at);

    // Actual Total Processing Time calculation
    $actualProcessingTime = 'N/A';
    if ($timestampsValid && $document->created_at) {
        $endTs = $document->completed_at ?? now();
        $diffSeconds = $document->created_at->diffInSeconds($endTs);
        $d = floor($diffSeconds / 86400);
        $h = floor(($diffSeconds % 86400) / 3600);
        $m = floor(($diffSeconds % 3600) / 60);
        if ($d > 0) {
            $actualProcessingTime = "{$d}d {$h}h {$m}m";
        } elseif ($h > 0) {
            $actualProcessingTime = "{$h}h {$m}m";
        } else {
            $actualProcessingTime = "{$m}m";
        }
    }

    // QR Image source resolution
    $qrUrl = '';
    if ($document->qr_code) {
        if (str_contains($document->qr_code, 'qr_codes/')) {
            $qrUrl = asset('storage/' . $document->qr_code);
        } else {
            try {
                $qrObj = new \Endroid\QrCode\QrCode(route('documents.show', $document->id), size: 300);
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $qrResult = $writer->write($qrObj);
                $qrUrl = 'data:image/png;base64,' . base64_encode($qrResult->getString());
            } catch (\Exception $e) {
                $qrUrl = '';
            }
        }
    }

    // Filter real QR scans from activity logs
    $qrScanLogs = $document->activityLogs->filter(function($log) {
        $act = strtolower($log->action);
        return str_contains($act, 'scan') || str_contains($act, 'qr');
    });
    $totalQrScans = $qrScanLogs->count();
    $lastQrScan = $qrScanLogs->first();
@endphp

<div class="doc-details-workspace p-3 p-md-4">
    <!-- Breadcrumb Navigation & Top Action Controls -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size: 0.85rem;">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">NAAP Enterprise</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('documents.index') }}" class="text-decoration-none text-muted">Documents</a></li>
                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Document Details</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2 mt-1">
                <span class="file-type-icon {{ $iconClass }}">{{ $document->type ?? 'DOC' }}</span>
                <h1 class="h4 fw-bold mb-0 text-dark">{{ $document->title }}</h1>
            </div>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <a href="{{ route('documents.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="border-radius: 8px; height: 36px; font-weight: 500;">
                <i class="bi bi-arrow-left"></i> Back to Documents
            </a>
            <a href="{{ route('documents.passport', $document->id) }}" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" style="border-radius: 8px; height: 36px; font-weight: 500;">
                <i class="bi bi-journal-bookmark"></i> Document Passport
            </a>
            <a href="{{ route('documents.qr-label', $document->id) }}?autoprint=1" target="_blank" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="border-radius: 8px; height: 36px; font-weight: 500;">
                <i class="bi bi-printer"></i> Print QR Label
            </a>
            @if($document->file_path)
                <a href="{{ route('documents.download', $document->id) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" style="border-radius: 8px; height: 36px; font-weight: 600;">
                    <i class="bi bi-download"></i> Download File
                </a>
            @endif
        </div>
    </div>

    <!-- Security Lock Notice if QR Verification Required -->
    @if(isset($isLocked) && $isLocked)
    <div class="detail-card border-danger mb-4">
        <div class="detail-card-body p-4 text-center">
            <div class="mb-3">
                <i class="bi bi-shield-lock-fill text-danger" style="font-size: 3.5rem;"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">Document Access Verification Required</h4>
            <p class="text-secondary small mb-4" style="max-width: 540px; margin: 0 auto;">
                This document requires authentication via QR verification before workflow actions can be executed. Please scan the physical or dynamic QR code using the NAAP Scanner.
            </p>
            <div class="d-inline-flex gap-2">
                <a href="{{ route('qr.index', ['document_id' => $document->id]) }}" class="btn btn-primary px-4 py-2" style="border-radius: 8px; font-weight: 600;">
                    <i class="bi bi-qr-code-scan me-1"></i> Open QR Scanner
                </a>
                <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary px-4 py-2" style="border-radius: 8px;">
                    Back to Documents
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- Confidential Document Alert Banner -->
    @if($document->is_confidential)
    <div class="alert alert-warning border-0 d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4" style="border-radius: 12px; background: rgba(245, 158, 11, 0.12); border-left: 4px solid #F59E0B !important; color: #B45309;">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-shield-lock-fill fs-3 text-warning"></i>
            <div>
                <strong class="d-block text-dark fw-bold">
                    Confidential Document &bull; Restricted Access
                    <a href="{{ route('help.manual', ['section' => 'confidential-documents']) }}" target="_blank" class="text-decoration-none text-muted ms-2" style="font-size: 11px; font-weight: normal;">
                        <i class="bi bi-question-circle"></i> Guide
                    </a>
                </strong>
                <span class="small text-secondary">Only designated receivers, current office members, and administrators are authorized to inspect or act upon this record.</span>
            </div>
        </div>
        @if($isAdmin)
            <form action="{{ route('documents.regeneratePin', $document->id) }}" method="POST" onsubmit="return confirm('Regenerate access PIN for this confidential document?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold" style="border-radius: 6px;">
                    <i class="bi bi-key me-1"></i> Regenerate PIN
                </button>
            </form>
        @endif
    </div>
    @endif

    <!-- Awaiting Receiver Acceptance Action Card -->
    @php
        $canAct = $canPerformWorkflowAction ?? ($document->receiver_user_id === (auth()->id() ?? session('user_id')));
    @endphp
    @if($canAct && !in_array($document->status, ['Accepted', 'Completed', 'Archived', 'Rejected']) && !($isLocked ?? false))
    <div class="detail-card mb-4" style="border-color: rgba(37, 99, 235, 0.3); background: rgba(37, 99, 235, 0.03);">
        <div class="detail-card-body p-3 p-md-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary text-white" style="font-size: 11px;">Action Required</span>
                    <h6 class="fw-bold text-dark mb-0">Custody & Receipt Verification</h6>
                </div>
                <p class="text-secondary small mb-0">You are the active handler for this document. Verify contents and formally acknowledge receipt to advance routing.</p>
            </div>
            <button type="button" class="btn btn-primary px-4 py-2 text-nowrap" data-bs-toggle="modal" data-bs-target="#acceptDocumentModal" style="border-radius: 8px; font-weight: 600;">
                <i class="bi bi-check2-circle me-1"></i> Accept & Acknowledge
            </button>
        </div>
    </div>
    @endif

    <!-- SECTION 3: TOP DOCUMENT BANNER CARD -->
    <div class="detail-card mb-4">
        <div class="detail-card-body p-4">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <span class="badge {{ $statusBadgeClass }} px-2.5 py-1 text-uppercase" style="font-size: 11px; font-weight: 700;">
                            {{ $document->status }}
                        </span>
                        <span class="sla-badge {{ $slaStatusClass }}">
                            <i class="bi bi-clock me-1"></i> {{ $slaStatusLabel }} ({{ $slaRemainingText }})
                        </span>
                        <span class="priority-badge">
                            <span class="priority-dot" style="background: {{ $priorityColor }};"></span>
                            Priority: {{ ucfirst($document->priority ?? 'Normal') }}
                        </span>
                        @if($document->is_confidential)
                            <span class="badge bg-danger text-white px-2 py-1" style="font-size: 10.5px;">
                                <i class="bi bi-lock-fill me-1"></i> Confidential
                            </span>
                        @endif
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap text-secondary small">
                        <span>Tracking No: <span class="tracking-code-pill">{{ $document->tracking_number ?? 'N/A' }}</span></span>
                        <span>&bull;</span>
                        <span>Type: <strong>{{ strtoupper($document->type ?? 'N/A') }}</strong></span>
                        <span>&bull;</span>
                        <span>Category: <strong>{{ $document->category ?? 'General' }}</strong></span>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end border-start-lg ps-lg-4">
                    <div class="small text-muted mb-1">Current Custody</div>
                    <div class="fw-bold text-dark h6 mb-0">{{ $currentOfficeName }}</div>
                    <div class="small text-secondary">Handler: {{ $currentReceiverName }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 5 & 6: CURRENT DOCUMENT STATE & REAL-TIME LOCATION -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="meta-item-box">
                <span class="meta-item-label"><i class="bi bi-geo-alt-fill text-primary"></i> Current Location</span>
                <span class="meta-item-value text-primary">{{ $currentOfficeName }}</span>
                <span class="meta-item-sub">Handler: {{ $currentReceiverName }}</span>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="meta-item-box">
                <span class="meta-item-label"><i class="bi bi-signpost-split text-info"></i> Next Destination</span>
                <span class="meta-item-value">{{ $nextDestination }}</span>
                <span class="meta-item-sub">Final: {{ $document->destinationOffice?->name ?? 'N/A' }}</span>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="meta-item-box">
                <span class="meta-item-label"><i class="bi bi-hourglass-split text-warning"></i> Next Required Action</span>
                <span class="meta-item-value text-dark">{{ $nextActionRequired }}</span>
                <span class="meta-item-sub">Status: {{ ucfirst($document->status) }}</span>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="meta-item-box">
                <span class="meta-item-label"><i class="bi bi-clock-history text-secondary"></i> Last Known Event</span>
                <span class="meta-item-value text-truncate">{{ $lastEventName }}</span>
                <span class="meta-item-sub">{{ $lastEventTime ? $lastEventTime->format('M d, Y h:i A') : 'N/A' }}</span>
            </div>
        </div>
    </div>

    <!-- SECTION 8 & 9: VISUAL DOCUMENT ROUTE / JOURNEY MAP -->
    <div class="detail-card mb-4">
        <div class="detail-card-header">
            <h6 class="detail-card-title"><i class="bi bi-diagram-3-fill text-primary"></i> Document Journey & Routing Path</h6>
            <span class="small text-muted">{{ $document->routings->count() }} Total Routing Hops</span>
        </div>
        <div class="detail-card-body p-3">
            @php
                $originOffice = $document->originOffice;
                $destinationOffice = $document->destinationOffice;
                $routingsList = $document->routings->sortBy('sort_order');
            @endphp
            <div class="tracking-steps-container">
                <!-- Origin Node -->
                <div class="tracking-step-node completed">
                    <div class="tracking-step-circle">
                        <i class="bi bi-box-arrow-up"></i>
                    </div>
                    <div class="tracking-step-title">{{ $originOffice?->name ?? 'Origin' }}</div>
                    <div class="tracking-step-sub text-success fw-semibold">Origin &bull; Created</div>
                </div>

                <div class="tracking-step-arrow"><i class="bi bi-arrow-right"></i></div>

                <!-- Intermediate Routing Nodes -->
                @forelse($routingsList as $step)
                    @php
                        $isCurrentHop = ($step->status === 'Pending' || $step->id === ($activeStep?->id ?? 0));
                        $isCompletedHop = in_array(strtolower($step->status), ['completed', 'approved', 'received', 'accepted']);
                        $nodeClass = $isCurrentHop ? 'active' : ($isCompletedHop ? 'completed' : '');
                    @endphp
                    <div class="tracking-step-node {{ $nodeClass }}">
                        <div class="tracking-step-circle">
                            @if($isCompletedHop)
                                <i class="bi bi-check-lg"></i>
                            @elseif($isCurrentHop)
                                <i class="bi bi-geo-alt-fill"></i>
                            @else
                                <i class="bi bi-circle"></i>
                            @endif
                        </div>
                        <div class="tracking-step-title">{{ $step->toOffice?->name ?? 'Office' }}</div>
                        <div class="tracking-step-sub">
                            @if($isCurrentHop)
                                <span class="text-primary fw-bold">Active Custody</span>
                            @else
                                {{ ucfirst($step->status) }}
                            @endif
                        </div>
                    </div>
                    @if(!$loop->last)
                        <div class="tracking-step-arrow"><i class="bi bi-arrow-right"></i></div>
                    @endif
                @empty
                    <div class="tracking-step-node active">
                        <div class="tracking-step-circle"><i class="bi bi-geo-alt-fill"></i></div>
                        <div class="tracking-step-title">{{ $currentOfficeName }}</div>
                        <div class="tracking-step-sub text-primary fw-semibold">Current Custody</div>
                    </div>
                @endforelse

                <!-- Final Destination Node if different -->
                @if($destinationOffice && (!$routingsList->count() || $routingsList->last()->to_office_id !== $destinationOffice->id))
                    <div class="tracking-step-arrow"><i class="bi bi-arrow-right"></i></div>
                    <div class="tracking-step-node {{ $isCompleted ? 'completed' : '' }}">
                        <div class="tracking-step-circle"><i class="bi bi-flag-fill"></i></div>
                        <div class="tracking-step-title">{{ $destinationOffice->name }}</div>
                        <div class="tracking-step-sub">{{ $isCompleted ? 'Delivered' : 'Final Destination' }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- SECTION 4 & 7: DOCUMENT PASSPORT & SPECIFICATIONS -->
    <div class="row g-4 mb-4">
        <!-- Left: Document Information & File Attributes -->
        <div class="col-lg-8">
            <div class="detail-card h-100 mb-0">
                <div class="detail-card-header">
                    <h6 class="detail-card-title"><i class="bi bi-file-earmark-text text-primary"></i> Document Specifications & Identity</h6>
                    <span class="badge bg-light text-secondary border">Passport ID: {{ $document->uuid ?? ('DOC-' . $document->id) }}</span>
                </div>
                <div class="detail-card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <span class="meta-item-label">Document Title</span>
                            <div class="meta-item-value">{{ $document->title }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="meta-item-label">Tracking Number</span>
                            <div class="meta-item-value"><span class="tracking-code-pill">{{ $document->tracking_number ?? 'N/A' }}</span></div>
                        </div>
                        <div class="col-sm-6">
                            <span class="meta-item-label">Category / Classification</span>
                            <div class="meta-item-value">{{ $document->category ?? 'General' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="meta-item-label">Origin Office</span>
                            <div class="meta-item-value">{{ $document->originOffice?->name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="meta-item-label">Final Destination Office</span>
                            <div class="meta-item-value">{{ $document->destinationOffice?->name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="meta-item-label">Uploaded / Created By</span>
                            <div class="meta-item-value">
                                {{ $document->uploader?->name ?? 'System' }}
                                <div class="meta-item-sub">{{ $document->uploader?->department?->name ?? ($document->uploader?->office?->name ?? '') }}</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <span class="meta-item-label">Created Timestamp</span>
                            <div class="meta-item-value">{{ $document->created_at ? $document->created_at->format('M d, Y h:i A') : 'N/A' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="meta-item-label">Last Modified Timestamp</span>
                            <div class="meta-item-value">{{ $document->updated_at ? $document->updated_at->format('M d, Y h:i A') : 'N/A' }}</div>
                        </div>
                    </div>

                    <!-- Description Block -->
                    <div class="mb-4">
                        <span class="meta-item-label mb-2">Description / Purpose</span>
                        <div class="p-3 bg-light rounded-3 border" style="font-size: 13px; line-height: 1.6; color: #334155;">
                            {{ $document->description ?: 'No additional description provided for this document.' }}
                        </div>
                    </div>

                    <!-- File Information & Secure Download Block -->
                    <div>
                        <span class="meta-item-label mb-2">Attached File Information</span>
                        @if($document->file_path)
                            @php
                                $fileName = basename($document->file_path);
                                $fileSizeFormatted = 'N/A';
                                if ($document->file_size) {
                                    $bytes = (int) $document->file_size;
                                    if ($bytes >= 1048576) {
                                        $fileSizeFormatted = round($bytes / 1048576, 2) . ' MB';
                                    } elseif ($bytes >= 1024) {
                                        $fileSizeFormatted = round($bytes / 1024, 2) . ' KB';
                                    } else {
                                        $fileSizeFormatted = $bytes . ' B';
                                    }
                                }
                            @endphp
                            <div class="p-3 bg-light rounded-3 border d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="file-type-icon {{ $iconClass }}">{{ $document->type ?? 'FILE' }}</span>
                                    <div>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 320px;">{{ $fileName }}</div>
                                        <div class="small text-muted">
                                            Size: {{ $fileSizeFormatted }} &bull; MIME: {{ $document->mime_type ?? 'application/octet-stream' }}
                                        </div>
                                        @if($document->file_hash)
                                            <div class="small text-muted font-monospace" style="font-size: 10px;">SHA256: {{ substr($document->file_hash, 0, 16) }}...</div>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('documents.download', $document->id) }}" class="btn btn-outline-primary btn-sm px-3" style="border-radius: 6px; font-weight: 600;">
                                    <i class="bi bi-download me-1"></i> Download File
                                </a>
                            </div>
                        @else
                            <div class="p-3 bg-light rounded-3 border text-muted small">
                                <i class="bi bi-info-circle me-1"></i> No digital file attached to this record.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: SLA Analytics & QR Summary -->
        <div class="col-lg-4">
            <!-- SECTION 12: SLA INFORMATION CARD -->
            <div class="detail-card mb-4">
                <div class="detail-card-header">
                    <h6 class="detail-card-title"><i class="bi bi-clock-history text-primary"></i> SLA & Processing Analytics</h6>
                </div>
                <div class="detail-card-body">
                    <div class="mb-3">
                        <span class="meta-item-label">SLA Target Standard</span>
                        <div class="meta-item-value">{{ $document->sla ?? 'Standard Processing' }}</div>
                    </div>
                    <div class="mb-3">
                        <span class="meta-item-label">Target Completion Deadline</span>
                        <div class="meta-item-value text-dark">
                            {{ $document->due_date ? $document->due_date->format('M d, Y h:i A') : 'N/A' }}
                        </div>
                    </div>
                    <div class="mb-3">
                        <span class="meta-item-label">SLA Remaining / Overdue Status</span>
                        <div>
                            <span class="sla-badge {{ $slaStatusClass }}">
                                {{ $slaStatusLabel }}: {{ $slaRemainingText }}
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <span class="meta-item-label">Actual Total Processing Time</span>
                        <div class="meta-item-value font-monospace">{{ $actualProcessingTime }}</div>
                    </div>

                    @if($document->due_date && !$isCompleted)
                    <div class="pt-2 border-top">
                        <div class="d-flex justify-content-between mb-1" style="font-size: 11px;">
                            <span class="text-secondary fw-semibold">SLA Target Timeline</span>
                            <span class="fw-bold {{ $isOverdue ? 'text-danger' : 'text-success' }}">{{ $slaPercentRemaining }}% remaining</span>
                        </div>
                        <div class="progress" style="height: 8px; border-radius: 4px;">
                            <div class="progress-bar {{ $isOverdue ? 'bg-danger' : ($slaPercentRemaining < 25 ? 'bg-warning' : 'bg-success') }}" role="progressbar" style="width: {{ $slaPercentRemaining }}%;"></div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- SECTION 13 & 14: QR INFORMATION -->
            <div class="detail-card mb-0">
                <div class="detail-card-header">
                    <h6 class="detail-card-title"><i class="bi bi-qr-code text-primary"></i> QR Identity & Verification</h6>
                    <span class="badge bg-light text-dark border">{{ $document->qr_status ?? 'Not Scanned' }}</span>
                </div>
                <div class="detail-card-body text-center">
                    @if($qrUrl)
                        <div class="p-3 bg-white rounded-3 border d-inline-block mb-3" style="box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                            <img src="{{ $qrUrl }}" alt="Document QR Code" class="img-fluid" style="width: 130px; height: 130px; display: block;">
                        </div>
                    @else
                        <div class="p-4 bg-light rounded-3 border text-muted small mb-3">
                            <i class="bi bi-qr-code text-secondary" style="font-size: 2.5rem;"></i>
                            <div class="mt-2">Code: {{ $document->qr_code ?? 'Not Generated' }}</div>
                        </div>
                    @endif

                    <div class="text-start small mb-3">
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-secondary">QR Status:</span>
                            <span class="fw-bold text-dark">{{ $document->qr_status ?? 'Not Scanned' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-secondary">Total Scans:</span>
                            <span class="fw-bold text-dark">{{ $totalQrScans }} scan(s)</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-secondary">Last Verified:</span>
                            <span class="fw-bold text-dark">{{ $lastQrScan ? $lastQrScan->created_at->format('M d, Y h:i A') : 'N/A' }}</span>
                        </div>
                    </div>

                    <a href="{{ route('documents.qr-label', $document->id) }}?autoprint=1" target="_blank" class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1" style="border-radius: 6px; font-weight: 600;">
                        <i class="bi bi-printer"></i> Print QR Label
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 8: ROUTING HISTORY TABLE -->
    <div class="detail-card mb-4">
        <div class="detail-card-header">
            <h6 class="detail-card-title"><i class="bi bi-signpost text-primary"></i> Routing History & Custody Logs</h6>
            <span class="small text-muted">{{ $document->routings->count() }} Route Hop(s)</span>
        </div>
        <div class="detail-card-body p-0">
            @if($document->routings->isEmpty())
                <div class="text-center py-4 text-muted small">No routing hops registered for this document.</div>
            @else
                <div class="table-responsive">
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Hop #</th>
                                <th>From Office</th>
                                <th>To Office</th>
                                <th>Sender</th>
                                <th>Receiver</th>
                                <th>Status</th>
                                <th>Routed At</th>
                                <th>Received At</th>
                                <th>Released At</th>
                                <th>Hop Duration</th>
                                <th>Signature Proof</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($document->routings->sortBy('sort_order') as $idx => $route)
                                @php
                                    $routeStatusClass = match(strtolower($route->status)) {
                                        'completed', 'approved' => 'bg-success text-white',
                                        'received', 'accepted' => 'bg-primary text-white',
                                        'pending' => 'bg-warning text-dark',
                                        'returned', 'rejected', 'reverted' => 'bg-danger text-white',
                                        default => 'bg-secondary text-white'
                                    };

                                    // Accurate Hop duration
                                    $hopDuration = 'N/A';
                                    if ($route->received_at && $route->released_at && $route->released_at >= $route->received_at) {
                                        $secs = $route->received_at->diffInSeconds($route->released_at);
                                        $dh = floor($secs / 3600);
                                        $dm = floor(($secs % 3600) / 60);
                                        $hopDuration = $dh > 0 ? "{$dh}h {$dm}m" : "{$dm}m";
                                    } elseif ($route->received_at) {
                                        $secs = $route->received_at->diffInSeconds(now());
                                        $dh = floor($secs / 3600);
                                        $dm = floor(($secs % 3600) / 60);
                                        $hopDuration = "{$dh}h {$dm}m (Active)";
                                    }
                                @endphp
                                <tr>
                                    <td class="fw-bold text-center" style="width: 50px;">{{ $idx + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $route->fromOffice?->name ?? 'Origin' }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-primary">{{ $route->toOffice?->name ?? 'Destination' }}</div>
                                    </td>
                                    <td>
                                        <div class="small">{{ $route->senderUser?->name ?? ($idx === 0 ? ($document->uploader?->name ?? 'System') : 'N/A') }}</div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold">{{ $route->receiverUser?->name ?? 'Unassigned' }}</div>
                                        @if($route->receiverUser?->department)
                                            <div class="small text-muted" style="font-size: 10.5px;">{{ $route->receiverUser->department->name }}</div>
                                        @endif
                                        @if($route->forwardedFromUser)
                                            <div class="small text-info" style="font-size: 10.5px;"><i class="bi bi-reply-fill"></i> Via {{ $route->forwardedFromUser->name }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $routeStatusClass }}" style="font-size: 10.5px;">{{ ucfirst($route->status) }}</span>
                                    </td>
                                    <td class="small text-secondary">{{ $route->created_at ? $route->created_at->format('M d, Y h:i A') : 'N/A' }}</td>
                                    <td class="small text-secondary">{{ $route->received_at ? $route->received_at->format('M d, Y h:i A') : 'N/A' }}</td>
                                    <td class="small text-secondary">{{ $route->released_at ? $route->released_at->format('M d, Y h:i A') : 'N/A' }}</td>
                                    <td class="small font-monospace">{{ $hopDuration }}</td>
                                    <td>
                                        @if($route->signature)
                                            <img src="{{ str_contains($route->signature, 'data:image') ? $route->signature : asset('storage/' . $route->signature) }}" alt="Signature Proof" style="max-height: 28px; max-width: 90px; border-radius: 4px; border: 1px solid #CBD5E1; background: #FFFFFF; padding: 2px;">
                                        @elseif($route->signed_by)
                                            <span class="small text-muted"><i class="bi bi-pen"></i> Signed</span>
                                        @else
                                            <span class="text-muted small">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($route->notes)
                                <tr style="background: #FAFAFA;">
                                    <td colspan="11" class="py-2 px-3 small text-secondary fst-italic">
                                        <i class="bi bi-chat-left-quote me-1 text-primary"></i> Remarks: "{{ $route->notes }}"
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- SECTION 16 & 17: APPROVAL & SIGNATURE STATUS -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="detail-card h-100 mb-0">
                <div class="detail-card-header">
                    <h6 class="detail-card-title"><i class="bi bi-check2-square text-primary"></i> Approval Record & Review State</h6>
                </div>
                <div class="detail-card-body">
                    @php
                        $isApproved = in_array(strtolower($document->status), ['approved', 'completed']);
                        $approvalLog = $document->activityLogs->first(fn($l) => str_contains(strtolower($l->action), 'approved'));
                    @endphp
                    <div class="mb-3">
                        <span class="meta-item-label">Approval Status</span>
                        <div>
                            @if($isApproved)
                                <span class="badge bg-success text-white"><i class="bi bi-check-circle-fill me-1"></i> Approved</span>
                            @elseif(in_array(strtolower($document->status), ['rejected']))
                                <span class="badge bg-danger text-white"><i class="bi bi-x-circle-fill me-1"></i> Rejected</span>
                            @elseif($document->status === 'Pending')
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i> Awaiting Review / Approval</span>
                            @else
                                <span class="badge bg-secondary text-white">{{ $document->status }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="mb-3">
                        <span class="meta-item-label">Approver / Reviewer</span>
                        <div class="meta-item-value">{{ $approvalLog ? $approvalLog->user : ($isApproved ? 'Authorized Handler' : 'Pending Assignment') }}</div>
                    </div>
                    <div class="mb-0">
                        <span class="meta-item-label">Approval Timestamp</span>
                        <div class="meta-item-value">{{ $document->approved_at ? $document->approved_at->format('M d, Y h:i A') : ($approvalLog ? $approvalLog->created_at->format('M d, Y h:i A') : 'N/A') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="detail-card h-100 mb-0">
                <div class="detail-card-header">
                    <h6 class="detail-card-title"><i class="bi bi-pen text-primary"></i> Electronic Signature Verification</h6>
                </div>
                <div class="detail-card-body">
                    @php
                        $signedHop = $document->routings->first(fn($r) => !empty($r->signature) || !empty($r->signed_by));
                    @endphp
                    <div class="mb-3">
                        <span class="meta-item-label">Signature Status</span>
                        <div>
                            @if($signedHop || $document->receiver_signature)
                                <span class="badge bg-success text-white"><i class="bi bi-patch-check-fill me-1"></i> Digitally Signed & Verified</span>
                            @else
                                <span class="badge bg-secondary text-white">Not Signed</span>
                            @endif
                        </div>
                    </div>
                    <div class="mb-3">
                        <span class="meta-item-label">Signer</span>
                        <div class="meta-item-value">{{ $signedHop?->receiverUser?->name ?? ($document->receiverUser?->name ?? 'Unassigned') }}</div>
                    </div>
                    <div>
                        <span class="meta-item-label">Signature Proof</span>
                        @if($signedHop && $signedHop->signature)
                            <div class="p-2 bg-light rounded border d-inline-block">
                                <img src="{{ str_contains($signedHop->signature, 'data:image') ? $signedHop->signature : asset('storage/' . $signedHop->signature) }}" alt="Digital Signature" style="max-height: 48px; border-radius: 4px; background: #FFFFFF;">
                            </div>
                        @elseif($document->receiver_signature)
                            <div class="p-2 bg-light rounded border d-inline-block">
                                <img src="{{ str_contains($document->receiver_signature, 'data:image') ? $document->receiver_signature : asset('storage/' . $document->receiver_signature) }}" alt="Receiver Signature" style="max-height: 48px; border-radius: 4px; background: #FFFFFF;">
                            </div>
                        @else
                            <div class="text-muted small">No signature captured.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 18 & 19: WORKFLOW EXECUTION ACTIONS (PERMISSION-AWARE) -->
    @php
        $showWorkflowCard = $canViewWorkflow ?? true;
        $canExecuteAction = $canPerformWorkflowAction ?? ($isAdmin || ($document->receiver_user_id === (auth()->id() ?? session('user_id')) && !in_array($document->status, ['Completed', 'Archived', 'Rejected'])));
    @endphp
    @if($showWorkflowCard)
    <div class="detail-card mb-4">
        <div class="detail-card-header">
            <h6 class="detail-card-title"><i class="bi bi-gear-fill text-primary"></i> Workflow Execution Actions & Status Update</h6>
            @if(!$canExecuteAction)
                <span class="badge bg-light text-secondary border"><i class="bi bi-eye me-1"></i> View Only</span>
            @endif
        </div>
        <div class="detail-card-body">
            @if(!$canExecuteAction)
                <div class="alert alert-light border small text-secondary mb-3 py-2 px-3">
                    @if(in_array($document->status, ['Completed', 'Archived', 'Cancelled']))
                        <i class="bi bi-check-circle-fill text-success me-1"></i> This document has completed its official lifecycle (terminal status: <strong>{{ $document->status }}</strong>).
                    @else
                        <i class="bi bi-info-circle text-primary me-1"></i> You are viewing this document in read-only mode. Only the designated recipient, current office staff, or an administrator can execute state changes.
                    @endif
                </div>
            @endif

            <form action="{{ route('documents.workflowAction', $document->id) }}" method="POST" enctype="multipart/form-data" id="workflowActionForm">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">Update Workflow Status</label>
                        <select name="status" class="form-select" required @disabled(!$canExecuteAction)>
                            <option value="Pending" {{ $document->status === 'Pending' ? 'selected' : '' }}>Pending</option>
                            <option value="Received" {{ $document->status === 'Received' ? 'selected' : '' }}>Received</option>
                            <option value="Under Review" {{ $document->status === 'Under Review' ? 'selected' : '' }}>Under Review</option>
                            <option value="Approved">Approved & Route to Next Step</option>
                            <option value="Accepted">Accept & Acknowledge</option>
                            <option value="Endorsed">Endorse Document</option>
                            <option value="Returned">Returned for Revision</option>
                            <option value="Reverted">Revert Back to Previous Office</option>
                            <option value="Completed" {{ $document->status === 'Completed' ? 'selected' : '' }}>Completed</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Archived" {{ $document->status === 'Archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">Action Remarks / Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Enter reason or approval notes..." @disabled(!$canExecuteAction)>
                    </div>
                </div>

                @if($canExecuteAction)
                    <!-- Signature Section for Workflow Action -->
                    <div id="workflow-signature-section" style="display: none;" class="mb-3 text-start">
                        <label class="form-label small fw-bold text-secondary">Digital Signature Authorization</label>
                        <div class="d-flex gap-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="signature_option" id="sigOptProfile" value="profile" checked>
                                <label class="form-check-label small" for="sigOptProfile">Use Profile Signature</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="signature_option" id="sigOptDraw" value="draw">
                                <label class="form-check-label small" for="sigOptDraw">Draw Signature</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="signature_option" id="sigOptUpload" value="upload">
                                <label class="form-check-label small" for="sigOptUpload">Upload Signature</label>
                            </div>
                        </div>

                        <!-- 1. Profile Signature Preview -->
                        <div id="sigProfileContainer" class="p-3 bg-light rounded text-center border">
                            @if(auth()->user() && auth()->user()->signature)
                                <img src="{{ asset('storage/' . auth()->user()->signature) }}" alt="Profile Signature" class="signature-img" style="max-height: 70px; background:white; padding:4px; border:1px solid #ddd; border-radius:4px;">
                                <div class="small text-muted mt-1">Using your verified profile signature.</div>
                            @else
                                <span class="text-danger small">No saved profile signature found. Please choose Draw or Upload.</span>
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

                    <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 8px; font-weight: 600;">
                        <i class="bi bi-check2-circle me-1"></i> Update State
                    </button>
                @else
                    <button type="button" class="btn btn-secondary px-4 py-2" style="border-radius: 8px; opacity: 0.7; cursor: not-allowed;" disabled>
                        Action Restricted (View Only)
                    </button>
                @endif
            </form>

            <!-- Forward Document Accordion / Action -->
            @if($canExecuteAction && !in_array($document->status, ['Completed', 'Archived', 'Rejected']))
            <div class="pt-4 mt-4 border-top">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-arrow-right-short text-primary"></i> Delegate / Forward Document</h6>
                <p class="small text-secondary mb-3">Re-route or delegate custody of this document to another office or responsible staff member.</p>
                <form action="{{ route('documents.forward', $document->id) }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Target Office</label>
                            <select id="forwardOfficeSelect" class="form-select form-select-sm" required>
                                <option value="">-- Choose Office --</option>
                                @foreach(\App\Models\Office::orderBy('name', 'asc')->get() as $office)
                                    <option value="{{ $office->id }}">{{ $office->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">New Recipient / Handler</label>
                            <select name="new_receiver_user_id" id="forwardReceiverSelect" class="form-select form-select-sm" required disabled>
                                <option value="">-- Select Office First --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Reason / Remarks</label>
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for forwarding..." required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-sm btn-outline-primary fw-bold px-3 py-1.5" style="border-radius: 6px;">
                                <i class="bi bi-arrow-right me-1"></i> Forward Document
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- SECTION 20 & 21: ACTIVITY TIMELINE & AUDIT TRAIL -->
    <div class="row g-4 mb-4">
        <!-- Left: Lifecycle Audit Trail -->
        <div class="col-lg-8">
            <div class="detail-card mb-0 h-100">
                <div class="detail-card-header">
                    <h6 class="detail-card-title"><i class="bi bi-list-task text-primary"></i> Document Lifecycle Audit Trail</h6>
                    <span class="small text-muted">{{ $document->activityLogs->count() }} Event(s) Recorded</span>
                </div>
                <div class="detail-card-body">
                    <div class="audit-timeline">
                        @forelse($document->activityLogs as $log)
                            @php
                                $act = strtolower($log->action);
                                $dotIcon = match(true) {
                                    str_contains($act, 'create') || str_contains($act, 'upload') => 'bi-cloud-arrow-up',
                                    str_contains($act, 'view') => 'bi-eye',
                                    str_contains($act, 'scan') || str_contains($act, 'qr') => 'bi-qr-code-scan',
                                    str_contains($act, 'accept') => 'bi-shield-check',
                                    str_contains($act, 'receive') => 'bi-envelope-open',
                                    str_contains($act, 'approve') => 'bi-check2-circle',
                                    str_contains($act, 'complete') => 'bi-check-all',
                                    str_contains($act, 'reject') => 'bi-x-circle',
                                    str_contains($act, 'forward') => 'bi-arrow-right',
                                    default => 'bi-activity'
                                };
                            @endphp
                            <div class="audit-timeline-item">
                                <div class="audit-timeline-dot">
                                    <i class="bi {{ $dotIcon }}"></i>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark" style="font-size: 13.5px;">{{ $log->action }}</span>
                                    <small class="text-secondary font-monospace">{{ $log->created_at ? $log->created_at->format('M d, Y h:i A') : 'N/A' }}</small>
                                </div>
                                <div class="small text-secondary" style="font-size: 12px; line-height: 1.45;">
                                    <span>Initiated by: <strong class="text-dark">{{ $log->user }}</strong></span>
                                    @if(isset($log->meta['office']) || isset($log->meta['department']))
                                        &bull; <span>Office: {{ $log->meta['office'] ?? $log->meta['department'] }}</span>
                                    @endif
                                    @if(isset($log->meta['notes']) && $log->meta['notes'])
                                        <div class="mt-1 text-dark fst-italic">"{{ $log->meta['notes'] }}"</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-muted text-center py-4 small">No lifecycle audit records logged yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Document Read Receipts / Views History -->
        <div class="col-lg-4">
            <div class="detail-card mb-0 h-100">
                <div class="detail-card-header">
                    <h6 class="detail-card-title"><i class="bi bi-eye text-primary"></i> Read Receipts (Views)</h6>
                    <span class="small text-muted">{{ $views->count() }} View(s)</span>
                </div>
                <div class="detail-card-body p-0">
                    @if($views->isEmpty())
                        <div class="text-center py-4 text-muted small">No read receipts recorded yet.</div>
                    @else
                        <div class="table-responsive">
                            <table class="details-table">
                                <thead>
                                    <tr>
                                        <th>Viewer</th>
                                        <th>Office</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($views as $v)
                                        <tr>
                                            <td class="fw-semibold text-dark">{{ $v->user?->name ?? 'Guest' }}</td>
                                            <td class="small text-secondary">{{ $v->office?->name ?? 'Unassigned' }}</td>
                                            <td class="small text-secondary">{{ $v->viewed_at ? $v->viewed_at->format('M d, h:i A') : 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Accept Document Modal (with Digital Signature Pad) -->
<div class="modal fade" id="acceptDocumentModal" tabindex="-1" aria-labelledby="acceptDocumentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel, #FFFFFF) !important; border: 1px solid var(--panel-border, #E2E8F0); border-radius: 14px; color: var(--text-main, #0F172A);">
            <div class="modal-header border-bottom p-3">
                <h5 class="modal-title fw-bold h6 mb-0 text-dark" id="acceptDocumentModalLabel">
                    <i class="bi bi-shield-check text-success me-2"></i>Accept & Acknowledge Document
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('documents.workflowAction', $document->id) }}" method="POST" enctype="multipart/form-data" id="acceptDocumentForm">
                @csrf
                <input type="hidden" name="status" value="Accepted">
                <div class="modal-body p-3 text-start">
                    <p class="small text-secondary mb-3">
                        Acknowledge custody and formally accept <strong>{{ $document->title }}</strong> into your office workflow.
                    </p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Acknowledgement Remarks (Optional)</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Enter remarks..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary d-block">Digital Signature</label>
                        <div class="d-flex gap-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modal_signature_option" id="modalSigOptProfile" value="profile" checked>
                                <label class="form-check-label small" for="modalSigOptProfile">Profile Signature</label>
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
                                <div class="small text-muted mt-1">Using your verified profile signature.</div>
                            @else
                                <span class="text-danger small">No saved profile signature. Please choose Draw or Upload.</span>
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
                            <input type="file" name="signature_file" class="form-control form-control-sm" accept="image/*">
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
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 6px;">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3" style="border-radius: 6px;">Confirm Acceptance</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
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

    // Workflow Action Signature Toggle
    const statusSelect = document.querySelector('select[name="status"]');
    const signatureSection = document.getElementById('workflow-signature-section');
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

    // Handle signature option radio buttons in workflow card
    const sigProfileContainer = document.getElementById('sigProfileContainer');
    const sigDrawContainer = document.getElementById('sigDrawContainer');
    const sigUploadContainer = document.getElementById('sigUploadContainer');
    const saveToProfileWrapper = document.getElementById('saveToProfileWrapper');

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

    // Signature Pad Initialization for Workflow Form
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
            penColor: '#000000'
        });

        document.getElementById('clearWorkflowSigBtn')?.addEventListener('click', function() {
            workflowSigPad.clear();
        });

        const workflowForm = document.getElementById('workflowActionForm');
        workflowForm?.addEventListener('submit', function(e) {
            const status = statusSelect ? statusSelect.value : '';
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
            penColor: '#000000'
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
@endsection