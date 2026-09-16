@extends('layouts.app')

@section('title', 'Notification History')

@section('head')
<style>
    .notif-container {
        max-width: 860px;
        margin: 0 auto;
        padding: 32px 24px;
    }

    .notif-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }

    .notif-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .notif-header .actions {
        display: flex;
        gap: 8px;
    }

    .notif-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .notif-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 10px;
        padding: 16px 20px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        transition: all 0.15s ease;
        position: relative;
    }

    .notif-card:hover {
        border-color: var(--accent-cyan);
        box-shadow: 0 2px 12px rgba(59, 130, 246, 0.08);
    }

    .notif-card.unread {
        border-left: 3px solid var(--accent-cyan);
        cursor: pointer;
    }

    /* Clickable card link: covers icon + content, but not actions */
    .notif-card-link {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        flex: 1;
        min-width: 0;
        text-decoration: none;
        color: inherit;
    }
    .notif-card-link:hover { color: inherit; text-decoration: none; }

    .notif-icon {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(59, 130, 246, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .notif-icon.sla_breached  { background: rgba(239,68,68,0.1); }
    .notif-icon.sla_near_due  { background: rgba(245,158,11,0.1); }
    .notif-icon.document_received { background: rgba(16,185,129,0.1); }
    .notif-icon.document_routed   { background: rgba(59,130,246,0.1); }

    .notif-content {
        flex: 1;
        min-width: 0;
    }

    .notif-message {
        font-size: 0.9rem;
        color: var(--text-main);
        font-weight: 500;
        line-height: 1.4;
        margin-bottom: 6px;
    }

    .notif-meta {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .notif-time {
        font-size: 0.75rem;
        color: var(--text-dim);
    }

    .notif-type-badge {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        padding: 2px 8px;
        border-radius: 4px;
        background: rgba(59,130,246,0.12);
        color: #3b82f6;
    }

    .notif-type-badge.sla_breached  { background: rgba(239,68,68,0.12); color: #ef4444; }
    .notif-type-badge.sla_near_due  { background: rgba(245,158,11,0.12); color: #f59e0b; }
    .notif-type-badge.document_received { background: rgba(16,185,129,0.12); color: #10b981; }

    .notif-unread-dot {
        flex-shrink: 0;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--accent-cyan);
        margin-top: 6px;
    }

    .notif-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }

    .notif-btn {
        border: 1px solid var(--panel-border);
        background: transparent;
        color: var(--text-dim);
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
    }

    .notif-btn:hover {
        background: var(--panel-border);
        color: var(--text-main);
    }

    .notif-btn.btn-view {
        color: #3b82f6;
        border-color: rgba(59,130,246,0.3);
    }

    .notif-btn.btn-view:hover {
        background: rgba(59,130,246,0.08);
    }

    .notif-btn.btn-delete {
        color: #ef4444;
        border-color: rgba(239,68,68,0.3);
    }

    .notif-btn.btn-delete:hover {
        background: rgba(239,68,68,0.08);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-dim);
    }

    .empty-state .icon {
        font-size: 3.5rem;
        margin-bottom: 12px;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--text-main);
        opacity: 0.7;
    }

    .pagination-wrapper {
        margin-top: 24px;
        display: flex;
        justify-content: center;
    }

    .btn-mark-all {
        background: var(--accent-cyan);
        color: #ffffff;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-mark-all:hover { opacity: 0.88; }
</style>
@endsection

@section('content')
<div class="notif-container">
    <!-- Header -->
    <div class="notif-header">
        <div>
            <h1>Notification History</h1>
            <p style="font-size:0.8rem; color: var(--text-dim); margin-top: 2px;">
                All notifications — {{ $notifications->total() }} total
            </p>
        </div>
        <div class="actions">
            @if($notifications->total() > 0)
            <form method="POST" action="{{ route('notifications.markAllRead') }}">
                @csrf
                <button type="submit" class="btn-mark-all"><i class="bi bi-check-lg me-1"></i>Mark All as Read</button>
            </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 mb-4" style="border-radius:10px; background: rgba(16,185,129,0.12); color: #10b981;">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filters Toolbar -->
    <div class="card p-3 mb-4 text-start" style="background: var(--panel); border: 1px solid var(--panel-border); border-radius: 10px;">
        <form method="GET" action="{{ route('notifications.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 11px; color: var(--text-dim) !important;">Search Notifications</label>
                <input type="text" name="search" class="form-control" placeholder="Search notification content..." value="{{ request('search') }}" style="height:38px; font-size:13px; background:var(--bg); border:1px solid var(--panel-border); color:var(--text-main);">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 11px; color: var(--text-dim) !important;">Type</label>
                <select name="type" class="form-select" style="height:38px; font-size:13px; background:var(--bg); border:1px solid var(--panel-border); color:var(--text-main);">
                    <option value="">All Types</option>
                    <option value="document_received" {{ request('type') === 'document_received' ? 'selected' : '' }}>Received</option>
                    <option value="document_routed" {{ request('type') === 'document_routed' ? 'selected' : '' }}>Routed</option>
                    <option value="sla_near_due" {{ request('type') === 'sla_near_due' ? 'selected' : '' }}>Near Due</option>
                    <option value="sla_breached" {{ request('type') === 'sla_breached' ? 'selected' : '' }}>Breached</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 11px; color: var(--text-dim) !important;">Status</label>
                <select name="status" class="form-select" style="height:38px; font-size:13px; background:var(--bg); border:1px solid var(--panel-border); color:var(--text-main);">
                    <option value="">All</option>
                    <option value="unread" {{ request('status') === 'unread' ? 'selected' : '' }}>Unread</option>
                    <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>Read</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100 fw-bold" style="height:38px; font-size:13px; border-radius:6px;"><i class="bi bi-search"></i></button>
                <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary w-100" style="height:38px; font-size:13px; display:inline-flex; align-items:center; justify-content:center; border-radius:6px;"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>

    <!-- Notification List -->
    <div class="notif-list">
        @forelse($notifications as $notification)
            @php
                $data    = $notification->data;
                $type    = $data['type'] ?? 'notification';
                $message = $data['message'] ?? 'New notification.';
                $docId   = $data['document_id'] ?? null;
                $isUnread = is_null($notification->read_at);

                $iconClass = match($type) {
                    'sla_breached'       => 'bi-exclamation-octagon-fill text-danger',
                    'sla_near_due'       => 'bi-clock-fill text-warning',
                    'document_received'  => 'bi-file-earmark-arrow-down-fill text-success',
                    'document_routed'    => 'bi-send-fill text-primary',
                    default              => 'bi-bell-fill text-secondary',
                };
            @endphp

            <div class="notif-card {{ $isUnread ? 'unread' : '' }}">
                <!-- Unread dot indicator -->
                @if($isUnread)
                    <div class="notif-unread-dot"></div>
                @endif

                {{--
                    Clicking the icon + content area marks ONLY this notification as read
                    and navigates to the associated document (via markSingleRead GET).
                    The action buttons below are independent and do not conflict.
                --}}
                <a href="{{ route('notifications.markRead', $notification->id) }}"
                   class="notif-card-link"
                   title="{{ $isUnread ? 'Click to mark as read and view' : 'View' }}">

                    <!-- Icon -->
                    <div class="notif-icon {{ $type }}">
                        <i class="bi {{ $iconClass }}" style="font-size: 1.15rem;"></i>
                    </div>

                    <!-- Content -->
                    <div class="notif-content">
                        <div class="notif-message">{{ $message }}</div>
                        <div class="notif-meta">
                            <span class="notif-time">{{ $notification->created_at->diffForHumans() }} &mdash; {{ $notification->created_at->format('M d, Y h:i A') }}</span>
                            <span class="notif-type-badge {{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</span>
                            @if(!$isUnread)
                                <span style="font-size:0.7rem; color: var(--text-dim);">✓ Read</span>
                            @else
                                <span style="font-size:0.7rem; color: var(--accent-cyan); font-weight:600;">● Unread</span>
                            @endif
                        </div>
                    </div>
                </a>

                <!-- Actions: independent — do not trigger the card link -->
                <div class="notif-actions">
                    @if($isUnread)
                        <form method="POST" action="{{ route('notifications.markRead', $notification->id) }}" style="display:inline;"
                              title="Mark as read without navigating">
                            @csrf
                            <button type="submit" class="notif-btn">Mark Read</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('notifications.delete', $notification->id) }}" style="display:inline;"
                          onsubmit="return confirm('Delete this notification?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="notif-btn btn-delete">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="icon">
                    <i class="bi bi-bell-slash text-muted" style="font-size: 3rem;"></i>
                </div>
                <h3>No notifications yet</h3>
                <p style="font-size:0.82rem;">You'll receive notifications here as documents are routed, received, or approach their SLA deadlines.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($notifications->hasPages())
        <div class="pagination-wrapper">
            {{ $notifications->links('pagination::bootstrap-4') }}
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Immediately refresh the notification badge when this page loads so the
    // count stays in sync without waiting for the 15-second polling interval.
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof loadNotifications === 'function') {
            loadNotifications();
        }
    });
</script>
@endsection
