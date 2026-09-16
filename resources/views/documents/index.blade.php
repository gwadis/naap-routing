@extends('layouts.app')

@section('title','Documents')

@section('content')
<style>
    .doc-card { 
        position: relative; 
        overflow: hidden; 
        background: #ffffff; 
        border: 1px solid var(--panel-border); 
        border-radius: 16px; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -1px rgba(0, 0, 0, 0.01);
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; 
    }
    .doc-card:hover { 
        transform: translateY(-4px); 
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
        border-color: var(--accent-cyan); 
    }
    .doc-card.overdue { border-color: #ef4444; box-shadow: 0 0 10px rgba(239, 68, 68, 0.15); }
    .doc-card > * { position: relative; z-index: 1; }
    .doc-card h6 { margin: 0; color: #1e293b; font-weight: 700; }
    .doc-card-header { display: flex; align-items: flex-start; gap: 16px; }
    .doc-card-header > div { min-width: 0; }
    .doc-card-header .file-icon { flex-shrink: 0; }
    .file-icon { width: auto; min-width: 45px; max-width: 110px; height: 45px; background: rgba(15, 23, 42, 0.06); border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; line-height: 1.1; color: #475569; z-index: 1; padding: 0 8px; white-space: normal; word-break: break-word; text-align: center; }
    .priority-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }
    .priority-Urgent, .priority-urgent { background: #ef4444; box-shadow: 0 0 8px #ef4444; }
    .priority-High, .priority-high { background: #f59e0b; box-shadow: 0 0 8px #f59e0b; }
    .priority-Normal, .priority-normal { background: #3b82f6; box-shadow: 0 0 8px #3b82f6; }
    .priority-Low, .priority-low { background: #10b981; box-shadow: 0 0 8px #10b981; }
    .upload-zone { border: 2px dashed rgba(148, 163, 184, 0.2); border-radius: 16px; padding: 30px; cursor: pointer; }
    .modal-header { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-right: 4.5rem; }
    .modal-header .custom-close-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.16);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        cursor: pointer;
        transition: background 0.2s ease;
        z-index: 5;
    }
    .modal-header .custom-close-btn:hover {
        background: rgba(255, 255, 255, 0.16);
    }
    .modal-header .custom-close-btn i {
        font-size: 1.1rem;
        line-height: 1;
    }
    .track-steps {
        display: flex;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }
    .track-step {
        position: relative;
        flex: 1;
        background: #ffffff;
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 1rem 0.9rem 0.8rem;
        text-align: center;
        transition: all 0.25s ease;
        min-width: 0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .track-step::after {
        content: '';
        position: absolute;
        top: 50%;
        right: -0.65rem;
        width: 1.3rem;
        height: 2px;
        background: rgba(59, 130, 246, 0.3);
        transform: translateY(-50%);
        z-index: 0;
    }
    .track-step:last-child::after {
        display: none;
    }
    .track-step-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        margin: 0 auto 0.85rem;
        display: grid;
        place-items: center;
        background: #f8fafc;
        border: 1px solid var(--panel-border);
        color: var(--text-dim);
        font-size: 1.1rem;
        z-index: 1;
    }
    .track-step.active {
        background: rgba(59, 130, 246, 0.08);
        border-color: #3B82F6;
        box-shadow: 0 0 20px rgba(59, 130, 246, 0.1);
    }
    .track-step.active .track-step-icon {
        background: #3B82F6;
        border-color: #3B82F6;
        color: white;
    }
    .track-step-title {
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--text-dim);
        margin-bottom: 0.35rem;
    }
    .track-step-name {
        color: var(--text-main);
        font-size: 0.95rem;
        font-weight: 700;
        margin-bottom: 0.2rem;
    }
    .track-step-desc {
        font-size: 0.78rem;
        color: var(--text-dim);
    }

    /* --- FIX FOR BIG PAGINATION BUTTONS --- */
    .pagination nav svg {
        width: 1.25rem !important;
        height: 1.25rem !important;
    }
    .pagination .flex.justify-between.flex-1 {
        display: none !important;
    }
    .pagination .page-link {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(0, 215, 255, 0.2);
        color: #00d7ff;
    }
    .pagination .page-link:hover {
        background: rgba(0, 215, 255, 0.1);
        border-color: #00d7ff;
    }
    .pagination .page-item.active .page-link {
        background: #00d7ff;
        border-color: #00d7ff;
        color: #0b1228;
    }

    /* Form validation styling */
    .is-invalid-field {
        border-color: var(--danger) !important;
        box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.1) !important;
    }
    .invalid-feedback-msg {
        display: block;
        color: var(--danger);
        font-size: 11.5px;
        font-weight: 500;
        margin-top: 4px;
    }

    /* Enterprise Table and Toolbar Styles */
    .enterprise-toolbar {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        padding: 16px;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: center;
        justify-content: space-between;
    }
    .enterprise-table-container {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        overflow-x: auto;
        box-shadow: var(--shadow-sm);
        margin-top: 16px;
    }
    .enterprise-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        min-width: 900px;
    }
    .enterprise-table th {
        background: #F8FAFC;
        padding: 12px 16px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748B;
        border-bottom: 1px solid var(--panel-border);
        position: sticky;
        top: 0;
        z-index: 10;
        letter-spacing: 0.05em;
    }
    .enterprise-table td {
        padding: 12px 16px;
        font-size: 13px;
        border-bottom: 1px solid var(--panel-border);
        color: var(--text-main);
        vertical-align: middle;
    }
    .enterprise-table tbody tr:hover {
        background: #F8FAFC;
    }
    .file-type-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 10px;
        color: #ffffff;
        text-transform: uppercase;
    }
    .file-pdf { background: #EF4444; }
    .file-docx, .file-doc { background: #2563EB; }
    .file-xlsx, .file-xls { background: #10B981; }
    .file-zip, .file-rar { background: #F59E0B; }
    .file-png, .file-jpg, .file-jpeg { background: #8B5CF6; }
    .file-default { background: #64748B; }

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
    .sla-overdue {
        background: rgba(239, 68, 68, 0.08);
        color: #B91C1C;
    }

    /* Visual Routing Timeline Builder */
    .timeline-builder {
        position: relative;
        padding: 16px;
        background: #F8FAFC;
        border-radius: var(--radius-lg);
        border: 1px solid var(--panel-border);
    }
    .timeline-step-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-md);
        padding: 16px;
        margin-bottom: 12px;
        position: relative;
        box-shadow: var(--shadow-sm);
        transition: all var(--transition-speed) ease;
    }
    .timeline-step-card:hover {
        border-color: var(--accent-cyan);
    }
    .timeline-connector {
        text-align: center;
        color: var(--text-dim);
        margin: 8px 0;
        font-size: 18px;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .timeline-step-card.final-dest-card {
        border-left: 4px solid var(--success);
        background: rgba(16, 185, 129, 0.01);
    }
    .timeline-step-num {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--accent-cyan);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
    }
    .timeline-card-actions {
        display: flex;
        gap: 6px;
    }
</style>

<div class="container-fluid p-4">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-title mb-1">Documents</h1>
            <p class="text-secondary small mb-0" style="color: var(--text-dim) !important;">Manage and track organization files.</p>
        </div>
        <button class="btn btn-primary px-4 py-2 fw-bold" style="border-radius: 12px;" data-bs-toggle="modal" data-bs-target="#uploadModal">
            + Upload New
        </button>
    </div>

    <!-- Enterprise Toolbar -->
    <div class="enterprise-toolbar mb-3">
        <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 500px;">
            <div class="position-relative w-100">
                <i class="bi bi-search position-absolute text-muted" style="left: 12px; top: 50%; transform: translateY(-50%);"></i>
                <input type="text" id="tableSearch" class="form-control" placeholder="Search by name, tracking number, or receiver..." style="height: 38px; border-radius: 8px; padding-left: 40px !important;">
            </div>
            <select id="statusFilter" class="form-select" style="max-width: 150px; height: 38px; border-radius: 8px;">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="in_transit">In Transit</option>
                <option value="completed">Completed</option>
                <option value="rejected">Rejected</option>
            </select>
            <select id="priorityFilter" class="form-select" style="max-width: 150px; height: 38px; border-radius: 8px;">
                <option value="">All Priorities</option>
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="btnBulkAction" style="height: 38px; border-radius: 8px;" disabled>
                <i class="bi bi-box-arrow-right"></i> Bulk Actions
            </button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="columnToggleBtn" data-bs-toggle="dropdown" aria-expanded="false" style="height: 38px; border-radius: 8px;">
                    <i class="bi bi-eye"></i> Columns
                </button>
                <ul class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="columnToggleBtn" style="min-width: 200px;">
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-tracking" checked> Tracking Number</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-origin" checked> Origin Office</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-current" checked> Current Office</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-destination" checked> Destination</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-receiver" checked> Receiver</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-sla" checked> SLA Status</label></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Enterprise Document Table -->
    <div class="enterprise-table-container">
        <table class="enterprise-table" id="documentsTable">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="selectAllDocs"></th>
                    <th>Document Name</th>
                    <th class="col-tracking">Tracking No.</th>
                    <th class="col-origin">Origin</th>
                    <th class="col-current">Current Office</th>
                    <th class="col-destination">Final Destination</th>
                    <th class="col-receiver">Receiver</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Last Updated</th>
                    <th class="col-sla">SLA Remaining</th>
                    <th style="width: 80px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $doc)
                    @php
                        $ext = strtolower($doc->type ?? '');
                        $iconClass = match($ext) {
                            'pdf' => 'file-pdf',
                            'docx', 'doc' => 'file-docx',
                            'xlsx', 'xls' => 'file-xlsx',
                            'zip', 'rar' => 'file-zip',
                            'png', 'jpg', 'jpeg' => 'file-png',
                            default => 'file-default'
                        };
                        
                        $statusKey = strtolower($doc->status);
                        $badgeColor = match($statusKey) {
                            'completed' => 'bg-success text-white',
                            'pending' => 'bg-warning text-dark',
                            'in_transit', 'in transit' => 'bg-info text-dark',
                            default => 'bg-secondary text-white'
                        };

                        $isOverdue = $doc->due_date && $doc->due_date->isPast();
                        $slaText = 'On Track';
                        $slaClass = 'sla-on-track';
                        if ($isOverdue) {
                            $slaText = 'Overdue';
                            $slaClass = 'sla-overdue';
                        }
                    @endphp
                    <tr class="document-row" data-title="{{ strtolower($doc->title) }}" data-tracking="{{ strtolower($doc->tracking_number ?? '') }}" data-status="{{ str_replace(' ', '_', $statusKey) }}" data-priority="{{ strtolower($doc->priority) }}" data-receiver="{{ strtolower($doc->receiverUser->name ?? '') }}">
                        <td><input type="checkbox" class="doc-select-chk" value="{{ $doc->id }}"></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="file-type-icon {{ $iconClass }}">{{ $ext ?: 'FILE' }}</div>
                                <div>
                                    <a href="{{ route('documents.show', $doc->id) }}" class="fw-bold text-dark text-decoration-none hover-cyan" style="font-size: 14px;">{{ $doc->title }}</a>
                                    <div class="text-muted" style="font-size: 11px;">By {{ $doc->uploader->name ?? 'System' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="col-tracking"><code style="font-size: 12px; color: #475569;">{{ $doc->tracking_number ?: 'N/A' }}</code></td>
                        <td class="col-origin">{{ $doc->originOffice->name ?? 'N/A' }}</td>
                        <td class="col-current"><span class="fw-600 text-dark">{{ $doc->currentOffice->name ?? 'In Transit' }}</span></td>
                        <td class="col-destination">{{ $doc->destinationOffice->name ?? 'N/A' }}</td>
                        <td class="col-receiver">
                            @if($doc->receiverUser)
                                <span class="fw-600">{{ $doc->receiverUser->name }}</span>
                                <div class="text-muted" style="font-size: 11px;">{{ $doc->receiverUser->department->name ?? 'No Dept' }}</div>
                            @else
                                <span class="text-muted">Unassigned</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $badgeColor }} text-uppercase" style="font-size: 10px; font-weight: 700; letter-spacing: 0.05em; padding: 5px 8px;">{{ $doc->status }}</span>
                        </td>
                        <td>
                            @php
                                $pColor = match(strtolower($doc->priority)) {
                                    'low' => '#64748B',
                                    'normal' => '#047857',
                                    'high' => '#B45309',
                                    'urgent' => '#BE123C',
                                    default => '#64748B'
                                };
                            @endphp
                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; color: {{ $pColor }};">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: {{ $pColor }};"></span>
                                {{ $doc->priority }}
                            </span>
                        </td>
                        <td style="font-size: 12px; color: #64748B;">{{ $doc->updated_at->diffForHumans() }}</td>
                        <td class="col-sla"><span class="sla-badge {{ $slaClass }}">{{ $slaText }}</span></td>
                        <td style="text-align: center;">
                            <div class="dropdown">
                                <button class="btn btn-link btn-sm text-secondary p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical" style="font-size: 18px;"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('documents.show', $doc->id) }}"><i class="bi bi-file-text me-2"></i> View Details</a></li>
                                    <li><a class="dropdown-item" href="{{ route('documents.download', $doc->id) }}" target="_blank"><i class="bi bi-download me-2"></i> Download File</a></li>
                                    @if($doc->qr_code)
                                        @php
                                            $qrValue = $doc->qr_code;
                                            $qrUrl = '';
                                            if (str_contains($qrValue, 'qr_codes/')) {
                                                $qrUrl = asset('storage/' . $qrValue);
                                            } else {
                                                try {
                                                    $qrObj = new \Endroid\QrCode\QrCode(route('documents.show', $doc->id), size: 300);
                                                    $writer = new \Endroid\QrCode\Writer\PngWriter();
                                                    $result = $writer->write($qrObj);
                                                    $qrUrl = 'data:image/png;base64,' . base64_encode($result->getString());
                                                } catch (\Exception $e) {
                                                    $qrUrl = '';
                                                }
                                            }
                                        @endphp
                                        @if($qrUrl)
                                            <li><button class="dropdown-item" type="button" onclick="showQR('{{ $qrUrl }}')"><i class="bi bi-qr-code me-2"></i> View QR Code</button></li>
                                        @endif
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted">
                            <div class="mb-2" style="font-size: 2.5rem;">📁</div>
                            <h6 class="fw-bold text-dark">No Documents Found</h6>
                            <p class="small mb-0">Upload a new document or change your search filters to get started.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($documents, 'links'))
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <div class="small text-muted">
                Showing {{ $documents->firstItem() ?? 0 }} to {{ $documents->lastItem() ?? 0 }} of {{ $documents->total() ?? 0 }} entries
            </div>
            <div>
                {{ $documents->links('pagination::bootstrap-4') }}
            </div>
        </div>
    @endif
</div>


<script>
    // General helper
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function showQR(src) {
        document.getElementById('qrImage').src = src;
        new bootstrap.Modal(document.getElementById('qrModal')).show();
    }

    function openTrackingModal(button) {
        const title = button.dataset.title || 'Document';
        const origin = button.dataset.origin || 'Unknown';
        const current = button.dataset.current || 'In Transit';
        const destination = button.dataset.destination || 'Unknown';
        const status = button.dataset.status || 'Unknown';

        document.getElementById('trackModalTitle').textContent = title;
        document.getElementById('trackModalStatus').textContent = status;
        document.getElementById('trackOriginText').textContent = origin;
        document.getElementById('trackCurrentText').textContent = current;
        document.getElementById('trackDestinationText').textContent = destination;

        const originBox = document.getElementById('trackOriginBox');
        const currentBox = document.getElementById('trackCurrentBox');
        const destinationBox = document.getElementById('trackDestinationBox');

        const statusKey = status.toLowerCase().replace(/\s+/g, '_');
        const isAtOrigin = current === origin && origin !== 'Unknown';
        const isAtDestination = current === destination && destination !== 'Unknown';
        const isInTransitStatus = statusKey === 'in_transit';

        const originActive = isAtOrigin && !isInTransitStatus;
        const destinationActive = isAtDestination && !isInTransitStatus;
        const currentActive = isInTransitStatus || current === 'In Transit' || (!isAtOrigin && !isAtDestination);

        originBox.classList.toggle('active', originActive);
        currentBox.classList.toggle('active', currentActive);
        destinationBox.classList.toggle('active', destinationActive);

        new bootstrap.Modal(document.getElementById('trackModal')).show();
    }

    // Recipients list logic for Route Action Modal
    const selectedRecipients = new Map();
    const autoSelectedRecipients = new Set();

    function updateRecipientDisplay() {
        const container = document.getElementById('selectedRecipients');
        const selectedIds = document.getElementById('selectedIds');
        
        if (selectedRecipients.size === 0) {
            container.innerHTML = '<small class="text-muted w-100">No recipients selected yet</small>';
            selectedIds.value = '';
        } else {
            const chips = Array.from(selectedRecipients.values()).map(user => `
                <div class="badge bg-info text-dark d-inline-flex align-items-center" style="padding: 8px 12px; font-size: 0.85rem; gap: 0.5rem;">
                    <span>${user.name}</span>
                    <button type="button" class="btn custom-close-btn" onclick="removeRecipient(${user.id})" aria-label="Remove recipient"><i class="bi bi-x-lg"></i></button>
                </div>
            `).join('');
            container.innerHTML = chips;
            selectedIds.value = Array.from(selectedRecipients.keys()).join(',');
        }
    }

    function removeRecipient(userId) {
        selectedRecipients.delete(userId);
        autoSelectedRecipients.delete(userId);
        updateRecipientDisplay();
        renderStaffList();
    }

    function addRecipient(user) {
        selectedRecipients.set(user.id, user);
        updateRecipientDisplay();
    }

    const currentUserId = @json(session('user_id'));
    const currentOfficeStaff = new Map();

    async function renderStaffList() {
        const officeSelect = document.getElementById('officeSelect');
        const officeId = officeSelect.value;
        const container = document.getElementById('staffContainer');

        if (!officeId) {
            container.innerHTML = '<div class="text-muted text-center py-4"><small>👇 Select an office to see available staff</small></div>';
            return;
        }

        try {
            const response = await fetch(`/api/offices/${officeId}/staff`);
            const data = await response.json();
            const staff = (data.staff || []).filter(user => user.id !== currentUserId);

            currentOfficeStaff.clear();
            staff.forEach(u => currentOfficeStaff.set(u.id, u));

            if (staff.length === 0) {
                container.innerHTML = '<div class="text-muted text-center py-4"><small>No staff assigned to this office</small></div>';
                return;
            }

            const html = `
                <div class="list-group" style="border: none;">
                    ${staff.map(user => {
                        const isSelected = selectedRecipients.has(user.id);
                        const isAutoSelected = autoSelectedRecipients.has(user.id);
                        return `
                            <label class="list-group-item" style="background: transparent; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 8px; cursor: pointer; ${isAutoSelected ? 'box-shadow: 0 0 10px rgba(0,215,255,0.3);' : ''}">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="form-check-input me-3" 
                                           ${isSelected ? 'checked' : ''} 
                                           onchange="toggleRecipient(${user.id}, this.checked)"
                                           ${isAutoSelected ? 'disabled' : ''}>
                                    <div style="flex: 1;">
                                        <div class="small fw-bold text-info">${user.name}</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">${user.role} • ${data.office}</div>
                                    </div>
                                    ${isAutoSelected ? '<span class="badge bg-success">Auto-assigned</span>' : ''}
                                </div>
                            </label>
                        `;
                    }).join('')}
                </div>
            `;
            container.innerHTML = html;
        } catch (error) {
            console.error('Error loading staff:', error);
            container.innerHTML = '<div class="text-danger small">Error loading staff members</div>';
        }
    }

    function toggleRecipient(userId, isChecked) {
        const user = currentOfficeStaff.get(userId);
        if (isChecked) {
            if (user) addRecipient(user);
        } else {
            selectedRecipients.delete(userId);
            autoSelectedRecipients.delete(userId);
            updateRecipientDisplay();
            renderStaffList();
        }
    }

    async function autoAssignStaff() {
        const officeSelect = document.getElementById('officeSelect');
        const officeId = officeSelect.value;
        if (!officeId) return;

        try {
            const response = await fetch(`/api/offices/${officeId}/staff`);
            const data = await response.json();
            selectedRecipients.clear();
            autoSelectedRecipients.clear();
            (data.staff || []).filter(user => user.id !== currentUserId).forEach(user => {
                selectedRecipients.set(user.id, user);
                autoSelectedRecipients.add(user.id);
            });
            updateRecipientDisplay();
            renderStaffList();
        } catch (error) { console.error('Error auto-assigning:', error); }
    }

    function clearRecipients() {
        selectedRecipients.clear();
        autoSelectedRecipients.clear();
        updateRecipientDisplay();
        renderStaffList();
    }

    function setDocId(docId) {
        document.getElementById('docId').value = docId;
        const form = document.getElementById('routeForm');
        form.action = '/routing/update/' + docId;
        selectedRecipients.clear();
        autoSelectedRecipients.clear();
        document.getElementById('officeSelect').value = '';
        document.getElementById('autoAssignToggle').checked = false;
        updateRecipientDisplay();
        renderStaffList();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Table filtering & search
        const tableSearch = document.getElementById('tableSearch');
        const statusFilter = document.getElementById('statusFilter');
        const priorityFilter = document.getElementById('priorityFilter');
        const rows = document.querySelectorAll('#documentsTable tbody .document-row');

        function filterTable() {
            const query = tableSearch ? tableSearch.value.trim().toLowerCase() : '';
            const status = statusFilter ? statusFilter.value.toLowerCase() : '';
            const priority = priorityFilter ? priorityFilter.value.toLowerCase() : '';

            rows.forEach(row => {
                const title = row.getAttribute('data-title') || '';
                const tracking = row.getAttribute('data-tracking') || '';
                const receiver = row.getAttribute('data-receiver') || '';
                
                const rStatus = row.getAttribute('data-status') || '';
                const rPriority = row.getAttribute('data-priority') || '';

                const matchesQuery = title.includes(query) || tracking.includes(query) || receiver.includes(query);
                const matchesStatus = !status || rStatus === status;
                const matchesPriority = !priority || rPriority === priority;

                if (matchesQuery && matchesStatus && matchesPriority) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        tableSearch?.addEventListener('input', filterTable);
        statusFilter?.addEventListener('change', filterTable);
        priorityFilter?.addEventListener('change', filterTable);

        // Column toggles
        document.querySelectorAll('.col-toggle-chk').forEach(chk => {
            chk.addEventListener('change', function() {
                const colClass = this.getAttribute('data-col');
                const show = this.checked;
                document.querySelectorAll(`.${colClass}`).forEach(el => {
                    el.style.display = show ? '' : 'none';
                });
            });
        });

        // Bulk Selection Checkbox
        const selectAllDocs = document.getElementById('selectAllDocs');
        const docSelectChks = document.querySelectorAll('.doc-select-chk');
        const btnBulkAction = document.getElementById('btnBulkAction');

        selectAllDocs?.addEventListener('change', function() {
            docSelectChks.forEach(chk => chk.checked = this.checked);
            updateBulkActionState();
        });

        docSelectChks.forEach(chk => {
            chk.addEventListener('change', updateBulkActionState);
        });

        function updateBulkActionState() {
            const selected = Array.from(docSelectChks).some(chk => chk.checked);
            if (btnBulkAction) btnBulkAction.disabled = !selected;
        }

        const officeSelect = document.getElementById('officeSelect');
        if (officeSelect) {
            officeSelect.addEventListener('change', function() {
                clearRecipients();
                renderStaffList();
            });
        }

        const autoAssignToggle = document.getElementById('autoAssignToggle');
        if (autoAssignToggle) {
            autoAssignToggle.addEventListener('change', function() {
                if (this.checked) autoAssignStaff();
                else clearRecipients();
            });
        }

        const routeForm = document.getElementById('routeForm');
        if (routeForm) {
            routeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.delete('receiver_user_ids[]');
                Array.from(selectedRecipients.keys()).forEach(id => {
                    formData.append('receiver_user_ids[]', id);
                });

                fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
                })
                .then(response => {
                    if (response.ok) {
                        window.location.reload();
                    }
                });
            });
        }
    });
