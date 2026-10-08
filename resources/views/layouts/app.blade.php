<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>NAAP Admin - @yield('title')</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @yield('head')

    <style>
        :root {
            --bg: #F8FAFC; /* Slate 50 */
            --bg-primary: #F8FAFC;
            --bg-secondary: #F1F5F9;
            --sidebar-bg: #0F172A; /* Slate 900 */
            --sidebar-width: 240px;
            --naap-blue: #1D4ED8; /* Royal Blue / Primary Accent */
            --accent-cyan: #1D4ED8; /* Royal Blue / Primary */
            --accent-navy: #0F172A; /* Slate 900 */
            --text-main: #0F172A; /* Slate 900 / Dark Navy */
            --text-primary: #0F172A;
            --text-dim: #64748B; /* Slate 500 / Muted Gray */
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --panel: #FFFFFF;
            --card-bg: #FFFFFF;
            --panel-border: #E2E8F0; /* Slate 200 */
            --border-color: #E2E8F0;
            --input-bg: #FFFFFF;
            --success: #059669; /* Emerald 600 - Semantic Success */
            --warning: #D97706; /* Amber 600 - Semantic Warning / Pending */
            --danger: #DC2626; /* Red 600 - Semantic Danger / Overdue */
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --transition-speed: 150ms;
        }


        body { 
            background-color: var(--bg) !important; 
            color: var(--text-main) !important; 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            margin: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Focus Ring Standard */
        *:focus-visible {
            outline: 2px solid var(--accent-cyan) !important;
            outline-offset: 2px;
        }

        h1, h2, h3, h4, h5, h6, strong, p, span, div, a, label, select, input, textarea {
            color: var(--text-main);
        }

        .dashboard-title {
            font-size: 28px !important;
            font-weight: 700 !important;
            letter-spacing: -0.02em;
            color: var(--text-main) !important;
        }

        .page-title {
            font-size: 24px !important;
            font-weight: 700 !important;
            letter-spacing: -0.02em;
            color: var(--text-main) !important;
        }

        .section-title {
            font-size: 18px !important;
            font-weight: 600 !important;
            color: var(--text-main) !important;
        }

        label, .form-label {
            font-size: 13px !important;
            font-weight: 500 !important;
            color: var(--text-dim) !important;
            margin-bottom: 6px !important;
        }

        /* SIDEBAR STYLING - Collapsible & Slim */
        .sidebar { 
            width: var(--sidebar-width); 
            background: var(--sidebar-bg) !important; 
            border-right: 1px solid var(--panel-border) !important;
            display: flex;
            flex-direction: column;
            padding: 10px 12px;
            position: fixed; 
            height: 100vh; 
            z-index: 3000; 
            transition: transform var(--transition-speed) ease-in-out, width var(--transition-speed) ease-in-out;
        }

        .sidebar * {
            color: #FFFFFF !important;
        }

        .sidebar .brand-section {
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            margin-bottom: 12px;
            padding-left: 8px;
        }

        .sidebar .logo-box {
            background: var(--accent-cyan);
            color: white !important;
            padding: 6px 10px;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 14px;
        }

        .sidebar .nav-link { 
            color: #94A3B8 !important; 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 10px;
            padding: 7px 11px !important;
            border-radius: var(--radius-md) !important;
            margin-bottom: 2px !important;
            transition: all var(--transition-speed) ease; 
            font-size: 13px !important;
            font-weight: 500;
        }

        .sidebar .nav-link:hover {
            color: #FFFFFF !important;
            background: rgba(255, 255, 255, 0.05) !important;
        }

        .sidebar .nav-link.active { 
            background: rgba(59, 130, 246, 0.1) !important; 
            color: #3B82F6 !important; 
            font-weight: 600 !important;
        }
        
        .sidebar .nav-link.active i, .sidebar .nav-link.active bi {
            color: #3B82F6 !important;
        }

        .sidebar .nav-link i, .sidebar .nav-link bi {
            font-size: 16px;
            color: #64748B;
        }

        .sidebar .user-profile-card {
            background: rgba(255, 255, 255, 0.04) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: var(--radius-lg);
            padding: 10px;
        }

        .sidebar .user-profile-card div * {
            color: #FFFFFF !important;
        }

        #sidebarOverlay {
            position: fixed; inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 2500; display: none;
        }

        /* MODALS & BACKDROP STACKING - Layer above sticky header (2000) & sidebar (3000) */
        .modal {
            z-index: 3500 !important;
        }
        @media (min-width: 993px) {
            .modal {
                left: var(--sidebar-width) !important;
                width: calc(100vw - var(--sidebar-width)) !important;
            }
        }
        .modal-backdrop {
            z-index: 3490 !important;
        }
        .modal-backdrop.show {
            opacity: 0.65 !important;
            background-color: #0F172A !important;
        }
        body.modal-open {
            overflow: hidden !important;
        }

        /* HEADER */
        header {
            background: var(--panel) !important;
            border-bottom: 1px solid var(--panel-border) !important;
            position: sticky;
            top: 0;
            height: 64px;
            display: flex;
            align-items: center;
            z-index: 2000;
        }

        header h4 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        header .header-action-btn {
            background: transparent !important;
            border: 1px solid var(--panel-border) !important;
            color: var(--text-dim) !important;
            border-radius: var(--radius-md) !important;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-speed);
        }

        header .header-action-btn:hover {
            background: var(--bg) !important;
            color: var(--text-main) !important;
        }

        /* SEARCH BAR */
        .search-container {
            position: relative;
            width: 280px;
        }

        .search-input {
            width: 100%;
            height: 36px !important;
            background: var(--bg) !important;
            border: 1px solid var(--panel-border) !important;
            border-radius: var(--radius-md) !important;
            padding: 8px 12px 8px 36px !important;
            font-size: 13px !important;
            transition: all var(--transition-speed);
        }

        .search-input:focus {
            border-color: var(--accent-cyan) !important;
            background: var(--panel) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            font-size: 14px;
            pointer-events: none;
        }

        /* FORMS & INPUTS - Stripe Style */
        .form-control, .form-select {
            background-color: var(--panel) !important;
            color: var(--text-main) !important;
            border: 1px solid var(--panel-border) !important;
            height: 40px !important; /* 40px standard size */
            border-radius: var(--radius-md) !important;
            padding: 8px 12px !important;
            font-size: 13.5px !important;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-cyan) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
            outline: none !important;
        }

        /* BUTTONS - Microsoft Fluent / Stripe */
        .btn {
            height: 38px !important;
            border-radius: var(--radius-md) !important;
            font-size: 13.5px !important;
            font-weight: 550 !important;
            padding: 8px 16px !important;
            transition: all var(--transition-speed) ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--accent-cyan) !important;
            border: 1px solid var(--accent-cyan) !important;
            color: #FFFFFF !important;
        }

        .btn-primary, .btn-primary * {
            color: #FFFFFF !important;
        }

        .btn-primary:hover {
            background-color: #1D4ED8 !important;
            border-color: #1D4ED8 !important;
            color: #FFFFFF !important;
            transform: translateY(-0.5px);
        }

        .btn-outline-secondary, .btn-secondary {
            background-color: var(--panel) !important;
            border: 1px solid var(--panel-border) !important;
            color: var(--text-dim) !important;
        }

        .btn-outline-secondary:hover, .btn-secondary:hover {
            background-color: var(--bg) !important;
            color: var(--text-main) !important;
        }

        .btn-danger {
            background-color: var(--danger) !important;
            border: 1px solid var(--danger) !important;
            color: #FFFFFF !important;
        }

        .btn-danger, .btn-danger * {
            color: #FFFFFF !important;
        }

        .btn-success {
            background-color: var(--success) !important;
            border: 1px solid var(--success) !important;
            color: #FFFFFF !important;
        }

        .btn-success, .btn-success * {
            color: #FFFFFF !important;
        }

        .btn-dark, .btn-dark * {
            color: #FFFFFF !important;
        }

        /* E-Signature Black Color Rendering (#000000) */
        .signature-preview img,
        .signature-img,
        img[alt*="Signature"],
        img[alt*="signature"] {
            filter: grayscale(100%) contrast(150%);
        }
        @media print {
            .signature-preview img,
            .signature-img,
            img[alt*="Signature"],
            img[alt*="signature"] {
                filter: grayscale(100%) contrast(150%) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Clean layout for printing */
            .sidebar, #sidebar, #sidebarOverlay, header {
                display: none !important;
            }
            .main-container {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 0 !important;
            }

            /* Universal chart sizing for print / export */
            .report-chart-wrapper,
            .report-chart-container,
            .chart-wrapper,
            .chart-container {
                height: 280px !important;
                max-height: 280px !important;
                min-height: 280px !important;
                width: 100% !important;
                flex: none !important;
                position: relative !important;
                overflow: hidden !important;
                margin: 0 auto !important;
            }

            .report-chart-wrapper canvas,
            .report-chart-container canvas,
            .chart-wrapper canvas,
            .chart-container canvas {
                height: 280px !important;
                max-height: 280px !important;
                min-height: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                display: block !important;
                object-fit: contain !important;
            }

            .card, .doc-card, .glass-card, .chart-card {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                box-shadow: none !important;
                border: 1px solid #CBD5E1 !important;
                background: #FFFFFF !important;
                height: auto !important;
                min-height: 0 !important;
            }
        }

        /* CARDS - Modern Minimalist Stripe */
        .card, .doc-card, .glass-card, .chart-card {
            background: var(--panel) !important;
            border: 1px solid var(--panel-border) !important;
            border-radius: var(--radius-lg) !important;
            padding: 20px !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03) !important;
            margin-bottom: 20px;
            transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
        }

        .card:hover, .doc-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
        }

        /* TABLES - Enterprise Grade Sticky */
        .table-responsive {
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-lg);
            background: var(--panel);
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }

        .table {
            background-color: var(--panel) !important;
            margin-bottom: 0 !important;
            border-collapse: collapse !important;
            width: 100%;
        }

        .table th {
            background-color: var(--bg) !important;
            color: var(--text-dim) !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 16px !important;
            border-bottom: 1px solid var(--panel-border) !important;
            height: 44px;
            position: sticky;
            top: 0;
        }

        .table td {
            padding: 12px 16px !important;
            border-bottom: 1px solid var(--panel-border) !important;
            font-size: 13.5px !important;
            vertical-align: middle !important;
            height: 48px;
            color: var(--text-main) !important;
        }

        .table tr:hover {
            background-color: rgba(59, 130, 246, 0.03) !important;
        }

        /* UNIFIED ENTERPRISE STATUS COLOR SYSTEM */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            border-radius: var(--radius-sm) !important;
            text-transform: capitalize;
            letter-spacing: normal;
            border: 1px solid transparent;
        }

        /* 1. PRIMARY BLUE: In Process, In Transit, Processing, Active Neutral */
        .bg-info, .badge-info, .badge-in-transit, .badge-in-process, .badge-processing {
            background-color: rgba(29, 78, 216, 0.08) !important;
            color: #1D4ED8 !important;
            border: 1px solid rgba(29, 78, 216, 0.2) !important;
        }
        .badge-primary, .bg-primary-subtle {
            background-color: rgba(29, 78, 216, 0.08) !important;
            color: #1D4ED8 !important;
            border: 1px solid rgba(29, 78, 216, 0.2) !important;
        }

        /* 2. GREEN: Completed, Successful, Within SLA, Positive states */
        .bg-success, .badge-success, .badge-completed, .badge-approved, .badge-successful {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #047857 !important;
            border: 1px solid rgba(16, 185, 129, 0.2) !important;
        }

        /* 3. AMBER/ORANGE: Pending, Awaiting Approval, Due Soon, Awaiting Receipt, Awaiting Signature, Warnings */
        .bg-warning, .badge-warning, .badge-pending, .badge-for-approval, .badge-awaiting-receipt, .badge-awaiting-signature, .badge-due-soon {
            background-color: rgba(245, 158, 11, 0.08) !important;
            color: #B45309 !important;
            border: 1px solid rgba(245, 158, 11, 0.2) !important;
        }

        /* 4. RED: Overdue, Failed, Urgent, Action Required, Security Warnings */
        .bg-danger, .badge-danger, .badge-overdue, .badge-failed, .badge-rejected, .badge-urgent {
            background-color: rgba(220, 38, 38, 0.08) !important;
            color: #B91C1C !important;
            border: 1px solid rgba(220, 38, 38, 0.2) !important;
        }

        /* 5. MUTED GRAY / NEUTRAL: Archived, System, N/A */
        .bg-secondary, .badge-secondary, .badge-neutral {
            background-color: rgba(100, 116, 139, 0.08) !important;
            color: #475569 !important;
            border: 1px solid rgba(100, 116, 139, 0.2) !important;
        }

        /* Enterprise Text Utilities */
        .text-primary { color: #1D4ED8 !important; }
        .text-success { color: #059669 !important; }
        .text-warning { color: #D97706 !important; }
        .text-danger  { color: #DC2626 !important; }
        .text-navy    { color: #0F172A !important; }

        /* PROGRESS BAR */
        .progress {
            height: 6px !important;
            border-radius: var(--radius-sm) !important;
            background-color: var(--panel-border) !important;
            overflow: hidden;
        }

        .progress-bar {
            background-color: var(--accent-cyan) !important;
            border-radius: var(--radius-sm) !important;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* CUSTOM SCROLLBARS */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }

        /* PAGINATION STYLING */
        .pagination {
            justify-content: center;
            margin-top: 24px;
        }

        .pagination .page-link {
            background: var(--panel) !important;
            border: 1px solid var(--panel-border) !important;
            color: var(--text-dim) !important;
            border-radius: var(--radius-md) !important;
            margin: 0 2px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }

        .pagination .page-link:hover {
            background: var(--bg) !important;
            border-color: var(--panel-border) !important;
            color: var(--text-main) !important;
        }

        .pagination .page-item.active .page-link {
            background: var(--accent-cyan) !important;
            border-color: var(--accent-cyan) !important;
            color: #FFFFFF !important;
        }

        .pagination .page-item.disabled .page-link {
            background: transparent !important;
            border-color: var(--panel-border) !important;
            color: var(--text-dim) !important;
            opacity: 0.5;
            cursor: not-allowed;
        }

        .main-container { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; transition: var(--transition-speed) ease; }

        .notif-badge {
            position: absolute; top: 1px; right: 1px;
            background: #fb7185; color: white;
            font-size: 0.6rem; font-weight: 800;
            padding: 1px 4px; border-radius: 10px;
            border: 1.5px solid var(--panel);
        }

        /* USER PROFILE CARD */
        .user-profile-card {
            background: var(--panel); border-radius: var(--radius-lg);
            padding: 8px 12px; margin-bottom: 8px;
            display: flex; align-items: center; gap: 10px;
            border: 1px solid var(--panel-border);
        }

        .avatar {
            width: 32px; height: 32px;
            background: var(--accent-cyan);
            border-radius: var(--radius-md); display: grid; place-items: center;
            font-weight: bold; font-size: 13px; color: white !important;
        }

        /* Breadcrumb typography */
        .breadcrumb-container {
            font-size: 12.5px;
            color: var(--text-dim);
            font-weight: 400;
        }

        .breadcrumb-item-active {
            color: var(--text-main);
            font-weight: 500;
        }

        /* Sidebar Navigation Scroll & Section Labels */
        .sidebar-nav-scroll {
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding-bottom: 16px;
        }
        .sidebar-nav-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-nav-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }
        .sidebar-section-label {
            font-size: 9.5px !important;
            font-weight: 700 !important;
            letter-spacing: 0.8px !important;
            color: #64748B !important;
            text-transform: uppercase !important;
            margin-top: 10px !important;
            margin-bottom: 3px !important;
            padding: 0 10px !important;
            opacity: 0.75;
        }

        /* Dropdown Stacking Fix */
        .dropdown-menu {
            z-index: 2500 !important;
        }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-container { margin-left: 0; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            body.sidebar-open #sidebarOverlay { display: block; }
        }

    </style>
</head>

<body>
    <div id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="brand-section">
            <div class="logo-group d-flex align-items-center gap-2">
                <div class="logo-box">NA</div>
                <div>
                    <h6 class="m-0 fw-bold" style="letter-spacing: -0.5px; font-size: 14px;">NAAP Routing</h6>
                    <small class="text-slate-400" style="font-size: 10px; letter-spacing: 0.5px; font-weight: 600; opacity: 0.8;">ENTERPRISE</small>
                </div>
            </div>
            <button class="btn d-lg-none text-white p-0" id="closeSidebar">
                <i class="bi bi-x-lg fs-5"></i>
            </button>
        </div>

        @php
            $currentUser = auth()->user() ?? \App\Models\User::find(session('user_id'));
            $role = session('user_role') ?? $currentUser?->role;
            $isAdmin = ($currentUser && $currentUser->isAdmin()) || \App\Models\User::isRoleAdmin($role);
            $documentsLabel = $isAdmin ? 'Documents' : 'My Documents';
            $roleLabel = match($role) {
                'ADMIN', 'Administrator' => 'Administrator',
                'Super Administrator', 'Super Admin' => 'Super Administrator',
                'Sender' => 'Sender',
                'Receiver' => 'Receiver',
                default => $role ?? 'User'
            };
        @endphp

        <!-- Sidebar Profile Card -->
        <div class="px-2 py-2 border-bottom border-slate-800 mb-1">
            <div class="d-flex align-items-center gap-3 mb-0 p-2 rounded" style="background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255,255,255,0.05);">
                <div class="position-relative" style="flex-shrink: 0;">
                    @if($currentUser && $currentUser->avatar)
                        <img src="{{ asset('storage/' . $currentUser->avatar) }}" alt="Avatar" class="rounded-circle" style="width: 42px; height: 42px; object-fit: cover; border: 2px solid var(--accent-cyan);">
                    @else
                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="width: 42px; height: 42px; background: var(--accent-navy); border: 2px solid var(--accent-cyan); font-size: 16px;">
                            {{ substr(session('user_name', 'A'), 0, 1) }}
                        </div>
                    @endif
                    <!-- Online Status Indicator -->
                    <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width: 11px; height: 11px; border-width: 2px !important;"></span>
                </div>
                <div class="overflow-hidden" style="flex: 1;">
                    <div class="fw-bold text-white text-truncate small" style="line-height: 1.2;">{{ session('user_name') }}</div>
                    <small class="text-slate-400 text-truncate d-block" style="font-size: 10.5px;">{{ $roleLabel }}</small>
                </div>
            </div>
        </div>

        <nav class="flex-grow-1 sidebar-nav-scroll">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <a href="{{ route('documents.index') }}" class="nav-link {{ request()->routeIs('documents.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text-fill"></i> {{ $documentsLabel }}
            </a>
            <a href="{{ route('qr.index') }}" class="nav-link {{ request()->routeIs('qr.*') ? 'active' : '' }}">
                <i class="bi bi-qr-code-scan"></i> QR Scanner
            </a>
            <a href="{{ route('track.index') }}" class="nav-link {{ request()->routeIs('track.*') ? 'active' : '' }}">
                <i class="bi bi-geo-alt-fill"></i> Tracking
            </a>

            @if($isAdmin)
                <div class="sidebar-section-label">Management</div>
                <a href="{{ route('offices.index') }}" class="nav-link {{ request()->routeIs('offices.*') ? 'active' : '' }}">
                    <i class="bi bi-building-fill"></i> Offices
                </a>
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i> User Accounts
                </a>
                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <i class="bi bi-graph-up-arrow"></i> Reports
                </a>
                <a href="{{ route('security.dashboard') }}" class="nav-link {{ request()->routeIs('security.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-shield-fill-check"></i> Security Console
                </a>
                <a href="{{ route('activity.index') }}" class="nav-link {{ request()->routeIs('activity.index') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i> Audit Logs
                </a>
                <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear-fill"></i> System Settings
                </a>
            @else
                <a href="{{ route('activity.my') }}" class="nav-link {{ request()->routeIs('activity.my') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i> My Activity
                </a>
            @endif

            <div class="sidebar-section-label">Profile</div>
            <a href="{{ route('profile') }}" class="nav-link {{ request()->routeIs('profile') ? 'active' : '' }}">
                <i class="bi bi-person-circle"></i> My Profile
            </a>
            <a href="{{ route('help.manual') }}" class="nav-link {{ request()->routeIs('help.*') ? 'active' : '' }}">
                <i class="bi bi-question-circle"></i> Help & User Manual
            </a>
            <a href="{{ route('logout') }}" class="nav-link logout-link" style="color: #fb7185 !important;">
                <i class="bi bi-box-arrow-left"></i> Sign Out
            </a>
        </nav>
    </aside>

    <div class="main-container">
        <header class="px-4 py-2 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn d-lg-none p-0 header-action-btn" id="hamburgerMenu">
                    <i class="bi bi-list fs-4"></i>
                </button>
                
                <!-- Enterprise Breadcrumb -->
                <div class="breadcrumb-container d-none d-md-flex align-items-center gap-2">
                    <span>NAAP Enterprise</span>
                    <i class="bi bi-chevron-right text-slate-400" style="font-size: 10px;"></i>
                    <span class="breadcrumb-item-active">@yield('title')</span>
                </div>
            </div>

            <!-- Global Search & Control Icons -->
            <div class="d-flex align-items-center gap-3">
                <!-- Search -->
                <div class="search-container d-none d-sm-block">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Search system...">
                </div>


                <!-- Notifications -->
                <div class="dropdown">
                    <div class="header-action-btn position-relative" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                        <i class="bi bi-bell"></i>
                        <span class="notif-badge" id="notifBadge">0</span>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border mt-2" aria-labelledby="notifDropdown" style="width: 320px; border-radius:var(--radius-lg); border-color:var(--panel-border); padding:0; overflow:hidden; background:var(--panel);" id="notifMenu">
                        <li class="px-3 py-2" style="color:var(--text-dim); font-size:12px;">Loading notifications...</li>
                        <li><hr class="dropdown-divider m-0"></li>
                        <li>
                            <a class="dropdown-item text-center py-2 fw-semibold" href="{{ route('notifications.index') }}" style="font-size:0.8rem; color:#3B82F6;">
                                View All Notifications
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="container-fluid px-4 py-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="background: rgba(16, 185, 129, 0.08); color: #047857; border-left: 4px solid #10b981; border-radius: var(--radius-md);">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="background: rgba(244, 63, 94, 0.08); color: #be123c; border-left: 4px solid #f43f5e; border-radius: var(--radius-md);">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')

    <script>
        // Global AJAX & Axios CSRF Setup
        function setupCsrfHeaders() {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!token) return;
            if (window.jQuery) {
                window.jQuery.ajaxSetup({
                    headers: { 'X-CSRF-TOKEN': token }
                });
            }
            if (window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
            }
        }
        setupCsrfHeaders();
        document.addEventListener('DOMContentLoaded', setupCsrfHeaders);

        const body = document.body;
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const closeSidebar = document.getElementById('closeSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        // Sidebar Toggle Logic
        if (hamburgerMenu) {
            hamburgerMenu.addEventListener('click', (e) => {
                e.stopPropagation();
                body.classList.add('sidebar-open');
            });
        }

        if (closeSidebar) {
            closeSidebar.addEventListener('click', () => body.classList.remove('sidebar-open'));
        }
        if (overlay) {
            overlay.addEventListener('click', () => body.classList.remove('sidebar-open'));
        }

        // Auto-dismiss Alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Show notification toast
        window.showNotification = function(message, type = 'success') {
            const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'i';
            const bgColor = type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6';
            const html = `
                <div style="position: fixed; top: 20px; right: 20px; background: ${bgColor}; color: white; padding: 16px 24px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); font-weight: 600; z-index: 9999; animation: slideIn 0.3s ease-out;">
                    <span style="font-size: 1.2rem; margin-right: 12px;">${icon}</span>${message}
                </div>
                <style>
                    @keyframes slideIn {
                        from { transform: translateX(400px); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                </style>
            `;
            const container = document.createElement('div');
            container.innerHTML = html;
            document.body.appendChild(container.firstElementChild);
            setTimeout(() => {
                container.firstElementChild?.remove();
            }, 3000);
        };

        async function loadNotifications() {
            const menu = document.getElementById('notifMenu');
            const badge = document.getElementById('notifBadge');

            if (!menu || !badge) return;

            // The "View All Notifications" footer is always appended so it
            // remains visible even if the API call fails.
            const viewAllHtml = `
                <li><hr class="dropdown-divider m-0"></li>
                <li>
                    <a class="dropdown-item text-center py-2 fw-semibold" href="{{ route('notifications.index', [], false) }}" style="font-size:0.8rem; color:#3B82F6;">
                        View All Notifications
                    </a>
                </li>
            `;

            try {
                const response = await fetch('{{ route('api.notifications', [], false) }}', {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) throw new Error('Failed to load notifications');

                const data = await response.json();
                const items = data.items || [];
                const count = data.count || 0;

                badge.textContent = count;
                badge.style.display = count ? 'inline-flex' : 'none';

                // Map notification type → Bootstrap Icon class + color
                function getNotifMeta(type) {
                    switch ((type || '').toLowerCase()) {
                        case 'document_submitted': return { icon: 'bi-cloud-upload-fill',   color: '#3B82F6' };
                        case 'document_received':  return { icon: 'bi-inbox-fill',           color: '#10b981' };
                        case 'document_forwarded': return { icon: 'bi-arrow-right-circle-fill', color: '#F59E0B' };
                        case 'document_routed':    return { icon: 'bi-send-fill',            color: '#6366F1' };
                        case 'document_approved':  return { icon: 'bi-check-circle-fill',    color: '#10b981' };
                        case 'document_rejected':  return { icon: 'bi-x-circle-fill',        color: '#EF4444' };
                        case 'document_archived':  return { icon: 'bi-archive-fill',         color: '#64748B' };
                        default:                   return { icon: 'bi-bell-fill',             color: '#3B82F6' };
                    }
                }

                let html = `
                    <li class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2" style="font-weight:700; color:#1E3A8A; font-size:0.88rem;">
                        <span><i class="bi bi-bell-fill me-2"></i>Notifications</span>
                        ${count > 0 ? '<small style="color:#3B82F6; cursor:pointer; font-weight:600;" onclick="markAllRead()">Mark all read</small>' : ''}
                    </li>
                    <li><hr class="dropdown-divider m-0"></li>
                    <li style="max-height: 280px; overflow-y: auto; overflow-x: hidden; list-style-type: none; padding: 0; margin: 0;">
                        <ul style="list-style-type: none; padding: 0; margin: 0;">
                `;

                if (items.length === 0) {
                    html += `
                        <li class="px-3 py-4 text-center">
                            <i class="bi bi-bell-slash" style="font-size:1.5rem; color:#94a3b8;"></i>
                            <p class="mb-0 mt-2 small" style="color:#64748B;">No new notifications</p>
                        </li>`;
                } else {
                    items.forEach((item) => {
                        const type = (item.details && item.details.type) ? item.details.type : 'notification';
                        const meta = getNotifMeta(type);
                        html += `
                            <li>
                                <a class="dropdown-item d-flex align-items-start gap-3 py-3 px-3" href="/notifications/${item.id}/read" style="border-bottom:1px solid #f1f5f9; white-space:normal;">
                                    <div style="flex-shrink:0; width:34px; height:34px; background:${meta.color}18; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                                        <i class="bi ${meta.icon}" style="color:${meta.color}; font-size:1rem;"></i>
                                    </div>
                                    <div style="flex:1; overflow:hidden;">
                                        <div style="font-size:0.83rem; font-weight:600; color:#1F2937; line-height:1.35; word-break:break-word;">${item.message || 'New notification'}</div>
                                        <div style="font-size:0.72rem; color:#64748B; margin-top:3px;">${item.time}</div>
                                    </div>
                                </a>
                            </li>`;
                    });
                }

                html += `
                        </ul>
                    </li>
                `;

                // Always append "View All Notifications" so it is visible
                // regardless of whether notifications loaded or not.
                menu.innerHTML = html + viewAllHtml;
            } catch (error) {
                console.error('Notification update failed:', error);
                // Still show View All even if the fetch fails.
                menu.innerHTML = `<li class="px-3 py-3 text-center" style="color:var(--text-dim); font-size:12px;">Could not load notifications.</li>` + viewAllHtml;
            }
        }

        async function markAllRead() {
            try {
                const response = await fetch('{{ route('api.notifications.markRead', [], false) }}', {
                    method: 'POST',
                    headers: { 
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                if (response.ok) {
                    showNotification('All notifications marked as read.', 'success');
                }
            } catch(e) { /* silent */ }
            loadNotifications();
        }


        loadNotifications();
        setInterval(loadNotifications, 15000);
    </script>
    <x-upload-modal />
</body>
</html>