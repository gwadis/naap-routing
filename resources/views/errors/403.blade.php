@extends('layouts.app')

@section('title', 'Access Restricted')

@section('content')
@php
    $rawMsg = trim($exception?->getMessage() ?? '');
    $lowerMsg = strtolower($rawMsg);

    // Map context-specific messages according to Requirement 14
    if (str_contains($lowerMsg, 'administrator') || str_contains($lowerMsg, 'admin')) {
        $primaryMessage = 'Administrator privileges are required to access this page.';
        $secondaryMessage = "You don't have permission to access this page. This page is available only to authorized administrators.";
    } elseif (str_contains($lowerMsg, 'download')) {
        $primaryMessage = 'You are not authorized to download this document.';
        $secondaryMessage = 'Document downloads are restricted to authorized recipients and handlers.';
    } elseif (str_contains($lowerMsg, 'tracking')) {
        $primaryMessage = 'You are not authorized to view the tracking information for this document.';
        $secondaryMessage = 'Tracking details are restricted to designated offices and authorized participants.';
    } elseif (str_contains($lowerMsg, 'view this document') || str_contains($lowerMsg, 'view this label') || str_contains($lowerMsg, 'document passport')) {
        $primaryMessage = 'You are not authorized to view this document.';
        $secondaryMessage = 'This document is restricted to its assigned office, participants, or authorized handlers.';
    } elseif (str_contains($lowerMsg, 'qr') || str_contains($lowerMsg, 'access this document')) {
        $primaryMessage = 'You are not authorized to access this document.';
        $secondaryMessage = 'Access requires an authorized role and legitimate relationship to this document.';
    } elseif (str_contains($lowerMsg, 'perform') || str_contains($lowerMsg, 'route') || str_contains($lowerMsg, 'forward') || str_contains($lowerMsg, 'action')) {
        $primaryMessage = 'You are not authorized to perform this action.';
        $secondaryMessage = 'You are not the designated recipient or approver for this workflow step.';
    } elseif (!empty($rawMsg) && $rawMsg !== 'This action is unauthorized.') {
        $primaryMessage = $rawMsg;
        $secondaryMessage = "You don't have permission to access this resource.";
    } else {
        $primaryMessage = "You don't have permission to access this resource.";
        $secondaryMessage = "This resource is restricted by role-based access control.";
    }
@endphp

<div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 200px); padding: 30px 0;">
    <div class="card border-0 shadow-sm text-center" style="max-width: 500px; width: 100%; padding: 40px 32px !important; border-radius: var(--radius-lg); background: var(--panel); border: 1px solid var(--panel-border) !important;">
        <!-- Brand Badge -->
        <div class="mb-4">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill" style="background: rgba(15, 23, 42, 0.05); border: 1px solid rgba(15, 23, 42, 0.08);">
                <div style="width: 18px; height: 18px; background: var(--accent-navy); color: #fff; border-radius: 4px; font-size: 10px; font-weight: 800; display: grid; place-items: center;">NA</div>
                <span class="fw-bold text-dark" style="font-size: 11px; letter-spacing: -0.2px;">NAAP ROUTING</span>
                <span class="text-muted" style="font-size: 9px; font-weight: 700; letter-spacing: 0.5px;">ENTERPRISE</span>
            </div>
        </div>

        <!-- Lock / Shield Icon -->
        <div class="d-inline-flex align-items-center justify-content-center mx-auto mb-3 rounded-circle" style="width: 72px; height: 72px; background: rgba(244, 63, 94, 0.1); color: var(--danger); border: 1px solid rgba(244, 63, 94, 0.2);">
            <i class="bi bi-shield-lock-fill" style="font-size: 32px;"></i>
        </div>

        <!-- Title -->
        <h4 class="fw-bold mb-2" style="font-size: 20px; color: var(--text-main); letter-spacing: -0.02em;">
            Access Restricted
        </h4>

        <!-- Context-specific Message -->
        <p class="text-dark fw-semibold mb-1" style="font-size: 14px; line-height: 1.5;">
            {!! $primaryMessage !!}
        </p>
        @if(!empty($secondaryMessage))
            <p class="text-muted mb-4 small" style="font-size: 12.5px; line-height: 1.5;">
                {!! $secondaryMessage !!}
            </p>
        @endif

        <!-- Actions -->
        <div class="d-flex align-items-center justify-content-center gap-2">
            <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ route('dashboard') }}'" class="btn btn-outline-secondary px-3" style="font-size: 13px; font-weight: 600;">
                <i class="bi bi-arrow-left me-1"></i> Go Back
            </button>
            <a href="{{ route('dashboard') }}" class="btn btn-primary px-3" style="font-size: 13px; font-weight: 600;">
                <i class="bi bi-grid-1x2-fill me-1"></i> Return to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
