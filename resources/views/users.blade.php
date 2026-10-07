@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<style>
    /* Enterprise User Management Styles */
    .mgmt-header {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }
    @media (min-width: 768px) {
        .mgmt-header {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }

    .page-main-title {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text-main);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .page-sub-title {
        font-size: 0.875rem;
        color: var(--text-dim);
        margin-top: 0.25rem;
        margin-bottom: 0;
    }

    /* KPI Summary Cards */
    .kpi-mini-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 1rem 1.1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        height: 100%;
        min-height: 78px;
    }
    .kpi-mini-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .kpi-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .kpi-icon-navy {
        background: #0F172A;
        color: #FFFFFF;
    }
    .kpi-icon-emerald {
        background: rgba(16, 185, 129, 0.12);
        color: #059669;
    }
    .kpi-icon-amber {
        background: rgba(245, 158, 11, 0.12);
        color: #D97706;
    }
    .kpi-icon-blue {
        background: rgba(29, 78, 216, 0.12);
        color: #1D4ED8;
    }
    .kpi-content {
        min-width: 0;
        flex: 1;
    }
    .kpi-value {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--text-main);
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .kpi-value-sm {
        font-size: 0.88rem;
        font-weight: 700;
        letter-spacing: -0.2px;
    }
    .kpi-label {
        font-size: 0.775rem;
        font-weight: 500;
        color: var(--text-dim);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Panel Card */
    .mgmt-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 14px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    /* Search & Filter Toolbar */
    .mgmt-toolbar {
        padding: 1.25rem;
        background: var(--panel);
        border-bottom: 1px solid var(--panel-border);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    @media (min-width: 1200px) {
        .mgmt-toolbar {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }

    .filter-controls-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem;
        flex: 1;
        min-width: 0;
    }

    .search-input-wrapper {
        position: relative;
        flex: 1 1 240px;
        min-width: 200px;
        max-width: 380px;
    }
    .search-input-wrapper i.search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-dim);
        font-size: 0.95rem;
        pointer-events: none;
    }
    .search-input-wrapper .search-clear-btn {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        color: var(--text-dim);
        cursor: pointer;
        padding: 2px 6px;
        font-size: 0.85rem;
        display: none;
    }
    .search-input-wrapper input {
        padding-left: 36px;
        padding-right: 32px;
        height: 40px;
        font-size: 0.875rem;
        border-radius: 8px;
        border: 1px solid var(--panel-border);
        background: var(--bg);
        color: var(--text-main);
        width: 100%;
    }
    .search-input-wrapper input:focus {
        border-color: #1D4ED8;
        box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        outline: none;
        background: var(--panel);
    }

    .filter-select {
        height: 40px;
        font-size: 0.825rem;
        font-weight: 500;
        border-radius: 8px;
        border: 1px solid var(--panel-border);
        background: var(--bg);
        color: var(--text-main);
        padding: 0 2rem 0 0.85rem;
        cursor: pointer;
        min-width: 130px;
        max-width: 180px;
        white-space: nowrap;
    }
    .filter-select:focus {
        border-color: #1D4ED8;
        box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        outline: none;
    }

    .btn-reset-filters {
        height: 40px;
        padding: 0 0.85rem;
        font-size: 0.825rem;
        font-weight: 500;
        border-radius: 8px;
        border: 1px dashed var(--panel-border);
        background: transparent;
        color: var(--text-dim);
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.15s ease;
    }
    .btn-reset-filters:hover {
        background: var(--bg);
        color: #F43F5E;
        border-color: #F43F5E;
    }

    .user-count-badge {
        font-size: 0.825rem;
        font-weight: 600;
        color: var(--text-dim);
        background: var(--bg);
        border: 1px solid var(--panel-border);
        padding: 0.5rem 0.85rem;
        border-radius: 8px;
        white-space: nowrap;
    }

    /* Table Styles */
    .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .enterprise-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }
    .enterprise-table thead th {
        background: var(--bg);
        color: var(--text-dim);
        font-size: 0.725rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.75px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--panel-border);
        white-space: nowrap;
        vertical-align: middle;
    }
    .enterprise-table tbody td {
        padding: 13px 16px;
        vertical-align: middle;
        border-bottom: 1px solid var(--panel-border);
        color: var(--text-main);
        font-size: 0.875rem;
        background: transparent;
    }
    .enterprise-table tbody tr:hover td {
        background-color: rgba(241, 245, 249, 0.6);
    }
    .enterprise-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* User Cell Typography & Avatar */
    .user-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 200px;
    }
    .user-avatar-badge {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        color: #FFFFFF;
        flex-shrink: 0;
        background: linear-gradient(135deg, #0F172A, #1E293B);
    }
    .user-cell-meta {
        display: flex;
        flex-direction: column;
        line-height: 1.25;
        overflow: hidden;
    }
    .user-cell-name {
        font-weight: 600;
        color: var(--text-main);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .user-cell-sub {
        font-size: 0.75rem;
        color: var(--text-dim);
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Truncated Email with Tooltip */
    .email-cell {
        max-width: 190px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--text-dim);
        font-size: 0.85rem;
    }

    /* Monospace Employee ID */
    .empid-badge {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-dim);
        background: var(--bg);
        border: 1px solid var(--panel-border);
        padding: 3px 7px;
        border-radius: 6px;
        white-space: nowrap;
    }

    /* Position Cell (clamp 2 lines) */
    .position-cell {
        max-width: 140px;
        font-size: 0.825rem;
        color: var(--text-dim);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.3;
    }

    /* Professional Role Badges */
    .badge-role {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 9px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        white-space: nowrap;
    }
    .badge-role-super {
        background: #0F172A;
        color: #FFFFFF;
        border: 1px solid rgba(99, 102, 241, 0.4);
    }
    .badge-role-admin {
        background: #1E293B;
        color: #F8FAFC;
        border: 1px solid #334155;
    }
    .badge-role-head {
        background: #EFF6FF;
        color: #1D4ED8;
        border: 1px solid #BFDBFE;
    }
    .badge-role-staff {
        background: #F0FDFA;
        color: #0F766E;
        border: 1px solid #99F6E4;
    }
    .badge-role-default {
        background: #F1F5F9;
        color: #475569;
        border: 1px solid #E2E8F0;
    }

    /* Professional Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .status-badge-active {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, 0.25);
    }
    .status-badge-active i {
        font-size: 6px;
        color: #10B981;
    }
    .status-badge-inactive {
        background: rgba(148, 163, 184, 0.15);
        color: #64748B;
        border: 1px solid rgba(148, 163, 184, 0.3);
    }
    .status-badge-inactive i {
        font-size: 6px;
        color: #94A3B8;
    }

    /* Action Menu Button */
    .btn-action-menu {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        background: transparent;
        border: 1px solid transparent;
        color: var(--text-dim);
        transition: all 0.15s ease;
    }
    .btn-action-menu:hover,
    .btn-action-menu:focus {
        background: var(--bg);
        border-color: var(--panel-border);
        color: var(--text-main);
    }

    /* Enterprise Add User Button */
    .btn-add-user {
        background: #0F172A;
        color: #FFFFFF;
        border: 1px solid #0F172A;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0.5rem 1.15rem;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    .btn-add-user:hover {
        background: #1D4ED8;
        border-color: #1D4ED8;
        color: #FFFFFF;
        box-shadow: 0 4px 10px rgba(29, 78, 216, 0.2);
    }

    /* Mobile Cards View */
    .user-mobile-cards-wrap {
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }
    .user-mobile-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .user-mobile-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 0.85rem;
        gap: 0.5rem;
    }
    .user-mobile-card-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.65rem 1rem;
        padding: 0.75rem 0;
        border-top: 1px solid var(--panel-border);
        border-bottom: 1px solid var(--panel-border);
        margin-bottom: 0.85rem;
        font-size: 0.825rem;
    }
    .user-mobile-card-label {
        font-size: 0.725rem;
        color: var(--text-dim);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }
    .user-mobile-card-val {
        color: var(--text-main);
        font-weight: 500;
        word-break: break-word;
    }
    .user-mobile-card-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Modal Enterprise Design */
    .modal-content-enterprise {
        background: #FFFFFF;
        border: 1px solid var(--panel-border);
        border-radius: 14px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        color: var(--text-main);
    }
    .modal-header-enterprise {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--panel-border);
        background: var(--bg);
        border-top-left-radius: 14px;
        border-top-right-radius: 14px;
    }
    .modal-body-enterprise {
        padding: 1.5rem;
    }
    .modal-footer-enterprise {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--panel-border);
        background: var(--bg);
        border-bottom-left-radius: 14px;
        border-bottom-right-radius: 14px;
    }

    .form-section-title {
        font-size: 0.775rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.75px;
        color: var(--text-dim);
        margin-bottom: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .form-section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--panel-border);
    }

    .form-label-req::after {
        content: ' *';
        color: #F43F5E;
        font-weight: 700;
    }

    /* Empty state */
    .empty-state-wrap {
        padding: 3rem 1.5rem;
        text-align: center;
    }
    .empty-state-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--bg);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--text-dim);
        font-size: 1.75rem;
        margin-bottom: 1rem;
        border: 1px dashed var(--panel-border);
    }
