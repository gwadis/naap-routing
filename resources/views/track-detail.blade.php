@extends('layouts.app')

@section('title','Document Tracking - ' . $document->title)

@section('content')
<style>
    .tracking-container {
        max-width: 900px;
        margin: 0 auto;
    }

    .back-link {
        color: var(--accent-cyan);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 24px;
        transition: 0.2s;
        font-weight: 600;
    }

    .back-link:hover {
        color: #1D4ED8;
        transform: translateX(-4px);
    }

    .document-header {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }

    .document-title {
        color: var(--accent-navy);
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0 0 16px 0;
    }

    .document-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .meta-item {
        background: var(--bg);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 16px;
    }

    .meta-label {
        color: var(--text-dim);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 8px;
    }

    .meta-value {
        color: var(--text-main);
        font-size: 1rem;
        font-weight: 600;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .status-completed {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .status-in-transit {
        background: rgba(59, 130, 246, 0.1);
        color: #2563EB;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: #D97706;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .timeline-section {
        margin-bottom: 30px;
    }

    .timeline-title {
        color: var(--accent-navy);
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .timeline-container {
        position: relative;
        padding-left: 40px;
    }

    .timeline-line {
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--panel-border);
    }

    .timeline-item {
        display: flex;
        margin-bottom: 32px;
        position: relative;
    }

    .timeline-dot {
        position: absolute;
        left: -30px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #FFFFFF !important;
        box-shadow: 0 0 0 3px var(--bg);
        z-index: 10;
    }

    .dot-created {
        background: var(--success);
    }

    .dot-transit {
        background: var(--accent-cyan);
    }

    .dot-signed {
        background: var(--accent-purple);
    }

    .dot-received {
        background: var(--success);
    }

    .timeline-content {
        flex: 1;
    }

    .timeline-event-title {
        color: var(--text-main);
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 8px;
    }

    .timeline-event-details {
        color: var(--text-dim);
        font-size: 0.9rem;
        margin-bottom: 8px;
    }

    .timeline-timestamp {
        color: var(--text-dim);
        font-size: 0.85rem;
        font-weight: 500;
    }

    .timeline-notes {
        background: var(--bg);
        border-left: 3px solid var(--accent-cyan);
        padding: 12px;
        border-radius: 8px;
        margin-top: 12px;
        color: var(--text-main);
        font-size: 0.9rem;
        font-style: italic;
        border: 1px solid var(--panel-border);
        border-left: 3px solid var(--accent-cyan) !important;
    }

    .proof-of-delivery {
        background: #F8FAFC;
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 16px;
        margin-top: 12px;
    }

    .proof-header {
        color: var(--success);
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }

    .proof-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--panel-border);
        font-size: 0.9rem;
    }

    .proof-row:last-child {
        border-bottom: none;
    }

    .proof-label {
        color: var(--text-dim);
    }

    .proof-value {
        color: var(--text-main);
        font-weight: 600;
    }

    .signature-preview {
        display: inline-block;
        margin-top: 12px;
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        padding: 8px;
        background: #FFFFFF;
    }

    .signature-preview img {
        max-width: 150px;
        height: auto;
        border-radius: 4px;
    }

    @media (max-width: 768px) {
        .document-title {
            font-size: 1.5rem;
        }

        .document-meta {
            grid-template-columns: 1fr;
        }

        .meta-item {
            padding: 12px;
        }

        .timeline-container {
            padding-left: 30px;
        }

        .timeline-dot {
            left: -22px;
            width: 28px;
            height: 28px;
            font-size: 0.75rem;
        }
    }
</style>

