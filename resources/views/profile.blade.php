@extends('layouts.app')

@section('title', 'My Profile - Enterprise Account Management')

@section('content')
<style>
    /* Enterprise Profile Page Styling */
    .profile-page-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px 20px 48px;
    }

    .enterprise-card {
        background: #FFFFFF;
        border: 1px solid var(--panel-border, #E2E8F0);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        padding: 24px;
        margin-bottom: 24px;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .enterprise-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #F1F5F9;
    }

    .enterprise-card-title {
        font-size: 16px;
        font-weight: 700;
        color: #0F172A;
        letter-spacing: -0.01em;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .enterprise-card-desc {
        font-size: 13px;
        color: #64748B;
        margin-top: 3px;
        margin-bottom: 0;
    }

    /* Identity Header Card */
    .profile-identity-card {
        background: #FFFFFF;
        border: 1px solid var(--panel-border, #E2E8F0);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }

    .identity-avatar-wrapper {
        position: relative;
        width: 76px;
        height: 76px;
        border-radius: 12px;
        background: #0F172A;
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: 800;
        overflow: hidden;
        border: 2px solid #E2E8F0;
        flex-shrink: 0;
    }

    .identity-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .identity-name {
        font-size: 20px;
        font-weight: 700;
        color: #0F172A;
        letter-spacing: -0.02em;
        margin-bottom: 4px;
    }

    .identity-meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12.5px;
        color: #475569;
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 500;
    }

    .status-dot-active {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #10B981;
        display: inline-block;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
    }

    .status-dot-inactive {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #94A3B8;
        display: inline-block;
    }

    /* Enterprise Form Controls */
    .form-group-label {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #475569;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .enterprise-input {
        height: 40px;
        font-size: 13.5px;
        border-radius: 7px;
        border: 1px solid #CBD5E1;
        color: #0F172A;
        background-color: #FFFFFF;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .enterprise-input:focus {
        border-color: #1D4ED8;
        box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        outline: none;
    }

    .enterprise-input:disabled, .enterprise-input[readonly] {
        background-color: #F8FAFC;
        color: #64748B;
        border-color: #E2E8F0;
        cursor: not-allowed;
    }

    /* Photo Upload Area */
    .photo-upload-container {
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 16px;
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 10px;
        margin-bottom: 22px;
    }

    .photo-preview-box {
        width: 64px;
        height: 64px;
        border-radius: 10px;
        background: #0F172A;
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        font-weight: 700;
        overflow: hidden;
        border: 1px solid #CBD5E1;
        flex-shrink: 0;
    }

    .photo-preview-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Digital Signature Component */
    .sig-preview-card {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        padding: 16px;
        text-align: center;
        min-height: 110px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .sig-preview-card img {
        max-width: 100%;
        max-height: 100px;
        object-fit: contain;
    }

    .sig-empty-state {
        padding: 24px 16px;
        text-align: center;
        background: #F8FAFC;
        border: 1px dashed #CBD5E1;
        border-radius: 8px;
    }

    #sig-canvas {
        border: 1px solid #CBD5E1;
        border-radius: 8px;
        cursor: crosshair;
        background: #FFFFFF;
        width: 100%;
        height: 160px;
        touch-action: none;
        display: block;
    }

    /* Security Metadata Rail */
    .security-meta-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 11px 0;
        border-bottom: 1px solid #F1F5F9;
        font-size: 13px;
    }

    .security-meta-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .security-meta-label {
        color: #64748B;
        font-weight: 500;
    }

    .security-meta-val {
        color: #0F172A;
        font-weight: 600;
        text-align: right;
    }

    /* Password strength helper pills */
    .pwd-req-list {
        list-style: none;
        padding: 0;
        margin: 8px 0 0;
        font-size: 11.5px;
        color: #64748B;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px;
    }

    .pwd-req-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .pwd-req-item i {
        font-size: 12px;
    }

    @media (max-width: 768px) {
        .profile-identity-card {
            flex-direction: column;
            text-align: center;
        }
        .identity-avatar-wrapper {
            margin: 0 auto;
        }
        .photo-upload-container {
            flex-direction: column;
            text-align: center;
        }
        .pwd-req-list {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="profile-page-container">

    {{-- System Breadcrumbs --}}
    <div class="d-flex align-items-center gap-2 mb-2 text-secondary" style="font-size: 13px;">
        <span class="text-secondary">NAAP Enterprise</span>
        <i class="bi bi-chevron-right" style="font-size: 10px; color: #94A3B8;"></i>
        <span class="fw-semibold text-dark">My Profile</span>
    </div>

    {{-- Header Banner --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1" style="font-size: 24px; font-weight: 700; color: #0F172A; letter-spacing: -0.02em;">My Profile</h1>
            <p class="text-secondary mb-0" style="font-size: 13.5px;">Manage your personal information, organization assignment, digital signature, and account security.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #047857; font-weight: 600; padding: 6px 12px; border-radius: 6px; font-size: 12px; border: 1px solid rgba(16, 185, 129, 0.2);">
                <span class="{{ ($user->status ?? 'active') === 'active' ? 'status-dot-active' : 'status-dot-inactive' }} me-1"></span>
                {{ ucfirst($user->status ?? 'Active') }}
            </span>
            <span class="badge" style="background: rgba(29, 78, 216, 0.08); color: #1D4ED8; font-weight: 600; padding: 6px 12px; border-radius: 6px; font-size: 12px; border: 1px solid rgba(29, 78, 216, 0.2);">
                <i class="bi bi-shield-check me-1"></i> {{ strtoupper($user->role ?? 'User') }}
            </span>
        </div>
    </div>

    {{-- Session Notifications --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-3 mb-4 d-flex align-items-center gap-2 shadow-sm" style="background: #ECFDF5; color: #065F46; border-left: 4px solid #10B981 !important;">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div class="flex-grow-1">
                <strong>Success:</strong> {{ session('success') }}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 mb-4 d-flex align-items-center gap-2 shadow-sm" style="background: #FEF2F2; color: #991B1B; border-left: 4px solid #EF4444 !important;">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <div class="flex-grow-1">
                <strong>Error:</strong> {{ session('error') }}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 mb-4 shadow-sm" style="background: #FEF2F2; color: #991B1B; border-left: 4px solid #EF4444 !important;">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                <strong>Please resolve the following errors:</strong>
            </div>
            <ul class="mb-0 ps-4 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- 1. PROFESSIONAL IDENTITY CARD --}}
    <div class="profile-identity-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="identity-avatar-wrapper">
                    @if($user->avatar)
                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="identity-avatar-img" id="identityAvatarImg">
                    @else
                        <span id="identityAvatarFallback">{{ strtoupper(substr($user->name ?: $user->email, 0, 1)) }}</span>
                    @endif
                </div>
                <div>
                    <h2 class="identity-name mb-1">{{ $user->name }}</h2>
                    <div class="d-flex flex-wrap align-items-center gap-2 text-secondary" style="font-size: 13px;">
                        <span class="fw-semibold text-dark">{{ $user->role }}</span>
                        <span>&bull;</span>
                        <span>{{ $user->department->name ?? 'Administration' }}</span>
                        @if($user->office)
                            <span>&bull;</span>
                            <span>{{ $user->office->name }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="identity-meta-chip">
                    <i class="bi bi-person-badge text-muted"></i>
                    <span>ID:</span>
                    <strong class="text-dark">{{ $user->employee_id ?: 'EMP-Unassigned' }}</strong>
                </div>
                <div class="identity-meta-chip">
                    <span class="{{ ($user->status ?? 'active') === 'active' ? 'status-dot-active' : 'status-dot-inactive' }}"></span>
                    <span>Status:</span>
                    <strong class="text-dark">{{ ucfirst($user->status ?? 'Active') }}</strong>
                </div>
                <div class="identity-meta-chip">
                    <i class="bi bi-envelope text-muted"></i>
                    <span class="text-dark">{{ $user->email }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="row g-4">
        
        {{-- LEFT COLUMN: Profile Info & Digital Signature --}}
        <div class="col-12 col-xl-8 col-lg-7 text-start">
            
            {{-- 2. PROFILE INFORMATION CARD --}}
            <div class="enterprise-card">
                <div class="enterprise-card-header">
                    <div>
                        <h2 class="enterprise-card-title">
                            <i class="bi bi-person-lines-fill text-primary"></i> Profile Information
                        </h2>
                        <p class="enterprise-card-desc">Personal and organizational details associated with your enterprise account.</p>
                    </div>
                    <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 11px;">
                        @php
                            $role = session('user_role');
                            $isAdmin = in_array($role, ['ADMIN', 'Administrator', 'Super Administrator']);
                        @endphp
                        {{ $isAdmin ? 'Full Administrative Access' : 'Standard Employee Access' }}
                    </span>
                </div>

                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" id="profileUpdateForm">
                    @csrf

                    {{-- 3. Profile Picture Component --}}
                    <div class="photo-upload-container">
                        <div class="photo-preview-box">
                            @if($user->avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}" alt="Avatar" id="formAvatarPreview">
                            @else
                                <span id="formAvatarFallback">{{ strtoupper(substr($user->name ?: $user->email, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-bold text-dark" style="font-size: 13.5px;">Profile Picture</span>
                                <span class="badge bg-secondary-subtle text-secondary" id="avatarSelectedBadge" style="display: none; font-size: 10px;">New photo selected</span>
                            </div>
                            <p class="text-secondary small mb-2">Upload a professional identification photo. Recommended aspect ratio 1:1.</p>
                            <div class="d-flex align-items-center gap-2">
                                <input type="file" name="avatar" id="avatarFileInput" accept="image/png,image/jpeg,image/jpg,image/webp" style="display: none;">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('avatarFileInput').click()" style="font-size: 12.5px; font-weight: 600; padding: 5px 12px;">
                                    <i class="bi bi-camera me-1"></i> Change Picture
                                </button>
                                <span class="text-muted" style="font-size: 11.5px;">PNG, JPG or WEBP &bull; Max 2MB</span>
                            </div>
                        </div>
                    </div>

                    {{-- 2-Column Enterprise Field Layout --}}
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-group-label" for="inputName">
                                <span>Full Name <span class="text-danger">*</span></span>
                            </label>
                            <input type="text" id="inputName" name="name" class="form-control enterprise-input" value="{{ old('name', $user->name) }}" required placeholder="Enter full name">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-group-label" for="inputEmpId">
                                <span>Employee ID</span>
                                @if(!$isAdmin)<span class="text-muted" style="text-transform: none; font-weight: normal; font-size: 11px;">(Admin-managed)</span>@endif
                            </label>
                            <input type="text" id="inputEmpId" name="employee_id" class="form-control enterprise-input" value="{{ old('employee_id', $user->employee_id) }}" placeholder="e.g. EMP-1042" @disabled(!$isAdmin)>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-group-label" for="inputPosition">
                                <span>Position / Title</span>
                                @if(!$isAdmin)<span class="text-muted" style="text-transform: none; font-weight: normal; font-size: 11px;">(Admin-managed)</span>@endif
                            </label>
                            <input type="text" id="inputPosition" name="position" class="form-control enterprise-input" value="{{ old('position', $user->position) }}" placeholder="e.g. Administrative Officer" @disabled(!$isAdmin)>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-group-label" for="inputPhone">
                                <span>Contact Number</span>
                            </label>
                            <input type="text" id="inputPhone" name="phone" class="form-control enterprise-input" value="{{ old('phone', $user->phone) }}" placeholder="e.g. 0917 123 4567">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-group-label" for="selectDepartment">
                                <span>Department</span>
                                @if(!$isAdmin)<span class="text-muted" style="text-transform: none; font-weight: normal; font-size: 11px;">(Admin-managed)</span>@endif
                            </label>
                            <select id="selectDepartment" name="department_id" class="form-select enterprise-input" @disabled(!$isAdmin)>
                                <option value="">-- Select Department --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ (old('department_id', $user->department_id) == $dept->id) ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-group-label" for="selectOffice">
                                <span>Assigned Office</span>
                                @if(!$isAdmin)<span class="text-muted" style="text-transform: none; font-weight: normal; font-size: 11px;">(Admin-managed)</span>@endif
                            </label>
                            <select id="selectOffice" name="office_id" class="form-select enterprise-input" @disabled(!$isAdmin)>
                                <option value="">-- Select Office --</option>
                                @foreach($offices as $off)
                                    <option value="{{ $off->id }}" {{ (old('office_id', $user->office_id) == $off->id) ? 'selected' : '' }}>
                                        {{ $off->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-group-label" for="inputEmail">
                                <span>Account Email Address <span class="text-danger">*</span></span>
                                <span class="badge bg-light text-secondary border" style="text-transform: none; font-weight: 500; font-size: 11px;">Primary Login Identifier</span>
                            </label>
                            <input type="email" id="inputEmail" name="email" class="form-control enterprise-input" value="{{ old('email', $user->email) }}" required placeholder="user@naap.org">
                            <div class="form-text text-muted" style="font-size: 11.5px;">
                                Used for enterprise authentication, two-factor OTP verification codes, and security audit notices.
                            </div>
                        </div>
                    </div>

                    {{-- Form Bottom Action Bar --}}
                    <div class="d-flex align-items-center justify-content-between pt-4 mt-4 border-top">
                        <span class="text-muted small">All changes are recorded in the security audit trail.</span>
                        <div class="d-flex align-items-center gap-2">
                            <button type="reset" class="btn btn-outline-secondary px-3" style="font-size: 13.5px; height: 38px;">
                                Reset
                            </button>
                            <button type="submit" class="btn btn-primary px-4 fw-bold" style="font-size: 13.5px; height: 38px;">
                                <i class="bi bi-check-lg me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- 5. & 6. DIGITAL SIGNATURE CARD --}}
            <div class="enterprise-card">
                <div class="enterprise-card-header">
                    <div>
                        <h2 class="enterprise-card-title">
                            <i class="bi bi-pen-fill text-primary"></i> Digital Signature
                        </h2>
                        <p class="enterprise-card-desc">Your digital signature is verified and applied during electronic document routing and approvals.</p>
                    </div>
                    <div>
                        @if($user->signature)
                            <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #047857; font-weight: 600; padding: 5px 10px; border-radius: 6px; font-size: 11.5px; border: 1px solid rgba(16, 185, 129, 0.2);">
                                <i class="bi bi-check2-circle me-1"></i> Signature Configured
                            </span>
                        @else
                            <span class="badge" style="background: rgba(148, 163, 184, 0.12); color: #475569; font-weight: 600; padding: 5px 10px; border-radius: 6px; font-size: 11.5px; border: 1px solid #CBD5E1;">
                                <i class="bi bi-circle me-1"></i> Not Configured
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Signature Display / Empty State --}}
                @if($user->signature)
                    <div class="mb-3">
                        <label class="form-group-label mb-2">Current Active Signature</label>
                        <div class="sig-preview-card">
                            <img src="{{ asset('storage/' . $user->signature) }}" alt="Current Signature" class="img-fluid">
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <span class="text-muted small">Saved signature on file. Applied automatically when completing workflow actions.</span>
                            <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" id="btnOpenSigEditor" style="font-size: 12.5px; height: 34px;">
                                <i class="bi bi-pencil-square me-1"></i> Replace Signature
                            </button>
                        </div>
                    </div>
                @else
                    <div class="sig-empty-state mb-3">
                        <i class="bi bi-pen text-secondary fs-2 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">No Digital Signature Configured</h6>
                        <p class="text-secondary small mb-3">You must configure a digital signature to electronically approve, endorse, or sign routed documents.</p>
                        <button type="button" class="btn btn-primary btn-sm px-3 fw-bold" id="btnOpenSigEditor" style="font-size: 12.5px; height: 34px;">
                            <i class="bi bi-plus-lg me-1"></i> Configure Signature Now
                        </button>
                    </div>
                @endif

                {{-- Interactive Signature Editor (Only Shown on Demand) --}}
                <div id="signatureEditorContainer" style="display: none;" class="pt-3 mt-3 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-bold text-dark" style="font-size: 14px;">
                            <i class="bi bi-sliders me-1 text-primary"></i> Add / Replace Digital Signature
                        </span>
                        <button type="button" class="btn btn-link btn-sm text-secondary p-0 text-decoration-none" id="btnCloseSigEditor">
                            <i class="bi bi-x-circle me-1"></i> Cancel
                        </button>
                    </div>

                    {{-- Mode Selector Pills --}}
                    <ul class="nav nav-pills mb-3 p-1 rounded-2 border" style="background: #F8FAFC; border-color: #E2E8F0 !important; width: fit-content;">
                        <li class="nav-item">
                            <button class="nav-link active small py-1 px-3 fw-semibold" data-bs-toggle="pill" data-bs-target="#draw-sig-tab" id="pillsDrawTab" style="font-size: 12.5px;">
                                <i class="bi bi-pencil me-1"></i> Draw Signature
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link small py-1 px-3 fw-semibold" data-bs-toggle="pill" data-bs-target="#upload-sig-tab" style="font-size: 12.5px;">
                                <i class="bi bi-cloud-arrow-up me-1"></i> Upload Image
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        {{-- Mode 1: Draw Signature --}}
                        <div class="tab-pane fade show active" id="draw-sig-tab">
                            <div class="mb-2">
                                <canvas id="sig-canvas"></canvas>
                                <div class="d-flex justify-content-between align-items-center text-muted small mt-1" style="font-size: 11.5px;">
                                    <span>Use mouse, touch pad, or stylus to draw your official signature above.</span>
                                    <span id="sigCanvasStatus">Ready</span>
                                </div>
                            </div>
                            <form id="sig-form" action="{{ route('profile.signature') }}" method="POST">
                                @csrf
                                <input type="hidden" name="signature_data" id="signature_data">
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" id="sig-clear" style="height: 36px;">
                                        <i class="bi bi-eraser me-1"></i> Clear
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="btnSaveDrawnSig" style="height: 36px;">
                                        <i class="bi bi-check2 me-1"></i> Save Signature
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Mode 2: Upload Signature --}}
                        <div class="tab-pane fade" id="upload-sig-tab">
                            <form action="{{ route('profile.signature') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="p-4 text-center border rounded-3" style="background: #F8FAFC; border-color: #CBD5E1 !important; border-style: dashed !important;">
                                    <i class="bi bi-cloud-arrow-up fs-2 text-primary mb-2 d-block"></i>
                                    <span class="fw-semibold text-dark d-block mb-1" style="font-size: 13.5px;">Select high-resolution signature image</span>
                                    <span class="text-secondary small d-block mb-3">Transparent PNG or clear white background recommended (Max 2MB).</span>
                                    <div class="d-inline-block">
                                        <input type="file" name="sig_file" id="sigFileInput" accept="image/png,image/jpeg,image/jpg" class="form-control form-control-sm" required>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" style="height: 36px;">
                                        <i class="bi bi-cloud-arrow-up me-1"></i> Upload Signature File
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- RIGHT COLUMN: Account Security & Connected Channels --}}
        <div class="col-12 col-xl-4 col-lg-5 text-start">

            {{-- 7. & 8. ACCOUNT SECURITY CARD --}}
            <div class="enterprise-card">
                <div class="enterprise-card-header">
                    <div>
                        <h2 class="enterprise-card-title">
                            <i class="bi bi-shield-lock-fill text-primary"></i> Account Security
                        </h2>
                        <p class="enterprise-card-desc">Password protection and identity authentication.</p>
                    </div>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #047857; font-weight: 600; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                        Secure
                    </span>
                </div>

                {{-- Password Update Form --}}
                <form action="{{ route('profile.password') }}" method="POST" class="mb-4">
                    @csrf
                    <div class="mb-3">
                        <label class="form-group-label" for="newPassword">
                            <span>New Password <span class="text-danger">*</span></span>
                        </label>
                        <div class="input-group">
                            <input type="password" id="newPassword" name="password" class="form-control enterprise-input" required placeholder="Enter new strong password" minlength="10">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('newPassword', this)" title="Show/Hide Password" style="border-color: #CBD5E1;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-group-label" for="confirmPassword">
                            <span>Confirm New Password <span class="text-danger">*</span></span>
                        </label>
                        <div class="input-group">
                            <input type="password" id="confirmPassword" name="password_confirmation" class="form-control enterprise-input" required placeholder="Re-enter password" minlength="10">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('confirmPassword', this)" title="Show/Hide Password" style="border-color: #CBD5E1;">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Enterprise Password Policy Checklist --}}
                    <div class="p-2 mb-3 rounded-2" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                        <span class="text-dark fw-bold d-block" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em;">Enterprise Policy:</span>
                        <ul class="pwd-req-list">
                            <li class="pwd-req-item"><i class="bi bi-check-circle text-muted"></i> 10+ characters</li>
                            <li class="pwd-req-item"><i class="bi bi-check-circle text-muted"></i> 1 uppercase letter</li>
                            <li class="pwd-req-item"><i class="bi bi-check-circle text-muted"></i> 1 lowercase letter</li>
                            <li class="pwd-req-item"><i class="bi bi-check-circle text-muted"></i> 1 number & 1 symbol</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn btn-outline-primary w-100 fw-bold" style="font-size: 13.5px; height: 38px;">
                        <i class="bi bi-key me-1"></i> Update Password
                    </button>
                </form>

                {{-- Security Metadata Details --}}
                <div class="pt-3 border-top">
                    <span class="form-group-label mb-2">Security Status & Audit</span>
                    
                    <div class="security-meta-item">
                        <span class="security-meta-label">Account Status</span>
                        <span class="security-meta-val text-success">
                            <span class="status-dot-active me-1"></span> Active
                        </span>
                    </div>

                    <div class="security-meta-item">
                        <span class="security-meta-label">Login Verification</span>
                        <span class="security-meta-val text-primary">
                            <i class="bi bi-shield-check me-1"></i> Email OTP Active
                        </span>
                    </div>

                    <div class="security-meta-item">
                        <span class="security-meta-label">Two-Factor App (TOTP)</span>
                        <span class="security-meta-val">
                            @if($user->two_factor_secret)
                                <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Enabled</span>
                            @else
                                <span class="text-muted">Not Configured</span>
                            @endif
                        </span>
                    </div>

                    <div class="security-meta-item">
                        <span class="security-meta-label">Password Last Updated</span>
                        <span class="security-meta-val text-secondary" style="font-size: 12px;">
                            @if($lastPasswordChange)
                                {{ $lastPasswordChange->created_at->format('M d, Y') }}
                            @else
                                Recorded in Audit
                            @endif
                        </span>
                    </div>

                    <div class="security-meta-item">
                        <span class="security-meta-label">Last Sign In</span>
                        <span class="security-meta-val text-secondary" style="font-size: 12px;">
                            @if($lastLogin && !empty($lastLogin->login_time))
                                {{ \Carbon\Carbon::parse($lastLogin->login_time)->format('M d, Y • h:i A') }}
                            @else
                                Current Active Session
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- 4. CONNECTED CHANNELS & TELEGRAM CARD --}}
            <div class="enterprise-card">
                <div class="enterprise-card-header">
                    <div>
                        <h2 class="enterprise-card-title">
                            <i class="bi bi-bell-fill text-primary"></i> Notification Channels
                        </h2>
                        <p class="enterprise-card-desc">External channels linked to your account.</p>
                    </div>
                    @if($user->isTelegramConnected())
                        <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #047857; font-weight: 600; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                            <i class="bi bi-check-circle-fill me-1"></i> Connected
                        </span>
                    @else
                        <span class="badge bg-light text-secondary border" style="font-size: 11px;">
                            Optional
                        </span>
                    @endif
                </div>

                <p class="small text-secondary mb-3">
                    Receive instant system announcements, maintenance alerts, and document notices directly on Telegram.
                </p>

                @if($user->isTelegramConnected())
                    <div class="p-3 rounded-2 mb-3 border" style="background: #F8FAFC; border-color: #E2E8F0 !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small text-secondary">Linked Handle:</span>
                            <span class="small fw-bold text-dark">{{ $user->telegram_username ?? 'Telegram User' }}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="small text-secondary">Linked Since:</span>
                            <span class="small text-secondary fw-semibold">{{ $user->telegram_connected_at?->format('M j, Y') ?? 'Recently' }}</span>
                        </div>
                    </div>

                    <!-- Category Preferences -->
                    <form action="{{ route('telegram.preferences') }}" method="POST" class="mb-3">
                        @csrf
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="telegram_notif_announcements" id="tgAnnounce" {{ $user->telegram_notif_announcements ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="form-check-label small" for="tgAnnounce">System Announcements</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="telegram_notif_documents" id="tgDocs" {{ $user->telegram_notif_documents ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="form-check-label small" for="tgDocs">Document Routing Alerts</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="telegram_notif_urgent" id="tgUrgent" {{ $user->telegram_notif_urgent ? 'checked' : '' }} onchange="this.form.submit()">
                            <label class="form-check-label small" for="tgUrgent">Critical Security Issues</label>
                        </div>
                    </form>

                    <form action="{{ route('telegram.disconnect') }}" method="POST" onsubmit="return confirm('Disconnect your Telegram notifications?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 fw-semibold" style="height: 36px;">
                            <i class="bi bi-x-circle me-1"></i> Disconnect Telegram
                        </button>
                    </form>
                @else
                    <button type="button" id="btnConnectTelegram" class="btn btn-outline-primary w-100 fw-semibold d-flex align-items-center justify-content-center gap-2" style="height: 38px;" onclick="startTelegramConnect()">
                        <i class="bi bi-telegram fs-6 text-primary"></i> Link Telegram Account
                    </button>
                    <div id="tgConnectStatus" class="mt-2 small text-secondary" style="display:none;"></div>
                @endif
            </div>

        </div>

    </div>

</div>
@endsection

@section('scripts')
<script>
    // --- AVATAR IMAGE LIVE PREVIEW ---
    const avatarInput = document.getElementById('avatarFileInput');
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    // Update form preview
                    const formPreview = document.getElementById('formAvatarPreview');
                    const formFallback = document.getElementById('formAvatarFallback');
                    if (formPreview) {
                        formPreview.src = evt.target.result;
                    } else if (formFallback) {
                        formFallback.innerHTML = `<img src="${evt.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
                    }

                    // Update header avatar preview
                    const headerImg = document.getElementById('identityAvatarImg');
                    const headerFallback = document.getElementById('identityAvatarFallback');
                    if (headerImg) {
                        headerImg.src = evt.target.result;
                    } else if (headerFallback) {
                        headerFallback.innerHTML = `<img src="${evt.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
                    }

                    const badge = document.getElementById('avatarSelectedBadge');
                    if (badge) badge.style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // --- DIGITAL SIGNATURE TOGGLE & DRAWING ---
    const btnOpenSigEditor = document.getElementById('btnOpenSigEditor');
    const btnCloseSigEditor = document.getElementById('btnCloseSigEditor');
    const sigEditorContainer = document.getElementById('signatureEditorContainer');
    const canvas = document.getElementById('sig-canvas');
    let ctx = canvas ? canvas.getContext('2d') : null;
    let isDrawing = false;
    let hasDrawn = false;

    function resizeCanvas() {
        if (!canvas) return;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = canvas.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            ctx.scale(ratio, ratio);
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#0F172A';
        }
    }

    if (btnOpenSigEditor && sigEditorContainer) {
        btnOpenSigEditor.addEventListener('click', function() {
            sigEditorContainer.style.display = 'block';
            setTimeout(resizeCanvas, 80);
            sigEditorContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    if (btnCloseSigEditor && sigEditorContainer) {
        btnCloseSigEditor.addEventListener('click', function() {
            sigEditorContainer.style.display = 'none';
        });
    }

    const pillsDrawTab = document.getElementById('pillsDrawTab');
    if (pillsDrawTab) {
        pillsDrawTab.addEventListener('shown.bs.tab', function() {
            setTimeout(resizeCanvas, 50);
        });
    }

    window.addEventListener('resize', resizeCanvas);

    if (canvas && ctx) {
        function getCanvasPos(e) {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        function startSigDraw(e) {
            if (e.cancelable) e.preventDefault();
            isDrawing = true;
            hasDrawn = true;
            const pos = getCanvasPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            const status = document.getElementById('sigCanvasStatus');
            if (status) status.innerText = 'Drawing...';
        }

        function sigDraw(e) {
            if (!isDrawing) return;
            if (e.cancelable) e.preventDefault();
            const pos = getCanvasPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        }

        function stopSigDraw() {
            if (!isDrawing) return;
            isDrawing = false;
            ctx.closePath();
            const status = document.getElementById('sigCanvasStatus');
            if (status) status.innerText = 'Signature captured';
        }

        canvas.addEventListener('mousedown', startSigDraw);
        canvas.addEventListener('mousemove', sigDraw);
        window.addEventListener('mouseup', stopSigDraw);

        canvas.addEventListener('touchstart', startSigDraw, { passive: false });
        canvas.addEventListener('touchmove', sigDraw, { passive: false });
        canvas.addEventListener('touchend', stopSigDraw);

        const sigClearBtn = document.getElementById('sig-clear');
        if (sigClearBtn) {
            sigClearBtn.addEventListener('click', function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
                const status = document.getElementById('sigCanvasStatus');
                if (status) status.innerText = 'Cleared';
            });
        }

        const sigForm = document.getElementById('sig-form');
        if (sigForm) {
            sigForm.addEventListener('submit', function(e) {
                if (!hasDrawn) {
                    e.preventDefault();
                    alert('Please draw your signature on the canvas before saving.');
                    return false;
                }
                document.getElementById('signature_data').value = canvas.toDataURL('image/png');
            });
        }
    }

    // --- PASSWORD VISIBILITY TOGGLE ---
    function togglePasswordVisibility(fieldId, btnEl) {
        const input = document.getElementById(fieldId);
        if (!input) return;
        const icon = btnEl.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    }

    // --- TELEGRAM CONNECT HANDLER ---
    function startTelegramConnect() {
        const btn = document.getElementById('btnConnectTelegram');
        const status = document.getElementById('tgConnectStatus');

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Generating link...';
        }

        if (status) {
            status.style.display = 'block';
            status.className = 'mt-2 small text-secondary';
            status.innerHTML = 'Connecting to Telegram gateway...';
        }

        fetch('{{ route("telegram.connect") }}', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.deep_link) {
                if (status) {
                    status.innerHTML = '<i class="bi bi-info-circle me-1"></i> Opening Telegram... Tap <b>START</b> in Telegram to complete linking.';
                }
                
                window.open(data.deep_link, '_blank');

                if (btn) {
                    btn.innerHTML = '<i class="bi bi-box-arrow-up-right me-1"></i> Open Telegram Bot';
                    btn.disabled = false;
                    btn.onclick = () => window.open(data.deep_link, '_blank');
                }

                let checkCount = 0;
                const pollInterval = setInterval(() => {
                    checkCount++;
                    if (checkCount > 30) {
                        clearInterval(pollInterval);
                        return;
                    }

                    fetch('{{ route("telegram.status") }}', { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(st => {
                        if (st.connected) {
                            clearInterval(pollInterval);
                            window.location.reload();
                        }
                    })
                    .catch(() => {});
                }, 3000);
            } else {
                if (status) {
                    status.className = 'mt-2 small text-danger';
                    status.innerText = data.error || 'Failed to initialize Telegram connection.';
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-telegram fs-6"></i> Link Telegram Account';
                }
            }
        })
        .catch(err => {
            if (status) {
                status.className = 'mt-2 small text-danger';
                status.innerText = 'Unable to reach server. Please try again.';
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-telegram fs-6"></i> Link Telegram Account';
            }
        });
    }
</script>
@endsection