</script>

<div class="modal fade" id="routeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:#FFFFFF; border: 1px solid var(--panel-border); border-radius: 12px; color: var(--text-main);">
            <form action="/routing/update/0" method="POST" id="routeForm">
                @csrf
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold" style="color: var(--accent-navy);">📤 Route Document</h5>
                    <button type="button" class="btn custom-close-btn" data-bs-dismiss="modal" aria-label="Close" style="color: var(--text-main);"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="modal-body p-4 pt-0" style="max-height: 500px; overflow-y: auto;">
                    <input type="hidden" id="docId" name="doc_id">
                    
                    <div class="mb-4 text-start">
                        <label class="form-label fw-bold text-uppercase" style="font-weight: 700; color: var(--accent-navy);">📍 Send To Office</label>
                        <select name="office_id" id="officeSelect" class="form-select" required>
                            <option value="">-- Select office --</option>
                            @foreach($offices as $office)
                                <option value="{{ $office->id }}">{{ $office->name }} ({{ $office->department }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4 p-3 border rounded" style="background: var(--bg);">
                        <div class="form-check form-switch text-start">
                            <input class="form-check-input" type="checkbox" id="autoAssignToggle">
                            <label class="form-check-label fw-bold" for="autoAssignToggle" style="color: var(--accent-navy);">⚡ Auto-assign all staff</label>
                        </div>
                    </div>

                    <div class="mb-4 text-start">
                        <label class="form-label fw-bold text-uppercase" style="font-weight: 700; color: var(--accent-navy);">👥 Recipients</label>
                        <div id="staffContainer" class="p-3 border rounded" style="background: #FFFFFF; min-height: 120px;">
                            <div class="text-muted text-center py-4"><small>Select an office first</small></div>
                        </div>
                    </div>

                    <div class="mb-4 text-start">
                        <label class="form-label fw-bold text-uppercase" style="font-weight: 700; color: var(--success);">✅ Selected Recipients</label>
                        <div id="selectedRecipients" class="d-flex flex-wrap gap-2">
                            <small class="text-muted w-100">No recipients selected</small>
                        </div>
                        <input type="hidden" name="receiver_user_ids[]" id="selectedIds" value="">
                    </div>

                    <div class="mb-3 text-start">
                        <label class="form-label fw-bold text-uppercase" style="font-weight: 700; color: var(--accent-navy);">📝 Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top p-4" style="border-color: var(--panel-border);">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-5" style="border-radius: 8px;">✓ Route Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#FFFFFF; border: 1px solid var(--panel-border); border-radius: 12px; color: var(--text-main);">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" style="color: var(--accent-navy);">Document QR Code</h5>
                <button type="button" class="btn custom-close-btn" data-bs-dismiss="modal" aria-label="Close" style="color: var(--text-main);"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body text-center">
                <img id="qrImage" src="" alt="QR Code" class="img-fluid" style="border: 1px solid var(--panel-border); border-radius: 8px; padding: 12px; background: #FFFFFF;">
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="trackModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#FFFFFF; border: 1px solid var(--panel-border); border-radius: 12px; color: var(--text-main);">
            <div class="modal-header border-0">
                <div class="text-start">
                    <h5 class="modal-title fw-bold" id="trackModalTitle" style="color: var(--accent-navy);">Document Route</h5>
                    <p class="text-secondary small mb-0" id="trackModalStatus"></p>
                </div>
                <button type="button" class="btn custom-close-btn" data-bs-dismiss="modal" aria-label="Close" style="color: var(--text-main);"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body">
                <div class="track-steps">
                    <div class="track-step" id="trackOriginBox">
                        <div class="track-step-icon">📤</div>
                        <div class="track-step-title">Origin</div>
                        <div id="trackOriginText" class="track-step-name"></div>
                        <div class="track-step-desc">Starting office</div>
                    </div>
                    <div class="track-step" id="trackCurrentBox">
                        <div class="track-step-icon">📍</div>
                        <div class="track-step-title">Current</div>
                        <div id="trackCurrentText" class="track-step-name"></div>
                        <div class="track-step-desc">Where the document is now</div>
                    </div>
                    <div class="track-step" id="trackDestinationBox">
                        <div class="track-step-icon">📥</div>
                        <div class="track-step-title">Destination</div>
                        <div id="trackDestinationText" class="track-step-name"></div>
                        <div class="track-step-desc">Final target office</div>
                    </div>
                </div>
                <p class="text-secondary small">This popup shows the current route position for the selected document. Only the active step is highlighted.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection