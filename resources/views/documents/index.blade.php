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
        background: var(--panel, #FFFFFF);
        border: 1px solid var(--panel-border, #E2E8F0);
        border-radius: var(--radius-lg, 12px);
        padding: 14px 18px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        position: relative;
    }
    .toolbar-left-group {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        flex: 1 1 540px;
        min-width: 0;
    }
    .toolbar-search-container {
        position: relative;
        flex: 1 1 260px;
        min-width: 220px;
    }
    .toolbar-search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94A3B8;
        font-size: 14px;
        pointer-events: none;
        z-index: 2;
    }
    .toolbar-search-input {
        width: 100%;
        height: 38px;
        border-radius: 8px;
        padding-left: 38px !important;
        padding-right: 12px !important;
        font-size: 13px;
        border: 1px solid #CBD5E1;
        text-overflow: ellipsis;
        white-space: nowrap;
        overflow: hidden;
        color: #0F172A;
    }
    .toolbar-search-input:focus {
        border-color: var(--accent-cyan, #1D4ED8);
        box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        outline: none;
    }
    .toolbar-select-container {
        flex: 0 0 auto;
        min-width: 140px;
        max-width: 165px;
    }
    .toolbar-filter-select {
        height: 38px;
        border-radius: 8px;
        font-size: 13px;
        border: 1px solid #CBD5E1;
        color: #0F172A;
        padding-left: 12px;
        padding-right: 32px !important;
        background-position: right 10px center;
        width: 100%;
        cursor: pointer;
    }
    .toolbar-filter-select:focus {
        border-color: var(--accent-cyan, #1D4ED8);
        box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        outline: none;
    }
    .toolbar-right-group {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .toolbar-btn {
        height: 38px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0 14px;
        border: 1px solid #CBD5E1;
        color: #334155;
        background: #FFFFFF;
        white-space: nowrap;
    }
    .toolbar-btn:hover {
        background: #F8FAFC;
        border-color: #94A3B8;
        color: #0F172A;
    }

    /* Enterprise Table Container */
    .enterprise-table-container {
        background: var(--panel, #FFFFFF);
        border: 1px solid var(--panel-border, #E2E8F0);
        border-radius: var(--radius-lg, 12px);
        overflow-x: auto;
        overflow-y: visible;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        margin-top: 16px;
        width: 100%;
        max-width: 100%;
        position: relative;
    }
    .enterprise-table {
        width: 100%;
        max-width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    .enterprise-table th {
        background: #F8FAFC;
        padding: 11px 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748B;
        border-bottom: 1px solid var(--panel-border, #E2E8F0);
        position: sticky;
        top: 0;
        z-index: 10;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }
    .enterprise-table td {
        padding: 12px 8px;
        font-size: 13px;
        border-bottom: 1px solid var(--panel-border, #E2E8F0);
        color: var(--text-main, #0F172A);
        vertical-align: middle;
    }
    .enterprise-table tbody tr.document-row:hover {
        background: #F8FAFC;
    }
    .enterprise-table th.col-actions {
        position: sticky;
        right: 0;
        top: 0;
        background: #F8FAFC !important;
        z-index: 20;
        width: 72px;
        min-width: 72px;
        max-width: 72px;
        text-align: center;
        padding-left: 8px !important;
        padding-right: 8px !important;
        box-shadow: -3px 0 6px -2px rgba(15, 23, 42, 0.06);
        border-left: 1px solid var(--panel-border, #E2E8F0);
    }
    .enterprise-table td.col-actions {
        position: sticky;
        right: 0;
        background: #FFFFFF !important;
        z-index: 10;
        width: 72px;
        min-width: 72px;
        max-width: 72px;
        text-align: center;
        padding-left: 8px !important;
        padding-right: 8px !important;
        box-shadow: -3px 0 6px -2px rgba(15, 23, 42, 0.06);
        border-left: 1px solid var(--panel-border, #E2E8F0);
    }
    .enterprise-table tbody tr.document-row:hover td.col-actions {
        background: #F8FAFC !important;
    }

    /* Column Typography and Truncation Controls */
    .col-select {
        width: 38px;
        min-width: 38px;
        max-width: 38px;
        text-align: center;
        padding-left: 12px !important;
    }
    .col-doc-name {
        max-width: 250px;
        min-width: 165px;
    }
    .doc-title-link {
        font-size: 13px;
        font-weight: 700;
        color: #0F172A;
        text-decoration: none;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.35;
        max-height: 2.7em;
        word-break: break-word;
    }
    .doc-title-link:hover {
        color: var(--accent-cyan, #1D4ED8);
    }
    .doc-uploader-sub {
        font-size: 11px;
        color: #64748B;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
    }

    .col-tracking {
        white-space: nowrap;
        max-width: 145px;
    }
    .col-tracking code {
        font-size: 11px;
        color: #475569;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        letter-spacing: -0.01em;
        white-space: nowrap;
        background: #F1F5F9;
        padding: 3px 6px;
        border-radius: 4px;
        border: 1px solid #E2E8F0;
        display: inline-block;
        max-width: 100%;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .col-office-cell {
        max-width: 145px;
        min-width: 100px;
        font-size: 12px;
        line-height: 1.35;
    }
    .col-office-cell .office-name-text {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        max-height: 2.7em;
        font-weight: 500;
        color: #1E293B;
        word-break: break-word;
    }

    .col-receiver-cell {
        max-width: 135px;
        min-width: 95px;
        font-size: 12px;
        line-height: 1.35;
    }
    .col-receiver-cell .receiver-name-text {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        max-height: 2.7em;
        font-weight: 600;
        color: #1E293B;
        word-break: break-word;
    }
    .col-receiver-cell .receiver-dept-text {
        font-size: 11px;
        color: #64748B;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 140px;
    }

    .col-status-cell {
        white-space: nowrap;
        max-width: 110px;
    }
    .col-sla, .col-sla-cell {
        white-space: nowrap;
        max-width: 125px;
    }

    /* Column visibility toggle helper - preserves responsive table display */
    .col-hidden {
        display: none !important;
    }
    .sortable-th {
        cursor: pointer;
        user-select: none;
        transition: color 0.15s ease;
    }
    .sortable-th:hover {
        color: var(--accent-cyan, #1D4ED8) !important;
    }

    /* Mobile Field Label */
    .mobile-field-label {
        display: none;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748B;
        margin-bottom: 2px;
    }

    /* Expandable Row Styling */
    .btn-row-toggle {
        width: 22px;
        height: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        color: #64748B;
        text-decoration: none !important;
        flex-shrink: 0;
        border: none;
        background: transparent;
        padding: 0;
    }
    .btn-row-toggle:hover {
        background: #E2E8F0;
        color: #0F172A;
    }
    .expanded-details-cell {
        background: #F8FAFC !important;
        padding: 16px 20px !important;
        border-bottom: 2px solid #E2E8F0 !important;
    }
    .expanded-details-wrapper {
        border-left: 3px solid var(--accent-cyan, #1D4ED8);
        padding-left: 16px;
    }
    .detail-label {
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748B;
        margin-bottom: 3px;
    }
    .detail-val {
        font-size: 13px;
        color: #0F172A;
        font-weight: 500;
    }
    .detail-sub {
        font-size: 11px;
        color: #94A3B8;
        margin-top: 2px;
    }

    .btn-action-trigger {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: background 0.15s ease;
        margin: 0 auto;
    }
    .btn-action-trigger:hover, .btn-action-trigger:focus {
        background: rgba(100, 116, 139, 0.12);
    }

    @media (max-width: 1199px) and (min-width: 769px) {
        .enterprise-toolbar {
            gap: 12px;
        }
        .toolbar-left-group {
            flex: 1 1 100%;
        }
        .toolbar-right-group {
            flex: 1 1 100%;
            justify-content: flex-start;
        }
    }

    /* Mobile Responsive Card Transformation */
    @media (max-width: 768px) {
        .enterprise-toolbar {
            flex-direction: column;
            align-items: stretch;
            padding: 14px;
            gap: 10px;
        }
        .toolbar-left-group {
            flex-direction: column;
            width: 100%;
            gap: 10px;
        }
        .toolbar-search-container,
        .toolbar-select-container {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            flex: 1 1 100%;
        }
        .toolbar-right-group {
            width: 100%;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        .toolbar-btn {
            flex: 1;
            justify-content: center;
        }

        .enterprise-table-container {
            background: transparent;
            border: none;
            box-shadow: none;
            overflow: visible;
            margin-top: 14px;
        }
        .enterprise-table {
            display: block;
            width: 100%;
        }
        .enterprise-table thead {
            display: none;
        }
        .enterprise-table tbody {
            display: flex;
            flex-direction: column;
            gap: 14px;
            width: 100%;
        }
        .enterprise-table tr.document-row {
            display: block;
            background: #ffffff;
            border: 1px solid var(--panel-border, #E2E8F0);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
            position: relative;
        }
        .enterprise-table tr.document-row > td {
            display: block;
            padding: 0;
            border: none;
            background: transparent !important;
            box-shadow: none !important;
        }
        .enterprise-table tr.document-row td.col-select {
            position: absolute;
            top: 16px;
            left: 14px;
            width: auto !important;
            min-width: 0 !important;
            z-index: 2;
        }
        .enterprise-table tr.document-row td.col-doc-name {
            max-width: 100%;
            padding-left: 28px;
            padding-right: 36px;
            margin-bottom: 8px;
        }
        .enterprise-table tr.document-row td.col-actions {
            position: absolute;
            top: 12px;
            right: 12px;
            width: auto !important;
            min-width: 0 !important;
            max-width: none !important;
            background: transparent !important;
            box-shadow: none !important;
            border-left: none !important;
            padding: 0 !important;
            z-index: 5;
        }
        .enterprise-table tr.document-row td.col-tracking {
            margin-bottom: 10px;
            padding-left: 28px;
        }
        .enterprise-table tr.document-row td.col-current {
            margin-bottom: 10px;
            padding-left: 28px;
            max-width: 100%;
        }
        .enterprise-table tr.document-row td.col-receiver {
            margin-bottom: 12px;
            padding-left: 28px;
            max-width: 100%;
        }
        .enterprise-table tr.document-row td.col-status-cell {
            display: inline-block;
            padding-left: 28px;
            margin-right: 14px;
            margin-bottom: 12px;
        }
        .enterprise-table tr.document-row td.col-sla-cell {
            display: inline-block;
            margin-bottom: 12px;
        }
        .mobile-field-label {
            display: block;
        }

        .enterprise-table tr.document-expanded-row {
            display: block;
            background: #F8FAFC;
            border: 1px solid var(--panel-border, #E2E8F0);
            border-top: none;
            border-radius: 0 0 12px 12px;
            margin-top: -14px;
            margin-bottom: 14px;
            padding: 14px;
        }
        .enterprise-table tr.document-expanded-row.d-none {
            display: none !important;
        }
        .enterprise-table tr.document-expanded-row > td {
            display: block;
            padding: 0 !important;
            border: none !important;
            background: transparent !important;
        }
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
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
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
    <div id="bulkActionFeedback" class="alert d-none mb-3" role="alert"></div>

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
        <div class="toolbar-left-group">
            <div class="toolbar-search-container">
                <i class="bi bi-search toolbar-search-icon"></i>
                <input type="text" id="tableSearch" name="search" value="{{ request('search') }}" class="form-control toolbar-search-input" placeholder="Search by name, tracking number, or receiver...">
            </div>
            <div class="toolbar-select-container">
                <select id="statusFilter" name="status" class="form-select toolbar-filter-select">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>In Transit</option>
                    <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>Received</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="for_approval" {{ request('status') === 'for_approval' ? 'selected' : '' }}>For Approval</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="reverted" {{ request('status') === 'reverted' ? 'selected' : '' }}>Reverted</option>
                    <option value="viewed" {{ request('status') === 'viewed' ? 'selected' : '' }}>Viewed</option>
                    <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
            </div>
            <div class="toolbar-select-container">
                <select id="priorityFilter" name="priority" class="form-select toolbar-filter-select">
                    <option value="">All Priorities</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="normal" {{ request('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                    <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>
        </div>
        <div class="toolbar-right-group">
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle toolbar-btn" type="button" id="filterOptionsBtn" data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false" title="Advanced Filters">
                    <i class="bi bi-funnel"></i> Filters @if(request('office_id') || request('category') || request('is_confidential') || request('sla_condition'))<span class="badge bg-primary ms-1" style="font-size: 8px; vertical-align: middle;">•</span>@endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-3 shadow-sm" aria-labelledby="filterOptionsBtn" style="min-width: 280px; z-index: 1060; font-size: 0.85rem;">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                        <span class="fw-bold text-uppercase" style="font-size: 10.5px; letter-spacing: 0.05em; color: var(--accent-navy);">Filter Documents</span>
                        @if(request('office_id') || request('category') || request('is_confidential') || request('sla_condition') || request('status') || request('priority') || request('search'))
                            <a href="{{ route('documents.index') }}" class="small text-danger text-decoration-none" style="font-size: 11px; font-weight: 600;">Reset All</a>
                        @endif
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1 text-muted" style="font-size: 11px; font-weight: 600;">Current Office</label>
                        <select id="advOfficeFilter" class="form-select form-select-sm">
                            <option value="">All Offices</option>
                            @foreach($offices as $off)
                                <option value="{{ $off->id }}" {{ request('office_id') == $off->id ? 'selected' : '' }}>{{ $off->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(isset($categories) && $categories->isNotEmpty())
                    <div class="mb-2">
                        <label class="form-label mb-1 text-muted" style="font-size: 11px; font-weight: 600;">Category</label>
                        <select id="advCategoryFilter" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="mb-2">
                        <label class="form-label mb-1 text-muted" style="font-size: 11px; font-weight: 600;">SLA Condition</label>
                        <select id="advSlaFilter" class="form-select form-select-sm">
                            <option value="">All SLA States</option>
                            <option value="on_track" {{ request('sla_condition') === 'on_track' ? 'selected' : '' }}>On Track</option>
                            <option value="nearing_sla" {{ request('sla_condition') === 'nearing_sla' ? 'selected' : '' }}>Nearing SLA</option>
                            <option value="overdue" {{ request('sla_condition') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                            <option value="na" {{ request('sla_condition') === 'na' ? 'selected' : '' }}>N/A (No SLA)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1 text-muted" style="font-size: 11px; font-weight: 600;">Security</label>
                        <select id="advConfidentialFilter" class="form-select form-select-sm">
                            <option value="">All Classifications</option>
                            <option value="1" {{ request('is_confidential') === '1' ? 'selected' : '' }}>Confidential Only</option>
                            <option value="0" {{ request('is_confidential') === '0' ? 'selected' : '' }}>Standard Access Only</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm w-100 fw-bold" id="btnApplyAdvFilters">Apply Filters</button>
                </div>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle toolbar-btn" type="button" id="btnBulkAction" data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false">
                    <i class="bi bi-box-arrow-right"></i> <span id="bulkActionText">Bulk Actions</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="btnBulkAction" style="border-radius: 8px; font-size: 0.9rem; z-index: 1060;">
                    <li><button type="button" class="dropdown-item bulk-action-item" data-action="completed"><i class="bi bi-check2-circle text-success me-2"></i> Mark as Completed</button></li>
                    <li><button type="button" class="dropdown-item bulk-action-item" data-action="assign_receiver"><i class="bi bi-person-check text-primary me-2"></i> Assign Receiver</button></li>
                    <li><button type="button" class="dropdown-item bulk-action-item" data-action="mark_viewed"><i class="bi bi-eye text-secondary me-2"></i> Mark as Viewed</button></li>
                    <li><button type="button" class="dropdown-item bulk-action-item" data-action="archive"><i class="bi bi-archive text-warning me-2"></i> Bulk Archive</button></li>
                    <li><button type="button" class="dropdown-item bulk-action-item" data-action="export"><i class="bi bi-file-earmark-spreadsheet text-info me-2"></i> Bulk Export (CSV)</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" class="dropdown-item bulk-action-item text-danger" data-action="delete"><i class="bi bi-trash text-danger me-2"></i> Bulk Delete</button></li>
                </ul>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle toolbar-btn" type="button" id="columnToggleBtn" data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false">
                    <i class="bi bi-eye"></i> Columns
                </button>
                <ul class="dropdown-menu dropdown-menu-end p-3 shadow-sm" aria-labelledby="columnToggleBtn" style="min-width: 220px; z-index: 1060;">
                    <li><div class="px-2 pb-1 text-muted text-uppercase fw-bold" style="font-size: 10px; letter-spacing: 0.05em;">Primary Columns</div></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-tracking" checked> Tracking Number</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-current" checked> Current Office</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-receiver" checked> Receiver</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-sla" checked> SLA Status</label></li>
                    <li><hr class="dropdown-divider my-2"></li>
                    <li><div class="px-2 pb-1 text-muted text-uppercase fw-bold" style="font-size: 10px; letter-spacing: 0.05em;">Optional Columns</div></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-origin"> Origin Office</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-destination"> Final Destination</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-priority"> Priority</label></li>
                    <li><label class="dropdown-item"><input type="checkbox" class="col-toggle-chk me-2" data-col="col-updated"> Last Updated</label></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Enterprise Document Table -->
    <div class="enterprise-table-container">
        <table class="enterprise-table" id="documentsTable">
            <thead>
                <tr>
                    <th class="col-select"><input type="checkbox" id="selectAllDocs" aria-label="Select all documents"></th>
                    <th class="col-doc-name sortable-th" data-sort="title" title="Sort by Title">Document <i class="bi bi-arrow-down-up opacity-50 ms-1" style="font-size: 10px;"></i></th>
                    <th class="col-tracking sortable-th" data-sort="tracking_number" title="Sort by Tracking Number">Tracking No. <i class="bi bi-arrow-down-up opacity-50 ms-1" style="font-size: 10px;"></i></th>
                    <th class="col-origin col-hidden">Origin</th>
                    <th class="col-current sortable-th" data-sort="office" title="Sort by Current Office">Current Office <i class="bi bi-arrow-down-up opacity-50 ms-1" style="font-size: 10px;"></i></th>
                    <th class="col-destination col-hidden">Final Destination</th>
                    <th class="col-receiver">Receiver</th>
                    <th class="col-status-cell sortable-th" data-sort="status" title="Sort by Status">Status <i class="bi bi-arrow-down-up opacity-50 ms-1" style="font-size: 10px;"></i></th>
                    <th class="col-priority col-priority-cell col-hidden sortable-th" data-sort="priority" title="Sort by Priority">Priority <i class="bi bi-arrow-down-up opacity-50 ms-1" style="font-size: 10px;"></i></th>
                    <th class="col-updated col-updated-cell col-hidden sortable-th" data-sort="updated_at" title="Sort by Last Updated">Last Updated <i class="bi bi-arrow-down-up opacity-50 ms-1" style="font-size: 10px;"></i></th>
                    <th class="col-sla col-sla-cell sortable-th" data-sort="due_date" title="Sort by SLA">SLA Remaining <i class="bi bi-arrow-down-up opacity-50 ms-1" style="font-size: 10px;"></i></th>
                    <th class="col-actions">Actions</th>
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
                            'received' => 'bg-primary text-white',
                            'approved', 'accepted', 'endorsed' => 'bg-success text-white',
                            'processing', 'under review', 'under_review', 'on process' => 'bg-info text-white',
                            'for approval', 'for_approval' => 'bg-warning text-dark',
                            'reverted', 'returned', 'rejected' => 'bg-danger text-white',
                            default => 'bg-secondary text-white'
                        };

                        // Strict chronological validation: created <= received <= processed <= completed
                        $createdTs   = $doc->created_at ? $doc->created_at->timestamp : null;
                        $receivedTs  = $doc->received_at ? $doc->received_at->timestamp : null;
                        $processedTs = $doc->processed_at ? $doc->processed_at->timestamp : null;
                        $completedTs = $doc->completed_at ? $doc->completed_at->timestamp : null;

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

                        $isCompleted = in_array($statusKey, ['completed', 'archived']);

                        if (!$doc->due_date || !$timestampsValid) {
                            $slaText = 'N/A';
                            $slaClass = 'sla-na';
                        } elseif ($isCompleted) {
                            if ($completedTs && $doc->due_date && $completedTs > $doc->due_date->timestamp) {
                                $slaText = 'Overdue';
                                $slaClass = 'sla-overdue';
                            } else {
                                $slaText = 'On Track';
                                $slaClass = 'sla-on-track';
                            }
                        } else {
                            if ($doc->due_date->isPast()) {
                                $slaText = 'Overdue';
                                $slaClass = 'sla-overdue';
                            } elseif (now()->diffInHours($doc->due_date, false) <= 48) {
                                $diffH = (int) now()->diffInHours($doc->due_date, false);
                                $diffM = abs((int) (now()->diffInMinutes($doc->due_date, false) % 60));
                                $slaText = $diffH > 0 ? "{$diffH}h {$diffM}m" : "{$diffM}m";
                                $slaClass = 'sla-nearing';
                            } else {
                                $slaText = 'On Track';
                                $slaClass = 'sla-on-track';
                            }
                        }
                    @endphp
                    <tr class="document-row" data-title="{{ strtolower($doc->title) }}" data-tracking="{{ strtolower($doc->tracking_number ?? '') }}" data-status="{{ str_replace(' ', '_', $statusKey) }}" data-priority="{{ strtolower($doc->priority) }}" data-receiver="{{ strtolower($doc->receiverUser->name ?? '') }}">
                        <td class="col-select"><input type="checkbox" class="doc-select-chk" value="{{ $doc->id }}" aria-label="Select document {{ $doc->title }}"></td>
                        <td class="col-doc-name">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-row-toggle d-none d-md-inline-flex" data-doc-id="{{ $doc->id }}" title="Toggle Document Details" aria-expanded="false">
                                    <i class="bi bi-chevron-right chevron-icon" style="font-size: 11px; transition: transform 0.2s ease;"></i>
                                </button>
                                <div class="file-type-icon {{ $iconClass }} flex-shrink-0">{{ $ext ?: 'FILE' }}</div>
                                <div style="min-width: 0; flex: 1;">
                                    <a href="{{ route('documents.show', $doc->id) }}" class="doc-title-link" title="{{ $doc->title }}">{{ $doc->title }}</a>
                                    <div class="doc-uploader-sub" title="By {{ $doc->uploader->name ?? 'System' }}">By: {{ $doc->uploader->name ?? 'System' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="col-tracking">
                            <span class="mobile-field-label">Tracking No.</span>
                            <code>{{ $doc->tracking_number ?: 'N/A' }}</code>
                        </td>
                        <td class="col-origin col-office-cell col-hidden">
                            <span class="mobile-field-label">Origin</span>
                            <div class="office-name-text" title="{{ $doc->originOffice->name ?? 'N/A' }}">{{ $doc->originOffice->name ?? 'N/A' }}</div>
                        </td>
                        <td class="col-current col-office-cell">
                            <span class="mobile-field-label">Current Office</span>
                            <div class="office-name-text fw-bold text-dark" title="{{ $doc->currentOffice->name ?? 'In Transit' }}">{{ $doc->currentOffice->name ?? 'In Transit' }}</div>
                        </td>
                        <td class="col-destination col-office-cell col-hidden">
                            <span class="mobile-field-label">Final Destination</span>
                            <div class="office-name-text" title="{{ $doc->destinationOffice->name ?? 'N/A' }}">{{ $doc->destinationOffice->name ?? 'N/A' }}</div>
                        </td>
                        <td class="col-receiver col-receiver-cell">
                            <span class="mobile-field-label">Receiver</span>
                            @if($doc->receiverUser)
                                <div class="receiver-name-text" title="{{ $doc->receiverUser->name }}">{{ $doc->receiverUser->name }}</div>
                                <div class="receiver-dept-text" title="{{ $doc->receiverUser->department->name ?? 'No Dept' }}">{{ $doc->receiverUser->department->name ?? 'No Dept' }}</div>
                            @else
                                <span class="text-muted small">Unassigned</span>
                            @endif
                        </td>
                        <td class="col-status-cell">
                            <span class="mobile-field-label">Status</span>
                            <span class="badge {{ $badgeColor }} text-uppercase" style="font-size: 10px; font-weight: 700; letter-spacing: 0.05em; padding: 5px 8px;">{{ $doc->status }}</span>
                        </td>
                        <td class="col-priority col-priority-cell col-hidden">
                            <span class="mobile-field-label">Priority</span>
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
                        <td class="col-updated col-updated-cell col-hidden" style="font-size: 12px; color: #64748B;">
                            <span class="mobile-field-label">Last Updated</span>
                            {{ $doc->updated_at->diffForHumans() }}
                        </td>
                        <td class="col-sla col-sla-cell">
                            <span class="mobile-field-label">SLA Remaining</span>
                            <span class="sla-badge {{ $slaClass }}">{{ $slaText }}</span>
                        </td>
                        <td class="col-actions text-center">
                            <div class="dropdown">
                                <button class="btn btn-link btn-sm text-secondary p-0 btn-action-trigger" type="button" data-bs-toggle="dropdown" data-bs-strategy="fixed" data-bs-boundary="viewport" aria-expanded="false" title="Actions">
                                    <i class="bi bi-three-dots-vertical" style="font-size: 18px;"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 205px; z-index: 1070; font-size: 0.88rem;">
                                    <li><a class="dropdown-item" href="{{ route('documents.show', $doc->id) }}"><i class="bi bi-file-text me-2 text-primary"></i> View Details</a></li>
                                    <li><a class="dropdown-item" href="{{ route('documents.passport', $doc->id) }}"><i class="bi bi-journal-bookmark me-2 text-primary"></i> Document Passport</a></li>
                                    @if($doc->file_path)
                                        <li><a class="dropdown-item" href="{{ route('documents.download', $doc->id) }}" target="_blank"><i class="bi bi-download me-2 text-secondary"></i> Download File</a></li>
                                    @endif

                                    @php
                                        $isTerminalStatus = in_array(strtolower($doc->status), ['completed', 'archived', 'cancelled', 'rejected']);
                                    @endphp

                                    @if(!$isTerminalStatus)
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li><button class="dropdown-item text-primary" type="button" onclick="setDocId({{ $doc->id }})" data-bs-toggle="modal" data-bs-target="#routeModal"><i class="bi bi-send me-2"></i> Route / Forward</button></li>
                                        @if(in_array(strtolower($doc->status), ['for approval', 'for_approval', 'pending approval']))
                                            <li><a class="dropdown-item text-warning" href="{{ route('documents.show', $doc->id) }}#approval"><i class="bi bi-check2-circle me-2"></i> Review & Approve</a></li>
                                        @elseif(in_array(strtolower($doc->status), ['pending', 'in transit', 'in_transit']))
                                            <li><a class="dropdown-item text-success" href="{{ route('documents.show', $doc->id) }}"><i class="bi bi-box-arrow-in-down me-2"></i> Receive Document</a></li>
                                        @endif
                                    @endif

                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><a class="dropdown-item" href="{{ route('documents.qr-label', $doc->id) }}?autoprint=1" target="_blank"><i class="bi bi-printer me-2 text-secondary"></i> Print QR Label</a></li>
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
                                            <li><button class="dropdown-item" type="button" onclick="showQR('{{ $qrUrl }}', '{{ route('documents.qr-label', $doc->id) }}?autoprint=1')"><i class="bi bi-qr-code me-2 text-secondary"></i> View QR Code</button></li>
                                        @endif
                                    @endif

                                    @if(isset($isAdmin) && $isAdmin)
                                        <li><hr class="dropdown-divider my-1"></li>
                                        @if(strtolower($doc->status) !== 'archived')
                                            <li><button type="button" class="dropdown-item text-warning single-bulk-btn" data-id="{{ $doc->id }}" data-action="archive"><i class="bi bi-archive me-2"></i> Archive</button></li>
                                        @endif
                                        <li><button type="button" class="dropdown-item text-danger single-bulk-btn" data-id="{{ $doc->id }}" data-action="delete"><i class="bi bi-trash me-2"></i> Delete</button></li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                    {{-- Mobile Card Action Bar (Visible only on mobile <= 768px) --}}
                    <tr class="d-md-none border-0" style="background: transparent;">
                        <td colspan="12" class="p-0 border-0" style="background: transparent;">
                            <div class="d-flex align-items-center justify-content-between gap-2 px-3 pb-3 pt-0" style="background: #ffffff; border: 1px solid var(--panel-border, #E2E8F0); border-top: none; border-radius: 0 0 12px 12px; margin-top: -14px; margin-bottom: 8px;">
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-row-toggle flex-grow-1" data-doc-id="{{ $doc->id }}" aria-expanded="false" style="font-size: 0.78rem; height: 32px; border-radius: 6px;">
                                    <i class="bi bi-chevron-down chevron-icon me-1"></i> <span class="toggle-text">Details</span>
                                </button>
                                <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-sm btn-light border flex-grow-1 text-center" style="font-size: 0.78rem; height: 32px; font-weight: 600; border-radius: 6px;">
                                    <i class="bi bi-file-text me-1 text-primary"></i> View
                                </a>
                                <a href="{{ route('documents.passport', $doc->id) }}" class="btn btn-sm btn-light border flex-grow-1 text-center" style="font-size: 0.78rem; height: 32px; font-weight: 600; border-radius: 6px;">
                                    <i class="bi bi-journal-bookmark me-1 text-primary"></i> Track
                                </a>
                            </div>
                        </td>
                    </tr>
                    {{-- Expandable Document Details Row --}}
                    <tr class="document-expanded-row d-none" id="expanded-row-{{ $doc->id }}">
                        <td colspan="12" class="expanded-details-cell">
                            <div class="expanded-details-wrapper">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-info-circle-fill text-primary"></i>
                                        <span class="fw-bold text-dark" style="font-size: 0.82rem; letter-spacing: 0.04em;">DOCUMENT PASSPORT & WORKFLOW METADATA</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" style="font-size: 0.75rem; padding: 4px 10px; font-weight: 600; border-radius: 6px;">
                                            <i class="bi bi-file-text"></i> Open Document
                                        </a>
                                        <a href="{{ route('documents.passport', $doc->id) }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="font-size: 0.75rem; padding: 4px 10px; font-weight: 600; border-radius: 6px;">
                                            <i class="bi bi-journal-bookmark text-primary"></i> Document Passport
                                        </a>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <div class="detail-label">Origin Office</div>
                                        <div class="detail-val">{{ $doc->originOffice->name ?? 'N/A' }}</div>
                                        <div class="detail-sub">Uploaded by {{ $doc->uploader->name ?? 'System' }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="detail-label">Current Location & Receiver</div>
                                        <div class="detail-val text-primary fw-bold">{{ $doc->currentOffice->name ?? 'In Transit' }}</div>
                                        <div class="detail-sub">Receiver: {{ $doc->receiverUser->name ?? 'Unassigned' }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="detail-label">Final Destination</div>
                                        <div class="detail-val">{{ $doc->destinationOffice->name ?? 'N/A' }}</div>
                                        <div class="detail-sub">Target SLA: {{ $doc->sla ?: 'Standard' }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="detail-label">SLA Condition</div>
                                        <div class="detail-val"><span class="sla-badge {{ $slaClass }}">{{ $slaText }}</span></div>
                                        <div class="detail-sub">{{ $doc->due_date ? 'Due: ' . $doc->due_date->format('M d, Y • h:i A') : 'No Due Date Set' }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="detail-label">Category / Classification</div>
                                        <div class="detail-val">{{ $doc->category ?: 'General Administrative' }}</div>
                                        <div class="detail-sub">{{ $doc->type ? strtoupper($doc->type) : 'Document' }} &bull; {{ $doc->file_size ? number_format($doc->file_size / 1024, 1) . ' KB' : 'Standard' }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="detail-label">Security & Access</div>
                                        <div class="detail-val">
                                            @if($doc->is_confidential)
                                                <span class="badge bg-danger text-white"><i class="bi bi-lock-fill me-1"></i>Confidential / OTP Protected</span>
                                            @else
                                                <span class="badge bg-light text-dark border"><i class="bi bi-unlock-fill me-1 text-success"></i>Standard Access</span>
                                            @endif
                                        </div>
                                        <div class="detail-sub">Tracking: {{ $doc->tracking_number }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="detail-label">QR Traceability State</div>
                                        <div class="detail-val">
                                            <span class="badge {{ $doc->qr_status === 'Verified' ? 'bg-success' : 'bg-primary' }} text-white">
                                                {{ $doc->qr_status ?: 'Active Trace' }}
                                            </span>
                                        </div>
                                        <div class="detail-sub">{{ $doc->qr_scanned_at ? 'Scanned: ' . $doc->qr_scanned_at->format('M d, h:i A') : 'Pending Physical Scan' }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="detail-label">Next Action</div>
                                        @php
                                            $nextActionText = match(strtolower($doc->status)) {
                                                'completed' => 'Completed — Closed',
                                                'archived' => 'Archived Record',
                                                'pending' => 'Awaiting Receipt',
                                                'in transit', 'in_transit' => 'In Transit',
                                                'received' => 'Received — Ready for Processing',
                                                'processing', 'under review', 'on process' => 'Under Review',
                                                'for approval', 'for_approval' => 'Requires Approval',
                                                'approved' => 'Approved',
                                                'reverted', 'returned' => 'Reverted for Revision',
                                                default => 'Processing'
                                            };
                                        @endphp
                                        <div class="detail-val text-dark fw-bold">{{ $nextActionText }}</div>
                                        <div class="detail-sub">Updated: {{ $doc->updated_at->diffForHumans() }}</div>
                                    </div>
                                    @if($doc->description)
                                        <div class="col-12 mt-2 pt-2 border-top">
                                            <div class="detail-label">Description / Remarks</div>
                                            <div class="detail-val text-secondary" style="font-size: 0.82rem;">{{ $doc->description }}</div>
                                        </div>
                                    @endif
                                </div>
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

    function showQR(src, printUrl = '') {
        document.getElementById('qrImage').src = src;
        const printBtn = document.getElementById('qrModalPrintBtn');
        if (printBtn) {
            if (printUrl) {
                printBtn.href = printUrl;
                printBtn.style.display = 'inline-flex';
            } else {
                printBtn.style.display = 'none';
            }
        }
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

                const docId = row.querySelector('.btn-row-toggle')?.getAttribute('data-doc-id');
                const expRow = docId ? document.getElementById(`expanded-row-${docId}`) : null;

                if (matchesQuery && matchesStatus && matchesPriority) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                    if (expRow) expRow.classList.add('d-none');
                }
            });
        }

        function applyServerFilters() {
            const url = new URL(window.location.href);
            const searchVal = tableSearch ? tableSearch.value.trim() : '';
            const statusVal = statusFilter ? statusFilter.value : '';
            const priorityVal = priorityFilter ? priorityFilter.value : '';

            const advOffice = document.getElementById('advOfficeFilter')?.value || '';
            const advCategory = document.getElementById('advCategoryFilter')?.value || '';
            const advSla = document.getElementById('advSlaFilter')?.value || '';
            const advConfidential = document.getElementById('advConfidentialFilter')?.value || '';

            if (searchVal) url.searchParams.set('search', searchVal);
            else url.searchParams.delete('search');

            if (statusVal) url.searchParams.set('status', statusVal);
            else url.searchParams.delete('status');

            if (priorityVal) url.searchParams.set('priority', priorityVal);
            else url.searchParams.delete('priority');

            if (advOffice) url.searchParams.set('office_id', advOffice);
            else url.searchParams.delete('office_id');

            if (advCategory) url.searchParams.set('category', advCategory);
            else url.searchParams.delete('category');

            if (advSla) url.searchParams.set('sla_condition', advSla);
            else url.searchParams.delete('sla_condition');

            if (advConfidential) url.searchParams.set('is_confidential', advConfidential);
            else url.searchParams.delete('is_confidential');

            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        document.getElementById('btnApplyAdvFilters')?.addEventListener('click', applyServerFilters);

        let searchDebounceTimer = null;
        tableSearch?.addEventListener('input', function() {
            filterTable();
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(function() {
                applyServerFilters();
            }, 750);
        });

        tableSearch?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchDebounceTimer);
                applyServerFilters();
            }
        });

        statusFilter?.addEventListener('change', applyServerFilters);
        priorityFilter?.addEventListener('change', applyServerFilters);

        // Sortable column headers
        document.querySelectorAll('.sortable-th').forEach(th => {
            th.addEventListener('click', function() {
                const sortField = this.getAttribute('data-sort');
                const url = new URL(window.location.href);
                const currentSort = url.searchParams.get('sort_by');
                const currentOrder = url.searchParams.get('order') || 'desc';
                let newOrder = 'desc';
                if (currentSort === sortField) {
                    newOrder = currentOrder === 'desc' ? 'asc' : 'desc';
                } else if (sortField === 'title' || sortField === 'tracking_number' || sortField === 'office') {
                    newOrder = 'asc';
                }
                url.searchParams.set('sort_by', sortField);
                url.searchParams.set('order', newOrder);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });
        });

        // Row Click: Opens Document Details without breaking checkboxes, buttons, or action triggers
        document.querySelectorAll('#documentsTable tbody tr.document-row').forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function(e) {
                if (e.target.closest('input, button, a, .dropdown, .dropdown-menu, .btn-row-toggle, .col-actions, .col-select')) {
                    return;
                }
                const link = this.querySelector('.doc-title-link');
                if (link && link.href) {
                    window.location.href = link.href;
                }
            });
        });

        // Column toggles with persistence and secondary columns hidden by default using .col-hidden class
        const defaultColState = {
            'col-tracking': true,
            'col-current': true,
            'col-receiver': true,
            'col-sla': true,
            'col-origin': false,
            'col-destination': false,
            'col-priority': false,
            'col-updated': false
        };
        const savedCols = Object.assign({}, defaultColState, JSON.parse(localStorage.getItem('doc_table_cols') || '{}'));
        document.querySelectorAll('.col-toggle-chk').forEach(chk => {
            const colClass = chk.getAttribute('data-col');
            if (savedCols[colClass] !== undefined) {
                chk.checked = savedCols[colClass];
            }
            document.querySelectorAll(`.${colClass}`).forEach(el => {
                el.classList.toggle('col-hidden', !chk.checked);
            });
            chk.addEventListener('change', function() {
                const show = this.checked;
                savedCols[colClass] = show;
                localStorage.setItem('doc_table_cols', JSON.stringify(savedCols));
                document.querySelectorAll(`.${colClass}`).forEach(el => {
                    el.classList.toggle('col-hidden', !show);
                });
            });
        });

        // Row Expand/Collapse Handler (works for desktop chevron and mobile details button)
        document.querySelectorAll('.btn-row-toggle').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const docId = this.getAttribute('data-doc-id');
                const targetRow = document.getElementById(`expanded-row-${docId}`);
                if (!targetRow) return;

                const isHidden = targetRow.classList.contains('d-none');
                if (isHidden) {
                    targetRow.classList.remove('d-none');
                } else {
                    targetRow.classList.add('d-none');
                }

                // Synchronize all toggle buttons for this document (desktop chevron & mobile card toggle)
                document.querySelectorAll(`.btn-row-toggle[data-doc-id="${docId}"]`).forEach(b => {
                    b.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                    const chevron = b.querySelector('.chevron-icon');
                    if (chevron) {
                        chevron.style.transform = isHidden ? 'rotate(90deg)' : 'rotate(0deg)';
                    }
                    const textSpan = b.querySelector('.toggle-text');
                    if (textSpan) {
                        textSpan.textContent = isHidden ? 'Hide' : 'Details';
                    }
                });
            });
        });

        // Bulk Selection Checkbox & Actions
        const selectAllDocs = document.getElementById('selectAllDocs');
        const docSelectChks = document.querySelectorAll('.doc-select-chk');
        const btnBulkAction = document.getElementById('btnBulkAction');
        const bulkActionText = document.getElementById('bulkActionText');

        function showBulkFeedback(type, message) {
            const feedbackEl = document.getElementById('bulkActionFeedback');
            if (!feedbackEl) {
                alert(message);
                return;
            }
            feedbackEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
            const alertClass = type === 'error' ? 'alert-danger' : (type === 'success' ? 'alert-success' : 'alert-warning');
            const iconClass = type === 'success' ? 'bi-check-circle-fill' : (type === 'error' ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill');
            feedbackEl.classList.add(alertClass, 'fade', 'show');
            feedbackEl.innerHTML = `
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi ${iconClass}"></i>
                        <span>${message}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            feedbackEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function updateBulkActionState() {
            const visibleBoxes = Array.from(docSelectChks).filter(chk => {
                const row = chk.closest('tr');
                return !row || row.style.display !== 'none';
            });
            const checkedBoxes = Array.from(docSelectChks).filter(chk => chk.checked);
            const count = checkedBoxes.length;

            if (bulkActionText) {
                bulkActionText.textContent = count > 0 ? `Bulk Actions (${count})` : 'Bulk Actions';
            }

            if (selectAllDocs) {
                const visibleChecked = visibleBoxes.filter(chk => chk.checked);
                if (visibleBoxes.length > 0 && visibleChecked.length === visibleBoxes.length) {
                    selectAllDocs.checked = true;
                    selectAllDocs.indeterminate = false;
                } else if (visibleChecked.length > 0) {
                    selectAllDocs.checked = false;
                    selectAllDocs.indeterminate = true;
                } else {
                    selectAllDocs.checked = false;
                    selectAllDocs.indeterminate = false;
                }
            }
        }

        selectAllDocs?.addEventListener('change', function() {
            const isChecked = this.checked;
            docSelectChks.forEach(chk => {
                const row = chk.closest('tr');
                if (!row || row.style.display !== 'none') {
                    chk.checked = isChecked;
                }
            });
            updateBulkActionState();
        });

        docSelectChks.forEach(chk => {
            chk.addEventListener('change', updateBulkActionState);
        });

        function executeBulkAction(action, ids, extraParams = {}) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const payload = Object.assign({
                action: action,
                document_ids: ids
            }, extraParams);

            fetch('{{ route('documents.bulk-action') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showBulkFeedback('success', data.message || 'Bulk action executed successfully.');
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    showBulkFeedback('error', data.message || 'An error occurred while executing bulk action.');
                }
            })
            .catch(err => {
                console.error('Bulk action error:', err);
                showBulkFeedback('error', 'Network error or server error. Please try again.');
            });
        }

        document.querySelectorAll('.bulk-action-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const action = this.getAttribute('data-action');
                const selectedIds = Array.from(docSelectChks).filter(chk => chk.checked).map(chk => chk.value);

                if (action === 'export' && selectedIds.length === 0) {
                    const params = new URLSearchParams(window.location.search);
                    params.set('action', 'export');
                    window.location.href = `{{ route('documents.bulk-action') }}?${params.toString()}`;
                    showBulkFeedback('success', 'Exporting all matching documents...');
                    return;
                }

                if (selectedIds.length === 0) {
                    showBulkFeedback('warning', 'No documents selected. Please select at least one document.');
                    return;
                }

                if (action === 'export') {
                    const params = new URLSearchParams();
                    params.set('action', 'export');
                    selectedIds.forEach(id => params.append('document_ids[]', id));
                    window.location.href = `{{ route('documents.bulk-action') }}?${params.toString()}`;
                    showBulkFeedback('success', `Exporting ${selectedIds.length} document(s)...`);
                    return;
                }

                if (action === 'assign_receiver') {
                    document.getElementById('bulkAssignCount').textContent = selectedIds.length;
                    new bootstrap.Modal(document.getElementById('bulkAssignModal')).show();
                    return;
                }

                let confirmMsg = '';
                if (action === 'delete') {
                    confirmMsg = `Are you sure you want to permanently delete ${selectedIds.length} selected document(s)? This action cannot be undone.`;
                } else if (action === 'archive') {
                    confirmMsg = `Are you sure you want to archive ${selectedIds.length} selected document(s)?`;
                } else if (action === 'completed') {
                    confirmMsg = `Are you sure you want to mark ${selectedIds.length} selected document(s) as completed?`;
                } else if (action === 'mark_viewed') {
                    confirmMsg = `Mark ${selectedIds.length} selected document(s) as viewed?`;
                }

                if (confirmMsg && !confirm(confirmMsg)) {
                    return;
                }

                executeBulkAction(action, selectedIds);
            });
        });

        // Single Bulk Trigger from 3-dot dropdown menu
        document.querySelectorAll('.single-bulk-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const action = this.getAttribute('data-action');
                const docId = this.getAttribute('data-id');
                if (!docId) return;

                let confirmMsg = '';
                if (action === 'delete') {
                    confirmMsg = `Are you sure you want to permanently delete this document? This cannot be undone.`;
                } else if (action === 'archive') {
                    confirmMsg = `Are you sure you want to archive this document?`;
                }

                if (confirmMsg && !confirm(confirmMsg)) {
                    return;
                }

                executeBulkAction(action, [docId]);
            });
        });

        // Bulk Assign Confirm Handler
        document.getElementById('confirmBulkAssignBtn')?.addEventListener('click', function() {
            const receiverId = document.getElementById('bulkReceiverSelect')?.value;
            if (!receiverId) {
                alert('Please select a receiver user to assign.');
                return;
            }
            const selectedIds = Array.from(docSelectChks).filter(chk => chk.checked).map(chk => chk.value);
            if (selectedIds.length === 0) {
                alert('No documents selected.');
                return;
            }
            const modalEl = document.getElementById('bulkAssignModal');
            bootstrap.Modal.getInstance(modalEl)?.hide();
            executeBulkAction('assign_receiver', selectedIds, { receiver_user_id: receiverId });
        });

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

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                formData.set('_token', csrfToken);

                fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
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

<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#FFFFFF; border: 1px solid var(--panel-border); border-radius: 12px; color: var(--text-main);">
            <div class="modal-header border-0 p-4">
                <h5 class="modal-title fw-bold" style="color: var(--accent-navy);"><i class="bi bi-person-check text-primary me-2"></i>Bulk Assign Receiver</h5>
                <button type="button" class="btn custom-close-btn" data-bs-dismiss="modal" aria-label="Close" style="color: var(--text-main);"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <p class="text-secondary small mb-3">Assign all selected documents (<span id="bulkAssignCount" class="fw-bold text-dark">0</span>) to a responsible staff member.</p>
                <div class="mb-3 text-start">
                    <label class="form-label fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.05em; color: var(--accent-navy);">Select Receiver User</label>
                    <select id="bulkReceiverSelect" class="form-select" required>
                        <option value="">-- Choose User --</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->department->name ?? ($u->office->name ?? $u->role) }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top p-3" style="border-color: var(--panel-border);">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm fw-bold px-4" id="confirmBulkAssignBtn">Confirm Assignment</button>
            </div>
        </div>
    </div>
</div>

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
                <img id="qrImage" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="QR Code" class="img-fluid mb-3" style="border: 1px solid var(--panel-border); border-radius: 8px; padding: 12px; background: #FFFFFF; max-width: 220px;">
                <div>
                    <a id="qrModalPrintBtn" href="#" target="_blank" class="btn btn-primary btn-sm px-3" style="font-weight: 600;">
                        <i class="bi bi-printer me-1"></i> Print Full QR Routing Label
                    </a>
                </div>
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