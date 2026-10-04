@extends('layouts.app')
@section('title', 'QR Scanner & Document Delivery')

@section('head')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js" onerror="loadJsQRFallback()"></script>
<script>
    function loadJsQRFallback() {
        console.warn("jsDelivr jsQR CDN failed, loading fallback from unpkg...");
        const s = document.createElement('script');
        s.src = "https://unpkg.com/jsqr@1.4.0/dist/jsQR.js";
        document.head.appendChild(s);
    }
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --glass-bg: var(--panel);
        --panel-border: var(--panel-border);
        --accent-cyan: var(--accent-cyan);
        --accent-success: var(--success);
        --accent-warning: var(--warning);
        --accent-danger: var(--danger);
    }

    .glass-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .glass-card h5 {
        color: var(--text-main);
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .scanner-box {
        border: 1.5px solid var(--panel-border);
        border-radius: var(--radius-lg);
        min-height: 340px;
        height: 380px;
        max-height: 420px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #0f172a;
        overflow: hidden;
        position: relative;
    }

    #scannerVideo {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: none;
    }

    .scanner-target-overlay {
        position: absolute;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        pointer-events: none;
        z-index: 5;
    }

    .scanner-frame {
        width: 220px;
        height: 220px;
        position: relative;
        box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.45);
        border-radius: 12px;
    }

    .scanner-frame .corner {
        position: absolute;
        width: 24px;
        height: 24px;
        border-color: #38bdf8;
        border-style: solid;
    }

    .scanner-frame .corner-tl { top: -2px; left: -2px; border-width: 3.5px 0 0 3.5px; border-top-left-radius: 8px; }
    .scanner-frame .corner-tr { top: -2px; right: -2px; border-width: 3.5px 3.5px 0 0; border-top-right-radius: 8px; }
    .scanner-frame .corner-bl { bottom: -2px; left: -2px; border-width: 0 0 3.5px 3.5px; border-bottom-left-radius: 8px; }
    .scanner-frame .corner-br { bottom: -2px; right: -2px; border-width: 0 3.5px 3.5px 0; border-bottom-right-radius: 8px; }

    .scanner-laser {
        position: absolute;
        top: 6%;
        left: 5%;
        width: 90%;
        height: 2px;
        background: linear-gradient(90deg, transparent, #38bdf8, transparent);
        box-shadow: 0 0 8px #38bdf8;
        animation: laserScan 2.4s ease-in-out infinite;
    }

    @keyframes laserScan {
        0%, 100% { top: 6%; opacity: 0.2; }
        50% { top: 92%; opacity: 1; }
    }

    .scanner-status-pill {
        margin-top: 14px;
        background: rgba(15, 23, 42, 0.85);
        color: #f8fafc;
        border: 1px solid rgba(255, 255, 255, 0.15);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 600;
        letter-spacing: 0.03em;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
    }

    .status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 6px #10b981;
        animation: pulseDot 1.6s infinite;
    }

    @keyframes pulseDot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.8); }
    }

    .scanner-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 24px;
        text-align: center;
    }

    .scanner-icon {
        font-size: 2.8rem;
        opacity: 0.75;
    }

    .scanner-text {
        color: var(--text-dim);
        font-size: 13px;
        text-align: center;
    }

    .btn-scan {
        background: var(--accent-cyan);
        border: none;
        color: #FFFFFF;
        font-weight: 600;
        padding: 10px 20px;
        border-radius: var(--radius-md);
        transition: all var(--transition-speed) ease;
        width: 100%;
        margin-top: 16px;
        height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-scan:hover {
        background: #1D4ED8;
        transform: translateY(-0.5px);
    }

    .signature-pad-container {
        border: 1.5px solid var(--panel-border);
        border-radius: var(--radius-lg);
        background: var(--bg);
        overflow: hidden;
        margin-bottom: 12px;
    }

    #signature {
        border-radius: var(--radius-md);
        cursor: crosshair;
        display: block;
        background: white;
    }

    .signature-btns {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }

    .signature-btns button {
        flex: 1;
        padding: 8px;
        border-radius: var(--radius-md);
        border: none;
        font-weight: 600;
        transition: var(--transition-speed);
        font-size: 13px;
    }

    .btn-clear-sig {
        background: rgba(244, 63, 94, 0.08);
        color: var(--danger);
        border: 1px solid rgba(244, 63, 94, 0.15);
    }

    .btn-clear-sig:hover {
        background: rgba(244, 63, 94, 0.12);
    }

    .btn-submit-sig {
        background: var(--success);
        color: white;
        border: none;
    }

    .btn-submit-sig:hover {
        background: #059669;
    }

    .alert-custom {
        border-radius: var(--radius-md);
        border: none;
        padding: 12px 16px;
        margin-bottom: 16px;
    }

    .alert-success-custom {
        background: rgba(16, 185, 129, 0.08);
        color: #047857;
        border-left: 4px solid #10b981;
    }

    .alert-danger-custom {
        background: rgba(244, 63, 94, 0.08);
        color: #be123c;
        border-left: 4px solid #f43f5e;
    }

    .alert-info-custom {
        background: rgba(59, 130, 246, 0.08);
        color: #1d4ed8;
        border-left: 4px solid #3b82f6;
    }

    .document-info {
        background: var(--bg);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        padding: 16px;
        margin-top: 12px;
    }

    .document-info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--panel-border);
    }

    .document-info-row:last-child {
        border-bottom: none;
    }

    .document-info-label {
        color: var(--text-dim);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .document-info-value {
        color: var(--text-main);
        font-weight: 600;
        font-size: 13px;
    }

    .badge-custom {
        display: inline-block;
        padding: 4px 10px;
        border-radius: var(--radius-sm);
        font-size: 11px;
        font-weight: 600;
        text-transform: capitalize;
    }

    .badge-success {
        background: rgba(16, 185, 129, 0.08);
        color: #047857;
        border: 1px solid rgba(16, 185, 129, 0.15);
    }

    .badge-pending {
        background: rgba(245, 158, 11, 0.08);
        color: #b45309;
        border: 1px solid rgba(245, 158, 11, 0.15);
    }

    .badge-info {
        background: rgba(59, 130, 246, 0.08);
        color: #1d4ed8;
        border: 1px solid rgba(59, 130, 246, 0.15);
    }

    .routing-flow {
        position: relative;
        padding: 10px 0;
    }

    .step {
        display: flex;
        align-items: center;
        margin-bottom: 16px;
        position: relative;
    }

    .step:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 15px;
        top: 28px;
        width: 1.5px;
        height: 28px;
        background: var(--panel-border);
    }

    .step-circle {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
        color: #FFFFFF;
        margin-right: 12px;
        z-index: 2;
        position: relative;
    }

    .step-1 { background: var(--accent-cyan); }
    .step-2 { background: var(--accent-purple); }
    .step-3 { background: var(--success); }

    .step-content h6 {
        color: var(--text-main);
        font-size: 13px;
        font-weight: 600;
        margin: 0 0 2px 0;
    }

    .step-content p {
        color: var(--text-dim);
        font-size: 11.5px;
        margin: 0;
    }

    .loading-spinner {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Recent Docs Carousel */
    .recent-doc-col {
        transition: transform var(--transition-speed);
        flex: 0 0 260px !important;
        width: 260px !important;
        max-width: 260px !important;
        flex-shrink: 0 !important;
    }
    .recent-doc-card {
        border-radius: var(--radius-lg) !important;
        background: var(--panel) !important;
        padding: 16px !important;
        height: 220px !important;
        min-height: 220px !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        border: 1px solid var(--panel-border) !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
        transition: transform var(--transition-speed), box-shadow var(--transition-speed), border-color var(--transition-speed) !important;
    }
    .recent-doc-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08) !important;
        border-color: var(--accent-cyan) !important;
    }
    #recentDocsCarousel {
        overflow-x: auto !important;
        display: flex !important;
        flex-wrap: nowrap !important;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 8px;
    }
    #recentDocsCarousel::-webkit-scrollbar {
        height: 4px;
    }
    #recentDocsCarousel::-webkit-scrollbar-thumb {
        background: var(--panel-border);
        border-radius: 2px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row g-4">
        <!-- Left Column: Scanner (70%) -->
        <div class="col-lg-8">
            <div class="glass-card">
                <h5><i class="bi bi-qr-code-scan" style="color:var(--accent-cyan);"></i> QR Code Scanner</h5>
                <p class="text-secondary small mb-3" style="color: var(--text-dim) !important;">Scan document QR codes to record location routing and signature receipt instantly.</p>
                
                <div class="d-flex gap-2 mb-3">
                    <button type="button" id="tab-scan" class="btn flex-grow-1" style="background: var(--accent-cyan); color: white; font-weight: 600;">
                        <i class="bi bi-camera me-1"></i> Scan with Camera
                    </button>
                    <button type="button" id="tab-upload" class="btn flex-grow-1" style="background: var(--bg); color: var(--text-main); font-weight: 600; border: 1px solid var(--panel-border);">
                        <i class="bi bi-upload me-1"></i> Upload Image
                    </button>
                </div>
                
                <div id="scan-mode">
                    <div class="scanner-box" id="scannerBox">
                        <!-- Native Video Feed -->
                        <video id="scannerVideo" playsinline autoplay muted></video>
                        <canvas id="scannerCanvas" style="display: none;"></canvas>

                        <!-- Viewfinder Targeting Overlay -->
                        <div id="scannerTargetOverlay" class="scanner-target-overlay">
                            <div class="scanner-frame">
                                <div class="corner corner-tl"></div>
                                <div class="corner corner-tr"></div>
                                <div class="corner corner-bl"></div>
                                <div class="corner corner-br"></div>
                                <div class="scanner-laser"></div>
                            </div>
                            <div class="scanner-status-pill" id="scannerStatusPill">
                                <span class="status-dot"></span>
                                <span id="scannerStatusText">Scanning...</span>
                            </div>
                        </div>

                        <!-- Placeholder / State Display -->
                        <div id="scanner-placeholder" class="scanner-placeholder">
                            <div class="scanner-icon" id="placeholderIcon"><i class="bi bi-camera" style="font-size: 2.8rem; color: var(--text-dim);"></i></div>
                            <div class="scanner-text">
                                <p class="mb-1 fw-bold" id="placeholderTitle" style="color: var(--text-main); font-size: 14px;">Camera Ready</p>
                                <p class="small mb-0" id="placeholderMessage" style="color: var(--text-dim); font-size: 12px;">Click "Start Camera" to scan document QR codes.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Camera Select Container -->
                    <div id="camera-select-container" class="mt-3 text-start" style="display: none;">
                        <label for="cameraSelect" class="form-label small fw-bold text-secondary mb-1">
                            <i class="bi bi-camera-video me-1"></i> Select Camera Device
                        </label>
                        <select id="cameraSelect" class="form-select form-select-sm" style="background: var(--bg); border: 1px solid var(--panel-border); color: var(--text-main);"></select>
                    </div>

                    <!-- Camera Access Insecure Context Warning -->
                    <div id="camera-help-box" class="alert alert-warning mt-3 small text-start border-0 shadow-sm" style="display: none; background: rgba(245, 158, 11, 0.08); border-left: 4px solid var(--accent-warning) !important; color: #b45309;">
                        <p class="mb-1 fw-bold"><i class="bi bi-shield-exclamation me-1"></i> Camera Requires Secure Context</p>
                        <p class="mb-0" style="font-size:12px;">Modern browsers only allow camera access on HTTPS or localhost. If camera access is blocked, please access NAAP over HTTPS or verify site permissions.</p>
                    </div>

                    <button type="button" id="start-scan" class="btn btn-scan" aria-label="Start Camera Scanner">
                        <i class="bi bi-camera me-1"></i> Start Camera
                    </button>
                </div>

                <div id="upload-mode" style="display: none;">
                    <div class="py-5 px-3 text-center" style="border: 1.5px dashed var(--panel-border); border-radius: var(--radius-lg); background: var(--bg);">
                        <i class="bi bi-image" style="font-size: 2.5rem; color: var(--text-dim); display: block; margin-bottom: 12px;"></i>
                        <p class="small text-slate-500 mb-1">Upload a QR code photo or screenshot</p>
                        <p class="text-secondary small mb-3" style="font-size: 11px;">Supported formats: PNG, JPG, JPEG, WEBP</p>
                        <input type="file" id="qr-file-input" accept="image/png, image/jpeg, image/jpg, image/webp" style="display: none;">
                        <button type="button" id="upload-qr-btn" class="btn btn-primary" style="height:36px !important;">
                            <i class="bi bi-search me-1"></i> Select File
                        </button>
                        <div id="upload-scanner-status" class="mt-3 text-center" style="display: none;">
                            <span class="loading-spinner me-2"></span>
                            <span class="small text-muted" id="upload-status-text">Scanning image for QR code...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scanned Document Info -->
            <div id="scanned-result" style="display: none;">
                <div class="glass-card">
                    <h5><i class="bi bi-file-earmark-check" style="color: var(--success);"></i> Scanned Document Information</h5>
                    <div id="scanned-content"></div>
                </div>
            </div>

            <!-- Signature Capture -->
            <div id="signature-section" style="display: none;" class="glass-card">
                <h5><i class="bi bi-pen" style="color: var(--accent-cyan);"></i> Acknowledge Receipt</h5>
                <p class="small text-slate-500 mb-3">Please sign inside the box below to complete document delivery verification.</p>
                
                <div class="signature-pad-container">
                    <canvas id="signature" height="180"></canvas>
                </div>

                <div class="signature-btns">
                    <button type="button" class="btn-clear-sig" id="clear-sig">
                        <i class="bi bi-arrow-counterclockwise"></i> Clear Canvas
                    </button>
                    <button type="button" class="btn-submit-sig" id="submit-sig">
                        <i class="bi bi-check-lg"></i> Sign & Approve
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column: Flow Info (30%) -->
        <div class="col-lg-4">
            <!-- How to Use -->
            <div class="glass-card">
                <h5><i class="bi bi-question-circle" style="color: var(--accent-cyan);"></i> Delivery Guide</h5>
                <ul class="ps-3 mb-0" style="color: var(--text-dim); font-size:12.5px; line-height: 1.8;">
                    <li class="mb-1"><strong>1. Position QR:</strong> Align QR code inside scanner brackets.</li>
                    <li class="mb-1"><strong>2. Enter PIN:</strong> Input 6-digit code if document is protected.</li>
                    <li class="mb-1"><strong>3. Sign Pad:</strong> Use touch or mouse to write signature.</li>
                    <li><strong>4. Complete:</strong> Hit sign to write audit trail and routing state.</li>
                </ul>
            </div>

            <!-- Routing Flow -->
            <div class="glass-card">
                <h5><i class="bi bi-diagram-3" style="color: var(--accent-cyan);"></i> Document Hops</h5>
                <div class="routing-flow">
                    <div class="step">
                        <div class="step-circle step-1"><i class="bi bi-upload"></i></div>
                        <div class="step-content">
                            <h6>1. Upload & Issue</h6>
                            <p>Uploader releases file</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-circle step-2"><i class="bi bi-truck"></i></div>
                        <div class="step-content">
                            <h6>2. Office Routing</h6>
                            <p>Moving between departments</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-circle step-3"><i class="bi bi-check-lg"></i></div>
                        <div class="step-content">
                            <h6>3. Signed Verification</h6>
                            <p>Final delivery acknowledged</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Documents carousel spanning 100% width below -->
        <div class="col-12">
            <div class="glass-card">
                <div class="d-flex justify-content-between align-items-center pb-2 mb-3" style="border-bottom:1px solid var(--panel-border);">
                    <h5 class="mb-0"><i class="bi bi-clock-history" style="color: var(--accent-cyan);"></i> Recent Scope Documents</h5>
                    
                    @if($documents->count() > 1)
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="prevRecentBtn" style="height:28px !important; padding:2px 8px !important; font-size:11px !important;">
                            <i class="bi bi-chevron-left"></i> Prev
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="nextRecentBtn" style="height:28px !important; padding:2px 8px !important; font-size:11px !important;">
                            Next <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    @endif
                </div>

                @if($documents->isEmpty())
                    <p style="color: var(--text-dim); text-align: center; margin: 20px 0;">No recent documents found.</p>
                @else
                    <div id="recentDocsCarousel" class="row flex-nowrap g-3 px-1">
                        @foreach($documents as $doc)
                            @php
                                $pColor = match(strtolower($doc->priority ?? 'normal')) {
                                    'urgent' => '#F43F5E',
                                    'high'   => '#F59E0B',
                                    'medium', 'normal' => '#10B981',
                                    default  => '#3B82F6',
                                };
                                $sLower = strtolower($doc->status ?? '');
                                $statusClass = match(true) {
                                    $sLower === 'completed'                        => 'badge-success',
                                    in_array($sLower, ['in_transit','in transit']) => 'badge-info',
                                    default                                        => 'badge-pending',
                                };
                            @endphp
                            <div class="recent-doc-col">
                                <div class="recent-doc-card">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <h6 class="fw-bold text-dark mb-0 text-truncate" style="font-size: 13.5px;" title="{{ $doc->title }}">{{ $doc->title }}</h6>
                                            <span class="badge border" style="font-size: 9px; color: {{ $pColor }}; border-color: {{ $pColor }}; padding: 1px 4px;">{{ $doc->priority }}</span>
                                        </div>

                                        <!-- Office -->
                                        <div class="d-flex align-items-center gap-2 mb-2" style="font-size: 12px; color: var(--text-dim);">
                                            <i class="bi bi-building" style="color: var(--accent-cyan);"></i>
                                            <span class="text-truncate" style="color: var(--text-main); font-weight:500;">
                                                {{ $doc->currentOffice?->name ?? 'In Transit' }}
                                            </span>
                                        </div>

                                        <!-- Receiver -->
                                        <div class="d-flex align-items-center gap-2" style="font-size: 12px; color: var(--text-dim);">
                                            <i class="bi bi-person"></i>
                                            <span class="text-truncate" style="color: var(--text-main);">
                                                {{ $doc->receiverUser?->name ?? '—' }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Status & Time -->
                                    <div class="d-flex justify-content-between align-items-center pt-2 mt-auto" style="border-top: 1px solid var(--panel-border);">
                                        <span class="badge {{ $statusClass }}">{{ $doc->status }}</span>
                                        <span style="font-size:11px; color: var(--text-dim);">{{ $doc->updated_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    let html5QrCode;
    let signaturePad;
    let currentScannedDoc = null;
    // If arrived from a locked document page, restrict scanning to that document only
    const targetDocumentId = new URLSearchParams(window.location.search).get('document_id') || null;

    document.addEventListener('DOMContentLoaded', function() {
        initializeScanner();
        initializeSignaturePad();
        initializeTabs();
        initializeRecentDocsCarousel();
        initializePinModalEvents();
    });

    function initializeRecentDocsCarousel() {
        const carousel = document.getElementById('recentDocsCarousel');
        const prevBtn = document.getElementById('prevRecentBtn');
        const nextBtn = document.getElementById('nextRecentBtn');

        if (carousel) {
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    const card = carousel.querySelector('.recent-doc-col');
                    if (card) {
                        const cardWidth = card.offsetWidth + 16;
                        carousel.scrollBy({ left: -cardWidth * 2, behavior: 'smooth' });
                    }
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    const card = carousel.querySelector('.recent-doc-col');
                    if (card) {
                        const cardWidth = card.offsetWidth + 16;
                        carousel.scrollBy({ left: cardWidth * 2, behavior: 'smooth' });
                    }
                });
            }
        }
    }

    let activeMediaStream = null;
    let scanIntervalId = null;
    let isScanningActive = false;
    let isCameraStarting = false;
    let isProcessingScan = false;
    let selectedCameraDeviceId = null;

    function updateScannerState(title, message, iconClass, isError = false) {
        const placeholder = document.getElementById('scanner-placeholder');
        const placeholderTitle = document.getElementById('placeholderTitle');
        const placeholderMessage = document.getElementById('placeholderMessage');
        const placeholderIcon = document.getElementById('placeholderIcon');
        const video = document.getElementById('scannerVideo');
        const overlay = document.getElementById('scannerTargetOverlay');

        if (video) video.style.display = 'none';
        if (overlay) overlay.style.display = 'none';
        if (placeholder) placeholder.style.display = 'flex';
        
        if (placeholderTitle) placeholderTitle.textContent = title;
        if (placeholderMessage) placeholderMessage.textContent = message;
        if (placeholderIcon) {
            placeholderIcon.innerHTML = `<i class="bi ${iconClass}" style="font-size: 2.8rem; color: ${isError ? 'var(--accent-danger)' : 'var(--text-dim)'};"></i>`;
        }
    }

    function updateStatusPill(text, isScanning = true) {
        const pillText = document.getElementById('scannerStatusText');
        const dot = document.querySelector('.status-dot');
        if (pillText) pillText.textContent = text;
        if (dot) {
            dot.style.background = isScanning ? '#10b981' : '#f59e0b';
            dot.style.boxShadow = isScanning ? '0 0 6px #10b981' : '0 0 6px #f59e0b';
        }
    }

    function initializeTabs() {
        const tabScan = document.getElementById('tab-scan');
        const tabUpload = document.getElementById('tab-upload');
        const scanMode = document.getElementById('scan-mode');
        const uploadMode = document.getElementById('upload-mode');
        const qrFileInput = document.getElementById('qr-file-input');
        const uploadQrBtn = document.getElementById('upload-qr-btn');

        tabScan.addEventListener('click', function() {
            scanMode.style.display = 'block';
            uploadMode.style.display = 'none';
            tabScan.style.background = 'var(--accent-cyan)';
            tabScan.style.color = '#FFFFFF';
            tabUpload.style.background = 'var(--bg)';
            tabUpload.style.color = 'var(--text-main)';
        });

        tabUpload.addEventListener('click', function() {
            // Stop camera when leaving camera mode to conserve battery and release hardware
            if (isScanningActive || activeMediaStream) {
                stopCameraStream();
            }

            scanMode.style.display = 'none';
            uploadMode.style.display = 'block';
            tabScan.style.background = 'var(--bg)';
            tabScan.style.color = 'var(--text-main)';
            tabUpload.style.background = 'var(--accent-cyan)';
            tabUpload.style.color = '#FFFFFF';
        });

        uploadQrBtn.addEventListener('click', function() {
            qrFileInput.click();
        });

        qrFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Reset input value so re-uploading the same file works
            qrFileInput.value = '';

            const allowedMimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
            const fileName = (file.name || '').toLowerCase();
            const hasValidExt = /\.(png|jpe?g|webp)$/i.test(fileName);
            const hasValidMime = allowedMimes.includes(file.type);

            if (!hasValidMime && !hasValidExt) {
                showAlert('Invalid file format. Please upload a PNG, JPG, JPEG, or WEBP image.', 'error');
                return;
            }

            const statusContainer = document.getElementById('upload-scanner-status');
            const statusText = document.getElementById('upload-status-text');
            const uploadBtn = document.getElementById('upload-qr-btn');

            if (statusContainer) {
                statusContainer.style.display = 'block';
                if (statusText) statusText.textContent = 'Decoding QR image...';
            }
            if (uploadBtn) uploadBtn.disabled = true;

            const resetUploadUI = () => {
                if (statusContainer) statusContainer.style.display = 'none';
                if (uploadBtn) uploadBtn.disabled = false;
            };

            const img = new Image();
            const objectUrl = URL.createObjectURL(file);

            img.onerror = function() {
                URL.revokeObjectURL(objectUrl);
                resetUploadUI();
                showAlert('The selected image is corrupted or cannot be read. Please upload a valid image file.', 'error');
            };

            img.onload = function() {
                URL.revokeObjectURL(objectUrl);

                decodeQrWithJsQR(img)
                    .then(decodedText => {
                        resetUploadUI();
                        console.log("QR decoded from image via jsQR:", decodedText);
                        isProcessingScan = true;
                        handleQRScan(decodedText);
                    })
                    .catch(err => {
                        resetUploadUI();
                        console.warn("All QR decode attempts failed for image:", err);
                        showAlert('Could not detect a valid QR code from the uploaded image. Please ensure the QR code is clearly visible and not cropped or blurry.', 'error');
                    });
            };

            img.src = objectUrl;
        });
    }

    function decodeQrWithJsQR(img) {
        return new Promise((resolve, reject) => {
            if (typeof jsQR === 'undefined') {
                return reject(new Error("jsQR library not available"));
            }

            try {
                const scales = [1.0];
                const maxDim = Math.max(img.width, img.height);
                if (maxDim > 1000) scales.push(1000 / maxDim);
                if (maxDim > 1600) scales.push(800 / maxDim);
                if (maxDim < 400 && maxDim > 50) scales.push(2.0);

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d', { willReadFrequently: true });

                for (const scale of scales) {
                    canvas.width = Math.max(1, Math.round(img.width * scale));
                    canvas.height = Math.max(1, Math.round(img.height * scale));
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    let code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: "attemptBoth"
                    });

                    if (code && code.data && code.data.trim()) {
                        return resolve(code.data.trim());
                    }
                }

                // Center crop pass (handles mobile screenshots where QR is centered)
                if (img.width > 120 && img.height > 120) {
                    const cropFractions = [0.8, 0.6];
                    for (const frac of cropFractions) {
                        const cropW = Math.round(img.width * frac);
                        const cropH = Math.round(img.height * frac);
                        const cropX = Math.round((img.width - cropW) / 2);
                        const cropY = Math.round((img.height - cropH) / 2);

                        canvas.width = cropW;
                        canvas.height = cropH;
                        ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);
                        const cropData = ctx.getImageData(0, 0, cropW, cropH);
                        const cropCode = jsQR(cropData.data, cropW, cropH, { inversionAttempts: "attemptBoth" });
                        if (cropCode && cropCode.data && cropCode.data.trim()) {
                            return resolve(cropCode.data.trim());
                        }
                    }
                }

                reject(new Error("No QR code detected by jsQR"));
            } catch (err) {
                reject(err);
            }
        });
    }

    function initializeScanner() {
        const scannerBtn = document.getElementById('start-scan');
        const cameraHelpBox = document.getElementById('camera-help-box');

        // Check secure context
        if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            if (cameraHelpBox) cameraHelpBox.style.display = 'block';
        }

        scannerBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (isCameraStarting) return;

            if (isScanningActive) {
                stopCameraStream();
            } else {
                startCameraStream(selectedCameraDeviceId);
            }
        });

        // Window lifecycle hooks to release camera
        window.addEventListener('beforeunload', stopCameraStream);
        window.addEventListener('pagehide', stopCameraStream);
    }

    async function startCameraStream(preferredDeviceId = null) {
        if (isCameraStarting) return;
        if (isScanningActive) {
            stopCameraStream();
            return;
        }

        const scannerBtn = document.getElementById('start-scan');
        const video = document.getElementById('scannerVideo');
        const overlay = document.getElementById('scannerTargetOverlay');
        const placeholder = document.getElementById('scanner-placeholder');
        const cameraHelpBox = document.getElementById('camera-help-box');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            if (cameraHelpBox) cameraHelpBox.style.display = 'block';
            updateScannerState('Camera Access Unavailable', 'Your browser does not support camera access or requires a secure HTTPS connection.', 'bi-shield-x', true);
            return;
        }

        isCameraStarting = true;
        if (scannerBtn) {
            scannerBtn.innerHTML = '<span class="loading-spinner me-2"></span> Initializing...';
            scannerBtn.disabled = true;
        }
        updateScannerState('Camera Permission Required', 'Please allow camera permission in your browser prompt...', 'bi-camera-video');

        // Build video constraints: prefer environment / rear camera on mobile, available webcam on desktop
        let constraints = null;
        if (preferredDeviceId) {
            constraints = {
                video: { deviceId: { exact: preferredDeviceId } },
                audio: false
            };
        } else {
            constraints = {
                video: {
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };
        }

        let stream = null;
        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (firstErr) {
            console.warn("Primary camera constraints failed, attempting fallback to any video device:", firstErr);
            try {
                // Graceful fallback without strict facingMode
                stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            } catch (fallbackErr) {
                isCameraStarting = false;
                if (scannerBtn) {
                    scannerBtn.innerHTML = '<i class="bi bi-camera me-1"></i> Start Camera';
                    scannerBtn.disabled = false;
                }

                const errName = fallbackErr.name || '';
                const errMsg = (fallbackErr.message || '').toLowerCase();

                if (errName === 'NotAllowedError' || errName === 'PermissionDeniedError' || errMsg.includes('permission') || errMsg.includes('denied')) {
                    updateScannerState('Camera Permission Denied', 'Camera access was denied. Please allow camera permissions in your browser address bar and try again.', 'bi-slash-circle', true);
                } else if (errName === 'NotFoundError' || errName === 'DevicesNotFoundError' || errMsg.includes('not found') || errMsg.includes('no camera')) {
                    updateScannerState('No Camera Detected', 'No active camera hardware was found on this device.', 'bi-camera-video-off', true);
                } else if (errName === 'NotReadableError' || errName === 'TrackStartError' || errMsg.includes('in use') || errMsg.includes('busy')) {
                    updateScannerState('Camera Currently Unavailable', 'The camera is currently in use by another application or browser tab. Please close other apps and try again.', 'bi-exclamation-triangle', true);
                } else if (errName === 'OverconstrainedError') {
                    updateScannerState('Camera Unavailable', 'The requested camera resolution or facing mode is not supported by your hardware.', 'bi-sliders', true);
                } else {
                    updateScannerState('Unable to Start Camera', 'Unable to access video stream. Ensure your device has an enabled camera and is using HTTPS.', 'bi-camera-video-off', true);
                }
                return;
            }
        }

        activeMediaStream = stream;
        video.srcObject = stream;

        video.onloadedmetadata = () => {
            video.play().then(() => {
                isScanningActive = true;
                isCameraStarting = false;
                if (placeholder) placeholder.style.display = 'none';
                video.style.display = 'block';
                if (overlay) overlay.style.display = 'flex';

                if (scannerBtn) {
                    scannerBtn.innerHTML = '<i class="bi bi-stop-circle me-1"></i> Stop Camera';
                    scannerBtn.classList.remove('btn-scan');
                    scannerBtn.classList.add('btn-outline-danger');
                    scannerBtn.disabled = false;
                }

                updateStatusPill('Scanning...', true);
                startDetectionLoop();
                refreshCameraDevicesList();
            }).catch(playErr => {
                console.error("Video play error:", playErr);
                isCameraStarting = false;
                stopCameraStream();
                updateScannerState('Unable to Start Camera', 'Video playback could not be started.', 'bi-exclamation-triangle', true);
            });
        };
    }

    function stopCameraStream() {
        isScanningActive = false;
        isCameraStarting = false;

        if (scanIntervalId) {
            clearInterval(scanIntervalId);
            scanIntervalId = null;
        }

        if (activeMediaStream) {
            activeMediaStream.getTracks().forEach(track => {
                try {
                    track.stop();
                } catch (e) {
                    console.warn("Track stop error:", e);
                }
            });
            activeMediaStream = null;
        }

        const video = document.getElementById('scannerVideo');
        if (video) {
            video.pause();
            video.srcObject = null;
            video.style.display = 'none';
        }

        const overlay = document.getElementById('scannerTargetOverlay');
        if (overlay) overlay.style.display = 'none';

        const placeholder = document.getElementById('scanner-placeholder');
        if (placeholder) placeholder.style.display = 'flex';
        updateScannerState('Camera Ready', 'Click "Start Camera" to scan document QR codes.', 'bi-camera');

        const scannerBtn = document.getElementById('start-scan');
        if (scannerBtn) {
            scannerBtn.innerHTML = '<i class="bi bi-camera me-1"></i> Start Camera';
            scannerBtn.classList.remove('btn-outline-danger');
            scannerBtn.classList.add('btn-scan');
            scannerBtn.disabled = false;
        }
    }

    function startDetectionLoop() {
        if (scanIntervalId) clearInterval(scanIntervalId);

        const video = document.getElementById('scannerVideo');
        const canvas = document.getElementById('scannerCanvas');
        if (!video || !canvas) return;

        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        // Scan interval: 120ms (approx 8-9 FPS, optimal balance of snappy QR detection and light CPU)
        scanIntervalId = setInterval(() => {
            if (!isScanningActive || isProcessingScan) return;
            if (video.readyState < video.HAVE_CURRENT_DATA) return;

            const width = video.videoWidth;
            const height = video.videoHeight;
            if (!width || !height) return;

            canvas.width = width;
            canvas.height = height;
            ctx.drawImage(video, 0, 0, width, height);

            const imageData = ctx.getImageData(0, 0, width, height);
            let code = null;

            if (typeof jsQR !== 'undefined') {
                code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: "attemptBoth"
                });
            }

            if (code && code.data && code.data.trim()) {
                const detectedPayload = code.data.trim();
                console.log("QR detected by camera:", detectedPayload);

                // Immediate duplicate scan prevention
                isProcessingScan = true;
                updateStatusPill('QR Detected! Verifying...', false);

                // Stop camera feed immediately to prevent duplicate camera requests
                stopCameraStream();

                handleQRScan(detectedPayload);
            }
        }, 120);
    }

    function refreshCameraDevicesList() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;

        navigator.mediaDevices.enumerateDevices().then(devices => {
            const videoDevices = devices.filter(d => d.kind === 'videoinput');
            const selectContainer = document.getElementById('camera-select-container');
            const cameraSelect = document.getElementById('cameraSelect');

            if (!selectContainer || !cameraSelect) return;

            if (videoDevices.length > 1) {
                cameraSelect.innerHTML = '';
                videoDevices.forEach((dev, idx) => {
                    const opt = document.createElement('option');
                    opt.value = dev.deviceId;
                    opt.textContent = dev.label || `Camera ${idx + 1}`;
                    if (selectedCameraDeviceId && dev.deviceId === selectedCameraDeviceId) {
                        opt.selected = true;
                    }
                    cameraSelect.appendChild(opt);
                });
                selectContainer.style.display = 'block';

                cameraSelect.onchange = function() {
                    selectedCameraDeviceId = this.value;
                    stopCameraStream();
                    startCameraStream(selectedCameraDeviceId);
                };
            } else {
                selectContainer.style.display = 'none';
            }
        }).catch(err => {
            console.warn("Could not enumerate camera devices:", err);
        });
    }

    let scanPinModal = null;
    let scanPinQrData = null;
    let resendCooldownInterval = null;
    let resendCooldownSeconds = 0;

    function showPinModal(qrData, maskedEmail) {
        scanPinQrData = qrData;
        document.getElementById('maskedRecipientEmail').textContent = maskedEmail || 'your email';
        document.getElementById('modalPinInput').value = '';
        document.getElementById('pinModalAlertContainer').innerHTML = '';
        
        // Reset or start cooldown timer if needed
        const btnResend = document.getElementById('btnResendPin');
        if (resendCooldownSeconds > 0) {
            btnResend.disabled = true;
            btnResend.textContent = `Resend in ${resendCooldownSeconds}s`;
        } else {
            btnResend.disabled = false;
            btnResend.textContent = 'Resend PIN';
        }

        if (!scanPinModal) {
            scanPinModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
        }
        scanPinModal.show();
    }

    function startResendCooldown() {
        resendCooldownSeconds = 60;
        const btnResend = document.getElementById('btnResendPin');
        btnResend.disabled = true;
        btnResend.textContent = `Resend in ${resendCooldownSeconds}s`;

        if (resendCooldownInterval) clearInterval(resendCooldownInterval);
        resendCooldownInterval = setInterval(() => {
            resendCooldownSeconds--;
            if (resendCooldownSeconds <= 0) {
                clearInterval(resendCooldownInterval);
                btnResend.disabled = false;
                btnResend.textContent = 'Resend PIN';
            } else {
                btnResend.textContent = `Resend in ${resendCooldownSeconds}s`;
            }
        }, 1000);
    }
    function initializePinModalEvents() {
        document.getElementById('btnResendPin')?.addEventListener('click', function() {
            if (resendCooldownSeconds > 0) return;

            const alertContainer = document.getElementById('pinModalAlertContainer');
            alertContainer.innerHTML = '<div class="alert alert-info py-2 small" style="border:none;"><i class="fas fa-spinner fa-spin me-2"></i>Sending new PIN...</div>';

            fetch('{{ route("qr.scan", [], false) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ qr_data: scanPinQrData, resend: true, target_document_id: targetDocumentId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertContainer.innerHTML = `<div class="alert alert-success py-2 small" style="border:none; background:rgba(16,185,129,0.08); color:#047857;"><i class="fas fa-check-circle me-2"></i>${data.message}</div>`;
                    startResendCooldown();
                } else {
                    alertContainer.innerHTML = `<div class="alert alert-danger py-2 small" style="border:none; background:rgba(244,63,94,0.08); color:#be123c;"><i class="fas fa-exclamation-circle me-2"></i>${data.message}</div>`;
                }
            })
            .catch(error => {
                alertContainer.innerHTML = '<div class="alert alert-danger py-2 small" style="border:none; background:rgba(244,63,94,0.08); color:#be123c;"><i class="fas fa-exclamation-circle me-2"></i>Error resending PIN. Please try again.</div>';
            });
        });

        document.getElementById('btnVerifyPin')?.addEventListener('click', function() {
            const pin = document.getElementById('modalPinInput').value;
            if (!pin || pin.length < 6) {
                document.getElementById('pinModalAlertContainer').innerHTML = '<div class="alert alert-danger py-2 small" style="border:none; background:rgba(244,63,94,0.08); color:#be123c;"><i class="fas fa-exclamation-circle me-2"></i>Please enter a valid 6-digit PIN.</div>';
                return;
            }

            console.log("PIN submitted:", pin);

            const alertContainer = document.getElementById('pinModalAlertContainer');
            alertContainer.innerHTML = '<div class="alert alert-info py-2 small" style="border:none;"><i class="fas fa-spinner fa-spin me-2"></i>Verifying PIN...</div>';

            fetch('{{ route("qr.scan", [], false) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ qr_data: scanPinQrData, pin: pin, target_document_id: targetDocumentId })
            })
            .then(response => response.json())
            .then(data => {
                console.log("Verification result:", data);
                if (data.success) {
                    scanPinModal.hide();
                    showAlert(data.message, 'success');
                    stopCameraStream();
                    const targetUrl = data.redirect_url || ('/track/' + data.document.id);
                    console.log("Before redirect: target URL =", targetUrl);
                    window.location.href = targetUrl;
                } else {
                    alertContainer.innerHTML = `<div class="alert alert-danger py-2 small" style="border:none; background:rgba(244,63,94,0.08); color:#be123c;"><i class="fas fa-exclamation-circle me-2"></i>${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error("Verification error:", error);
                alertContainer.innerHTML = '<div class="alert alert-danger py-2 small" style="border:none; background:rgba(244,63,94,0.08); color:#be123c;"><i class="fas fa-exclamation-circle me-2"></i>Error verifying PIN. Please try again.</div>';
            });
        });
    }

    function handleQRScan(qrData) {
        // Send QR data to server
        fetch('{{ route("qr.scan", [], false) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ qr_data: qrData, target_document_id: targetDocumentId })
        })
        .then(response => response.json())
        .then(data => {
            isProcessingScan = false;
            if (data.pin_required) {
                // Stop scanning immediately to prevent duplicate scans while PIN modal is open
                stopCameraStream();
                showPinModal(qrData, data.email);
                return;
            }

            if (data.success) {
                showAlert(data.message, 'success');
                stopCameraStream();
                
                // Redirect immediately to document details page
                window.location.href = data.redirect_url || ('/track/' + data.document.id);
                return;
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            isProcessingScan = false;
            console.error('Error:', error);
            showAlert('Error scanning QR code', 'error');
        });
    }

    function displayScannedDocument(doc) {
        const resultDiv = document.getElementById('scanned-result');
        const contentDiv = document.getElementById('scanned-content');
        
        contentDiv.innerHTML = `
            <div class="document-info">
                <div class="document-info-row">
                    <span class="document-info-label"><i class="bi bi-file-earmark-text me-1"></i> Title</span>
                    <span class="document-info-value">${doc.title}</span>
                </div>
                <div class="document-info-row">
                    <span class="document-info-label">Status</span>
                    <span class="badge-custom ${doc.status === 'Completed' ? 'badge-success' : 'badge-pending'}">
                        ${doc.status}
                    </span>
                </div>
                <div class="document-info-row">
                    <span class="document-info-label">Receiver</span>
                    <span class="document-info-value">${doc.receiver || 'N/A'}</span>
                </div>
                <div class="document-info-row">
                    <span class="document-info-label">Location</span>
                    <span class="document-info-value">${doc.current_office || 'N/A'}</span>
                </div>
                <div class="document-info-row">
                    <span class="document-info-label">Scanned</span>
                    <span class="document-info-value">${doc.scanned_at || 'Just now'}</span>
                </div>
            </div>
        `;
        
        resultDiv.style.display = 'block';
    }

    function initializeSignaturePad() {
        const canvas = document.getElementById('signature');
        if (!canvas) return;
        if (typeof SignaturePad === 'undefined') {
            console.error('SignaturePad library not loaded');
            showAlert('Signature Pad library failed to load. Please refresh the page.', 'error');
            return;
        }

        function resizeCanvas() {
            // Only resize when the canvas container is visible and has actual width
            const container = canvas.closest('.signature-pad-container');
            const displayWidth = container ? container.offsetWidth : canvas.parentElement.offsetWidth;
            if (!displayWidth) return;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            // Preserve current drawing data before resize
            const data = signaturePad ? signaturePad.toData() : [];
            canvas.width = displayWidth * ratio;
            canvas.height = 200 * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            // Apply explicit CSS size so it matches the pixel buffer
            canvas.style.width = displayWidth + 'px';
            canvas.style.height = '200px';
            signaturePad.clear();
            // Restore previous drawing data after resize
            if (data && data.length) {
                signaturePad.fromData(data);
            }
        }

        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'white',
            penColor: '#000000'
        });

        // Resize immediately now (section may already be visible if pre-scanned)
        // Also hook into when the signature section becomes visible
        const sigSection = document.getElementById('signature-section');
        const originalDisplay = sigSection ? sigSection.style.display : '';
        if (sigSection) {
            // Use MutationObserver to detect when section is shown
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'style' && sigSection.style.display !== 'none') {
                        // Section just became visible - resize canvas to correct dimensions
                        requestAnimationFrame(function() {
                            resizeCanvas();
                        });
                    }
                });
            });
            observer.observe(sigSection, { attributes: true });
        }

        // Initial resize (handles case where section is already visible)
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Clipboard paste support (Ctrl+V to paste signature image)
        document.addEventListener('paste', function(event) {
            const sigSection = document.getElementById('signature-section');
            if (!sigSection || sigSection.style.display === 'none') return;
            const items = (event.clipboardData || event.originalEvent.clipboardData).items;
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    const blob = items[i].getAsFile();
                    const url = URL.createObjectURL(blob);
                    const img = new Image();
                    img.onload = function() {
                        signaturePad.clear();
                        const ctx = canvas.getContext('2d');
                        const ratio = Math.max(window.devicePixelRatio || 1, 1);
                        const displayW = canvas.width / ratio;
                        const displayH = canvas.height / ratio;
                        // Scale image to fit canvas while preserving aspect ratio
                        const scale = Math.min(displayW / img.width, displayH / img.height);
                        const drawW = img.width * scale;
                        const drawH = img.height * scale;
                        const offsetX = (displayW - drawW) / 2;
                        const offsetY = (displayH - drawH) / 2;
                        ctx.fillStyle = 'white';
                        ctx.fillRect(0, 0, displayW, displayH);
                        ctx.drawImage(img, offsetX, offsetY, drawW, drawH);
                        URL.revokeObjectURL(url);
                    };
                    img.src = url;
                    event.preventDefault();
                    break;
                }
            }
        });

        document.getElementById('clear-sig').addEventListener('click', () => {
            signaturePad.clear();
        });

        document.getElementById('submit-sig').addEventListener('click', () => {
            if (signaturePad.isEmpty()) {
                showAlert('Please sign before submitting', 'error');
                return;
            }

            if (!currentScannedDoc) {
                showAlert('No document scanned yet', 'error');
                return;
            }

            submitSignature();
        });
    }

    function submitSignature() {
        const signatureData = signaturePad.toDataURL('image/png');
        const docId = currentScannedDoc.id;

        fetch('{{ route("qr.scan", [], false) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ 
                qr_data: docId, 
                signature: signatureData,
                pin: currentScannedDoc.pin || null,
                target_document_id: targetDocumentId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Document signed and delivered! ✓', 'success');
                signaturePad.clear();
                document.getElementById('signature-section').style.display = 'none';
                document.getElementById('scanned-result').style.display = 'none';
                
                // Reload page after 2 seconds
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error submitting signature', 'error');
        });
    }

    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-success-custom' : 'alert-danger-custom';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const alertHTML = `
            <div class="alert alert-custom ${alertClass} alert-dismissible fade show" role="alert">
                <i class="fas ${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        const container = document.querySelector('.container-fluid');
        const existingAlerts = container.querySelectorAll('.alert-custom');
        if (existingAlerts.length > 0) {
            existingAlerts[0].remove();
        }
        
        container.insertAdjacentHTML('afterbegin', alertHTML);
    }
</script>

<!-- Confidential PIN verification Modal -->
<div class="modal fade" id="pinVerificationModal" tabindex="-1" aria-labelledby="pinVerificationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--panel); border: 1px solid var(--panel-border); border-radius: var(--radius-lg); color: var(--text-main);">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="pinVerificationModalLabel"><i class="fas fa-lock text-warning me-2"></i>Confidential Document Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: var(--close-btn-filter);"></button>
            </div>
            <div class="modal-body pt-3 text-start">
                <div id="pinModalAlertContainer"></div>
                <p class="small text-slate-500 mb-3" style="font-size: 13px; line-height: 1.55;">
                    This document is confidential. A 6-digit verification code has been dispatched to <strong class="text-dark" id="maskedRecipientEmail">your email</strong>.
                </p>
                <div class="mb-3">
                    <label for="modalPinInput" class="form-label small fw-bold text-secondary text-uppercase" style="font-size:11px; letter-spacing:0.5px;">Verification PIN</label>
                    <input type="text" id="modalPinInput" class="form-control text-center fw-bold fs-4" placeholder="••••••" maxlength="6" style="letter-spacing: 6px; height: 50px; background: var(--bg); border: 1px solid var(--panel-border); color: var(--text-main);">
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary" id="btnResendPin" style="font-size:12px; font-weight:600; border-radius: 8px;">
                    Resend PIN
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-size:12px; font-weight:600; border-radius: 8px;">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnVerifyPin" style="font-size:12px; font-weight:600; border-radius: 8px;">Verify & Unlock</button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