<div class="tracking-container">
    <a href="{{ route('track.index') }}" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Tracking
    </a>

    <!-- Document Header -->
    <div class="document-header">
        <h1 class="document-title">{{ $document->title }}</h1>
        <div class="document-meta">
            <div class="meta-item">
                <div class="meta-label"><i class="fas fa-file-alt text-primary me-1"></i> Document ID</div>
                <div class="meta-value">#{{ $document->id }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label"><i class="fas fa-qrcode text-primary me-1"></i> QR Code ID</div>
                <div class="meta-value">{{ $document->qr_id ?? 'N/A' }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Status</div>
                <span class="status-badge status-{{ strtolower(str_replace(' ', '-', $document->status)) }}">
                    {{ $document->status }}
                </span>
            </div>
            <div class="meta-item">
                <div class="meta-label"><i class="fas fa-paper-plane text-primary me-1"></i> Origin</div>
                <div class="meta-value">{{ $document->originOffice?->name ?? 'Unknown' }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label"><i class="fas fa-inbox text-primary me-1"></i> Destination</div>
                <div class="meta-value">{{ $document->destinationOffice?->name ?? 'Unknown' }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label"><i class="fas fa-map-marker-alt text-primary me-1"></i> Current Location</div>
                <div class="meta-value">{{ $document->currentOffice?->name ?? 'In Transit' }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label"><i class="fas fa-clock text-primary me-1"></i> Created</div>
                <div class="meta-value">{{ $document->created_at->format('M j, Y H:i') }}</div>
            </div>
            <div class="meta-item">
                <div class="meta-label"><i class="fas fa-eye text-primary me-1"></i> Last Viewed</div>
                <div class="meta-value">
                    @php
                        $latestView = $document->views->sortByDesc('viewed_at')->first();
                    @endphp
                    @if($latestView)
                        <div style="font-weight: 700;">{{ $latestView->viewed_at->format('M d, Y • h:i A') }}</div>
                        <div style="font-size: 0.8rem; font-weight: normal; color: var(--text-dim); margin-top: 4px;">Viewed by {{ $latestView->user->name }}</div>
                    @else
                        <span style="color: var(--text-dim); font-weight: normal;">Never Viewed</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Routing Timeline -->
    <div class="timeline-section">
        <h2 class="timeline-title">
            <i class="fas fa-map-pin"></i> Routing Timeline
        </h2>

        <div class="timeline-container">
            <div class="timeline-line"></div>

            <!-- Document Created -->
            <div class="timeline-item">
                <div class="timeline-dot dot-created">✓</div>
                <div class="timeline-content">
                    <div class="timeline-event-title">Document Created</div>
                    <div class="timeline-event-details">
                        {{ $document->originOffice?->name ?? 'Unknown Office' }}
                    </div>
                    <div class="timeline-timestamp">
                        {{ $document->created_at->format('M j, Y \a\t H:i:s') }}
                    </div>
                </div>
            </div>

            <!-- Routing Steps -->
            @forelse($document->routings()->orderBy('created_at')->get() as $routing)
            <div class="timeline-item">
                <div class="timeline-dot dot-transit">→</div>
                <div class="timeline-content">
                    <div class="timeline-event-title">{{ ucfirst($routing->status) }}</div>
                    <div class="timeline-event-details">
                        {{ $routing->fromOffice?->name }} 
                        <i class="fas fa-arrow-right" style="color: #94a3b8; margin: 0 8px;"></i> 
                        {{ $routing->toOffice?->name }}
                    </div>
                    <div class="timeline-timestamp">
                        Routed: {{ $routing->created_at->format('M j, Y \a\t H:i:s') }}
                        @if($routing->scanned_at)
                            <br><span style="color: #00d7ff;">Scanned: {{ $routing->scanned_at->format('M j, Y \a\t H:i:s') }}</span>
                        @endif
                        @if($routing->received_at)
                            <br><span style="color: #10b981;">Received: {{ $routing->received_at->format('M j, Y \a\t H:i:s') }}</span>
                        @endif
                    </div>

                    @if($routing->notes)
                    <div class="timeline-notes">
                        "{{ $routing->notes }}"
                    </div>
                    @endif

                    <!-- Signature Proof -->
                    @if($routing->signature)
                    <div class="proof-of-delivery">
                        <div class="proof-header">
                            <i class="fas fa-pen"></i> Signed for Receipt
                        </div>
                        <div class="proof-row">
                            <span class="proof-label">Signed by:</span>
                            <span class="proof-value">{{ $routing->signed_by ?? 'System' }}</span>
                        </div>
                        <div class="proof-row">
                            <span class="proof-label">Signed at:</span>
                            <span class="proof-value">{{ $routing->received_at?->format('M j, Y H:i') ?? 'N/A' }}</span>
                        </div>
                        <div class="signature-preview">
                            <img src="{{ str_contains($routing->signature, 'data:image') ? $routing->signature : asset('storage/' . $routing->signature) }}" alt="Signature Proof" onerror="this.style.display='none'">
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @empty
            @endforelse

            <!-- Document Received & Signed -->
            @if($document->received_at)
            <div class="timeline-item">
                <div class="timeline-dot dot-signed">✓</div>
                <div class="timeline-content">
                    <div class="timeline-event-title">Document Received & Signed</div>
                    <div class="timeline-event-details">
                        {{ $document->destinationOffice?->name ?? 'Unknown' }}
                    </div>
                    <div class="timeline-timestamp">
                        {{ $document->received_at->format('M j, Y \a\t H:i:s') }}
                    </div>

                    @if($document->receiver_signature)
                    <div class="proof-of-delivery">
                        <div class="proof-header">
                            <i class="fas fa-check-circle"></i> Proof of Delivery (QR Signed)
                        </div>
                        <div class="proof-row">
                            <span class="proof-label">Received by:</span>
                            <span class="proof-value">{{ $document->receiverUser?->name ?? 'Unknown' }}</span>
                        </div>
                        <div class="proof-row">
                            <span class="proof-label">Received at:</span>
                            <span class="proof-value">{{ $document->received_at->format('M j, Y H:i:s') }}</span>
                        </div>
                        <div class="proof-row">
                            <span class="proof-label">QR Scanned:</span>
                            <span class="proof-value">{{ $document->qr_scanned_at?->format('M j, Y H:i:s') ?? 'N/A' }}</span>
                        </div>
                        <div class="signature-preview">
                            <img src="{{ str_contains($document->receiver_signature, 'data:image') ? $document->receiver_signature : asset('storage/' . $document->receiver_signature) }}" alt="Receiver Signature" onerror="this.style.display='none'">
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Status: Completed -->
            @if($document->status === 'Completed')
            <div class="timeline-item">
                <div class="timeline-dot dot-received">✓</div>
                <div class="timeline-content">
                    <div class="timeline-event-title">Delivery Completed</div>
                    <div class="timeline-event-details">
                        All routing steps completed
                    </div>
                    <div class="timeline-timestamp">
                        {{ $document->updated_at->format('M j, Y \a\t H:i:s') }}
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Real-time tracking with polling
    const documentId = {{ $document->id }};
    let lastUpdateTime = new Date();

    function updateTrackingStatus() {
        fetch(`/api/documents/${documentId}/status`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.updated) {
                // Reload the page to show updated status
                location.reload();
            }
        })
        .catch(error => console.log('Polling update check...'));
    }

    // Poll every 5 seconds for updates
    setInterval(updateTrackingStatus, 5000);

    // Initial check when page loads
    document.addEventListener('DOMContentLoaded', function() {
        updateTrackingStatus();
    });
</script>
@endsection