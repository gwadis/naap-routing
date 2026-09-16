@extends('layouts.app')
@section('title', 'Route Document')

@section('head')
<script src="https://unpkg.com/html5-qrcode"></script>
<style>
    /* Light Mode default variables */
    :root {
        --glass-bg: var(--panel);
        --panel-border: var(--panel-border);
        --accent-cyan: var(--accent-cyan);
        --accent-purple: var(--accent-purple);
        --accent-green: var(--success);
        --accent-red: var(--danger);
        --accent-orange: var(--warning);
    }
    
    /* Dark Mode specific variables */
    body.dark-mode {
        --glass-bg: rgba(30, 41, 59, 0.7);
        --panel-border: rgba(255, 255, 255, 0.08);
        --accent-cyan: #22d3ee;
        --accent-purple: #a855f7;
        --accent-green: #10b981;
        --accent-red: #ef4444;
        --accent-orange: #f59e0b;
    }

    .glass-card {
        background: var(--glass-bg);
        border: 1px solid var(--panel-border);
        border-radius: 24px;
        backdrop-filter: blur(10px);
    }

    /* Adaptive colors inside glass card */
    .glass-card .text-white {
        color: var(--text-main) !important;
    }
    .glass-card .text-secondary {
        color: var(--text-dim) !important;
    }
    .glass-card .text-muted {
        color: var(--text-dim) !important;
    }
    #start-scan {
        background: var(--panel-border) !important;
        color: var(--text-main) !important;
        border: 1px solid var(--panel-border) !important;
    }
    .form-label-custom {
        color: #94a3b8; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; display: block;
    }
    .custom-input {
        background: #1e293b !important; border: 1px solid var(--panel-border) !important;
        color: white !important; padding: 12px 16px; border-radius: 12px;
    }
    .custom-input:focus {
        border-color: var(--accent-cyan) !important;
        box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.1);
    }
    
    .priority-pill {
        flex: 1; padding: 10px; border: 1px solid var(--panel-border);
        background: transparent; color: #64748b; border-radius: 12px; 
        font-weight: 600; transition: all 0.2s ease; cursor: pointer;
    }
    .priority-pill:hover { background: rgba(255, 255, 255, 0.05); }
    
    .priority-pill.active[data-value="Low"] { background: rgba(16, 185, 129, 0.1); border-color: var(--accent-green); color: var(--accent-green); }
    .priority-pill.active[data-value="Medium"] { background: rgba(245, 158, 11, 0.1); border-color: var(--accent-orange); color: var(--accent-orange); }
    .priority-pill.active[data-value="High"] { background: rgba(239, 68, 68, 0.1); border-color: var(--accent-red); color: var(--accent-red); }

    .step-line { position: absolute; left: 17px; top: 36px; bottom: -20px; width: 2px; background: rgba(255, 255, 255, 0.1); }
    .qr-preview-box {
        border: 2px dashed rgba(255,255,255,0.1); border-radius: 20px;
        min-height: 300px; display: grid; place-items: center; background: rgba(0,0,0,0.2); overflow: hidden;
    }
    #scanner-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.85rem;
        width: 100%;
        padding: 1rem;
        text-align: center;
    }
    #scanner-placeholder .scanner-icon {
        width: 96px;
        height: 96px;
        min-width: 96px;
        min-height: 96px;
        display: grid;
        place-items: center;
        background: rgba(15, 23, 42, 0.95);
        border-radius: 50%;
        border: 1px solid rgba(148, 163, 184, 0.18);
    }
    #scanner-placeholder p {
        color: rgba(226, 232, 240, 0.88);
        margin: 0;
        line-height: 1.5;
        font-size: 0.95rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid p-4">
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 12px; background: rgba(16, 185, 129, 0.2); color: #10b981;">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 12px; background: rgba(239, 68, 68, 0.15); color: #f87171;">
            {{ session('error') }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card p-4 mb-4">
                <h5 class="mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-qr-code-scan text-info"></i> Scan Document QR
                </h5>
                <p class="text-secondary mb-0">Use this page to scan a document QR code and open the corresponding document details. No uploading or routing is required here.</p>
            </div>

            @if(session('scanned_doc'))
                <div class="glass-card p-4 mb-4">
                    <h5 class="mb-4">Last Scanned Document</h5>
                    <div class="text-white">
                        <p class="mb-1"><strong>Title:</strong> {{ session('scanned_doc')->title }}</p>
                        <p class="mb-1"><strong>Status:</strong> {{ session('scanned_doc')->status }}</p>
                        <p class="mb-0"><strong>Destination:</strong> {{ session('scanned_doc')->destinationOffice?->name ?? 'N/A' }}</p>
                    </div>
                </div>
            @endif

            <div class="glass-card p-4">
                <h5 class="mb-4">How to use</h5>
                <ul class="list-unstyled text-secondary mb-0" style="line-height: 1.8;">
                    <li>1. Click <strong>Start Camera</strong>.</li>
                    <li>2. Point your device camera at the document QR code.</li>
                    <li>3. The scanner will automatically open the document page.</li>
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card p-4 mb-4">
                <h5 class="mb-4">QR Scanner</h5>
                <div id="reader" class="qr-preview-box">
                    <div id="scanner-placeholder">
                        <div class="scanner-icon">
                            <i class="bi bi-camera" style="font-size: 2.25rem; color: var(--text-dim);"></i>
                        </div>
                        <p>Scan document QR code to view</p>
                    </div>
                </div>
                <button type="button" id="start-scan" class="btn w-100 mt-3 p-3 fw-bold text-dark" style="background: #e2e8f0; border-radius: 12px;">
                    Start Camera
                </button>
            </div>

            <div class="glass-card p-4">
                <h5 class="mb-4">Routing Flow</h5>
                <div class="position-relative">
                    @php
                        $steps = [
                            ['n' => 1, 't' => 'Document Created', 'd' => 'Origin office', 'color' => 'var(--accent-cyan)'],
                            ['n' => 2, 't' => 'In Transit', 'd' => 'Being routed', 'color' => 'var(--accent-purple)'],
                            ['n' => 3, 't' => 'Received', 'd' => 'Destination office', 'color' => 'var(--accent-green)'],
                        ];
                    @endphp
                    @foreach($steps as $st)
                    <div class="d-flex mb-4 position-relative align-items-center">
                        @if(!$loop->last) <div class="step-line"></div> @endif
                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold me-3" 
                             style="width: 36px; height: 36px; background: {{ $st['color'] }}; color: #000; z-index: 2;">
                            {{ $st['n'] }}
                        </div>
                        <div>
                            <div class="fw-bold small text-white">{{ $st['t'] }}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">{{ $st['d'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const scannerBtn = document.getElementById('start-scan');
        const placeholder = document.getElementById('scanner-placeholder');
        let html5QrCode;

        scannerBtn.addEventListener('click', function() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().then(() => {
                    placeholder.style.display = 'block';
                    scannerBtn.innerText = "Start Camera";
                });
                return;
            }

            placeholder.style.display = 'none';
            scannerBtn.innerText = "Stop Camera";
            html5QrCode = new Html5Qrcode("reader");

            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    if (decodedText.includes('/documents/')) {
                        window.location.href = decodedText;
                    } else {
                        alert('Scanned QR is not a valid document code. Scanned: ' + decodedText);
                    }

                    html5QrCode.stop().then(() => {
                        placeholder.style.display = 'block';
                        scannerBtn.innerText = "Start Camera";
                    });
                },
                (errorMessage) => { /* quiet scanning */ }
            ).catch((err) => {
                console.error("Camera error:", err);
                placeholder.style.display = 'block';
                scannerBtn.innerText = "Start Camera";
            });
        });
    });
</script>
@endsection