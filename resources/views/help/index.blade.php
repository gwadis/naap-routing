@extends('layouts.app')

@section('title', 'Help & User Manual')

@section('content')
<style>
    /* Help & User Manual Styling - Strictly using existing CSS variables */
    .help-header-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .help-toc-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        padding: 16px;
        position: sticky;
        top: 80px;
        max-height: calc(100vh - 100px);
        overflow-y: auto;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .toc-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: var(--radius-md);
        color: var(--text-dim) !important;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        transition: all var(--transition-speed) ease;
        margin-bottom: 3px;
    }

    .toc-item:hover {
        background: var(--bg);
        color: var(--text-main) !important;
        transform: translateX(2px);
    }

    .toc-item.active {
        background: rgba(59, 130, 246, 0.1);
        color: var(--accent-cyan) !important;
        font-weight: 600;
    }

    .toc-badge {
        width: 22px;
        height: 22px;
        border-radius: 6px;
        background: var(--bg);
        border: 1px solid var(--panel-border);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        color: var(--text-dim);
        flex-shrink: 0;
    }

    .toc-item.active .toc-badge {
        background: var(--accent-cyan);
        color: #ffffff;
        border-color: var(--accent-cyan);
    }

    .manual-section {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-lg);
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        scroll-margin-top: 80px;
        transition: box-shadow 0.3s ease, border-color 0.3s ease;
    }

    .manual-section.target-highlight {
        border-color: var(--accent-cyan) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25) !important;
    }

    .manual-section-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--panel-border);
    }

    .section-letter-badge {
        width: 34px;
        height: 34px;
        border-radius: var(--radius-md);
        background: rgba(59, 130, 246, 0.1);
        color: var(--accent-cyan);
        border: 1px solid rgba(59, 130, 246, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 14px;
        flex-shrink: 0;
    }

    .step-item {
        display: flex;
        gap: 14px;
        margin-bottom: 16px;
        align-items: flex-start;
    }

    .step-number {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--accent-cyan);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .step-content {
        flex: 1;
    }

    .step-title {
        font-weight: 600;
        color: var(--text-main);
        font-size: 13.5px;
        margin-bottom: 4px;
    }

    .step-desc {
        color: var(--text-dim);
        font-size: 13px;
        line-height: 1.55;
        margin-bottom: 0;
    }

    .help-callout {
        border-radius: var(--radius-md);
        padding: 14px 16px;
        margin: 16px 0;
        border-left: 4px solid var(--accent-cyan);
        background: rgba(59, 130, 246, 0.05);
        font-size: 13px;
        line-height: 1.55;
    }

    .help-callout-warning {
        border-left-color: var(--warning);
        background: rgba(245, 158, 11, 0.05);
    }

    .help-callout-success {
        border-left-color: var(--success);
        background: rgba(16, 185, 129, 0.05);
    }

    .troubleshoot-card {
        background: var(--bg);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-md);
        padding: 16px;
        margin-bottom: 12px;
    }

    .troubleshoot-header {
        font-weight: 600;
        color: var(--text-main);
        font-size: 13.5px;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .troubleshoot-body {
        font-size: 13px;
        color: var(--text-dim);
        line-height: 1.55;
    }

    .faq-accordion .accordion-item {
        background: var(--panel) !important;
        border: 1px solid var(--panel-border) !important;
        border-radius: var(--radius-md) !important;
        margin-bottom: 10px;
        overflow: hidden;
    }

    .faq-accordion .accordion-button {
        background: var(--panel) !important;
        color: var(--text-main) !important;
        font-size: 13.5px !important;
        font-weight: 600 !important;
        box-shadow: none !important;
        padding: 14px 16px !important;
    }

    .faq-accordion .accordion-button:not(.collapsed) {
        background: rgba(59, 130, 246, 0.04) !important;
        color: var(--accent-cyan) !important;
    }

    .faq-accordion .accordion-body {
        font-size: 13px;
        color: var(--text-dim);
        line-height: 1.6;
        padding: 16px;
        border-top: 1px solid var(--panel-border);
    }

    .search-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        padding: 4px 10px;
        border-radius: 20px;
        background: var(--bg);
        border: 1px solid var(--panel-border);
        color: var(--text-dim) !important;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .search-tag:hover {
        background: rgba(59, 130, 246, 0.1);
        color: var(--accent-cyan) !important;
        border-color: rgba(59, 130, 246, 0.3);
    }

    mark.search-highlight {
        background-color: #fde047;
        color: #000000;
        padding: 1px 3px;
        border-radius: 3px;
    }

    .status-explanation-card {
        background: var(--bg);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius-md);
        padding: 14px;
        height: 100%;
    }

    /* Search Input spacing & icon alignment */
    .manual-search-box {
        position: relative;
    }

    .manual-search-box .search-icon {
        position: absolute;
        top: 50%;
        left: 14px;
        transform: translateY(-50%);
        color: var(--text-dim) !important;
        font-size: 14px;
        pointer-events: none;
        z-index: 2;
    }

    .manual-search-box input#manualSearchInput {
        padding-left: 42px !important;
        padding-right: 42px !important;
    }
</style>