</style>

{{-- ALERT NOTIFICATIONS --}}
@if(session('success'))
    <div class="alert alert-success d-flex align-items-center justify-content-between mb-4 border-0 shadow-sm" style="background: rgba(16, 185, 129, 0.12); color: #065F46; border-radius: 10px; padding: 0.85rem 1.25rem;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <span class="fw-medium">{{ session('success') }}</span>
        </div>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger d-flex align-items-center justify-content-between mb-4 border-0 shadow-sm" style="background: rgba(244, 63, 94, 0.12); color: #9F1239; border-radius: 10px; padding: 0.85rem 1.25rem;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <span class="fw-medium">{{ session('error') }}</span>
        </div>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- PAGE HEADER --}}
<div class="mgmt-header">
    <div>
        <h1 class="page-main-title">
            <i class="bi bi-people-fill text-primary" style="font-size: 1.4rem;"></i>
            User Management
        </h1>
        <p class="page-sub-title">
            Manage user accounts, access, roles, offices, and account status.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn-add-user" onclick="openAddUserModal()">
            <i class="bi bi-person-plus-fill"></i>
            <span>Add User</span>
        </button>
    </div>
</div>

{{-- KPI METRICS OVERVIEW --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-mini-card">
            <div class="kpi-icon-wrap kpi-icon-navy">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value">{{ $users->count() }}</div>
                <div class="kpi-label">Total Accounts</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-mini-card">
            <div class="kpi-icon-wrap kpi-icon-emerald">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value">{{ $users->where('status', 'active')->count() }}</div>
                <div class="kpi-label">Active Users</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-mini-card">
            <div class="kpi-icon-wrap kpi-icon-amber">
                <i class="bi bi-person-slash"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value">{{ $users->where('status', 'inactive')->count() }}</div>
                <div class="kpi-label">Deactivated</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-mini-card">
            <div class="kpi-icon-wrap kpi-icon-blue">
                <i class="bi bi-building"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value kpi-value-sm">{{ $departments->count() }} Depts / {{ $offices->count() }} Offices</div>
                <div class="kpi-label">Assigned Units</div>
            </div>
        </div>
    </div>
</div>

{{-- PRIMARY USER LIST WORKSPACE --}}
<div class="mgmt-card">
    {{-- SEARCH & FILTERS TOOLBAR --}}
    <div class="mgmt-toolbar">
        <div class="filter-controls-group">
            <div class="search-input-wrapper">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="userSearchInput" placeholder="Search by name, email, employee ID..." autocomplete="off">
                <button type="button" id="clearSearchBtn" class="search-clear-btn" title="Clear search">&times;</button>
            </div>

            <select id="roleFilterSelect" class="form-select filter-select">
                <option value="all">All Roles</option>
                <option value="Super Administrator">Super Administrator</option>
                <option value="Administrator">Administrator</option>
                <option value="Office Head">Office Head</option>
                <option value="Staff">Staff</option>
                <option value="Employee">Employee</option>
                <option value="ADMIN">ADMIN (Legacy)</option>
                <option value="USER">USER (Legacy)</option>
            </select>

            <select id="deptFilterSelect" class="form-select filter-select">
                <option value="all">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>

            <select id="officeFilterSelect" class="form-select filter-select">
                <option value="all">All Offices</option>
                @foreach($offices as $office)
                    <option value="{{ $office->id }}">{{ $office->name }}</option>
                @endforeach
            </select>

            <select id="statusFilterSelect" class="form-select filter-select" style="max-width: 140px;">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <button type="button" id="resetFiltersBtn" class="btn-reset-filters d-none">
                <i class="bi bi-x-circle"></i> Clear Filters
            </button>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="user-count-badge" id="userCountBadge">
                Total: {{ $users->count() }}
            </div>
        </div>
    </div>

    {{-- DESKTOP USERS TABLE (>= 768px) --}}
    <div class="table-container d-none d-md-block">
        <table class="table enterprise-table">
            <thead>
                <tr>
                    <th style="width: 22%;">User</th>
                    <th style="width: 17%;">Email</th>
                    <th style="width: 11%;">Employee ID</th>
                    <th style="width: 13%;">Position</th>
                    <th style="width: 11%;">Role</th>
                    <th style="width: 10%;">Department</th>
                    <th style="width: 10%;">Office</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 5%;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="userTableBody">
                @forelse($users as $user)
                    @php
                        $roleNorm = strtoupper(trim((string)$user->role));
                        $roleBadgeClass = match(true) {
                            str_contains($roleNorm, 'SUPER') => 'badge-role-super',
                            str_contains($roleNorm, 'ADMIN') => 'badge-role-admin',
                            str_contains($roleNorm, 'HEAD')  => 'badge-role-head',
                            str_contains($roleNorm, 'STAFF') => 'badge-role-staff',
                            default                          => 'badge-role-default',
                        };
                        $roleIcon = match(true) {
                            str_contains($roleNorm, 'SUPER') => 'bi-shield-shaded',
                            str_contains($roleNorm, 'ADMIN') => 'bi-shield-check',
                            str_contains($roleNorm, 'HEAD')  => 'bi-building',
                            str_contains($roleNorm, 'STAFF') => 'bi-person-badge',
                            default                          => 'bi-person',
                        };
                        $userSafeJson = [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'employee_id' => $user->employee_id ?? '',
                            'position' => $user->position ?? '',
                            'phone' => $user->phone ?? '',
                            'role' => $user->role,
                            'department_id' => $user->department_id,
                            'department_name' => $user->department?->name ?? 'Unassigned',
                            'office_id' => $user->office_id ?? '',
                            'office_name' => $user->office?->name ?? 'Unassigned',
                            'status' => $user->status ?? 'active',
                            'created_at' => $user->created_at ? $user->created_at->format('M d, Y h:i A') : 'N/A',
                            'updated_at' => $user->updated_at ? $user->updated_at->format('M d, Y h:i A') : 'N/A',
                        ];
                    @endphp
                    <tr class="user-row" 
                        data-name="{{ strtolower($user->name) }}"
                        data-email="{{ strtolower($user->email) }}"
                        data-empid="{{ strtolower($user->employee_id ?? '') }}"
                        data-position="{{ strtolower($user->position ?? '') }}"
                        data-role="{{ $user->role }}"
                        data-dept="{{ $user->department_id }}"
                        data-office="{{ $user->office_id ?? '' }}"
                        data-status="{{ strtolower($user->status ?? 'active') }}">
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-badge">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                                <div class="user-cell-meta">
                                    <span class="user-cell-name">{{ $user->name }}</span>
                                    <span class="user-cell-sub">{{ $user->role }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="email-cell" title="{{ $user->email }}">
                                {{ $user->email }}
                            </div>
                        </td>
                        <td>
                            @if(!empty($user->employee_id))
                                <span class="empid-badge">{{ $user->employee_id }}</span>
                            @else
                                <span class="text-muted small">N/A</span>
                            @endif
                        </td>
                        <td>
                            <div class="position-cell" title="{{ $user->position ?? 'N/A' }}">
                                {{ $user->position ?? 'N/A' }}
                            </div>
                        </td>
                        <td>
                            <span class="badge-role {{ $roleBadgeClass }}">
                                <i class="bi {{ $roleIcon }}"></i>
                                {{ $user->role }}
                            </span>
                        </td>
                        <td>
                            <span class="text-dim small fw-medium">{{ $user->department?->name ?? 'Unassigned' }}</span>
                        </td>
                        <td>
                            <span class="text-dim small fw-medium">{{ $user->office?->name ?? 'Unassigned' }}</span>
                        </td>
                        <td>
                            @if(strtolower((string)$user->status) === 'active')
                                <span class="status-badge status-badge-active">
                                    <i class="bi bi-circle-fill"></i> Active
                                </span>
                            @else
                                <span class="status-badge status-badge-inactive">
                                    <i class="bi bi-circle-fill"></i> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn-action-menu dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false" title="Account Actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="min-width: 190px; border-radius: 10px; z-index: 1055;">
                                    <li><h6 class="dropdown-header text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">User Administration</h6></li>
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="showUserDetails({{ json_encode($userSafeJson) }})">
                                            <i class="bi bi-person-lines-fill text-primary"></i> View Details
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="openEditUserModal({{ json_encode($userSafeJson) }})">
                                            <i class="bi bi-pencil text-info"></i> Edit User
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="confirmResetPassword({{ $user->id }}, '{{ addslashes($user->name) }}')">
                                            <i class="bi bi-key text-warning"></i> Reset Password
                                        </button>
                                    </li>
                                    @if($user->id !== auth()->id() && $user->id !== session('user_id'))
                                        <li>
                                            <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="confirmToggleStatus({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->status ?? 'active' }}')">
                                                @if(strtolower((string)$user->status) === 'active')
                                                    <i class="bi bi-person-slash text-secondary"></i> Deactivate
                                                @else
                                                    <i class="bi bi-person-check text-success"></i> Activate
                                                @endif
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <button class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" type="button" onclick="confirmDeleteUser({{ $user->id }}, '{{ addslashes($user->name) }}')">
                                                <i class="bi bi-trash3"></i> Delete User
                                            </button>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-dim">
                            <i class="bi bi-people fs-1 d-block mb-2 text-muted"></i>
                            No users found in the system.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE RESPONSIVE USER CARDS (< 768px, 430px, 390px) --}}
    <div class="user-mobile-cards-wrap d-block d-md-none" id="userMobileCardsContainer">
        @forelse($users as $user)
            @php
                $roleNorm = strtoupper(trim((string)$user->role));
                $roleBadgeClass = match(true) {
                    str_contains($roleNorm, 'SUPER') => 'badge-role-super',
                    str_contains($roleNorm, 'ADMIN') => 'badge-role-admin',
                    str_contains($roleNorm, 'HEAD')  => 'badge-role-head',
                    str_contains($roleNorm, 'STAFF') => 'badge-role-staff',
                    default                          => 'badge-role-default',
                };
                $roleIcon = match(true) {
                    str_contains($roleNorm, 'SUPER') => 'bi-shield-shaded',
                    str_contains($roleNorm, 'ADMIN') => 'bi-shield-check',
                    str_contains($roleNorm, 'HEAD')  => 'bi-building',
                    str_contains($roleNorm, 'STAFF') => 'bi-person-badge',
                    default                          => 'bi-person',
                };
                $userSafeJson = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id ?? '',
                    'position' => $user->position ?? '',
                    'phone' => $user->phone ?? '',
                    'role' => $user->role,
                    'department_id' => $user->department_id,
                    'department_name' => $user->department?->name ?? 'Unassigned',
                    'office_id' => $user->office_id ?? '',
                    'office_name' => $user->office?->name ?? 'Unassigned',
                    'status' => $user->status ?? 'active',
                    'created_at' => $user->created_at ? $user->created_at->format('M d, Y h:i A') : 'N/A',
                    'updated_at' => $user->updated_at ? $user->updated_at->format('M d, Y h:i A') : 'N/A',
                ];
            @endphp
            <div class="user-mobile-card user-card-item"
                data-name="{{ strtolower($user->name) }}"
                data-email="{{ strtolower($user->email) }}"
                data-empid="{{ strtolower($user->employee_id ?? '') }}"
                data-position="{{ strtolower($user->position ?? '') }}"
                data-role="{{ $user->role }}"
                data-dept="{{ $user->department_id }}"
                data-office="{{ $user->office_id ?? '' }}"
                data-status="{{ strtolower($user->status ?? 'active') }}">
                
                <div class="user-mobile-card-header">
                    <div class="d-flex align-items-center gap-3 overflow-hidden">
                        <div class="user-avatar-badge">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                        <div class="overflow-hidden">
                            <div class="user-cell-name">{{ $user->name }}</div>
                            <div class="user-cell-sub text-truncate">{{ $user->email }}</div>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn-action-menu" type="button" data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="min-width: 180px; border-radius: 10px; z-index: 1055;">
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="showUserDetails({{ json_encode($userSafeJson) }})">
                                    <i class="bi bi-person-lines-fill text-primary"></i> View Details
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="openEditUserModal({{ json_encode($userSafeJson) }})">
                                    <i class="bi bi-pencil text-info"></i> Edit User
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="confirmResetPassword({{ $user->id }}, '{{ addslashes($user->name) }}')">
                                    <i class="bi bi-key text-warning"></i> Reset Password
                                </button>
                            </li>
                            @if($user->id !== auth()->id() && $user->id !== session('user_id'))
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" onclick="confirmToggleStatus({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->status ?? 'active' }}')">
                                        @if(strtolower((string)$user->status) === 'active')
                                            <i class="bi bi-person-slash text-secondary"></i> Deactivate
                                        @else
                                            <i class="bi bi-person-check text-success"></i> Activate
                                        @endif
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" type="button" onclick="confirmDeleteUser({{ $user->id }}, '{{ addslashes($user->name) }}')">
                                        <i class="bi bi-trash3"></i> Delete User
                                    </button>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>

                <div class="user-mobile-card-grid">
                    <div>
                        <div class="user-mobile-card-label">Role</div>
                        <span class="badge-role {{ $roleBadgeClass }}">
                            <i class="bi {{ $roleIcon }}"></i>
                            {{ $user->role }}
                        </span>
                    </div>
                    <div>
                        <div class="user-mobile-card-label">Status</div>
                        @if(strtolower((string)$user->status) === 'active')
                            <span class="status-badge status-badge-active">
                                <i class="bi bi-circle-fill"></i> Active
                            </span>
                        @else
                            <span class="status-badge status-badge-inactive">
                                <i class="bi bi-circle-fill"></i> Inactive
                            </span>
                        @endif
                    </div>
                    <div>
                        <div class="user-mobile-card-label">Department</div>
                        <div class="user-mobile-card-val">{{ $user->department?->name ?? 'Unassigned' }}</div>
                    </div>
                    <div>
                        <div class="user-mobile-card-label">Office</div>
                        <div class="user-mobile-card-val">{{ $user->office?->name ?? 'Unassigned' }}</div>
                    </div>
                    <div>
                        <div class="user-mobile-card-label">Employee ID</div>
                        <div class="user-mobile-card-val">{{ $user->employee_id ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="user-mobile-card-label">Position</div>
                        <div class="user-mobile-card-val">{{ $user->position ?? 'N/A' }}</div>
                    </div>
                </div>

                <div class="user-mobile-card-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100 fw-semibold" onclick="showUserDetails({{ json_encode($userSafeJson) }})">
                        <i class="bi bi-eye me-1"></i> View Details
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary px-3" onclick="openEditUserModal({{ json_encode($userSafeJson) }})">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </div>
        @empty
            <div class="text-center py-5 text-dim">
                <i class="bi bi-people fs-1 d-block mb-2 text-muted"></i>
                No users found.
            </div>
        @endforelse
    </div>

    {{-- EMPTY SEARCH STATE --}}
    <div id="noUsersFoundState" class="empty-state-wrap d-none">
        <div class="empty-state-icon">
            <i class="bi bi-search"></i>
        </div>
        <h5 class="fw-bold mb-1">No matching users found</h5>
        <p class="text-dim mb-3 small">No user accounts match your current search and filter settings.</p>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetAllFilters()">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset All Filters
        </button>
    </div>
</div>

{{-- MODAL: ADD / EDIT USER --}}
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-enterprise">
            <form id="userForm" action="{{ route('users.store') }}" method="POST">
                @csrf
                <div id="methodSpoofContainer"></div>
                <input type="hidden" name="_form_mode" id="formModeInput" value="{{ old('_form_mode', 'create') }}">
                <input type="hidden" name="_edit_user_id" id="editUserIdInput" value="{{ old('_edit_user_id', '') }}">

                <div class="modal-header modal-header-enterprise">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="userModalTitle">
                            <i class="bi bi-person-plus text-primary me-2"></i> Add New User
                        </h5>
                        <p class="text-dim small mb-0 mt-1" id="userModalSubtitle">
                            Configure user identity, system role, and organizational assignment.
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body modal-body-enterprise">
                    {{-- SECTION 1: IDENTITY --}}
                    <div class="form-section-title">
                        <i class="bi bi-person-badge"></i> User Profile & Identity
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label form-label-req">Full Name</label>
                            <input type="text" name="name" id="modalInputName" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Javriel Dimayuga" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-req">Email Address</label>
                            <input type="email" name="email" id="modalInputEmail" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="e.g. j.dimayuga@naap.edu.ph" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Employee ID</label>
                            <input type="text" name="employee_id" id="modalInputEmployeeId" value="{{ old('employee_id') }}" class="form-control @error('employee_id') is-invalid @enderror" placeholder="e.g. EMP-1042">
                            @error('employee_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Position / Title</label>
                            <input type="text" name="position" id="modalInputPosition" value="{{ old('position') }}" class="form-control @error('position') is-invalid @enderror" placeholder="e.g. Administrative Officer">
                            @error('position') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" id="modalInputPhone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" placeholder="e.g. 09171234567">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- SECTION 2: ASSIGNMENT & ROLE --}}
                    <div class="form-section-title">
                        <i class="bi bi-shield-check"></i> System Access & Organization
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label form-label-req">System Role</label>
                            <select name="role" id="modalInputRole" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="" disabled {{ old('role') ? '' : 'selected' }}>Select role</option>
                                <option value="Super Administrator" {{ old('role') === 'Super Administrator' ? 'selected' : '' }}>Super Administrator</option>
                                <option value="Administrator" {{ old('role') === 'Administrator' ? 'selected' : '' }}>Administrator</option>
                                <option value="Office Head" {{ old('role') === 'Office Head' ? 'selected' : '' }}>Office Head</option>
                                <option value="Staff" {{ old('role') === 'Staff' ? 'selected' : '' }}>Staff</option>
                                <option value="Employee" {{ old('role') === 'Employee' ? 'selected' : '' }}>Employee</option>
                                <option value="ADMIN" {{ old('role') === 'ADMIN' ? 'selected' : '' }}>ADMIN (Legacy)</option>
                                <option value="USER" {{ old('role') === 'USER' ? 'selected' : '' }}>USER (Legacy)</option>
                            </select>
                            @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-req">Department</label>
                            <select name="department_id" id="modalInputDepartment" class="form-select @error('department_id') is-invalid @enderror" required>
                                <option value="" disabled {{ old('department_id') ? '' : 'selected' }}>Select department</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Office</label>
                            <select name="office_id" id="modalInputOffice" class="form-select @error('office_id') is-invalid @enderror">
                                <option value="" selected>None / General Department</option>
                                @foreach($offices as $office)
                                    <option value="{{ $office->id }}" {{ old('office_id') == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
                                @endforeach
                            </select>
                            @error('office_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-req">Account Status</label>
                            <select name="status" id="modalInputStatus" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active (Can Login)</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive (Deactivated)</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- SECTION 3: CREDENTIALS --}}
                    <div class="form-section-title">
                        <i class="bi bi-key"></i> Security Credentials
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" id="modalPasswordLabel">Account Password *</label>
                            <div class="input-group">
                                <input type="password" name="password" id="modalInputPassword" class="form-control @error('password') is-invalid @enderror" placeholder="••••••••••••" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordVisibility" title="Show/Hide Password">
                                    <i class="bi bi-eye" id="passwordEyeIcon"></i>
                                </button>
                            </div>
                            <small class="text-dim mt-1 d-block" id="passwordHelpText">
                                Min 10 characters • Uppercase • Lowercase • Number • Special character (!@#$%^&* etc.)
                            </small>
                            @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer modal-footer-enterprise">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="modalSubmitBtn" style="background:#0F172A; border-color:#0F172A;">
                        <i class="bi bi-check-lg me-1"></i> Create User Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: VIEW USER DETAILS INSPECTOR --}}
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content modal-content-enterprise">
            <div class="modal-header modal-header-enterprise">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar-badge" id="detailAvatar" style="width: 44px; height: 44px; font-size: 1.1rem;">U</div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="detailName">User Details</h5>
                        <div class="text-dim small" id="detailEmail">user@example.com</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body modal-body-enterprise p-4">
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 mb-3" style="background: var(--bg); border: 1px solid var(--panel-border);">
                    <div>
                        <div class="text-dim small text-uppercase fw-bold" style="font-size: 11px;">Current Status</div>
                        <div class="mt-1" id="detailStatusBadge"></div>
                    </div>
                    <div class="text-end">
                        <div class="text-dim small text-uppercase fw-bold" style="font-size: 11px;">System Role</div>
                        <div class="mt-1" id="detailRoleBadge"></div>
                    </div>
                </div>

                <div class="row g-3" style="font-size: 0.85rem;">
                    <div class="col-6">
                        <div class="text-dim small fw-semibold">Employee ID</div>
                        <div class="fw-semibold text-main mt-1" id="detailEmployeeId">N/A</div>
                    </div>
                    <div class="col-6">
                        <div class="text-dim small fw-semibold">Position</div>
                        <div class="fw-semibold text-main mt-1" id="detailPosition">N/A</div>
                    </div>
                    <div class="col-6">
                        <div class="text-dim small fw-semibold">Department</div>
                        <div class="fw-semibold text-main mt-1" id="detailDepartment">Unassigned</div>
                    </div>
                    <div class="col-6">
                        <div class="text-dim small fw-semibold">Assigned Office</div>
                        <div class="fw-semibold text-main mt-1" id="detailOffice">Unassigned</div>
                    </div>
                    <div class="col-6">
                        <div class="text-dim small fw-semibold">Phone</div>
                        <div class="fw-semibold text-main mt-1" id="detailPhone">N/A</div>
                    </div>
                    <div class="col-6">
                        <div class="text-dim small fw-semibold">Account Created</div>
                        <div class="text-main mt-1" id="detailCreatedAt">N/A</div>
                    </div>
                    <div class="col-12">
                        <div class="text-dim small fw-semibold">Last Updated</div>
                        <div class="text-main mt-1" id="detailUpdatedAt">N/A</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer modal-footer-enterprise d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="detailEditBtn" style="background:#1D4ED8; border-color:#1D4ED8;">
                        <i class="bi bi-pencil me-1"></i> Edit Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: RESET PASSWORD CONFIRMATION --}}
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content modal-content-enterprise">
            <form id="resetPasswordForm" method="POST">
                @csrf
                <div class="modal-body text-center p-4">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width: 52px; height: 52px; background: rgba(245, 158, 11, 0.12); color: #D97706; font-size: 1.5rem;">
                        <i class="bi bi-key-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Reset Password?</h5>
                    <p class="text-dim small mb-3">
                        Issue a temporary password for <strong id="resetUserName" class="text-main"></strong>?
                    </p>
                    <p class="text-muted small" style="font-size: 12px; line-height: 1.4;">
                        A temporary password will be dispatched to their email address. Mandatory password reset will be enforced upon next login.
                    </p>
                </div>
                <div class="modal-footer modal-footer-enterprise d-flex justify-content-between p-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold px-3">
                        <i class="bi bi-shield-lock me-1"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: TOGGLE STATUS CONFIRMATION --}}
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content modal-content-enterprise">
            <form id="toggleStatusForm" method="POST">
                @csrf
                <div class="modal-body text-center p-4">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" id="toggleStatusIconWrap" style="width: 52px; height: 52px; font-size: 1.5rem;">
                        <i class="bi bi-person-slash" id="toggleStatusIcon"></i>
                    </div>
                    <h5 class="fw-bold mb-2" id="toggleStatusTitle">Deactivate Account?</h5>
                    <p class="text-dim small mb-2">
                        Are you sure you want to <span id="toggleStatusVerb">deactivate</span> <strong id="toggleStatusName" class="text-main"></strong>?
                    </p>
                    <p class="text-muted small mb-0" id="toggleStatusDesc" style="font-size: 12px; line-height: 1.4;">
                        Deactivated users are blocked from logging in immediately and their active sessions are revoked.
                    </p>
                </div>
                <div class="modal-footer modal-footer-enterprise d-flex justify-content-between p-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm px-3 fw-bold" id="toggleStatusSubmitBtn">
                        Confirm Action
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: DELETE USER CONFIRMATION --}}
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content modal-content-enterprise">
            <form id="deleteUserForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body text-center p-4">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width: 52px; height: 52px; background: rgba(244, 63, 94, 0.12); color: #F43F5E; font-size: 1.5rem;">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Delete User?</h5>
                    <p class="text-dim small mb-2">
                        Permanently remove <strong id="deleteUserName" class="text-main"></strong> from the system?
                    </p>
                    <p class="text-danger small mb-0" style="font-size: 12px; line-height: 1.4;">
                        This action is irreversible and recorded in the audit trail.
                    </p>
                </div>
                <div class="modal-footer modal-footer-enterprise d-flex justify-content-between p-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3 fw-bold">
                        <i class="bi bi-trash3 me-1"></i> Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // --- STATE MANAGEMENT ---
    let currentUserSelected = null;

    // --- DOM REFERENCES ---
    const searchInput = document.getElementById('userSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const roleFilter = document.getElementById('roleFilterSelect');
    const deptFilter = document.getElementById('deptFilterSelect');
    const officeFilter = document.getElementById('officeFilterSelect');
    const statusFilter = document.getElementById('statusFilterSelect');
    const resetFiltersBtn = document.getElementById('resetFiltersBtn');
    const userCountBadge = document.getElementById('userCountBadge');
    const noUsersFoundState = document.getElementById('noUsersFoundState');

    // --- FILTER ENGINE ---
    function applyUserFilters() {
        const query = (searchInput.value || '').trim().toLowerCase();
        const role = roleFilter.value;
        const dept = deptFilter.value;
        const office = officeFilter.value;
        const status = statusFilter.value;

        // Toggle clear search button
        clearSearchBtn.style.display = query.length > 0 ? 'block' : 'none';

        // Check if any filter is active
        const isFiltered = query.length > 0 || role !== 'all' || dept !== 'all' || office !== 'all' || status !== 'all';
        resetFiltersBtn.classList.toggle('d-none', !isFiltered);

        const rows = document.querySelectorAll('#userTableBody tr.user-row');
        const cards = document.querySelectorAll('#userMobileCardsContainer .user-card-item');

        let visibleCount = 0;
        const totalCount = rows.length;

        rows.forEach((row, idx) => {
            const name = row.getAttribute('data-name') || '';
            const email = row.getAttribute('data-email') || '';
            const empid = row.getAttribute('data-empid') || '';
            const position = row.getAttribute('data-position') || '';
            const rowRole = row.getAttribute('data-role') || '';
            const rowDept = row.getAttribute('data-dept') || '';
            const rowOffice = row.getAttribute('data-office') || '';
            const rowStatus = row.getAttribute('data-status') || '';

            // Match conditions
            const matchesSearch = !query || name.includes(query) || email.includes(query) || empid.includes(query) || position.includes(query);
            const matchesRole = role === 'all' || rowRole === role;
            const matchesDept = dept === 'all' || rowDept === dept;
            const matchesOffice = office === 'all' || rowOffice === office;
            const matchesStatus = status === 'all' || rowStatus === status;

            const isVisible = matchesSearch && matchesRole && matchesDept && matchesOffice && matchesStatus;

            row.style.display = isVisible ? '' : 'none';
            if (cards[idx]) {
                cards[idx].style.display = isVisible ? '' : 'none';
            }

            if (isVisible) {
                visibleCount++;
            }
        });

        // Update Counter
        if (isFiltered) {
            userCountBadge.innerText = `Showing ${visibleCount} of ${totalCount} users`;
        } else {
            userCountBadge.innerText = `Total: ${totalCount}`;
        }

        // Toggle Empty State
        noUsersFoundState.classList.toggle('d-none', visibleCount > 0 || totalCount === 0);
    }

    // --- EVENT LISTENERS FOR FILTERS ---
    searchInput.addEventListener('input', applyUserFilters);
    clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        applyUserFilters();
        searchInput.focus();
    });
    roleFilter.addEventListener('change', applyUserFilters);
    deptFilter.addEventListener('change', applyUserFilters);
    officeFilter.addEventListener('change', applyUserFilters);
    statusFilter.addEventListener('change', applyUserFilters);

    window.resetAllFilters = function() {
        searchInput.value = '';
        roleFilter.value = 'all';
        deptFilter.value = 'all';
        officeFilter.value = 'all';
        statusFilter.value = 'all';
        applyUserFilters();
    };
    resetFiltersBtn.addEventListener('click', resetAllFilters);

    // --- ADD USER MODAL ---
    window.openAddUserModal = function() {
        const form = document.getElementById('userForm');
        form.reset();
        form.action = "{{ route('users.store') }}";
        document.getElementById('methodSpoofContainer').innerHTML = '';
        document.getElementById('formModeInput').value = 'create';
        document.getElementById('editUserIdInput').value = '';

        document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus text-primary me-2"></i> Add New User';
        document.getElementById('userModalSubtitle').innerText = 'Configure user identity, system role, and organizational assignment.';
        document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Create User Account';
        document.getElementById('modalSubmitBtn').style.background = '#0F172A';
        document.getElementById('modalSubmitBtn').style.borderColor = '#0F172A';

        document.getElementById('modalPasswordLabel').innerHTML = 'Account Password <span class="text-danger">*</span>';
        document.getElementById('modalInputPassword').required = true;
        document.getElementById('modalInputPassword').placeholder = '••••••••••••';
        document.getElementById('passwordHelpText').innerText = 'Min 10 characters • Uppercase • Lowercase • Number • Special character (!@#$%^&* etc.)';

        const modalEl = document.getElementById('userModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    };

    // --- EDIT USER MODAL ---
    window.openEditUserModal = function(user) {
        currentUserSelected = user;
        const form = document.getElementById('userForm');
        form.action = `/users/${user.id}`;
        document.getElementById('methodSpoofContainer').innerHTML = `@method('PUT')`;
        document.getElementById('formModeInput').value = 'edit';
        document.getElementById('editUserIdInput').value = user.id;

        document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-pencil-square text-info me-2"></i> Edit User Account';
        document.getElementById('userModalSubtitle').innerText = `Editing user profile and privileges for ${user.name}`;
        document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Save Changes';
        document.getElementById('modalSubmitBtn').style.background = '#1D4ED8';
        document.getElementById('modalSubmitBtn').style.borderColor = '#1D4ED8';

        // Prepopulate inputs
        document.getElementById('modalInputName').value = user.name || '';
        document.getElementById('modalInputEmail').value = user.email || '';
        document.getElementById('modalInputEmployeeId').value = user.employee_id || '';
        document.getElementById('modalInputPosition').value = user.position || '';
        document.getElementById('modalInputPhone').value = user.phone || '';
        document.getElementById('modalInputRole').value = user.role || '';
        document.getElementById('modalInputDepartment').value = user.department_id || '';
        document.getElementById('modalInputOffice').value = user.office_id || '';
        document.getElementById('modalInputStatus').value = user.status || 'active';

        // Password optional on edit
        document.getElementById('modalPasswordLabel').innerHTML = 'Account Password <span class="text-muted fw-normal">(Optional)</span>';
        document.getElementById('modalInputPassword').required = false;
        document.getElementById('modalInputPassword').value = '';
        document.getElementById('modalInputPassword').placeholder = 'Leave blank to preserve existing password';
        document.getElementById('passwordHelpText').innerText = 'Leave empty unless you wish to overwrite the current password.';

        // Close details modal if open
        const detailsModalEl = document.getElementById('userDetailsModal');
        const detailsModal = bootstrap.Modal.getInstance(detailsModalEl);
        if (detailsModal) {
            detailsModal.hide();
        }

        const modalEl = document.getElementById('userModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    };

    // --- VIEW USER DETAILS MODAL ---
    window.showUserDetails = function(user) {
        currentUserSelected = user;
        document.getElementById('detailAvatar').innerText = (user.name || 'U').charAt(0).toUpperCase();
        document.getElementById('detailName').innerText = user.name || 'N/A';
        document.getElementById('detailEmail').innerText = user.email || 'N/A';
        document.getElementById('detailEmployeeId').innerText = user.employee_id || 'N/A';
        document.getElementById('detailPosition').innerText = user.position || 'N/A';
        document.getElementById('detailDepartment').innerText = user.department_name || 'Unassigned';
        document.getElementById('detailOffice').innerText = user.office_name || 'Unassigned';
        document.getElementById('detailPhone').innerText = user.phone || 'N/A';
        document.getElementById('detailCreatedAt').innerText = user.created_at || 'N/A';
        document.getElementById('detailUpdatedAt').innerText = user.updated_at || 'N/A';

        // Badges
        const isActive = (user.status || '').toLowerCase() === 'active';
        document.getElementById('detailStatusBadge').innerHTML = isActive
            ? '<span class="status-badge status-badge-active"><i class="bi bi-circle-fill"></i> Active</span>'
            : '<span class="status-badge status-badge-inactive"><i class="bi bi-circle-fill"></i> Inactive</span>';

        document.getElementById('detailRoleBadge').innerHTML = `<span class="badge-role badge-role-admin"><i class="bi bi-shield-check"></i> ${user.role}</span>`;

        document.getElementById('detailEditBtn').onclick = () => openEditUserModal(user);

        const modalEl = document.getElementById('userDetailsModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    };

    // --- CONFIRMATION ACTION MODALS ---
    window.confirmResetPassword = function(id, name) {
        const form = document.getElementById('resetPasswordForm');
        form.action = `/users/${id}/reset-password`;
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
        }
        document.getElementById('resetUserName').innerText = name;
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('resetPasswordModal'));
        modal.show();
    };

    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            const submitBtn = resetForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                submitBtn.disabled = true;
            }
        });
    }

    window.confirmToggleStatus = function(id, name, currentStatus) {
        const form = document.getElementById('toggleStatusForm');
        form.action = `/users/${id}/toggle-status`;
        const isCurrentlyActive = (currentStatus || '').toLowerCase() === 'active';
        const newVerb = isCurrentlyActive ? 'deactivate' : 'activate';

        document.getElementById('toggleStatusName').innerText = name;
        document.getElementById('toggleStatusVerb').innerText = newVerb;
        document.getElementById('toggleStatusTitle').innerText = isCurrentlyActive ? 'Deactivate Account?' : 'Activate Account?';
        
        const iconWrap = document.getElementById('toggleStatusIconWrap');
        const submitBtn = document.getElementById('toggleStatusSubmitBtn');

        if (isCurrentlyActive) {
            iconWrap.style.background = 'rgba(244, 63, 94, 0.12)';
            iconWrap.style.color = '#F43F5E';
            submitBtn.className = 'btn btn-sm btn-danger px-3 fw-bold';
            submitBtn.innerText = 'Deactivate Account';
            document.getElementById('toggleStatusDesc').innerText = 'Deactivated users are blocked from logging in immediately and their active sessions are revoked.';
        } else {
            iconWrap.style.background = 'rgba(16, 185, 129, 0.12)';
            iconWrap.style.color = '#059669';
            submitBtn.className = 'btn btn-sm btn-success px-3 fw-bold';
            submitBtn.innerText = 'Activate Account';
            document.getElementById('toggleStatusDesc').innerText = 'The account will be reactivated, allowing the user to log in and access system functions according to their role.';
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('toggleStatusModal'));
        modal.show();
    };

    window.confirmDeleteUser = function(id, name) {
        const form = document.getElementById('deleteUserForm');
        form.action = `/users/${id}`;
        document.getElementById('deleteUserName').innerText = name;
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteUserModal'));
        modal.show();
    };

    // --- PASSWORD VISIBILITY TOGGLE ---
    const togglePassBtn = document.getElementById('togglePasswordVisibility');
    if (togglePassBtn) {
        togglePassBtn.addEventListener('click', function() {
            const passInput = document.getElementById('modalInputPassword');
            const icon = document.getElementById('passwordEyeIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                passInput.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    }

    // --- AUTO-OPEN MODAL ON VALIDATION ERRORS ---
    @if($errors->any())
        document.addEventListener('DOMContentLoaded', function() {
            const mode = "{{ old('_form_mode', 'create') }}";
            const editId = "{{ old('_edit_user_id', '') }}";
            const modalEl = document.getElementById('userModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                if (mode === 'edit' && editId) {
                    const form = document.getElementById('userForm');
                    form.action = `/users/${editId}`;
                    document.getElementById('methodSpoofContainer').innerHTML = `@method('PUT')`;
                    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-pencil-square text-info me-2"></i> Edit User Account';
                    document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Save Changes';
                }
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        });
    @endif
</script>
@endsection