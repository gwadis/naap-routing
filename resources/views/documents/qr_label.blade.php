<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official QR Tracking Label – {{ $document->tracking_number ?? $document->qr_id ?? 'DOC-' . $document->id }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Roboto+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #eef2f6;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
            padding: 24px 16px;
        }

        /* Screen Preview Controls */
        .print-actions {
            width: 100%;
            max-width: 680px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .print-actions .action-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e3a5f;
        }

        .btn-print {
            background: #1e3a5f;
            color: #ffffff;
            border: none;
            padding: 9px 24px;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s ease;
        }

        .btn-print:hover {
            background: #152d4a;
        }

        .btn-close {
            background: transparent;
            color: #64748b;
            border: 1px solid #cbd5e1;
            padding: 9px 18px;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            margin-left: 8px;
        }

        .btn-close:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        /* Container & Card */
        .label-wrapper {
            width: 100%;
            max-width: 680px;
        }

        .official-label {
            background: #ffffff;
            border: 2px solid #1e3a5f;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* 1. Official Header */
        .label-header {
            background: #1e3a5f;
            color: #ffffff;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #0f172a;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-logo-badge {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
        }

        .header-titles .system-name {
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .header-titles .slip-subtitle {
            font-size: 0.65rem;
            opacity: 0.85;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .header-tags {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .badge-tag {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .badge-confidential {
            background: #ef4444;
            color: #ffffff;
        }

        .badge-regular {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }

        .badge-priority-urgent {
            background: #dc2626;
            color: #ffffff;
        }

        .badge-priority-high {
            background: #ea580c;
            color: #ffffff;
        }

        .badge-priority-normal {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }

        .badge-priority-low {
            background: rgba(255, 255, 255, 0.15);
            color: #e2e8f0;
        }

        .badge-type {
            background: #ffffff;
            color: #1e3a5f;
            border: 1px solid #ffffff;
        }

        /* 2. Prominent Tracking Banner */
        .tracking-banner {
            background: #f8fafc;
            border-bottom: 1.5px solid #cbd5e1;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .tracking-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .tracking-label {
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #64748b;
        }

        .tracking-number-val {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e3a5f;
            letter-spacing: 0.05em;
            line-height: 1.2;
        }

        .tracking-status-block {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
        }

        .status-header-lbl {
            font-size: 0.60rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #64748b;
        }

        .status-pill {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border: 1px solid transparent;
        }

        .status-pending   { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .status-in-transit, .status-transit { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
        .status-received  { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .status-completed { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .status-rejected  { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .status-default   { background: #e2e8f0; color: #334155; border-color: #cbd5e1; }

        /* 3. Main Body Grid: QR Verification (Left) + Document Information (Right) */
        .label-grid {
            display: grid;
            grid-template-columns: 210px 1fr;
            gap: 16px;
            padding: 16px 18px;
            border-bottom: 1.5px solid #e2e8f0;
        }

        /* QR Section (The Hero Element) */
        .qr-card {
            background: #ffffff;
            border: 1.5px solid #1e3a5f;
            border-radius: 6px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            text-align: center;
            height: 100%;
        }

        .qr-card-header {
            margin-bottom: 8px;
        }

        .qr-scan-title {
            font-size: 0.65rem;
            font-weight: 800;
            color: #1e3a5f;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            display: block;
        }

        .qr-quiet-zone {
            width: 172px;
            height: 172px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        .no-qr-text {
            color: #94a3b8;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .qr-card-footer {
            margin-top: 8px;
            width: 100%;
        }

        .qr-mono-trk {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.72rem;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 4px;
            word-break: break-all;
        }

        .qr-instruction {
            font-size: 0.58rem;
            color: #64748b;
            line-height: 1.3;
            letter-spacing: 0.01em;
        }

        /* Document Info Column */
        .info-col {
            display: flex;
            flex-direction: column;
            gap: 12px;
            justify-content: space-between;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
        }

        .info-box-heading {
            font-size: 0.60rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }

        .doc-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .doc-desc {
            font-size: 0.70rem;
            color: #475569;
            line-height: 1.35;
            margin-bottom: 8px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 12px;
        }

        .detail-row {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .detail-lbl {
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
        }

        .detail-val {
            font-size: 0.75rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.25;
        }

        .office-matrix {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .office-cell {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 8px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .office-cell-lbl {
            font-size: 0.56rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }

        .office-cell-val {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1e3a5f;
            line-height: 1.2;
        }

        /* 4. Sequential Routing Pathway */
        .routing-pathway-section {
            background: #ffffff;
            padding: 10px 18px 12px 18px;
            border-bottom: 1.5px solid #cbd5e1;
        }

        .pathway-heading {
            font-size: 0.60rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 8px;
        }

        .pathway-chain {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .path-step {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 4px 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .step-index {
            font-size: 0.60rem;
            font-weight: 800;
            color: #ffffff;
            background: #1e3a5f;
            border-radius: 3px;
            padding: 1px 4px;
            line-height: 1.2;
        }

        .step-office {
            font-size: 0.72rem;
            font-weight: 700;
            color: #1e3a5f;
        }

        .step-tag {
            font-size: 0.58rem;
            color: #64748b;
            font-weight: 500;
        }

        .path-arrow {
            color: #2563eb;
            font-weight: 800;
            font-size: 0.85rem;
            line-height: 1;
        }

        /* 5. Official Footer */
        .label-footer {
            background: #f8fafc;
            padding: 8px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.65rem;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        .footer-left {
            font-weight: 600;
            color: #1e3a5f;
        }

        .footer-center {
            font-family: 'Roboto Mono', monospace;
            font-weight: 600;
            color: #334155;
        }

        .footer-right {
            color: #64748b;
        }

        .security-notice-bar {
            background: #f1f5f9;
            padding: 5px 18px;
            font-size: 0.55rem;
            color: #64748b;
            text-align: center;
            letter-spacing: 0.02em;
            line-height: 1.3;
        }

        /* Dedicated Print Stylesheet: Guaranteed EXACTLY ONE PAGE on A4 Portrait */
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }
            *, *::before, *::after {
                box-shadow: none !important;
                text-shadow: none !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            html, body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                height: auto !important;
                min-height: 0 !important;
                max-height: none !important;
                display: block !important;
                overflow: visible !important;
                page-break-after: avoid !important;
                break-after: avoid !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .print-actions {
                display: none !important;
            }
            .label-wrapper {
                margin: 0 auto !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .official-label {
                box-shadow: none !important;
                border: 2px solid #1e3a5f !important;
                margin: 0 auto !important;
                width: 100% !important;
                max-width: 100% !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .official-label, .official-label * {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

@php
    $trackingNumber = $document->tracking_number ?? $document->qr_id ?? ('DOC-' . str_pad($document->id, 6, '0', STR_PAD_LEFT));

    // Dynamic sequential routing stops
    $stops = [];
    if ($document->originOffice) {
        $stops[] = [
            'name' => $document->originOffice->name,
            'role' => 'Origin',
            'status' => 'Dispatched'
        ];
    }

    if ($document->routings && $document->routings->count() > 0) {
        $sortedRoutings = $document->routings->sortBy('sort_order');
        foreach ($sortedRoutings as $r) {
            $officeName = $r->toOffice?->name;
            if ($officeName && !collect($stops)->contains('name', $officeName)) {
                $stops[] = [
                    'name' => $officeName,
                    'role' => ($r->to_office_id == $document->destination_office_id) ? 'Destination' : 'Transit',
                    'status' => $r->status ?? 'Pending'
                ];
            }
        }
    } elseif (!empty($document->destination_offices) && is_array($document->destination_offices)) {
        foreach ($document->destination_offices as $hop) {
            $office = \App\Models\Office::find($hop['office_id'] ?? null);
            if ($office && !collect($stops)->contains('name', $office->name)) {
                $stops[] = [
                    'name' => $office->name,
                    'role' => ($office->id == $document->destination_office_id) ? 'Destination' : 'Transit',
                    'status' => 'Pending'
                ];
            }
        }
    }

    if ($document->destinationOffice && !collect($stops)->contains('name', $document->destinationOffice->name)) {
        $stops[] = [
            'name' => $document->destinationOffice->name,
            'role' => 'Destination',
            'status' => 'Pending'
        ];
    }

    if (empty($stops)) {
        $stops[] = ['name' => 'Origin Office', 'role' => 'Origin', 'status' => ''];
        $stops[] = ['name' => 'Destination Office', 'role' => 'Destination', 'status' => ''];
    }

    // High quality QR rendering
    $qrPayload = $document->getQrPayloadUrl();

    $qrUrl = '';
    if ($document->qr_code && \Storage::disk('public')->exists($document->qr_code)) {
        $qrUrl = asset('storage/' . $document->qr_code);
    } else {
        try {
            $qrObj = new \Endroid\QrCode\QrCode($qrPayload, size: 360, margin: 8);
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrObj);
            $qrUrl = 'data:image/png;base64,' . base64_encode($result->getString());
        } catch (\Throwable $e) {
            $qrUrl = $document->qr_code ? asset('storage/' . $document->qr_code) : '';
        }
    }
@endphp

<!-- Screen Action Bar (Hidden on Print) -->
<div class="print-actions">
    <div class="action-left">
        <span>📄 Official QR Document Routing Label</span>
    </div>
    <div>
        <button type="button" class="btn-print" onclick="window.print()">
            🖨️ Print Label
        </button>
        <button type="button" class="btn-close" onclick="window.close()">
            ✕ Close
        </button>
    </div>
</div>

<!-- Main Printable Label Container -->
<div class="label-wrapper">
    <div class="official-label">
        <!-- 1. Official Header -->
        <div class="label-header">
            <div class="header-brand">
                <div class="header-logo-badge">NP</div>
                <div class="header-titles">
                    <div class="system-name">NAAP Document Routing System</div>
                    <div class="slip-subtitle">Official Document Tracking & Routing Slip</div>
                </div>
            </div>
            <div class="header-tags">
                @if($document->is_confidential)
                    <span class="badge-tag badge-confidential">Confidential</span>
                @else
                    <span class="badge-tag badge-regular">Regular</span>
                @endif

                @php
                    $prio = strtolower($document->priority ?? 'normal');
                    $prioClass = match($prio) {
                        'urgent' => 'badge-priority-urgent',
                        'high'   => 'badge-priority-high',
                        'low'    => 'badge-priority-low',
                        default  => 'badge-priority-normal',
                    };
                @endphp
                <span class="badge-tag {{ $prioClass }}">{{ $document->priority ?? 'Normal' }} Priority</span>
                <span class="badge-tag badge-type">{{ strtoupper($document->type ?? 'PDF') }}</span>
            </div>
        </div>

        <!-- 2. Prominent Tracking Banner -->
        <div class="tracking-banner">
            <div class="tracking-meta">
                <span class="tracking-label">Official Tracking Number</span>
                <span class="tracking-number-val">{{ $trackingNumber }}</span>
            </div>
            <div class="tracking-status-block">
                <span class="status-header-lbl">Current Status</span>
                @php
                    $normStatus = strtolower(trim($document->status ?? 'pending'));
                    $statusClass = match($normStatus) {
                        'pending'               => 'status-pending',
                        'in transit', 'transit' => 'status-in-transit',
                        'received'              => 'status-received',
                        'completed'             => 'status-completed',
                        'rejected'              => 'status-rejected',
                        default                 => 'status-default',
                    };
                @endphp
                <span class="status-pill {{ $statusClass }}">{{ $document->status ?? 'Pending' }}</span>
            </div>
        </div>

        <!-- 3. Main Body Grid: QR Verification (Left) + Document Information (Right) -->
        <div class="label-grid">
            <!-- Left Column: Dedicated QR Verification Section (THE HERO FOCUS) -->
            <div class="qr-col">
                <div class="qr-card">
                    <div class="qr-card-header">
                        <span class="qr-scan-title">Scan to Verify Document</span>
                    </div>
                    <div class="qr-quiet-zone">
                        @if($qrUrl)
                            <img src="{{ $qrUrl }}" alt="QR Code" class="qr-img">
                        @else
                            <div class="no-qr-text">📦 NO QR DATA</div>
                        @endif
                    </div>
                    <div class="qr-card-footer">
                        <div class="qr-mono-trk">{{ $trackingNumber }}</div>
                        <div class="qr-instruction">
                            Scan this QR code to verify and access the authorized document record.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Document Details & Routing Info -->
            <div class="info-col">
                <!-- Document Information -->
                <div class="info-box">
                    <div class="info-box-heading">Document Information</div>
                    <div class="doc-title">{{ $document->title }}</div>
                    @if($document->description)
                        <div class="doc-desc">{{ \Illuminate\Support\Str::limit($document->description, 140) }}</div>
                    @endif
                    <div class="detail-grid">
                        <div class="detail-row">
                            <span class="detail-lbl">Category</span>
                            <span class="detail-val">{{ $document->category ?? 'General Document' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-lbl">Date Created</span>
                            <span class="detail-val">{{ $document->created_at ? $document->created_at->format('M d, Y h:i A') : 'N/A' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-lbl">Uploaded By</span>
                            <span class="detail-val">{{ $document->uploader?->name ?? 'System' }}</span>
                        </div>
                        @if($document->due_date)
                        <div class="detail-row">
                            <span class="detail-lbl">SLA Due Date</span>
                            <span class="detail-val">{{ $document->due_date->format('M d, Y h:i A') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Office Routing Summary -->
                <div class="info-box">
                    <div class="info-box-heading">Office Routing & Responsibility</div>
                    <div class="office-matrix">
                        <div class="office-cell">
                            <span class="office-cell-lbl">Origin Office</span>
                            <span class="office-cell-val">{{ $document->originOffice?->name ?? 'N/A' }}</span>
                        </div>
                        <div class="office-cell">
                            <span class="office-cell-lbl">Current Location</span>
                            <span class="office-cell-val">{{ $document->currentOffice?->name ?? $document->originOffice?->name ?? 'In Transit' }}</span>
                        </div>
                        <div class="office-cell">
                            <span class="office-cell-lbl">Destination Office</span>
                            <span class="office-cell-val">{{ $document->destinationOffice?->name ?? 'N/A' }}</span>
                        </div>
                        <div class="office-cell">
                            <span class="office-cell-lbl">Assigned Receiver</span>
                            <span class="office-cell-val">{{ $document->receiverUser?->name ?? 'Office Staff' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Sequential Routing Pathway -->
        <div class="routing-pathway-section">
            <div class="pathway-heading">Official Routing Pathway</div>
            <div class="pathway-chain">
                @foreach($stops as $idx => $stop)
                    <div class="path-step">
                        <span class="step-index">{{ $idx + 1 }}</span>
                        <span class="step-office">{{ $stop['name'] }}</span>
                        @if(!empty($stop['role']))
                            <span class="step-tag">({{ $stop['role'] }})</span>
                        @endif
                    </div>
                    @if(!$loop->last)
                        <span class="path-arrow">➔</span>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- 5. Official Footer -->
        <div class="label-footer">
            <div class="footer-left">
                <span>NAAP Document Routing System</span>
            </div>
            <div class="footer-center">
                <span>Tracking No: <strong>{{ $trackingNumber }}</strong></span>
            </div>
            <div class="footer-right">
                <span>Generated: {{ now()->setTimezone('Asia/Manila')->format('M d, Y h:i A') }} PHT</span>
            </div>
        </div>
        <div class="security-notice-bar">
            Official Document Tracking Slip • Attach to physical document folder • Scan QR code to verify authenticity and log transfer
        </div>
    </div>
</div>

<script>
    // Auto-trigger print if requested via query param
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('autoprint') === '1') {
        window.addEventListener('load', function() {
            setTimeout(() => window.print(), 500);
        });
    }
</script>
</body>
</html>