<div class="container-fluid px-0">

    <!-- Header Section -->
    <div class="help-header-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <h1 class="page-title mb-0">Help & User Manual</h1>
                    @if($manualType === 'admin')
                        <span class="badge bg-primary text-white ms-1" style="font-size: 11px; padding: 4px 8px;">Admin Edition</span>
                    @else
                        <span class="badge bg-info text-white ms-1" style="font-size: 11px; padding: 4px 8px;">Staff Edition</span>
                    @endif
                </div>
                <div class="mb-2">
                    @if($manualType === 'admin')
                        <h4 class="fw-bold text-primary mb-0" style="font-size: 17px; letter-spacing: -0.01em;">NAAP Administrator Manual</h4>
                    @else
                        <h4 class="fw-bold text-primary mb-0" style="font-size: 17px; letter-spacing: -0.01em;">NAAP Staff/User Manual</h4>
                    @endif
                </div>
                <p class="text-secondary small mb-0" style="color: var(--text-dim) !important; max-width: 760px;">
                    Comprehensive operational documentation, step-by-step procedures, tracking explanations, QR/OTP verification workflows, troubleshooting guides, and frequently asked questions for the NAAP Automated Document Routing and Tracking System.
                </p>
            </div>

            <!-- Role View Switcher for Admins -->
            @if($isAdmin)
                <div class="d-flex align-items-center gap-2 bg-light p-1 rounded-3 border" style="background: var(--bg) !important; border-color: var(--panel-border) !important;">
                    <a href="{{ route('help.manual', ['view' => 'admin']) }}" class="btn btn-sm {{ $manualType === 'admin' ? 'btn-primary' : 'btn-outline-secondary' }}" style="height: 32px !important; font-size: 12px; font-weight: 600;">
                        <i class="bi bi-shield-lock-fill me-1"></i> Admin Manual
                    </a>
                    <a href="{{ route('help.manual', ['view' => 'staff']) }}" class="btn btn-sm {{ $manualType === 'staff' ? 'btn-primary' : 'btn-outline-secondary' }}" style="height: 32px !important; font-size: 12px; font-weight: 600;">
                        <i class="bi bi-person-fill me-1"></i> Staff Manual
                    </a>
                </div>
            @endif
        </div>

        <!-- Real Working Search Bar -->
        <div class="mt-4 pt-3 border-top" style="border-color: var(--panel-border) !important;">
            <div class="row g-2 align-items-center">
                <div class="col-lg-6 col-md-8">
                    <div class="manual-search-box position-relative">
                        <i class="bi bi-search search-icon" style="color: var(--text-dim) !important;"></i>
                        <input type="text" id="manualSearchInput" class="form-control" style="padding-left: 42px !important; padding-right: 42px !important;" placeholder="Search manual (e.g., Upload, QR, OTP, Tracking, Routing, Reports)..." value="{{ $searchQuery }}">
                        <button type="button" id="clearSearchBtn" class="btn btn-link position-absolute top-50 end-0 translate-middle-y me-2 p-0 text-secondary text-decoration-none" style="display: {{ $searchQuery ? 'inline-block' : 'none' }}; border:none !important; width:24px; height:24px; z-index: 2;">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                </div>
                <div class="col-lg-6 col-md-4">
                    <div id="searchMatchStatus" class="small text-secondary" style="color: var(--text-dim) !important; font-weight: 500;">
                        <!-- Updated dynamically via JS -->
                    </div>
                </div>
            </div>

            <!-- Quick Search Tags -->
            <div class="d-flex align-items-center gap-2 flex-wrap mt-3">
                <span class="small fw-bold text-secondary me-1" style="font-size: 12px;">Quick Terms:</span>
                <span class="search-tag" data-term="Upload"><i class="bi bi-cloud-arrow-up"></i> Upload</span>
                <span class="search-tag" data-term="QR"><i class="bi bi-qr-code"></i> QR</span>
                <span class="search-tag" data-term="OTP"><i class="bi bi-shield-check"></i> OTP</span>
                <span class="search-tag" data-term="Tracking"><i class="bi bi-geo-alt"></i> Tracking</span>
                <span class="search-tag" data-term="Routing"><i class="bi bi-arrow-left-right"></i> Routing</span>
                <span class="search-tag" data-term="Notifications"><i class="bi bi-bell"></i> Notifications</span>
                <span class="search-tag" data-term="Password"><i class="bi bi-key"></i> Password</span>
                @if($manualType === 'admin')
                    <span class="search-tag" data-term="User Management"><i class="bi bi-people"></i> User Management</span>
                    <span class="search-tag" data-term="Reports"><i class="bi bi-graph-up"></i> Reports</span>
                    <span class="search-tag" data-term="Audit Trail"><i class="bi bi-clock-history"></i> Audit Trail</span>
                @else
                    <span class="search-tag" data-term="Confidential"><i class="bi bi-lock"></i> Confidential</span>
                    <span class="search-tag" data-term="Status"><i class="bi bi-check-circle"></i> Status</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Main Content Layout (TOC on Left, Sections on Right) -->
    <div class="row g-4">

        <!-- Table of Contents Sidebar -->
        <div class="col-lg-3 col-md-4 d-none d-md-block">
            <div class="help-toc-card">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom" style="border-color: var(--panel-border) !important;">
                    <span class="fw-bold text-uppercase small" style="font-size: 11px; letter-spacing: 0.5px; color: var(--text-dim);">Table of Contents</span>
                    <i class="bi bi-list-nested text-secondary"></i>
                </div>

                <nav id="helpTocNav">
                    @if($manualType === 'admin')
                        <!-- Admin TOC -->
                        <a href="#admin-dashboard" class="toc-item" data-target="admin-dashboard">
                            <span class="toc-badge">A</span>
                            <span class="text-truncate">Admin Dashboard</span>
                        </a>
                        <a href="#user-management" class="toc-item" data-target="user-management">
                            <span class="toc-badge">B</span>
                            <span class="text-truncate">User Management</span>
                        </a>
                        <a href="#document-tracking" class="toc-item" data-target="document-tracking">
                            <span class="toc-badge">C</span>
                            <span class="text-truncate">Document Tracking</span>
                        </a>
                        <a href="#reports-analytics" class="toc-item" data-target="reports-analytics">
                            <span class="toc-badge">D</span>
                            <span class="text-truncate">Reports & Analytics</span>
                        </a>
                        <a href="#system-security" class="toc-item" data-target="system-security">
                            <span class="toc-badge">E</span>
                            <span class="text-truncate">Security Monitoring</span>
                        </a>
                        <a href="#audit-trail" class="toc-item" data-target="audit-trail">
                            <span class="toc-badge">F</span>
                            <span class="text-truncate">Audit Trail</span>
                        </a>
                        <a href="#notifications-admin" class="toc-item" data-target="notifications-admin">
                            <span class="toc-badge">G</span>
                            <span class="text-truncate">Notifications</span>
                        </a>
                        <a href="#troubleshooting-admin" class="toc-item" data-target="troubleshooting-admin">
                            <span class="toc-badge">H</span>
                            <span class="text-truncate">Troubleshooting</span>
                        </a>
                        <a href="#faq-admin" class="toc-item" data-target="faq-admin">
                            <span class="toc-badge">I</span>
                            <span class="text-truncate">Frequently Asked Questions</span>
                        </a>
                    @else
                        <!-- Staff/User TOC -->
                        <a href="#getting-started" class="toc-item" data-target="getting-started">
                            <span class="toc-badge">A</span>
                            <span class="text-truncate">Getting Started</span>
                        </a>
                        <a href="#user-dashboard" class="toc-item" data-target="user-dashboard">
                            <span class="toc-badge">B</span>
                            <span class="text-truncate">User Dashboard</span>
                        </a>
                        <a href="#upload-document" class="toc-item" data-target="upload-document">
                            <span class="toc-badge">C</span>
                            <span class="text-truncate">Upload Document</span>
                        </a>
                        <a href="#document-routing" class="toc-item" data-target="document-routing">
                            <span class="toc-badge">D</span>
                            <span class="text-truncate">Document Routing</span>
                        </a>
                        <a href="#document-tracking" class="toc-item" data-target="document-tracking">
                            <span class="toc-badge">E</span>
                            <span class="text-truncate">Document Tracking</span>
                        </a>
                        <a href="#qr-code" class="toc-item" data-target="qr-code">
                            <span class="toc-badge">F</span>
                            <span class="text-truncate">QR Code System</span>
                        </a>
                        <a href="#confidential-documents" class="toc-item" data-target="confidential-documents">
                            <span class="toc-badge">G</span>
                            <span class="text-truncate">Confidential Documents</span>
                        </a>
                        <a href="#notifications-staff" class="toc-item" data-target="notifications-staff">
                            <span class="toc-badge">H</span>
                            <span class="text-truncate">Notifications</span>
                        </a>
                        <a href="#profile-management" class="toc-item" data-target="profile-management">
                            <span class="toc-badge">I</span>
                            <span class="text-truncate">Profile Management</span>
                        </a>
                        <a href="#document-status" class="toc-item" data-target="document-status">
                            <span class="toc-badge">J</span>
                            <span class="text-truncate">Document Status Guide</span>
                        </a>
                        <a href="#troubleshooting-staff" class="toc-item" data-target="troubleshooting-staff">
                            <span class="toc-badge">K</span>
                            <span class="text-truncate">Troubleshooting</span>
                        </a>
                        <a href="#faq-staff" class="toc-item" data-target="faq-staff">
                            <span class="toc-badge">L</span>
                            <span class="text-truncate">Frequently Asked Questions</span>
                        </a>
                    @endif
                </nav>

                <div class="mt-4 pt-3 border-top" style="border-color: var(--panel-border) !important;">
                    <div class="p-2 rounded" style="background: var(--bg); border: 1px solid var(--panel-border);">
                        <div class="small fw-bold text-main mb-1" style="font-size: 11.5px;"><i class="bi bi-info-circle-fill text-primary me-1"></i> System Info</div>
                        <div class="small text-secondary" style="font-size: 11px; line-height: 1.4;">
                            NAAP Routing Enterprise v2.5<br>
                            Compliant with RA 11032 SLA Standards
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Sections Content Area -->
        <div class="col-lg-9 col-md-8 col-12">

            <!-- "No Search Results" Banner (Hidden by default) -->
            <div id="noResultsAlert" class="alert alert-warning text-center p-4" style="display: none; border-radius: var(--radius-lg); background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); color: #B45309;">
                <i class="bi bi-search fs-2 d-block mb-2 text-warning"></i>
                <h6 class="fw-bold mb-1">No matching manual sections found</h6>
                <p class="small mb-3">We couldn't find any topics matching your keyword. Please try a different term or clear your search.</p>
                <button type="button" class="btn btn-sm btn-secondary" onclick="resetManualSearch()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Search
                </button>
            </div>

            @if($manualType === 'admin')
                <!-- ========================================================================= -->
                <!-- ADMINISTRATOR MANUAL SECTIONS (A through I)                               -->
                <!-- ========================================================================= -->

                <!-- Section A: Administrator Dashboard -->
                <section id="admin-dashboard" class="manual-section" data-section-name="Administrator Dashboard">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">A</div>
                        <div>
                            <h4 class="section-title mb-0">Administrator Dashboard</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Executive control center, university throughput, and system overview</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3" style="line-height: 1.6;">
                        The Administrator Dashboard provides university leadership and system administrators with a consolidated, real-time command cockpit. It aggregates institution-wide document processing statistics, monitors active queues, and identifies workflow bottlenecks across all academic and administrative offices.
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-2 small"><i class="bi bi-speedometer2 text-primary me-1"></i> Dashboard Overview & Controls</h6>
                                <p class="small text-secondary mb-0">
                                    Instant visibility of key operational indicators including total active documents, average turnover velocity, system health status, and quick shortcuts to all administrative modules.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-2 small"><i class="bi bi-pie-chart-fill text-info me-1"></i> Document Statistics</h6>
                                <p class="small text-secondary mb-0">
                                    Dynamic counters displaying Total Documents, Pending Transactions, In-Transit Transfers, and Completed Dispositions filtered by university academic period.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-2 small"><i class="bi bi-building-fill text-warning me-1"></i> Office Workload Distribution</h6>
                                <p class="small text-secondary mb-0">
                                    Visual workload charts highlighting office capacities, pending transaction counts per department, and balancing document volumes across operating units.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-2 small"><i class="bi bi-graph-up-arrow text-success me-1"></i> Processing Information & Reports</h6>
                                <p class="small text-secondary mb-0">
                                    Aggregated processing times measured against Republic Act 11032 (Ease of Doing Business) SLA requirements, highlighting overdue or at-risk transactions.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section B: User Management -->
                <section id="user-management" class="manual-section" data-section-name="User Management">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">B</div>
                        <div>
                            <h4 class="section-title mb-0">User Management</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Provisioning, role assignments, account lifecycle, and access control</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">
                        Administrators manage the full lifecycle of employee accounts in the <strong>User Accounts</strong> module (<code>/users</code>). Follow these step-by-step procedures:
                    </p>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Add User</div>
                            <div class="step-desc">
                                Click the <strong>+ New User</strong> button in User Accounts. Fill in the required fields: Full Name, Official Email Address, Employee ID, Username, Initial Password, User Role (Super Administrator, Administrator, Office Head, Staff, Employee), and assign their primary Department and Office.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Edit User</div>
                            <div class="step-desc">
                                Locate the user in the accounts directory. Click <strong>Edit</strong> to modify contact details, reassign the user to a different office upon internal transfer, or promote/adjust role privileges.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Reset Password</div>
                            <div class="step-desc">
                                When a user is locked out or forgets their credentials, click <strong>Reset Password</strong> on their account card. The administrator can generate a secure temporary password. On their subsequent login, the system enforces a mandatory first-time password reset.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">Deactivate & Reactivate User</div>
                            <div class="step-desc">
                                Toggle the account status between <strong>Active</strong> and <strong>Inactive</strong>. Deactivating immediately invalidates existing sessions and prevents future logins without deleting historical routing records. Clicking <strong>Reactivate</strong> restores full system access.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">5</div>
                        <div class="step-content">
                            <div class="step-title">Delete User & Account Management</div>
                            <div class="step-desc">
                                Permanent account deletion is restricted to unreferenced test accounts. Active users with recorded document audit trails should be deactivated rather than deleted to preserve institutional audit integrity.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section C: Document Tracking -->
                <section id="document-tracking" class="manual-section" data-section-name="Document Tracking">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">C</div>
                        <div>
                            <h4 class="section-title mb-0">Document Tracking (Administrator)</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Institution-wide search, location mapping, and timestamp audit verification</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">
                        Administrators have unrestricted search visibility across all university documents, including cross-departmental routing hops and confidential files.
                    </p>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Search & Locate Documents</div>
                            <div class="step-desc">
                                Navigate to <strong>Tracking</strong> (<code>/track</code>). Use the enterprise search bar to search by unique tracking code (e.g. <code>DOC-20261007-8821</code>), document title, sender, receiver, or office name. Filter by SLA condition, date range, or priority.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">View Current Status & Holding Location</div>
                            <div class="step-desc">
                                The tracking console displays the exact office and staff custodian currently holding physical or digital custody of the item, along with its current state: <em>Pending</em>, <em>In Transit</em>, <em>Received</em>, <em>On Process</em>, <em>For Approval</em>, or <em>Completed</em>.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">View Routing History & Understand Timestamps</div>
                            <div class="step-desc">
                                Open the document detail view to examine the chronological movement passport. Every action—initial creation, physical hand-off, camera scan, acceptance, forwarding, and supervisor approval—is stamped with certified server timestamps down to the second. This guarantees an immutable record for regulatory verification.
                            </div>
                        </div>
                    </div>

                    <div class="help-callout help-callout-success">
                        <strong><i class="bi bi-check-circle-fill me-1"></i> Timestamp Verification:</strong>
                        All document actions capture the user ID, full name, physical office, IP address, and precise timestamp. When audits occur, administrators can export this timeline directly as proof of compliance.
                    </div>
                </section>

                <!-- Section D: Reports & Analytics -->
                <section id="reports-analytics" class="manual-section" data-section-name="Reports & Analytics">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">D</div>
                        <div>
                            <h4 class="section-title mb-0">Reports & Analytics</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Business intelligence, performance metrics, scan volume, and exportable reports</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">
                        The <strong>Reports</strong> module (<code>/reports</code>) compiles quantitative analytics to evaluate university operational performance, compliance with RA 11032 Citizen's Charter standards, and office resource utilization.
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-1 small"><i class="bi bi-bar-chart-fill text-primary me-1"></i> Document Volume</h6>
                                <p class="small text-secondary mb-0">Evaluates total inflow and outflow trends monthly, quarterly, and annually.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-1 small"><i class="bi bi-buildings text-info me-1"></i> Documents per Office</h6>
                                <p class="small text-secondary mb-0">Breakdown of transaction quantities handled by each university unit.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-1 small"><i class="bi bi-clock-history text-warning me-1"></i> Average Processing Time</h6>
                                <p class="small text-secondary mb-0">Measures the turnaround duration from document submission to final disposition.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-1 small"><i class="bi bi-qr-code-scan text-success me-1"></i> Daily Scan Activity & Workload</h6>
                                <p class="small text-secondary mb-0">Tallies QR verification throughput and identifies the university's most active operating office.</p>
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Export & Print Reports</div>
                            <div class="step-desc">
                                Configure the desired date range and office filters in the Reports screen. Click <strong>Export PDF</strong> or <strong>Export CSV/Excel</strong> to generate official print-ready executive summaries for institutional accreditation and Board of Regents compliance filings.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section E: Security -->
                <section id="system-security" class="manual-section" data-section-name="Security Monitoring">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">E</div>
                        <div>
                            <h4 class="section-title mb-0">Security Monitoring</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Access control auditing, login forensics, 2FA security, and anomaly detection</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">
                        The <strong>Security Console</strong> (<code>/security/dashboard</code>) provides real-time threat intelligence and monitoring tools to protect sensitive institutional records.
                    </p>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Login History & Access Records</div>
                            <div class="step-desc">
                                Review detailed authentication logs including successful sign-ins, IP addresses, client operating systems, browsers, and geographic indicators.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Suspicious Activity Monitoring</div>
                            <div class="step-desc">
                                The system automatically flags brute-force attacks, repeated failed login attempts (5 consecutive attempts trigger an automatic 15-minute lockout), unauthorized attempts to access confidential files, and unusual session shifts.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Multi-Factor Authentication (2FA) & OTP Oversight</div>
                            <div class="step-desc">
                                Monitor system-wide adoption of Two-Factor Authentication and oversee OTP delivery metrics to safeguard user endpoints.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section F: Audit Trail -->
                <section id="audit-trail" class="manual-section" data-section-name="Audit Trail">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">F</div>
                        <div>
                            <h4 class="section-title mb-0">Audit Trail</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Immutable transaction ledger, search filters, and forensics</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">
                        NAAP Routing System maintains a permanent, tamper-resistant <strong>Audit Trail</strong> (<code>/activity</code>) for every meaningful action performed within the software.
                    </p>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Viewing & Searching Audit Logs</div>
                            <div class="step-desc">
                                Navigate to Audit Logs in the sidebar. Search logs by action type, document identifier, record ID, or user email.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Filtering Logs</div>
                            <div class="step-desc">
                                Filter entries by specific staff member, target module (Documents, Users, Security Settings, Routing), or custom date ranges.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Understanding Recorded Actions</div>
                            <div class="step-desc">
                                Every audit record details: (a) Initiating User, (b) Action description (e.g., <em>Document Uploaded</em>, <em>Password Reset Initiated</em>, <em>Confidential Document Decrypted</em>), (c) Affected record URI, (d) Client IP and User Agent, and (e) Certified timestamp. Audit entries cannot be modified or deleted.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section G: Notifications -->
                <section id="notifications-admin" class="manual-section" data-section-name="Notifications">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">G</div>
                        <div>
                            <h4 class="section-title mb-0">Notifications (Administrator)</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">System announcements, document alerts, and multi-channel delivery</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">
                        Administrators manage notifications across multiple broadcast tiers:
                    </p>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">System-Wide Broadcasts</div>
                            <div class="step-desc">
                                Push emergency system announcements, scheduled maintenance downtime warnings, or university administrative memos directly to all logged-in user feeds.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Document & SLA Escalation Alerts</div>
                            <div class="step-desc">
                                Automated system triggers alert administrators when transactions exceed designated SLA processing thresholds or when urgent documents remain unaccepted for over 24 hours.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Multi-Channel Delivery (Telegram & Email)</div>
                            <div class="step-desc">
                                Configure the Telegram Bot Webhook integration to broadcast urgent institutional notifications to staff Telegram channels alongside standard email notifications.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section H: Troubleshooting -->
                <section id="troubleshooting-admin" class="manual-section" data-section-name="Troubleshooting">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">H</div>
                        <div>
                            <h4 class="section-title mb-0">Administrator Troubleshooting Guide</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Diagnosing and resolving administrative, routing, and access incidents</small>
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-danger">
                            <i class="bi bi-person-x-fill"></i> User Account Locked Due to Failed Logins
                        </div>
                        <div class="troubleshoot-body">
                            <strong>Cause:</strong> The user entered an incorrect password 5 consecutive times, triggering the automated security lockout.<br>
                            <strong>Resolution:</strong> Navigate to <strong>User Accounts</strong> &rarr; find the user &rarr; click <strong>Reset Password</strong> or set their <code>locked_until</code> field to <code>null</code>. Verify their email address and provide temporary credentials.
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-warning">
                            <i class="bi bi-envelope-exclamation-fill"></i> Email OTP Verification Codes Not Delivering
                        </div>
                        <div class="troubleshoot-body">
                            <strong>Cause:</strong> SMTP or Mail API transport credentials misconfigured, or receiving mail server marked message as spam.<br>
                            <strong>Resolution:</strong> Inspect <code>storage/logs/laravel.log</code> for Mail delivery errors. Confirm SMTP port, host, and API keys in <code>.env</code>. Verify the user's registered email address is correctly spelled without trailing spaces.
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-info">
                            <i class="bi bi-arrow-repeat"></i> Document Stuck in Workflow / Custodian Unavailable
                        </div>
                        <div class="troubleshoot-body">
                            <strong>Cause:</strong> Assigned recipient is on leave or transfer without reassigning active documents.<br>
                            <strong>Resolution:</strong> Administrators can use Document Management to manually reassign document custody to another active personnel within that office or forward it to the next office in the routing chain.
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-primary">
                            <i class="bi bi-qr-code"></i> QR Scanner Fails to Decode Physical Label
                        </div>
                        <div class="troubleshoot-body">
                            <strong>Cause:</strong> Poor camera resolution, print damage, or browser camera permissions blocked.<br>
                            <strong>Resolution:</strong> Ensure the user's browser has granted Camera permissions (HTTPS required). The user or admin can alternatively enter the 6-digit or alphanumeric tracking code manually into the Tracking search bar.
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-secondary">
                            <i class="bi bi-shield-lock"></i> Session Expiration / CSRF Token Mismatches
                        </div>
                        <div class="troubleshoot-body">
                            <strong>Cause:</strong> User left browser idle exceeding the session timeout limit.<br>
                            <strong>Resolution:</strong> Prompt the user to refresh the page or clear cookies. For persistent issues, verify <code>SESSION_LIFETIME</code> and <code>SESSION_DOMAIN</code> settings in the server environment.
                        </div>
                    </div>
                </section>

                <!-- Section I: FAQ -->
                <section id="faq-admin" class="manual-section" data-section-name="Frequently Asked Questions">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">I</div>
                        <div>
                            <h4 class="section-title mb-0">Administrator Frequently Asked Questions (FAQ)</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Common administrative queries and operational best practices</small>
                        </div>
                    </div>

                    <div class="accordion faq-accordion" id="adminFaqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingA1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseA1" aria-expanded="true" aria-controls="collapseA1">
                                    Can an Administrator override a document's routing sequence or destination?
                                </button>
                            </h2>
                            <div id="collapseA1" class="accordion-collapse collapse show" aria-labelledby="headingA1" data-bs-parent="#adminFaqAccordion">
                                <div class="accordion-body">
                                    Yes. Super Administrators and Administrators have executive oversight rights to re-route, reassign, or cancel deadlocked transactions when offices become unavailable or erroneous selections are made by originators. Every override action is logged in the Audit Trail with the admin's credentials.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingA2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseA2" aria-expanded="false" aria-controls="collapseA2">
                                    How are confidential documents accessed and audited by administrators?
                                </button>
                            </h2>
                            <div id="collapseA2" class="accordion-collapse collapse" aria-labelledby="headingA2" data-bs-parent="#adminFaqAccordion">
                                <div class="accordion-body">
                                    Confidential documents restrict payload attachments and sensitive text to authorized participants. While administrators can inspect routing metadata, office hops, and audit records, viewing protected contents requires completing two-factor OTP verification. Every access pass generated is logged in the Security Console.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingA3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseA3" aria-expanded="false" aria-controls="collapseA3">
                                    How do I export system audit logs for external agency inspection?
                                </button>
                            </h2>
                            <div id="collapseA3" class="accordion-collapse collapse" aria-labelledby="headingA3" data-bs-parent="#adminFaqAccordion">
                                <div class="accordion-body">
                                    Navigate to <strong>Reports & Analytics</strong> or <strong>Audit Logs</strong>. Set the date filter to the required review period and click <strong>Export CSV</strong> or <strong>Print / Export PDF</strong>. The generated document contains cryptographic check metadata and certified server timestamps.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingA4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseA4" aria-expanded="false" aria-controls="collapseA4">
                                    What happens to audit trails if an employee account is deleted?
                                </button>
                            </h2>
                            <div id="collapseA4" class="accordion-collapse collapse" aria-labelledby="headingA4" data-bs-parent="#adminFaqAccordion">
                                <div class="accordion-body">
                                    The NAAP Routing System uses soft archiving and persistent user name references in the <code>audit_trails</code> database table. Even if an account is archived or removed, historical routing entries permanently preserve the original user name, office name, and timestamps.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingA5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseA5" aria-expanded="false" aria-controls="collapseA5">
                                    How can administrators configure Telegram notifications?
                                </button>
                            </h2>
                            <div id="collapseA5" class="accordion-collapse collapse" aria-labelledby="headingA5" data-bs-parent="#adminFaqAccordion">
                                <div class="accordion-body">
                                    Under System Settings & Telegram Management (<code>/telegram/connect</code>), supply the verified Telegram Bot Token and webhook URL. Users can then link their individual Telegram handles to receive instant push alerts for urgent and SLA-critical documents.
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

            @else
                <!-- ========================================================================= -->
                <!-- STAFF/USER MANUAL SECTIONS (A through L)                                  -->
                <!-- ========================================================================= -->

                <!-- Section A: Getting Started -->
                <section id="getting-started" class="manual-section" data-section-name="Getting Started">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">A</div>
                        <div>
                            <h4 class="section-title mb-0">Getting Started</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Logging in, first-time setup, password management, and security</small>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Logging In</div>
                            <div class="step-desc">
                                Access the NAAP Routing portal (<code>/login</code>). Enter your registered <strong>Username</strong> or <strong>Official Email</strong> and your password. Click <strong>Sign In</strong>. If multiple failed attempts occurred from your network, complete the security reCAPTCHA when prompted.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">First-Time Login (Mandatory Password Change)</div>
                            <div class="step-desc">
                                If this is your first time logging in with a temporary password provided by your administrator, the system automatically redirects you to the <strong>First-Time Password Reset</strong> screen. Enter your current temporary password, followed by a new strong password (minimum 8 characters, combining uppercase, lowercase, numbers, and symbols). Confirm and submit.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Changing Your Password</div>
                            <div class="step-desc">
                                To update your password at any time, click <strong>My Profile</strong> in the bottom sidebar, or access <strong>Security Settings</strong>. Input your existing password and specify your new password. Click <strong>Update Password</strong>.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">Logging Out</div>
                            <div class="step-desc">
                                When finished with your work session, click <strong>Sign Out</strong> at the bottom of the sidebar. Always log out when using shared office computers to protect unauthorized access to sensitive documents.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section B: User Dashboard -->
                <section id="user-dashboard" class="manual-section" data-section-name="User Dashboard">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">B</div>
                        <div>
                            <h4 class="section-title mb-0">User Dashboard</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Overview of your personal office workspace, metrics, and active items</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">
                        The User Dashboard is tailored to your designated office and role. It highlights daily tasks requiring your attention:
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-1 small text-primary"><i class="bi bi-file-earmark-text"></i> Total Documents</h6>
                                <p class="small text-secondary mb-0">Cumulative count of all documents submitted by or assigned to your unit.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-1 small text-warning"><i class="bi bi-hourglass-split"></i> Pending Documents</h6>
                                <p class="small text-secondary mb-0">Items requiring your physical scan, review, acceptance, or processing.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="status-explanation-card">
                                <h6 class="fw-bold mb-1 small text-success"><i class="bi bi-check2-all"></i> Completed Documents</h6>
                                <p class="small text-secondary mb-0">Transactions successfully concluded and archived by your office.</p>
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Recent Activities Timeline</div>
                            <div class="step-desc">
                                Shows incoming transfers, document approvals, and hand-offs in chronological sequence with real-time status badges.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Notifications Indicator</div>
                            <div class="step-desc">
                                The notification bell in the top header displays unread alert counts whenever documents are routed to you or actions are completed.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section C: Upload Document -->
                <section id="upload-document" class="manual-section" data-section-name="Upload Document">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">C</div>
                        <div>
                            <h4 class="section-title mb-0">Upload Document</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Registering new documents, routing metadata, file attachments, and confidentiality</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">
                        To register and initiate the routing of a new document, click <strong>+ Upload New</strong> in the Documents page or Dashboard.
                    </p>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Required Document Information</div>
                            <div class="step-desc">
                                Fill in all required information:
                                <ul class="small text-secondary mt-1 mb-0 ps-3">
                                    <li><strong>Document Title:</strong> A clear, identifying subject title.</li>
                                    <li><strong>Description:</strong> Brief summary explaining the background, purpose, and required action.</li>
                                    <li><strong>Origin Office:</strong> Automatically populated with your assigned office.</li>
                                    <li><strong>Final Destination Office:</strong> The target department or office responsible for resolving the request.</li>
                                    <li><strong>Destination Receiver:</strong> Select the active receiving personnel in that office.</li>
                                    <li><strong>Processing SLA:</strong> Choose between Simple (3 Days), Complex (7 Days), or Highly Technical (20 Days).</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Attaching a File</div>
                            <div class="step-desc">
                                Drag and drop your digital document into the upload zone or browse from your device. Allowed file formats include <code>.pdf</code>, <code>.doc</code>, <code>.docx</code>, <code>.xls</code>, <code>.xlsx</code>, <code>.jpg</code>, and <code>.png</code> (up to 25MB).
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Confidential Document Option</div>
                            <div class="step-desc">
                                Check the <strong>Restrict View (Confidential)</strong> toggle for sensitive institutional records (e.g. legal cases, procurement evaluations, executive memoranda). When marked Confidential:
                                <ul class="small text-secondary mt-1 mb-0 ps-3">
                                    <li>The file cannot be previewed or downloaded by unauthorized users.</li>
                                    <li>Access is locked until the designated recipient performs QR verification and enters a 6-digit email OTP.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">Submitting & QR Code Generation</div>
                            <div class="step-desc">
                                Click <strong>Route Document / Submit</strong>. The system automatically creates a unique Tracking Number and generates a corresponding QR Code label. Print or attach the QR label to the physical document folder.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section D: Document Routing -->
                <section id="document-routing" class="manual-section" data-section-name="Document Routing">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">D</div>
                        <div>
                            <h4 class="section-title mb-0">Document Routing</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Sending, accepting, processing, forwarding, and completing transactions</small>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Sending / Routing a Document</div>
                            <div class="step-desc">
                                Once submitted, the document enters <em>In Transit</em> status. The destination office and receiver receive an instant in-app notification.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Accepting a Document</div>
                            <div class="step-desc">
                                When a physical document arrives at your office, open the <strong>QR Scanner</strong> (<code>/scan-qr</code>) and scan its QR label, or locate it in your pending list and click <strong>Accept Document</strong>. This confirms physical receipt, moves the status to <em>Received</em> / <em>On Process</em>, and logs an immutable timestamp.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Processing a Document</div>
                            <div class="step-desc">
                                While in <em>On Process</em>, take necessary institutional action: evaluate forms, sign endorse slips, add operational notes/remarks, or append supplemental attachments.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">Forwarding / Routing when Applicable</div>
                            <div class="step-desc">
                                If the transaction requires subsequent endorsement from another office (e.g., from Registrar to VP Academic Affairs), click <strong>Forward Document</strong>. Choose the next office and receiving officer.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">5</div>
                        <div class="step-content">
                            <div class="step-title">Completing Document Processing</div>
                            <div class="step-desc">
                                When all required approvals and releases are complete, click <strong>Mark as Completed</strong>. The document transitions to <em>Completed</em> status, archiving the transaction.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section E: Document Tracking -->
                <section id="document-tracking" class="manual-section" data-section-name="Document Tracking">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">E</div>
                        <div>
                            <h4 class="section-title mb-0">Document Tracking (Staff)</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Live status tracking, location verification, and routing timestamps</small>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Searching for a Document</div>
                            <div class="step-desc">
                                Go to <strong>Tracking</strong> (<code>/track</code>). Enter the Tracking Number (e.g. <code>DOC-20261007-1234</code>) or search by document title, originator, or office.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Viewing Current Status & Physical Location</div>
                            <div class="step-desc">
                                The tracking view highlights the current office location and assigned custodian holding the file, along with color-coded status badges.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Viewing Routing History & Understanding Timestamps</div>
                            <div class="step-desc">
                                <strong>IMPORTANT:</strong> The tracking view displays the document's certified routing and activity timestamps. For every step—creation, physical dispatch, camera scan, office acceptance, and completion—the system records the exact date, hour, minute, and second. Users can verify exactly when actions occurred and calculate turnaround times against Citizen's Charter standards.
                            </div>
                        </div>
                    </div>

                    <div class="help-callout help-callout-success">
                        <strong><i class="bi bi-clock-history me-1"></i> Transparent Audit Timestamps:</strong>
                        Every movement record displays <em>"Action by [Staff Name] at [Office Name] on [Date] at [Time]"</em>. This ensures full accountability across university departments.
                    </div>
                </section>

                <!-- Section F: QR Code -->
                <section id="qr-code" class="manual-section" data-section-name="QR Code System">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">F</div>
                        <div>
                            <h4 class="section-title mb-0">QR Code System</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">QR generation, scanning, verification, and handling scan issues</small>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Generating QR Code</div>
                            <div class="step-desc">
                                Every document submitted in NAAP Routing is automatically assigned a cryptographic QR code. Click <strong>Print QR Label / Passport</strong> from the document view to print a label for attachment to the document folder.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Scanning QR Code</div>
                            <div class="step-desc">
                                Open <strong>QR Scanner</strong> (<code>/scan-qr</code>) from the sidebar. Position the QR code within the camera frame. The scanner supports webcams, smartphone cameras, and handheld USB barcode scanners.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">QR Verification & Access</div>
                            <div class="step-desc">
                                When a valid code is scanned, the system verifies its authenticity and loads the document details card with action buttons (Accept, Route, Sign). For non-confidential documents, successful verification grants immediate access.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">What to Do if QR Code Cannot Be Verified</div>
                            <div class="step-desc">
                                If the scanner does not read the code:
                                <ul class="small text-secondary mt-1 mb-0 ps-3">
                                    <li>Ensure adequate lighting and hold the code steady without glare or wrinkles.</li>
                                    <li>If the physical label is damaged, type the tracking code manually in the <strong>Tracking</strong> module.</li>
                                    <li>You can reprint a fresh QR label anytime from Document Details.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section G: Confidential Documents -->
                <section id="confidential-documents" class="manual-section" data-section-name="Confidential Documents">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">G</div>
                        <div>
                            <h4 class="section-title mb-0">Confidential Documents</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Security protocol, QR verification, email OTP dispatch, and expiration</small>
                        </div>
                    </div>

                    <p class="small text-secondary mb-3">
                        Confidential documents protect sensitive university affairs. Access requires a two-factor verification barrier:
                    </p>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">How Confidential Access Works</div>
                            <div class="step-desc">
                                Unlike standard documents, confidential attachments cannot be opened simply by having the tracking number. The recipient must scan the QR code and complete One-Time Password (OTP) verification.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Email OTP Verification</div>
                            <div class="step-desc">
                                Upon scanning the confidential QR code, the system generates a secure <strong>6-digit verification PIN</strong> and dispatches it immediately to the authorized recipient's registered email address.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">OTP Expiration (5 Minutes)</div>
                            <div class="step-desc">
                                The OTP verification code expires strictly in <strong>5 minutes</strong> for security compliance. If expired, click the <strong>Resend PIN</strong> button on the verification modal to request a fresh code.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">Access After Successful Verification</div>
                            <div class="step-desc">
                                Entering the valid 6-digit PIN in the verification prompt unlocks the document contents and attachments for that session, and records an official decryption audit log.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section H: Notifications -->
                <section id="notifications-staff" class="manual-section" data-section-name="Notifications">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">H</div>
                        <div>
                            <h4 class="section-title mb-0">Notifications</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Viewing alerts, document routing updates, and notifications index</small>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Viewing Notifications</div>
                            <div class="step-desc">
                                Click the bell icon in the top header. Unread notifications appear in a quick dropdown list. Click any item to jump directly to the relevant document.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Document Routing & Approval Alerts</div>
                            <div class="step-desc">
                                You receive automatic alerts when: (a) a document is routed to your office, (b) an incoming item requires your signature, (c) a document you submitted changes status, or (d) an approval is granted.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">View All Notifications Page</div>
                            <div class="step-desc">
                                Click <strong>View All Notifications</strong> at the bottom of the notification dropdown to access the dedicated notifications page (<code>/notifications</code>). Here you can filter, mark items as read, or review historical alerts.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section I: Profile Management -->
                <section id="profile-management" class="manual-section" data-section-name="Profile Management">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">I</div>
                        <div>
                            <h4 class="section-title mb-0">Profile Management</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Updating profile info, managing credentials, and digital signatures</small>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Viewing Profile</div>
                            <div class="step-desc">
                                Click <strong>My Profile</strong> in the bottom sidebar (<code>/profile</code>) to view your account details, designated office, employee ID, and assigned privileges.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Updating Profile Information</div>
                            <div class="step-desc">
                                Update your contact phone number, position designation, and upload an official profile photo. Official name changes or department reassignments require administrator action.
                            </div>
                        </div>
                    </div>

                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Changing Password & Signatures</div>
                            <div class="step-desc">
                                You can change your password anytime under Profile & Security Settings. You can also upload or draw your authorized electronic signature for digital sign-offs.
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section J: Document Status Guide -->
                <section id="document-status" class="manual-section" data-section-name="Document Status Guide">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">J</div>
                        <div>
                            <h4 class="section-title mb-0">Document Status Guide</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Definitions of lifecycle statuses used across NAAP Routing</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="status-explanation-card border-warning">
                                <span class="badge bg-warning text-dark mb-2">On Process</span>
                                <h6 class="fw-bold mb-1 small">Active Processing</h6>
                                <p class="small text-secondary mb-0">
                                    The document has been accepted by the handling office and is actively undergoing review, evaluation, endorsement drafting, or necessary institutional work.
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="status-explanation-card border-success">
                                <span class="badge bg-success text-white mb-2">Completed</span>
                                <h6 class="fw-bold mb-1 small">Transaction Concluded</h6>
                                <p class="small text-secondary mb-0">
                                    All required institutional processes, signatures, and releases have successfully concluded. The transaction has reached its final official disposition and is safely archived.
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="status-explanation-card border-info">
                                <span class="badge bg-info text-white mb-2">For Approval</span>
                                <h6 class="fw-bold mb-1 small">Awaiting Official Sign-Off</h6>
                                <p class="small text-secondary mb-0">
                                    The document has been prepared and queued for an authorized supervisor, Office Head, or Executive officer awaiting formal signature or endorsement.
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="status-explanation-card border-primary">
                                <span class="badge bg-primary text-white mb-2">Approved</span>
                                <h6 class="fw-bold mb-1 small">Officially Endorsed</h6>
                                <p class="small text-secondary mb-0">
                                    The designated approving officer has officially signed and approved the transaction, allowing it to advance to execution, releasing, or completion.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section K: Troubleshooting -->
                <section id="troubleshooting-staff" class="manual-section" data-section-name="Troubleshooting">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">K</div>
                        <div>
                            <h4 class="section-title mb-0">Staff Troubleshooting Guide</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Solutions for common operational and system access issues</small>
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-danger">
                            <i class="bi bi-x-circle-fill"></i> Cannot Log In
                        </div>
                        <div class="troubleshoot-body">
                            Check that your Caps Lock is off and verify your username or email. If your account was locked after 5 failed attempts, wait 15 minutes or contact your system administrator to unlock your account.
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-warning">
                            <i class="bi bi-envelope-x-fill"></i> OTP Not Received / OTP Expired
                        </div>
                        <div class="troubleshoot-body">
                            Check your email Spam or Junk folder. OTPs expire strictly in 5 minutes. If expired, click the <strong>Resend PIN / OTP</strong> button on the verification dialog to receive a fresh 6-digit code.
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-info">
                            <i class="bi bi-qr-code-scan"></i> QR Code Cannot Be Verified
                        </div>
                        <div class="troubleshoot-body">
                            Ensure proper lighting, avoid glare on glossy surfaces, and hold the camera steady. If physical damage makes scanning impossible, search by the document tracking number in the Tracking menu.
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-primary">
                            <i class="bi bi-file-earmark-lock"></i> Cannot Access Document
                        </div>
                        <div class="troubleshoot-body">
                            If the document is marked <em>Confidential</em>, only the designated recipient can unlock it using the email OTP sent to their address. Contact the originator if you need to be added as a recipient.
                        </div>
                    </div>

                    <div class="troubleshoot-card">
                        <div class="troubleshoot-header text-secondary">
                            <i class="bi bi-cloud-slash-fill"></i> File Upload Problem
                        </div>
                        <div class="troubleshoot-body">
                            Verify that your file does not exceed the 25MB size limit and that the file format is allowed (PDF, DOC, DOCX, XLS, XLSX, JPG, PNG). Check your internet connection if the upload stalls.
                        </div>
                    </div>
                </section>

                <!-- Section L: FAQ -->
                <section id="faq-staff" class="manual-section" data-section-name="Frequently Asked Questions">
                    <div class="manual-section-title">
                        <div class="section-letter-badge">L</div>
                        <div>
                            <h4 class="section-title mb-0">Frequently Asked Questions (FAQ)</h4>
                            <small class="text-secondary" style="color: var(--text-dim) !important;">Answers to common everyday user questions</small>
                        </div>
                    </div>

                    <div class="accordion faq-accordion" id="staffFaqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingS1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseS1" aria-expanded="true" aria-controls="collapseS1">
                                    Can I route a document to multiple offices sequentially?
                                </button>
                            </h2>
                            <div id="collapseS1" class="accordion-collapse collapse show" aria-labelledby="headingS1" data-bs-parent="#staffFaqAccordion">
                                <div class="accordion-body">
                                    Yes. Documents are routed step-by-step. Once the initial destination office receives and processes their required portion, they simply click <strong>Forward Document</strong> to select the subsequent office in the chain.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingS2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseS2" aria-expanded="false" aria-controls="collapseS2">
                                    What should I do if a printed QR code label is damaged or lost?
                                </button>
                            </h2>
                            <div id="collapseS2" class="accordion-collapse collapse" aria-labelledby="headingS2" data-bs-parent="#staffFaqAccordion">
                                <div class="accordion-body">
                                    You can easily reprint the QR label anytime. Simply search for the document in <strong>Documents</strong> or <strong>Tracking</strong>, open the details page, and click <strong>Print QR Label / Passport</strong>.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingS3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseS3" aria-expanded="false" aria-controls="collapseS3">
                                    Why do I need an email OTP for confidential documents?
                                </button>
                            </h2>
                            <div id="collapseS3" class="accordion-collapse collapse" aria-labelledby="headingS3" data-bs-parent="#staffFaqAccordion">
                                <div class="accordion-body">
                                    The email OTP provides two-factor authorization to ensure that only the authorized recipient can view sensitive files, in compliance with the Data Privacy Act of 2012 (RA 10173).
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingS4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseS4" aria-expanded="false" aria-controls="collapseS4">
                                    How do I know if my document is delayed or overdue?
                                </button>
                            </h2>
                            <div id="collapseS4" class="accordion-collapse collapse" aria-labelledby="headingS4" data-bs-parent="#staffFaqAccordion">
                                <div class="accordion-body">
                                    The tracking timeline calculates elapsed time against the document's selected SLA (Simple: 3 Days, Complex: 7 Days, Highly Technical: 20 Days). Delayed documents show a red warning badge in your dashboard and notification list.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingS5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseS5" aria-expanded="false" aria-controls="collapseS5">
                                    Can I scan QR codes using my mobile phone?
                                </button>
                            </h2>
                            <div id="collapseS5" class="accordion-collapse collapse" aria-labelledby="headingS5" data-bs-parent="#staffFaqAccordion">
                                <div class="accordion-body">
                                    Yes! The NAAP QR Scanner works with any standard web browser on smartphones and tablets with camera support. Just log into your NAAP account on your mobile browser and open <strong>QR Scanner</strong>.
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('manualSearchInput');
        const clearBtn = document.getElementById('clearSearchBtn');
        const matchStatus = document.getElementById('searchMatchStatus');
        const noResultsAlert = document.getElementById('noResultsAlert');
        const sections = document.querySelectorAll('.manual-section');
        const tocItems = document.querySelectorAll('.toc-item');
        const quickTags = document.querySelectorAll('.search-tag');

        // Store original section HTML content for clean keyword highlighting & reset
        const originalSectionContents = new Map();
        sections.forEach(sec => {
            originalSectionContents.set(sec.id, sec.innerHTML);
        });

        // Function: Execute Search
        function performSearch(query) {
            const term = query.trim().toLowerCase();

            if (!term) {
                // Reset to default state
                sections.forEach(sec => {
                    sec.style.display = 'block';
                    sec.innerHTML = originalSectionContents.get(sec.id);
                });
                tocItems.forEach(item => item.style.display = 'flex');
                if (noResultsAlert) noResultsAlert.style.display = 'none';
                if (matchStatus) matchStatus.textContent = '';
                if (clearBtn) clearBtn.style.display = 'none';
                return;
            }

            if (clearBtn) clearBtn.style.display = 'inline-block';

            let matchCount = 0;
            const regex = new RegExp(`(${escapeRegExp(term)})`, 'gi');

            sections.forEach(sec => {
                const text = sec.textContent || '';
                const title = sec.getAttribute('data-section-name') || '';
                const isMatch = text.toLowerCase().includes(term) || title.toLowerCase().includes(term);

                if (isMatch) {
                    sec.style.display = 'block';
                    matchCount++;

                    // Highlight matches without breaking HTML tags
                    highlightKeywords(sec, originalSectionContents.get(sec.id), term);

                    // Show corresponding TOC item
                    const matchingToc = document.querySelector(`.toc-item[data-target="${sec.id}"]`);
                    if (matchingToc) matchingToc.style.display = 'flex';

                    // If match is inside accordion, expand it
                    const accordions = sec.querySelectorAll('.accordion-collapse');
                    accordions.forEach(acc => {
                        if (acc.textContent.toLowerCase().includes(term)) {
                            const bsCollapse = bootstrap.Collapse.getInstance(acc) || new bootstrap.Collapse(acc, { toggle: false });
                            bsCollapse.show();
                        }
                    });
                } else {
                    sec.style.display = 'none';
                    const nonMatchingToc = document.querySelector(`.toc-item[data-target="${sec.id}"]`);
                    if (nonMatchingToc) nonMatchingToc.style.display = 'none';
                }
            });

            // Update UI match counter & empty alert
            if (matchStatus) {
                matchStatus.textContent = matchCount > 0 
                    ? `Found ${matchCount} matching section${matchCount > 1 ? 's' : ''} for "${query}"`
                    : '';
            }

            if (noResultsAlert) {
                noResultsAlert.style.display = matchCount === 0 ? 'block' : 'none';
            }
        }

        // Helper: highlight keywords safely
        function highlightKeywords(container, originalHtml, term) {
            container.innerHTML = originalHtml;
            const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
            const textNodes = [];
            while (walker.nextNode()) {
                // Don't highlight inside script or style
                if (walker.currentNode.parentElement.tagName !== 'SCRIPT' && walker.currentNode.parentElement.tagName !== 'STYLE') {
                    textNodes.push(walker.currentNode);
                }
            }

            const regex = new RegExp(`(${escapeRegExp(term)})`, 'gi');
            textNodes.forEach(node => {
                if (regex.test(node.nodeValue)) {
                    const span = document.createElement('span');
                    span.innerHTML = node.nodeValue.replace(regex, '<mark class="search-highlight">$1</mark>');
                    node.parentNode.replaceChild(span, node);
                }
            });
        }

        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // Search Input listeners
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                performSearch(this.value);
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    resetManualSearch();
                }
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                resetManualSearch();
            });
        }

        // Quick Tag click listener
        quickTags.forEach(tag => {
            tag.addEventListener('click', function() {
                const term = this.getAttribute('data-term');
                if (searchInput) {
                    searchInput.value = term;
                    performSearch(term);
                    searchInput.focus();
                }
            });
        });

        // Global Reset function
        window.resetManualSearch = function() {
            if (searchInput) {
                searchInput.value = '';
                performSearch('');
                searchInput.focus();
            }
        };

        // Scroll to specific section if specified in URL (anchor or query param)
        const urlParams = new URLSearchParams(window.location.search);
        const sectionParam = urlParams.get('section');
        const searchParam = urlParams.get('search');
        const hash = window.location.hash ? window.location.hash.substring(1) : '';

        const targetId = sectionParam || hash;

        if (searchParam) {
            if (searchInput) {
                searchInput.value = searchParam;
                performSearch(searchParam);
            }
        } else if (targetId) {
            // Find section by targetId or alias
            let resolvedTarget = document.getElementById(targetId);
            if (!resolvedTarget) {
                // Map common aliases
                if (targetId === 'otp-verification') {
                    resolvedTarget = document.getElementById('confidential-documents') || document.getElementById('troubleshooting-admin') || document.getElementById('troubleshooting-staff');
                } else if (targetId === 'qr-verification') {
                    resolvedTarget = document.getElementById('qr-code') || document.getElementById('troubleshooting-admin');
                }
            }

            if (resolvedTarget) {
                setTimeout(() => {
                    resolvedTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    resolvedTarget.classList.add('target-highlight');
                    setTimeout(() => {
                        resolvedTarget.classList.remove('target-highlight');
                    }, 3500);

                    // Update active TOC item
                    tocItems.forEach(t => t.classList.remove('active'));
                    const activeToc = document.querySelector(`.toc-item[data-target="${resolvedTarget.id}"]`);
                    if (activeToc) activeToc.classList.add('active');
                }, 200);
            }
        }

        // TOC active state update on scroll (IntersectionObserver)
        const observerOptions = {
            root: null,
            rootMargin: '-80px 0px -70% 0px',
            threshold: 0
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    tocItems.forEach(item => {
                        item.classList.toggle('active', item.getAttribute('data-target') === id);
                    });
                }
            });
        }, observerOptions);

        sections.forEach(sec => observer.observe(sec));
    });
</script>
@endsection
