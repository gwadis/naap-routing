@php
    $offices = \App\Models\Office::orderBy('name', 'asc')->get();
    $users = \App\Models\User::with('department')->orderBy('name', 'asc')->get();
@endphp

<style>
    /* Fixed scrollable layout for modal */
    #uploadModal .modal-dialog {
        max-width: 1100px !important;
        width: 95% !important;
        margin: 1.75rem auto !important;
    }
    #uploadModal .modal-content {
        max-height: 90vh !important;
        height: 90vh !important;
        display: flex !important;
        flex-direction: column !important;
        border-radius: var(--radius-xl) !important;
        border: 1px solid var(--panel-border) !important;
        background: var(--panel) !important;
        overflow: hidden !important;
    }
    #uploadModal .modal-header {
        flex-shrink: 0 !important;
        position: sticky !important;
        top: 0 !important;
        background: var(--panel) !important;
        border-bottom: 1px solid var(--panel-border) !important;
        z-index: 1060 !important;
        padding: 16px 24px !important;
    }
    #uploadModal .modal-body {
        overflow-y: auto !important;
        flex-grow: 1 !important;
        background: var(--bg) !important;
        padding: 24px !important;
    }
    #uploadModal .modal-footer {
        flex-shrink: 0 !important;
        position: sticky !important;
        bottom: 0 !important;
        background: var(--panel) !important;
        border-top: 1px solid var(--panel-border) !important;
        z-index: 1060 !important;
        padding: 16px 24px !important;
        display: flex !important;
        justify-content: flex-end !important;
        align-items: center !important;
        gap: 12px !important;
    }

    #uploadModal form {
        display: flex !important;
        flex-direction: column !important;
        height: 100% !important;
        width: 100% !important;
        margin: 0 !important;
        overflow: hidden !important;
    }

    /* Visual Workflow Timeline Builder */
    .timeline-builder {
        position: relative;
        padding: 20px;
        background: var(--bg-secondary, #F8FAFC);
        border-radius: var(--radius-lg);
        border: 1px solid var(--panel-border);
    }
    .timeline-step-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-md);
        padding: 16px;
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
        border-left: 4px solid var(--success) !important;
        background: rgba(16, 185, 129, 0.01) !important;
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

    /* Premium Drag & Drop Area */
    .premium-upload-zone {
        border: 2px dashed var(--panel-border);
        border-radius: var(--radius-lg);
        background: var(--panel);
        padding: 32px 24px;
        text-align: center;
        cursor: pointer;
        transition: all var(--transition-speed) ease;
        position: relative;
    }
    .premium-upload-zone:hover {
        border-color: var(--accent-cyan);
        background: rgba(59, 130, 246, 0.01);
    }
    .premium-upload-zone i.upload-icon {
        font-size: 2.2rem;
        color: var(--text-dim);
        margin-bottom: 12px;
        display: inline-block;
        transition: color var(--transition-speed);
    }
    .premium-upload-zone:hover i.upload-icon {
        color: var(--accent-cyan);
    }

    /* File details panel after selection */
    .file-details-panel {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px;
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        background: var(--panel);
        margin-top: 12px;
        text-align: left;
    }
    .file-details-icon {
        width: 44px;
        height: 44px;
        border-radius: var(--radius-md);
        background: rgba(59, 130, 246, 0.08);
        color: var(--accent-cyan);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    /* Priority selector chips */
    .priority-chips {
        display: flex;
        gap: 8px;
        width: 100%;
    }
    .priority-chip {
        flex: 1;
        padding: 8px 12px;
        border-radius: var(--radius-md);
        border: 1px solid var(--panel-border);
        font-size: 12.5px;
        font-weight: 600;
        text-align: center;
        cursor: pointer;
        transition: all var(--transition-speed) ease;
        background: var(--panel);
    }
    .priority-chip.selected {
        border-color: currentColor;
        box-shadow: 0 0 0 2px currentColor;
    }
    .priority-chip-low { color: #64748B; background: rgba(100, 116, 139, 0.04); }
    .priority-chip-low.selected, .priority-chip-low:hover { background: rgba(100, 116, 139, 0.08); }
    .priority-chip-normal { color: #047857; background: rgba(16, 185, 129, 0.04); }
    .priority-chip-normal.selected, .priority-chip-normal:hover { background: rgba(16, 185, 129, 0.08); }
    .priority-chip-high { color: #B45309; background: rgba(245, 158, 11, 0.04); }
    .priority-chip-high.selected, .priority-chip-high:hover { background: rgba(245, 158, 11, 0.08); }
    .priority-chip-urgent { color: #BE123C; background: rgba(244, 63, 94, 0.04); }
    .priority-chip-urgent.selected, .priority-chip-urgent:hover { background: rgba(244, 63, 94, 0.08); }

    .confidential-card {
        transition: all var(--transition-speed) ease;
    }
    .confidential-card.confidential-active {
        border-color: var(--danger) !important;
        background: rgba(244, 63, 94, 0.02) !important;
        box-shadow: 0 0 0 2px rgba(244, 63, 94, 0.1) !important;
    }

    .tags-input-container {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 6px 12px;
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-md);
        background: var(--panel);
        min-height: 40px;
        align-items: center;
    }
    .tag-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(59, 130, 246, 0.08);
        color: var(--accent-cyan);
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    .tag-chip-remove {
        background: transparent;
        border: none;
        color: var(--text-dim);
        cursor: pointer;
        padding: 0;
        font-size: 10px;
        display: flex;
        align-items: center;
    }
    .tag-chip-remove:hover {
        color: var(--danger);
    }
    .tags-input-container input {
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
        background: transparent !important;
        font-size: 13px !important;
        flex: 1;
        min-width: 60px;
        height: 28px !important;
        padding: 0 !important;
    }
    .bg-slate-100 {
        background-color: #f1f5f9 !important;
    }
    .text-slate-600 {
        color: #475569 !important;
    }
</style>

<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" id="documentUploadForm" novalidate>
                @csrf
                <!-- Sticky Header -->
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="modal-title fw-bold" style="color: var(--text-main); font-size:16px;">Document Upload</h5>
                        <p class="small text-slate-500 mb-0">Upload and configure the routing for your organization document.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Scrollable Body -->
                <div class="modal-body">
                    <div class="row g-4">
                        <!-- Left Column: Primary Config (70% width) -->
                        <div class="col-lg-8">
                            <!-- Section 1: Upload File -->
                            <div class="card p-4 border text-start mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold mb-0" style="font-size:13.5px;"><i class="bi bi-cloud-arrow-up text-primary me-1"></i> 1. Upload File</h6>
                                    <span class="badge bg-slate-100 text-slate-600 border" style="font-size:10px;">Required</span>
                                </div>
                                <p class="small text-slate-500 mb-3" style="font-size: 12px; margin-top:-8px;">Drag your document here or select it from your device.</p>
                                
                                <div class="premium-upload-zone" id="uploadZone"
                                     ondragover="event.preventDefault(); this.style.borderColor='var(--accent-cyan)';"
                                     ondragleave="this.style.borderColor='var(--panel-border)';"
                                     ondrop="handleFileDrop(event);">
                                    <input type="file" name="file" id="fileHidden" 
                                           style="position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 5;"
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                                           onchange="updateFileName(this)">
                                    <div id="uploadPlaceholder">
                                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                                        <h6 class="fw-bold mb-1" style="font-size: 13.5px;">Drag and drop file here</h6>
                                        <p class="small text-slate-500 mb-3" style="font-size: 12px;">or click to browse from device</p>
                                        <div class="text-slate-400" style="font-size: 11px;">
                                            PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG — Max 10MB
                                        </div>
                                    </div>
                                    
                                    <!-- File Details -->
                                    <div id="uploadFileDetailsPanel" style="display: none; z-index: 6;" class="file-details-panel">
                                        <div class="file-details-icon">
                                            <i class="bi bi-file-earmark-text" id="fileDetailsIcon"></i>
                                        </div>
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-bold text-truncate" id="fileDetailsName" style="font-size:13px; color: var(--text-main);">filename.pdf</span>
                                                <span class="badge bg-success" style="font-size: 9px; padding: 2px 6px;">Ready</span>
                                            </div>
                                            <span class="small text-slate-400" id="fileDetailsSize">1.2 MB</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary p-0" id="removeFileBtn" style="width:28px; height:28px; border-radius: 50% !important; border:none !important;" title="Remove File">
                                            <i class="bi bi-x-lg text-danger"></i>
                                        </button>
                                    </div>
                                </div>
                                <span class="invalid-feedback-msg" id="validation-file" style="display: none;">Please select a file to upload.</span>
                            </div>

                            <!-- Section 2: Document Info -->
                            <div class="card p-4 border text-start mb-4">
                                <h6 class="fw-bold mb-3" style="font-size:13.5px;"><i class="bi bi-file-earmark-text text-primary me-1"></i> 2. Document Information</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-secondary">Document Title</label>
                                        <input type="text" name="title" id="titleField" class="form-control" placeholder="Enter file descriptive title" required>
                                        <span class="invalid-feedback-msg" id="validation-title" style="display: none;">Document title is required.</span>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-secondary">Document Description</label>
                                        <textarea name="description" id="descriptionField" class="form-control" rows="3" placeholder="Explain the purpose, summary, and add any notes..." required></textarea>
                                        <span class="invalid-feedback-msg" id="validation-description" style="display: none;">Document description is required.</span>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label small fw-bold text-secondary">Origin Office</label>
                                        <select name="origin_office_id" id="originOfficeSelect" class="form-select" required>
                                            @php
                                                $currUserOfficeId = auth()->user()?->office_id ?? session('office_id');
                                                if (!$currUserOfficeId && session('user_id')) {
                                                    $currUserOfficeId = \App\Models\User::find(session('user_id'))?->office_id;
                                                }
                                            @endphp
                                            @foreach($offices as $office)
                                                <option value="{{ $office->id }}" {{ $currUserOfficeId == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-secondary">Final Destination Office</label>
                                        <select name="final_office_id" id="finalOfficeSelect" class="form-select" required>
                                            <option value="">-- Choose Office --</option>
                                            @foreach($offices as $office)
                                                <option value="{{ $office->id }}">{{ $office->name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback-msg" id="validation-final-office" style="display: none;">Final destination office is required.</span>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-secondary">Final Destination Receiver</label>
                                        <select name="final_receiver_id" id="finalReceiverSelect" class="form-select" required disabled>
                                            <option value="">-- Select Office First --</option>
                                        </select>
                                        <span class="invalid-feedback-msg" id="validation-final-receiver" style="display: none;">Final receiver user is required.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Routing Builder -->
                            <div class="card p-4 border text-start mb-4">
                                <h6 class="fw-bold mb-1" style="font-size:13.5px;"><i class="bi bi-diagram-3 text-primary me-1"></i> 3. Routing Sequence Builder</h6>
                                <p class="small text-slate-500 mb-3" style="font-size:12px;">Configure step-by-step approvals between Origin and Final Destination.</p>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1" style="font-size: 11px !important;">Select Office</label>
                                        <select id="seqOfficeSelect" class="form-select">
                                            <option value="">-- Choose Office --</option>
                                            @foreach($offices as $office)
                                                <option value="{{ $office->id }}" data-department="{{ $office->department }}">{{ $office->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1" style="font-size: 11px !important;">Select Receiver</label>
                                        <select id="seqUserSelect" class="form-select" disabled>
                                            <option value="">-- Select Office First --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1" style="font-size: 11px !important;">Approval Type</label>
                                        <select id="seqApprovalType" class="form-select">
                                            <option value="sequential">Sequential</option>
                                            <option value="parallel">Parallel Approval</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1" style="font-size: 11px !important;">Expected SLA</label>
                                        <select id="seqSla" class="form-select">
                                            <option value="24_hours">24 Hours</option>
                                            <option value="48_hours" selected>48 Hours</option>
                                            <option value="3_days">3 Days</option>
                                            <option value="5_days">5 Days</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center justify-content-between pt-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="seqSignatureRequired" checked>
                                            <label class="form-check-label small fw-bold text-secondary" for="seqSignatureRequired">Sign Required</label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm w-100" id="addSeqStepBtn" style="height:38px !important;">
                                            <i class="bi bi-plus-lg"></i> Add Approval Step
                                        </button>
                                    </div>
                                </div>

                                <!-- Sequence Visual Timeline Builder -->
                                <div class="timeline-builder">
                                    <label class="form-label small fw-bold text-secondary mb-2 d-block">Visual Flow Sequence</label>
                                    
                                    <!-- Origin Step Card -->
                                    <div class="timeline-step-card border-dashed bg-light text-start py-2 px-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-secondary rounded-circle">O</span>
                                            <strong class="text-secondary small">Origin Office: <span id="timelineOriginOfficeName">N/A</span></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-connector"><i class="bi bi-arrow-down-short"></i></div>

                                    <!-- Dyn Approval Steps -->
                                    <div id="routingSequenceContainer" style="min-height: 20px;">
                                        <!-- Steps added go here -->
                                    </div>
                                    
                                    <!-- Final Destination Card -->
                                    <div class="timeline-step-card final-dest-card mt-2">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span style="font-size: 14px;">🏁</span>
                                            <h6 class="fw-bold mb-0 text-success" style="font-size: 13px;">Final Destination: <span id="timelineFinalOfficeName">Choose final...</span></h6>
                                            <span class="badge bg-success-subtle text-success ms-auto" style="font-size: 9px;">End Step</span>
                                        </div>
                                        <p class="small text-muted mb-0" style="font-size: 10.5px; line-height:1.3;">Document permanently ends here. This step is locked to the bottom.</p>
                                        <div class="small text-muted mt-2" id="timelineFinalReceiverDisplay" style="display:none; font-size:11px;">
                                            Receiver: <span class="fw-bold text-dark" id="timelineFinalReceiverName"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <span class="invalid-feedback-msg" id="validation-routing" style="display: none;">At least one approval step is required.</span>
                                <span class="invalid-feedback-msg" id="validation-circular" style="display: none;">Circular routing detected. Office cannot be added twice.</span>
                                <span class="invalid-feedback-msg" id="validation-duplicate" style="display: none;">Duplicate step detected in routing.</span>
                                <small class="text-muted d-block mt-2" id="seqCountMsg">No steps added to sequence.</small>
                            </div>
                        </div>

                        <!-- Right Column: Summary & Metadata (30% width) -->
                        <div class="col-lg-4">
                            <!-- Section 4: Classification -->
                            <div class="card p-4 border text-start mb-4">
                                <h6 class="fw-bold mb-3" style="font-size:13.5px;"><i class="bi bi-tag text-primary me-1"></i> Classification</h6>
                                
                                <!-- Priority Select -->
                                <input type="hidden" name="priority" value="Normal">

                                <!-- Category Dropdown -->
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">Document Type / Category</label>
                                    <select name="category" id="categorySelect" class="form-select" required>
                                        <option value="">-- Choose Type --</option>
                                        <option value="Class Schedule">Class Schedule</option>
                                        <option value="Faculty Workload">Faculty Workload</option>
                                        <option value="Operational Plans">Operational Plans</option>
                                        <option value="Endorsements">Endorsements</option>
                                        <option value="Payrolls">Payrolls</option>
                                        <option value="Finance Related Documents">Finance Related Documents</option>
                                        <option value="Others">Others</option>
                                    </select>
                                    <span class="invalid-feedback-msg" id="validation-category" style="display: none;">Document category is required.</span>

                                    <!-- Custom Category Input (for "Others") -->
                                    <div class="mt-2" id="customCategoryInputWrapper" style="display: none;">
                                        <label class="form-label small fw-bold text-secondary">Specify Category / Subject</label>
                                        <input type="text" name="custom_category" id="customCategoryInput" class="form-control" placeholder="Enter custom subject or category...">
                                    </div>
                                </div>

                                <!-- Tags Field -->
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">Tags</label>
                                    <input type="hidden" name="tags" id="realTagsInput">
                                    <div class="tags-input-container" id="tagsInputContainer">
                                        <input type="text" id="tagsTextHelper" placeholder="Add tag...">
                                    </div>
                                </div>

                                <!-- SLA Select -->
                                <div>
                                    <label class="form-label small fw-bold text-secondary">Processing Time Category</label>
                                    <select name="sla" class="form-select" required>
                                        <option value="Simple Transaction (3 Working Days)">Simple Transaction (3 Working Days)</option>
                                        <option value="Complex Transaction (7 Working Days)">Complex Transaction (7 Working Days)</option>
                                        <option value="Highly Technical Transaction (20 Working Days)">Highly Technical Transaction (20 Working Days)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Section 5: Security -->
                            <div class="card p-4 border text-start mb-4 confidential-card" id="confidentialCardWrapper">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold mb-0" style="font-size:13.5px;"><i class="bi bi-shield-lock text-primary me-1"></i> Security &amp; Permissions</h6>
                                    <span class="badge bg-slate-100 text-slate-600 border" style="font-size:10px; color: #475569 !important; background-color: #f1f5f9 !important;">Optional</span>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_confidential" id="confidentialToggle" value="1">
                                    <label class="form-check-label small fw-bold text-secondary" for="confidentialToggle">Restrict View (Confidential)</label>
                                </div>
                            </div>

                            <!-- Section 6: Review Summary -->
                            <div class="card p-4 border text-start mb-4" id="routeSummaryPanel" style="display: none;">
                                <h6 class="fw-bold mb-3" style="font-size:13.5px;"><i class="bi bi-card-checklist text-primary me-1"></i> Route Summary</h6>
                                <div class="p-3 bg-light rounded border mb-2 small" style="line-height: 1.6;">
                                    <div><span class="text-secondary">Origin Office:</span> <strong id="summaryOriginOffice">N/A</strong></div>
                                    <div><span class="text-secondary">Final Destination:</span> <strong id="summaryFinalDestination">N/A</strong></div>
                                    <div><span class="text-secondary">Est. Lead Time:</span> <strong id="summaryTotalTime">N/A</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky Footer -->
                <div class="modal-footer">
                    <div id="uploadProgressContainer" class="flex-grow-1 text-start" style="display: none; max-width: 50%;">
                        <div class="d-flex justify-content-between mb-1 small text-muted">
                            <span>Uploading Document...</span>
                            <span id="uploadProgressPercent" class="fw-bold">0%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="uploadProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary px-4" id="btnSaveDraftPlaceholder">Save Draft</button>
                    <button type="submit" class="btn btn-primary px-4">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="duplicateModal" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#FFFFFF; border-radius: 12px; border: 1px solid var(--panel-border); color: var(--text-main); height: auto !important; max-height: none !important;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Duplicate File Warning</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-2 text-start" style="background: transparent !important; overflow-y: visible !important;">
                <p>The exact file you uploaded already exists in the system as:</p>
                <div class="p-3 bg-light rounded border mb-3">
                    <strong style="color: var(--accent-navy);" id="duplicateDocTitle">N/A</strong><br>
                    <small class="text-secondary">Tracking: <span id="duplicateDocTracking">N/A</span></small>
                </div>
                <p class="mb-0 small text-muted">What would you like to do with this document upload?</p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-end gap-2" style="background: transparent !important; border-top: none !important;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning fw-bold text-dark" id="btnDuplicateOverwrite">Overwrite Existing</button>
                <button type="button" class="btn btn-primary fw-bold" id="btnDuplicateVersion">Upload as New Version</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadSuccessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px !important;">
        <div class="modal-content text-center p-4" style="background:#FFFFFF; border-radius: 16px; border: 1px solid var(--panel-border); box-shadow: 0 20px 40px rgba(0,0,0,0.15); height: auto !important; max-height: none !important;">
            <div class="mb-3">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: #ecfdf5; color: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 30px;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
            <h4 class="fw-bold mb-1" style="color: #1e293b;">Document Uploaded!</h4>
            <p class="text-secondary small mb-3">Your document has been registered and initialized in the routing system.</p>
            
            <div class="p-3 mb-3 text-start rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small fw-semibold">TRACKING NUMBER</span>
                    <span class="badge bg-primary px-2 py-1 font-monospace" id="successModalTracking">DOC-000000</span>
                </div>
                <div class="fw-bold text-dark text-truncate" id="successModalTitle">Document Title</div>
            </div>

            <div id="popupBlockedAlert" class="alert alert-warning text-start small py-2 px-3 mb-3 d-none" style="font-size: 13px;">
                <i class="bi bi-exclamation-circle-fill me-1"></i>
                <strong>Notice:</strong> Your browser blocked the automatic new tab. Click the button below to view and print your QR Routing Label.
            </div>

            <div class="d-grid gap-2">
                <a id="btnSuccessPrintQr" href="#" target="_blank" class="btn btn-primary py-2 fw-bold d-flex align-items-center justify-content-center gap-2" style="background: #2563eb; border-color: #2563eb; font-size: 0.95rem;">
                    <i class="bi bi-printer-fill fs-5"></i> Open & Print QR Routing Label
                </a>
                <div class="d-flex gap-2">
                    <a id="btnSuccessViewDoc" href="#" class="btn btn-outline-secondary flex-fill py-2" style="font-size: 0.9rem;">
                        <i class="bi bi-file-text me-1"></i> View Details
                    </a>
                    <button type="button" id="btnSuccessClose" class="btn btn-light flex-fill py-2 border" onclick="window.location.href = '{{ route("documents.index") }}'" style="font-size: 0.9rem;">
                        All Documents
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Priority chips interactive logic removed.

    // Remove file selection button logic
    document.getElementById('removeFileBtn')?.addEventListener('click', function(e) {
        e.stopPropagation();
        const input = document.getElementById('fileHidden');
        if (input) {
            input.value = '';
            updateFileName(input);
        }
    });

    // Confidentiality Toggle handling
    const confidentialToggle = document.getElementById('confidentialToggle');
    const confidentialCardWrapper = document.getElementById('confidentialCardWrapper');

    if (confidentialToggle) {
        confidentialToggle.addEventListener('change', function() {
            const isChecked = this.checked;
            if (confidentialCardWrapper) {
                if (isChecked) {
                    confidentialCardWrapper.classList.add('confidential-active');
                } else {
                    confidentialCardWrapper.classList.remove('confidential-active');
                }
            }
        });
    }

    // Dynamic tags array logic
    const tags = [];
    const tagsInputContainer = document.getElementById('tagsInputContainer');
    const tagsTextHelper = document.getElementById('tagsTextHelper');
    const realTagsInput = document.getElementById('realTagsInput');

    function renderTags() {
        if (!tagsInputContainer || !tagsTextHelper || !realTagsInput) return;
        tagsInputContainer.querySelectorAll('.tag-chip').forEach(chip => chip.remove());
        
        tags.forEach((tag, idx) => {
            const chip = document.createElement('span');
            chip.className = 'tag-chip';
            chip.innerHTML = `
                <span>${escapeHtml(tag)}</span>
                <button type="button" class="tag-chip-remove" onclick="removeTag(${idx})"><i class="bi bi-x-lg"></i></button>
            `;
            tagsInputContainer.insertBefore(chip, tagsTextHelper);
        });
        
        realTagsInput.value = tags.join(',');
    }

    window.removeTag = function(idx) {
        tags.splice(idx, 1);
        renderTags();
    };

    if (tagsTextHelper) {
        tagsTextHelper.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const val = this.value.trim().replace(/,/g, '');
                if (val && !tags.includes(val)) {
                    tags.push(val);
                    this.value = '';
                    renderTags();
                }
            } else if (e.key === 'Backspace' && this.value === '' && tags.length > 0) {
                tags.pop();
                renderTags();
            }
        });
    }

    function handleFileDrop(event) {
        event.preventDefault();
        const zone  = document.getElementById('uploadZone');
        const input = document.getElementById('fileHidden');
        if (event.dataTransfer.files.length > 0) {
            const dt = new DataTransfer();
            dt.items.add(event.dataTransfer.files[0]);
            input.files = dt.files;
            updateFileName(input);
        }
        if (zone) zone.style.borderColor = 'var(--panel-border)';
    }

    function updateFileName(input) {
        const placeholder = document.getElementById('uploadPlaceholder');
        const detailsPanel = document.getElementById('uploadFileDetailsPanel');
        const detailsName = document.getElementById('fileDetailsName');
        const detailsSize = document.getElementById('fileDetailsSize');
        const detailsIcon = document.getElementById('fileDetailsIcon');
        const zone = document.getElementById('uploadZone');

        if (input.files.length > 0) {
            const file = input.files[0];
            const sizeMB = (file.size / 1024 / 1024).toFixed(2);
            const ext = file.name.split('.').pop().toLowerCase();
            
            let iconClass = 'bi-file-earmark-text';
            if (ext === 'pdf') iconClass = 'bi-file-earmark-pdf text-danger';
            else if (['doc', 'docx'].includes(ext)) iconClass = 'bi-file-earmark-word text-primary';
            else if (['xls', 'xlsx'].includes(ext)) iconClass = 'bi-file-earmark-excel text-success';
            else if (['jpg', 'jpeg', 'png'].includes(ext)) iconClass = 'bi-file-earmark-image text-warning';
            
            if (detailsIcon) detailsIcon.className = `bi ${iconClass}`;
            if (detailsName) detailsName.textContent = file.name;
            if (detailsSize) detailsSize.textContent = `${sizeMB} MB`;
            
            if (placeholder) placeholder.style.display = 'none';
            if (detailsPanel) detailsPanel.style.display = 'flex';
            if (zone) zone.style.borderColor = 'var(--accent-cyan)';
            
            zone.classList.remove('is-invalid-field');
            const err = document.getElementById('validation-file');
            if (err) err.style.display = 'none';
        } else {
            if (placeholder) placeholder.style.display = 'block';
            if (detailsPanel) detailsPanel.style.display = 'none';
            if (zone) zone.style.borderColor = 'var(--panel-border)';
        }
    }

    // Visual Timeline Builder JS
    let routingSteps = [];
    const estProcessingTimes = {
        'Registrar': '1 Day',
        'Academic Dean': '2 Days',
        'Finance Office': '3 Days',
        'HR Office': '2 Days',
        'VPAA': '1 Day'
    };

    const seqOfficeSelect = document.getElementById('seqOfficeSelect');
    const seqUserSelect = document.getElementById('seqUserSelect');
    const addSeqStepBtn = document.getElementById('addSeqStepBtn');
    const routingSequenceContainer = document.getElementById('routingSequenceContainer');
    const noRoutingStepsMsg = document.getElementById('noRoutingStepsMsg');
    const seqCountMsg = document.getElementById('seqCountMsg');
    const finalDestinationDisplay = document.getElementById('finalDestinationDisplay');
    const finalDestinationId = document.getElementById('finalDestinationId');

    // Origin office changes event listener
    const originOfficeSelect = document.getElementById('originOfficeSelect');
    if (originOfficeSelect) {
        originOfficeSelect.addEventListener('change', function() {
            const originName = this.options[this.selectedIndex]?.text || 'N/A';
            const el = document.getElementById('timelineOriginOfficeName');
            if (el) el.textContent = originName;
            renderRoutingSequence();
        });
        // Trigger initial load
        setTimeout(() => {
            const originName = originOfficeSelect.options[originOfficeSelect.selectedIndex]?.text || 'N/A';
            const el = document.getElementById('timelineOriginOfficeName');
            if (el) el.textContent = originName;
        }, 100);
    }

    // Step Add user fetchers
    if (seqOfficeSelect) {
        seqOfficeSelect.addEventListener('change', async function() {
            const officeId = this.value;
            if (!officeId) {
                seqUserSelect.innerHTML = '<option value="">-- Choose Office First --</option>';
                seqUserSelect.disabled = true;
                if (addSeqStepBtn) addSeqStepBtn.disabled = true;
                return;
            }

            seqUserSelect.innerHTML = '<option value="">Loading staff...</option>';
            seqUserSelect.disabled = true;
            if (addSeqStepBtn) addSeqStepBtn.disabled = true;

            try {
                const response = await fetch(`/api/offices/${officeId}/staff`);
                const data = await response.json();
                const staff = data.staff || [];

                if (staff.length === 0) {
                    seqUserSelect.innerHTML = '<option value="">No available receivers for this office.</option>';
                    seqUserSelect.disabled = true;
                    if (addSeqStepBtn) addSeqStepBtn.disabled = true;
                } else {
                    seqUserSelect.innerHTML = '<option value="">-- Select Receiver --</option>';
                    staff.forEach(user => {
                        const opt = document.createElement('option');
                        opt.value = user.id;
                        opt.textContent = `${user.name} (${user.role})`;
                        seqUserSelect.appendChild(opt);
                    });
                    seqUserSelect.disabled = false;
                    if (addSeqStepBtn) addSeqStepBtn.disabled = false;
                }
            } catch(e) {
                console.error(e);
                seqUserSelect.innerHTML = '<option value="">Error loading staff</option>';
                if (addSeqStepBtn) addSeqStepBtn.disabled = true;
            }
        });
    }

    // Final Destination office/receiver selection handler
    const finalOfficeSelect = document.getElementById('finalOfficeSelect');
    const finalReceiverSelect = document.getElementById('finalReceiverSelect');
    if (finalOfficeSelect && finalReceiverSelect) {
        finalOfficeSelect.addEventListener('change', async function() {
            const officeId = this.value;
            finalReceiverSelect.innerHTML = '<option value="">-- Loading Staff --</option>';
            finalReceiverSelect.disabled = true;

            const elName = document.getElementById('timelineFinalOfficeName');
            const officeText = this.options[this.selectedIndex]?.text || 'Choose final...';
            if (elName) elName.textContent = officeText;

            if (!officeId) {
                finalReceiverSelect.innerHTML = '<option value="">-- Select Office First --</option>';
                document.getElementById('timelineFinalReceiverDisplay').style.display = 'none';
                renderRoutingSequence();
                return;
            }

            try {
                const response = await fetch(`/api/offices/${officeId}/staff`);
                const data = await response.json();
                const staff = data.staff || [];

                if (staff.length === 0) {
                    finalReceiverSelect.innerHTML = '<option value="">No available receivers for this office.</option>';
                } else {
                    finalReceiverSelect.innerHTML = '<option value="">-- Select Receiver --</option>';
                    staff.forEach(user => {
                        const opt = document.createElement('option');
                        opt.value = user.id;
                        opt.textContent = `${user.name} (${user.role})`;
                        finalReceiverSelect.appendChild(opt);
                    });
                    finalReceiverSelect.disabled = false;
                }
                renderRoutingSequence();
            } catch(e) {
                console.error(e);
                finalReceiverSelect.innerHTML = '<option value="">Error loading staff</option>';
            }
        });

        finalReceiverSelect.addEventListener('change', function() {
            const recName = this.options[this.selectedIndex]?.text || '';
            const elRec = document.getElementById('timelineFinalReceiverName');
            const disp = document.getElementById('timelineFinalReceiverDisplay');
            if (recName) {
                if (elRec) elRec.textContent = recName.split('(')[0].trim();
                if (disp) disp.style.display = 'block';
            } else {
                if (disp) disp.style.display = 'none';
            }
            renderRoutingSequence();
        });
    }

    if (addSeqStepBtn) {
        addSeqStepBtn.addEventListener('click', function() {
            const officeId = seqOfficeSelect.value;
            const officeName = seqOfficeSelect.options[seqOfficeSelect.selectedIndex].text;
            const department = seqOfficeSelect.options[seqOfficeSelect.selectedIndex].getAttribute('data-department');
            const userId = seqUserSelect.value;
            const userName = seqUserSelect.options[seqUserSelect.selectedIndex]?.text;
            const approvalType = document.getElementById('seqApprovalType').value;
            const sla = document.getElementById('seqSla').value.replace('_', ' ');
            const signatureRequired = document.getElementById('seqSignatureRequired').checked;

            if (!officeId || !userId) {
                alert('Please select both an Office and a Receiver.');
                return;
            }

            // Add step to array
            routingSteps.push({
                officeId: officeId,
                officeName: officeName,
                userId: userId,
                userName: userName,
                department: department,
                approvalType: approvalType,
                sla: sla,
                signatureRequired: signatureRequired
            });

            // Reset select dropdowns
            seqOfficeSelect.value = '';
            seqUserSelect.innerHTML = '<option value="">-- Choose Office First --</option>';
            seqUserSelect.disabled = true;
            addSeqStepBtn.disabled = true;

            renderRoutingSequence();
        });
    }

    function renderRoutingSequence() {
        if (!routingSequenceContainer) return;
        routingSequenceContainer.innerHTML = '';

        if (routingSteps.length === 0) {
            if (seqCountMsg) {
                seqCountMsg.textContent = 'No approval steps added. Direct path to Final Destination.';
                seqCountMsg.style.color = 'var(--text-dim)';
            }
            
            // Render Summary Panel
            const originOfficeName = originOfficeSelect ? originOfficeSelect.options[originOfficeSelect.selectedIndex]?.text : 'N/A';
            const finalOfficeName = finalOfficeSelect ? finalOfficeSelect.options[finalOfficeSelect.selectedIndex]?.text : 'N/A';

            document.getElementById('summaryOriginOffice').textContent = originOfficeName;
            document.getElementById('summaryFinalDestination').textContent = finalOfficeName;
            document.getElementById('summaryTotalTime').textContent = '2 Days';
            document.getElementById('routeSummaryPanel').style.display = 'block';
            return;
        }

        let totalDays = 0;
        const timelineWrapper = document.createElement('div');
        timelineWrapper.className = 'w-100';

        routingSteps.forEach((step, index) => {
            const row = document.createElement('div');
            row.className = 'w-100';
            
            const estTime = step.sla || '48 Hours';
            const days = estTime.includes('day') ? parseInt(estTime) : 2;
            totalDays += days;

            const stepNum = index + 1;
            const badgeType = step.approvalType === 'parallel' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700';
            const typeLabel = step.approvalType === 'parallel' ? 'Parallel Approval' : 'Sequential';
            const sigLabel = step.signatureRequired ? 'Signature Required' : 'Signature Not Required';
            const sigClass = step.signatureRequired ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600';

            row.innerHTML = `
                <div class="timeline-step-card p-3 d-flex flex-column gap-2 text-start">
                    <div class="d-flex justify-content-between align-items-start w-100">
                        <div class="d-flex align-items-center gap-2">
                            <span class="timeline-step-num">${stepNum}</span>
                            <strong class="text-dark" style="font-size:13.5px;">${escapeHtml(step.officeName)}</strong>
                        </div>
                        <div class="timeline-card-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm p-1" onclick="moveRouteStep(${index}, -1)" ${index === 0 ? 'disabled' : ''} style="height: 24px !important; width: 24px !important; display: inline-flex; align-items: center; justify-content: center; border:none !important;"><i class="bi bi-arrow-up"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm p-1" onclick="moveRouteStep(${index}, 1)" ${index === routingSteps.length - 1 ? 'disabled' : ''} style="height: 24px !important; width: 24px !important; display: inline-flex; align-items: center; justify-content: center; border:none !important;"><i class="bi bi-arrow-down"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm p-1" onclick="duplicateRouteStep(${index})" style="height: 24px !important; width: 24px !important; display: inline-flex; align-items: center; justify-content: center; border:none !important;"><i class="bi bi-files"></i></button>
                            <button type="button" class="btn btn-outline-danger btn-sm p-1" onclick="removeRouteStep(${index})" style="height: 24px !important; width: 24px !important; display: inline-flex; align-items: center; justify-content: center; border:none !important;"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <div class="small text-muted mt-1">
                        Receiver: <span class="fw-bold text-dark">${escapeHtml(step.userName.split('(')[0].trim())}</span> | Dept: ${escapeHtml(step.department || 'N/A')}
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge ${badgeType}">${typeLabel}</span>
                        <span class="badge ${sigClass}">${sigLabel}</span>
                        <span class="badge bg-light border text-dark">SLA: ${step.sla}</span>
                    </div>
                </div>
                <div class="timeline-connector"><i class="bi bi-arrow-down-short"></i></div>
            `;
            timelineWrapper.appendChild(row);
        });

        routingSequenceContainer.appendChild(timelineWrapper);

        if (seqCountMsg) {
            seqCountMsg.textContent = `${routingSteps.length} approval step(s) configured.`;
            seqCountMsg.style.color = '#10b981';
        }

        // Render Summary Panel
        const originOfficeName = originOfficeSelect ? originOfficeSelect.options[originOfficeSelect.selectedIndex]?.text : 'N/A';
        const finalOfficeName = finalOfficeSelect ? finalOfficeSelect.options[finalOfficeSelect.selectedIndex]?.text : 'N/A';

        document.getElementById('summaryOriginOffice').textContent = originOfficeName;
        document.getElementById('summaryFinalDestination').textContent = finalOfficeName;
        document.getElementById('summaryTotalTime').textContent = `${totalDays} Days`;
        document.getElementById('routeSummaryPanel').style.display = 'block';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    window.moveRouteStep = function(index, direction) {
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= routingSteps.length) return;
        const temp = routingSteps[index];
        routingSteps[index] = routingSteps[targetIndex];
        routingSteps[targetIndex] = temp;
        renderRoutingSequence();
    };

    window.removeRouteStep = function(index) {
        routingSteps.splice(index, 1);
        renderRoutingSequence();
    };

    window.duplicateRouteStep = function(index) {
        const stepToDup = routingSteps[index];
        routingSteps.splice(index + 1, 0, { ...stepToDup });
        renderRoutingSequence();
    };

    // AJAX Form submit with inline validations
    const documentUploadForm = document.getElementById('documentUploadForm');
    let duplicateData = null;

    let isUploading = false;

    function performUpload(formData, preOpenedTab = null) {
        if (isUploading) return;
        isUploading = true;

        const submitBtn = documentUploadForm.querySelector('button[type="submit"]');
        const progressContainer = document.getElementById('uploadProgressContainer');
        const progressBar = document.getElementById('uploadProgressBar');
        const progressPercent = document.getElementById('uploadProgressPercent');

        if (submitBtn) submitBtn.disabled = true;
        if (progressContainer) progressContainer.style.display = 'block';
        if (progressBar) {
            progressBar.style.width = '0%';
            progressBar.setAttribute('aria-valuenow', '0');
        }
        if (progressPercent) progressPercent.textContent = '0%';

        // Ensure CSRF token is attached both in FormData and Request Header
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
        formData.set('_token', csrfToken);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', documentUploadForm.action, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        // Inject routing sequence hops into form data
        formData.delete('routing_office_ids[]');
        formData.delete('routing_user_ids[]');
        formData.delete('routing_approval_types[]');
        formData.delete('routing_signatures_required[]');

        routingSteps.forEach(step => {
            formData.append('routing_office_ids[]', step.officeId);
            formData.append('routing_user_ids[]', step.userId);
            formData.append('routing_approval_types[]', step.approvalType);
            formData.append('routing_signatures_required[]', step.signatureRequired ? 1 : 0);
        });

        // Append Final Destination at the end of the routing hops
        const finalOffice = document.getElementById('finalOfficeSelect').value;
        const finalReceiver = document.getElementById('finalReceiverSelect').value;
        if (finalOffice) {
            formData.append('destination_office_id', finalOffice);
        }
        if (finalOffice && finalReceiver) {
            formData.append('routing_office_ids[]', finalOffice);
            formData.append('routing_user_ids[]', finalReceiver);
            formData.append('routing_approval_types[]', 'sequential');
            formData.append('routing_signatures_required[]', 1);
        }

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressBar.setAttribute('aria-valuenow', percentComplete);
                progressPercent.textContent = percentComplete + '%';
            }
        });

        xhr.onload = function() {
            isUploading = false;
            if (submitBtn) submitBtn.disabled = false;
            if (progressContainer) progressContainer.style.display = 'none';

            let response = null;
            try {
                response = JSON.parse(xhr.responseText);
            } catch(e) {}

            // Always synchronize CSRF token across DOM if returned
            if (response && response.csrf_token) {
                const metaCsrf = document.querySelector('meta[name="csrf-token"]');
                if (metaCsrf) metaCsrf.setAttribute('content', response.csrf_token);
                document.querySelectorAll('input[name="_token"]').forEach(input => {
                    input.value = response.csrf_token;
                });
                if (window.jQuery) {
                    window.jQuery.ajaxSetup({
                        headers: { 'X-CSRF-TOKEN': response.csrf_token }
                    });
                }
                if (window.axios) {
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = response.csrf_token;
                }
            }

            if (xhr.status === 409) {
                if (preOpenedTab && !preOpenedTab.closed) {
                    try { preOpenedTab.close(); } catch(e) {}
                }
                if (response && response.duplicate) {
                    duplicateData = response;
                    document.getElementById('duplicateDocTitle').textContent = response.title;
                    document.getElementById('duplicateDocTracking').textContent = response.tracking_number;
                    bootstrap.Modal.getInstance(document.getElementById('uploadModal'))?.hide();
                    const dupModal = new bootstrap.Modal(document.getElementById('duplicateModal'));
                    dupModal.show();
                } else {
                    showNotification('Duplicate file detected.', 'warning');
                }
                return;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                if (response && response.success) {
                    showNotification(response.message || 'Document uploaded successfully', 'success');
                    
                    const qrTargetUrl = (response.qr_label_url || ('/documents/' + response.document_id + '/qr-label')) + '?autoprint=1';
                    let popupOpened = false;

                    // 1. If tab was pre-opened during user click gesture, navigate it now
                    if (preOpenedTab && !preOpenedTab.closed) {
                        try {
                            preOpenedTab.location.href = qrTargetUrl;
                            popupOpened = true;
                        } catch (e) {
                            console.warn('Pre-opened tab navigation error:', e);
                        }
                    } else {
                        // 2. Otherwise attempt standard popup window
                        try {
                            const newTab = window.open(qrTargetUrl, '_blank');
                            if (newTab && !newTab.closed && typeof newTab.closed !== 'undefined') {
                                popupOpened = true;
                            }
                        } catch (e) {
                            console.warn('Fallback window.open error:', e);
                        }
                    }

                    // Hide upload modal
                    bootstrap.Modal.getInstance(document.getElementById('uploadModal'))?.hide();

                    // Show success confirmation modal with direct print QR action
                    const successModalEl = document.getElementById('uploadSuccessModal');
                    if (successModalEl) {
                        const trackingNum = response.tracking_number || ('DOC-' + response.document_id);
                        const docTitle = response.title || (formData.get('title') || 'Document');

                        const trackingEl = document.getElementById('successModalTracking');
                        if (trackingEl) trackingEl.textContent = trackingNum;

                        const titleEl = document.getElementById('successModalTitle');
                        if (titleEl) titleEl.textContent = docTitle;
                        
                        const btnPrint = document.getElementById('btnSuccessPrintQr');
                        if (btnPrint) {
                            btnPrint.href = qrTargetUrl;
                        }

                        const btnView = document.getElementById('btnSuccessViewDoc');
                        if (btnView) {
                            btnView.href = '/documents/' + response.document_id;
                        }

                        const blockedNotice = document.getElementById('popupBlockedAlert');
                        if (blockedNotice) {
                            if (!popupOpened) {
                                blockedNotice.classList.remove('d-none');
                            } else {
                                blockedNotice.classList.add('d-none');
                            }
                        }

                        const successModal = new bootstrap.Modal(successModalEl);
                        successModal.show();
                    } else {
                        // Fallback if modal not present
                        setTimeout(() => {
                            window.location.href = response.redirect || '{{ route("documents.index") }}';
                        }, 2000);
                    }
                } else {
                    if (preOpenedTab && !preOpenedTab.closed) {
                        try { preOpenedTab.close(); } catch(e) {}
                    }
                    showNotification(response?.message || 'Upload failed', 'danger');
                }
            } else {
                if (preOpenedTab && !preOpenedTab.closed) {
                    try { preOpenedTab.close(); } catch(e) {}
                }
                let errMsg = 'Upload failed.';
                if (response && response.errors) {
                    errMsg = Object.values(response.errors).flat().join('\n');
                } else if (response && response.message) {
                    errMsg = response.message;
                } else {
                    if (xhr.status === 413) {
                        errMsg = 'File size exceeds server upload limit. Please upload a file smaller than 10MB.';
                    } else if (xhr.status === 419) {
                        errMsg = 'Session expired. Please refresh the page and try again.';
                    } else if (xhr.status === 500) {
                        try {
                            const doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
                            const title = doc.querySelector('title')?.textContent || doc.querySelector('h1')?.textContent;
                            errMsg = title ? `Server error (500): ${title.trim()}` : 'Server error during upload. Please check server logs.';
                        } catch(parseErr) {
                            errMsg = 'Server error during upload. Please check server logs.';
                        }
                    } else if (xhr.statusText) {
                        errMsg = 'Upload failed: ' + xhr.statusText;
                    }
                }
                console.error('Document upload failed [status ' + xhr.status + ']:', xhr.responseText);
                showNotification(errMsg, 'danger');
            }
        };

        xhr.onerror = function() {
            isUploading = false;
            if (preOpenedTab && !preOpenedTab.closed) {
                try { preOpenedTab.close(); } catch(e) {}
            }
            if (submitBtn) submitBtn.disabled = false;
            if (progressContainer) progressContainer.style.display = 'none';
            showNotification('A network error occurred. Please check connection.', 'danger');
        };

        xhr.send(formData);
    }

    if (documentUploadForm) {
        documentUploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (isUploading) return false;
            
            const file = document.getElementById('fileHidden');
            const title = document.getElementById('titleField');
            const finalOffice = document.getElementById('finalOfficeSelect');
            const finalReceiver = document.getElementById('finalReceiverSelect');
            let isValid = true;

            // Reset validation errors
            document.querySelectorAll('#uploadModal .is-invalid-field').forEach(el => el.classList.remove('is-invalid-field'));
            document.querySelectorAll('#uploadModal .invalid-feedback-msg').forEach(el => el.style.display = 'none');

            if (!file || !file.files.length) {
                isValid = false;
                const zone = document.getElementById('uploadZone');
                if (zone) zone.classList.add('is-invalid-field');
                const err = document.getElementById('validation-file');
                if (err) {
                    err.textContent = 'Please select a file to upload.';
                    err.style.display = 'block';
                }
            } else if (file.files[0].size > 10 * 1024 * 1024) {
                isValid = false;
                const zone = document.getElementById('uploadZone');
                if (zone) zone.classList.add('is-invalid-field');
                const err = document.getElementById('validation-file');
                if (err) {
                    err.textContent = 'File size must not exceed 10MB.';
                    err.style.display = 'block';
                }
            }

            if (!title || !title.value.trim()) {
                isValid = false;
                if (title) title.classList.add('is-invalid-field');
                const err = document.getElementById('validation-title');
                if (err) err.style.display = 'block';
            }

            const description = document.getElementById('descriptionField');
            if (!description || !description.value.trim()) {
                isValid = false;
                if (description) description.classList.add('is-invalid-field');
                const err = document.getElementById('validation-description');
                if (err) err.style.display = 'block';
            }

            if (!finalOffice || !finalOffice.value) {
                isValid = false;
                if (finalOffice) finalOffice.classList.add('is-invalid-field');
                const err = document.getElementById('validation-final-office');
                if (err) err.style.display = 'block';
            }

            if (!finalReceiver || !finalReceiver.value) {
                isValid = false;
                if (finalReceiver) finalReceiver.classList.add('is-invalid-field');
                const err = document.getElementById('validation-final-receiver');
                if (err) err.style.display = 'block';
            }

            const categorySelect = document.getElementById('categorySelect');
            if (!categorySelect || !categorySelect.value) {
                isValid = false;
                if (categorySelect) categorySelect.classList.add('is-invalid-field');
                const err = document.getElementById('validation-category');
                if (err) err.style.display = 'block';
            } else if (categorySelect.value === 'Others') {
                const customCategoryInput = document.getElementById('customCategoryInput');
                if (!customCategoryInput || !customCategoryInput.value.trim()) {
                    isValid = false;
                    if (customCategoryInput) customCategoryInput.classList.add('is-invalid-field');
                    let err = document.getElementById('validation-custom-category');
                    if (!err) {
                        customCategoryInput.insertAdjacentHTML('afterend', '<span class="invalid-feedback-msg" id="validation-custom-category" style="color: red; font-size: 11px; display: block; margin-top: 4px;">Please specify a category.</span>');
                    } else {
                        err.style.display = 'block';
                    }
                }
            }



            if (!isValid) {
                const firstErr = document.querySelector('#uploadModal .is-invalid-field');
                if (firstErr) {
                    firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }

            let preOpenedTab = null;
            try {
                preOpenedTab = window.open('about:blank', '_blank');
            } catch (e) {
                preOpenedTab = null;
            }

            performUpload(new FormData(this), preOpenedTab);
        });
    }

    // Toggle custom category specification field
    const categorySelectEl = document.getElementById('categorySelect');
    const customCategoryWrapperEl = document.getElementById('customCategoryInputWrapper');
    if (categorySelectEl && customCategoryWrapperEl) {
        categorySelectEl.addEventListener('change', function() {
            if (this.value === 'Others') {
                customCategoryWrapperEl.style.display = 'block';
            } else {
                customCategoryWrapperEl.style.display = 'none';
            }
        });
    }

    document.getElementById('btnSaveDraftPlaceholder')?.addEventListener('click', function(e) {
        e.preventDefault();
        showNotification('Document draft saved successfully.', 'success');
    });

    // Duplicate overwrite click handler
    document.getElementById('btnDuplicateOverwrite')?.addEventListener('click', function() {
        if (!duplicateData || isUploading) return;
        bootstrap.Modal.getInstance(document.getElementById('duplicateModal'))?.hide();

        const form = document.getElementById('documentUploadForm');
        const formData = new FormData(form);
        formData.append('duplicate_action', 'overwrite');
        formData.append('duplicate_doc_id', duplicateData.document_id);

        let preOpenedTab = null;
        try {
            preOpenedTab = window.open('about:blank', '_blank');
        } catch (e) {
            preOpenedTab = null;
        }

        performUpload(formData, preOpenedTab);
    });

    // Duplicate version click handler
    document.getElementById('btnDuplicateVersion')?.addEventListener('click', function() {
        if (!duplicateData || isUploading) return;
        bootstrap.Modal.getInstance(document.getElementById('duplicateModal'))?.hide();

        const form = document.getElementById('documentUploadForm');
        const formData = new FormData(form);
        formData.append('duplicate_action', 'version');
        formData.append('duplicate_doc_id', duplicateData.document_id);

        let preOpenedTab = null;
        try {
            preOpenedTab = window.open('about:blank', '_blank');
        } catch (e) {
            preOpenedTab = null;
        }

        performUpload(formData, preOpenedTab);
    });
</script>
