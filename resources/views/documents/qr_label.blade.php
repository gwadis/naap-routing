<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Label – {{ $document->tracking_number ?? $document->qr_id }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Roboto+Mono:wght@500&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }

        .label-card {
            background: #ffffff;
            border: 2px solid #1e3a5f;
            border-radius: 12px;
            width: 420px;
            padding: 0;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            overflow: hidden;
        }

        .label-header {
            background: #1e3a5f;
            color: #ffffff;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .label-header .system-name {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            opacity: 0.85;
        }

        .label-header .doc-type {
            font-size: 0.7rem;
            background: rgba(255,255,255,0.15);
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .label-body {
            padding: 20px;
        }

        .label-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .label-subtitle {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 16px;
        }

        .tracking-number {
            background: #f1f5f9;
            border: 1.5px dashed #1e3a5f;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tracking-number .label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.1em;
            flex-shrink: 0;
        }

        .tracking-number .value {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.9rem;
            font-weight: 500;
            color: #1e3a5f;
            word-break: break-all;
        }

        .qr-section {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .qr-image {
            flex-shrink: 0;
            width: 130px;
            height: 130px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .no-qr {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 0.7rem;
            text-align: center;
        }

        .meta-grid {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .meta-label {
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #94a3b8;
            font-weight: 700;
        }

        .meta-value {
            font-size: 0.78rem;
            font-weight: 600;
            color: #334155;
            line-height: 1.3;
        }

        .route-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }

        .route-bar-label {
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .route-path {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .route-stop {
            font-size: 0.72rem;
            font-weight: 600;
            color: #1e3a5f;
            background: #e2e8f0;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .route-arrow {
            color: #3b82f6;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .status-pending    { background: #fef3c7; color: #92400e; }
        .status-transit    { background: #dbeafe; color: #1e40af; }
        .status-received   { background: #d1fae5; color: #065f46; }
        .status-completed  { background: #d1fae5; color: #065f46; }
        .status-rejected   { background: #fee2e2; color: #991b1b; }
        .status-default    { background: #f1f5f9; color: #475569; }

        .label-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .label-footer .generated {
            font-size: 0.65rem;
            color: #94a3b8;
        }

        .label-footer .priority-badge {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .priority-urgent { background: #fee2e2; color: #991b1b; }
        .priority-high   { background: #fed7aa; color: #9a3412; }
        .priority-normal { background: #d1fae5; color: #065f46; }
        .priority-low    { background: #f1f5f9; color: #475569; }

        .print-actions {
            text-align: center;
            padding: 24px;
        }

        .btn-print {
            background: #1e3a5f;
            color: #fff;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-right: 8px;
        }

        .btn-print:hover { background: #152d4a; }

        .btn-close {
            background: transparent;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-close:hover { background: #f1f5f9; }

        @media print {
            body { background: #ffffff; padding: 0; }
            .label-card { box-shadow: none; margin: 0 auto; }
            .print-actions { display: none !important; }
        }
    </style>
</head>
<body>

<div>
    <div class="label-card">
        <!-- Header -->
        <div class="label-header">
            <div>
                <div class="system-name">NAAP Document Routing System</div>
                <div style="font-size:0.7rem; opacity:0.65; margin-top:2px;">Official Document Tracking Label</div>
            </div>
            <div class="doc-type">{{ $document->type }}</div>
        </div>

        <!-- Body -->
        <div class="label-body">
            <!-- Title -->
            <div class="label-title">{{ $document->title }}</div>
            <div class="label-subtitle">{{ $document->description ? \Str::limit($document->description, 80) : 'No description provided.' }}</div>

            <!-- Tracking Number -->
            <div class="tracking-number">
                <span class="label">Tracking No.</span>
                <span class="value">{{ $document->tracking_number ?? $document->qr_id ?? 'DOC-' . str_pad($document->id, 6, '0', STR_PAD_LEFT) }}</span>
            </div>

            <!-- QR + Meta Grid -->
            <div class="qr-section">
                <div class="qr-image">
                    @if($document->qr_code)
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
                            <img src="{{ $qrUrl }}" alt="QR Code">
                        @else
                            <div class="no-qr">
                                <div style="font-size:2rem; margin-bottom:4px;">📦</div>
                                <div>No QR</div>
                            </div>
                        @endif
                    @else
                        <div class="no-qr">
                            <div style="font-size:2rem; margin-bottom:4px;">📦</div>
                            <div>No QR</div>
                        </div>
                    @endif
                </div>

                <div class="meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">Status</span>
                        @php
                            $statusClass = match(strtolower($document->status ?? '')) {
                                'pending'    => 'status-pending',
                                'in transit' => 'status-transit',
                                'received'   => 'status-received',
                                'completed'  => 'status-completed',
                                'rejected'   => 'status-rejected',
                                default      => 'status-default',
                            };
                        @endphp
                        <span class="status-badge {{ $statusClass }}">{{ $document->status }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Origin Office</span>
                        <span class="meta-value">{{ $document->originOffice?->name ?? 'N/A' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Destination</span>
                        <span class="meta-value">{{ $document->destinationOffice?->name ?? 'N/A' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Uploaded By</span>
                        <span class="meta-value">{{ $document->uploader?->name ?? 'System' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Date Created</span>
                        <span class="meta-value">{{ $document->created_at->format('M d, Y h:i A') }}</span>
                    </div>
                </div>
            </div>

            <!-- Route Path -->
            @if($document->destinationOffices ?? $document->destination_offices)
                <div class="route-bar">
                    <div class="route-bar-label">Routing Path</div>
                    <div class="route-path">
                        <span class="route-stop">{{ $document->originOffice?->name ?? 'Origin' }}</span>
                        @php
                            $hops = is_array($document->destination_offices) ? $document->destination_offices : [];
                        @endphp
                        @foreach($hops as $hop)
                            @php $office = \App\Models\Office::find($hop['office_id'] ?? null); @endphp
                            @if($office)
                                <span class="route-arrow">→</span>
                                <span class="route-stop">{{ $office->name }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @else
                <div class="route-bar">
                    <div class="route-bar-label">Route</div>
                    <div class="route-path">
                        <span class="route-stop">{{ $document->originOffice?->name ?? 'Origin' }}</span>
                        <span class="route-arrow">→</span>
                        <span class="route-stop">{{ $document->destinationOffice?->name ?? 'Destination' }}</span>
                    </div>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="label-footer">
            <span class="generated">Generated: {{ now()->format('M d, Y h:i A') }}</span>
            @php
                $priorityClass = match(strtolower($document->priority ?? 'normal')) {
                    'urgent' => 'priority-urgent',
                    'high'   => 'priority-high',
                    'low'    => 'priority-low',
                    default  => 'priority-normal',
                };
            @endphp
            <span class="priority-badge {{ $priorityClass }}">{{ $document->priority ?? 'Normal' }} Priority</span>
        </div>
    </div>

    <!-- Print Action Buttons -->
    <div class="print-actions">
        <button class="btn-print" onclick="window.print()">🖨️ Print Label</button>
        <button class="btn-close" onclick="window.close()">✕ Close</button>
    </div>
</div>

<script>
    // Auto-trigger print if opened via auto-print URL param
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('autoprint') === '1') {
        window.addEventListener('load', function() {
            setTimeout(() => window.print(), 500);
        });
    }
</script>
</body>
</html>
