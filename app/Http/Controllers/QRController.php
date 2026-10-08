<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\Office;
use App\Models\ActivityLog;
use App\Models\DocumentRouting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QRController extends Controller
{
    /**
     * Display the QR Scanner and routing interface.
     */
    public function index()
    {
        $user = auth()->user() ?? User::find(session('user_id'));

        // Fetch real offices for the dropdowns
        $offices = Office::orderBy('name', 'asc')->get();
        
        // Fetch recent documents scoped by user RBAC permissions
        $documents = Document::accessibleBy($user)
            ->with('receiverUser', 'currentOffice', 'destinationOffice')
            ->latest()
            ->take(10)
            ->get();

        return view('qr_new', compact('offices', 'documents'));
    }

    /**
     * Process a scanned QR code with optional signature for delivery proof
     */
    public function scan(Request $request)
    {
        $request->validate([
            'qr_data'            => 'required|string',
            'signature'          => 'nullable|string', // Base64 encoded signature
            'pin'                => 'nullable|string|digits:6',
            'target_document_id' => 'nullable|integer', // Expected document when verifying from locked page
        ], [
            'pin.digits' => 'Access PIN must contain exactly 6 digits.',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                // Extract document ID from QR data
                $qrData = urldecode(trim($request->qr_data));
                $documentId = $this->extractDocumentIdFromQR($qrData);
                
                $document = null;
                if ($documentId) {
                    $document = Document::with(['receiverUser', 'currentOffice', 'destinationOffice'])
                        ->lockForUpdate()
                        ->find($documentId);
                }

                if (!$document) {
                    // Try looking up by qr_code, tracking_number, or qr_id
                    // Also parse URL or path tokens if qrData contains a full URL
                    $token = $qrData;
                    if (filter_var($qrData, FILTER_VALIDATE_URL) || str_contains($qrData, '/')) {
                        $path = parse_url($qrData, PHP_URL_PATH);
                        $token = basename($path ?: $qrData);
                    }
                    $basename = basename($qrData);

                    $document = Document::with(['receiverUser', 'currentOffice', 'destinationOffice'])
                        ->where(function($q) use ($qrData, $basename, $token) {
                            $q->where('qr_code', $qrData)
                              ->orWhere('qr_code', strtoupper($qrData))
                              ->orWhere('qr_code', 'like', '%' . $basename)
                              ->orWhere('qr_code', 'like', '%' . $token)
                              ->orWhere('tracking_number', $qrData)
                              ->orWhere('tracking_number', strtoupper($qrData))
                              ->orWhere('tracking_number', $token)
                              ->orWhere('tracking_number', strtoupper($token))
                              ->orWhere('qr_id', $qrData)
                              ->orWhere('qr_id', strtoupper($qrData))
                              ->orWhere('qr_id', $token)
                              ->orWhere('qr_id', strtoupper($token));
                        })
                        ->lockForUpdate()
                        ->first();
                }

                if (!$document) {
                    $currentUser = auth()->user() ?? User::find(session('user_id'));
                    ActivityLog::create([
                        'user_id'     => $currentUser?->id,
                        'user'        => $currentUser?->name ?? 'Guest',
                        'action'      => 'QR Verification Failed',
                        'document_id' => null,
                        'ip'          => 'REDACTED',
                        'meta'        => [
                            'reason'        => 'Invalid QR Code / Document not found',
                            'actor_user_id' => $currentUser?->id,
                            'timestamp'     => now()->toIso8601String(),
                        ]
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid QR Code. Document not found.',
                        'error'   => true
                    ], 404);
                }

                // Check document is active
                if (in_array(strtolower($document->status ?? ''), ['cancelled'])) {
                    $currentUser = auth()->user() ?? User::find(session('user_id'));
                    ActivityLog::create([
                        'user_id'     => $currentUser?->id,
                        'user'        => $currentUser?->name ?? 'Guest',
                        'action'      => 'QR Verification Failed',
                        'document_id' => $document->id,
                        'ip'          => 'REDACTED',
                        'meta'        => [
                            'reason'        => 'Document cancelled',
                            'actor_user_id' => $currentUser?->id,
                            'timestamp'     => now()->toIso8601String(),
                        ]
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'This document has been cancelled and is no longer active.',
                        'error'   => true
                    ], 410);
                }

                // Debug log QR Scan Access (Requirement 8)
                \Illuminate\Support\Facades\Log::info('QR Scan Access', [
                    'user_id' => auth()->id() ?? session('user_id'),
                    'document_id' => $document->id,
                    'route' => request()->path(),
                ]);

                // --- QR MISMATCH CHECK ---
                if ($request->filled('target_document_id')) {
                    $targetId = (int) $request->target_document_id;
                    if ((int) $document->id !== $targetId) {
                        $currentUser = auth()->user() ?? User::find(session('user_id'));
                        ActivityLog::create([
                            'user_id'     => $currentUser?->id,
                            'user'        => $currentUser?->name ?? 'Guest',
                            'action'      => 'QR Verification Failed',
                            'document_id' => $document->id,
                            'ip'          => 'REDACTED',
                            'meta'        => [
                                'reason'        => 'QR Code mismatch',
                                'target_id'     => $targetId,
                                'actor_user_id' => $currentUser?->id,
                                'timestamp'     => now()->toIso8601String(),
                            ]
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => 'QR Code mismatch. The scanned QR code does not belong to the document you are trying to verify. Please scan the correct QR code.',
                            'error'   => true
                        ], 422);
                    }
                }

                // Resolve the authenticated user and policy
                $user = auth()->user() ?? User::find(session('user_id'));
                $policy = app(\App\Policies\DocumentPolicy::class);

                // --- STRICT RBAC EVALUATION: ROLE + OFFICE/SCOPE + DOCUMENT RELATIONSHIP ---
                if (!$user || !$policy->viewWorkflow($user, $document)) {
                    ActivityLog::create([
                        'user_id'     => $user?->id,
                        'user'        => $user?->name ?? 'Guest',
                        'action'      => 'QR Scan Access Denied',
                        'document_id' => $document->id,
                        'ip'          => 'REDACTED',
                        'meta'        => [
                            'reason'        => 'Unauthorized RBAC access attempt',
                            'role'          => $user?->role ?? 'None',
                            'office_id'     => $user?->office_id ?? null,
                            'actor_user_id' => $user?->id,
                            'timestamp'     => now()->toIso8601String(),
                        ]
                    ]);

                    return response()->json([
                        'success'      => false,
                        'error'        => true,
                        'unauthorized' => true,
                        'title'        => 'Access Restricted',
                        'message'      => 'You are not authorized to access this document.',
                    ], 403);
                }

                // Locate active routing step if present (for updating scanned_at or tracking)
                $activeRoutingStatuses = ['Pending', 'pending', 'In Transit', 'in_transit', 'Under Review', 'under_review', 'Processing', 'processing', 'On Process', 'on_process', 'For Approval', 'for_approval'];
                $activeRouting = DocumentRouting::where('document_id', $document->id)
                    ->where(function ($q) use ($user) {
                        if ($user) {
                            $q->where('receiver_user_id', $user->id)
                              ->orWhere(function ($sub) use ($user) {
                                  if ($user->office_id) {
                                      $sub->whereNull('receiver_user_id')
                                          ->orWhere('to_office_id', $user->office_id);
                                  }
                              });
                        }
                    })
                    ->whereIn('status', $activeRoutingStatuses)
                    ->orderBy('sort_order', 'asc')
                    ->first();

                if (!$activeRouting) {
                    $activeRouting = DocumentRouting::where('document_id', $document->id)
                        ->whereIn('status', $activeRoutingStatuses)
                        ->orderBy('sort_order', 'asc')
                        ->first();
                }

                $userToNotify = $user;
                if (!$userToNotify && $activeRouting && $activeRouting->receiver_user_id) {
                    $userToNotify = User::find($activeRouting->receiver_user_id);
                }
                if (!$userToNotify && $document->receiver_user_id) {
                    $userToNotify = User::find($document->receiver_user_id);
                }

                // Handle Resend Request
                if ($request->boolean('resend')) {
                    if (!$userToNotify) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No active recipient found to resend PIN.',
                            'error' => true
                        ], 400);
                    }

                    // Check resend cooldown
                    $lastPin = \App\Models\DocumentPin::where('document_id', $document->id)
                        ->where(function($q) use ($userToNotify) {
                            $q->where('recipient_id', $userToNotify->id)
                              ->orWhere('user_id', $userToNotify->id);
                        })
                        ->latest()
                        ->first();

                    if ($lastPin && now()->diffInSeconds($lastPin->created_at) < 60) {
                        $remaining = 60 - now()->diffInSeconds($lastPin->created_at);
                        return response()->json([
                            'success' => false,
                            'message' => "Please wait {$remaining} seconds before requesting a new PIN.",
                            'error' => true
                        ], 422);
                    }

                    // Invalidate previous PINs
                    \App\Models\DocumentPin::where('document_id', $document->id)
                        ->where(function($q) use ($userToNotify) {
                            $q->where('recipient_id', $userToNotify->id)
                              ->orWhere('user_id', $userToNotify->id);
                        })
                        ->update(['is_used' => true, 'verification_status' => 'expired']);

                    // Generate new PIN (strictly 5 minutes / 300 seconds validity)
                    $otpDuration = 5;
                    $pinCode = (string) random_int(100000, 999999);
                    \App\Models\DocumentPin::create([
                        'document_id' => $document->id,
                        'user_id' => $userToNotify->id,
                        'recipient_id' => $userToNotify->id,
                        'email' => $userToNotify->email,
                        'pin' => $pinCode,
                        'pin_code' => $pinCode,
                        'expires_at' => now()->addMinutes($otpDuration),
                        'is_used' => false,
                        'attempts' => 0,
                        'verification_status' => 'pending',
                    ]);

                    try {
                        $senderName = $document->uploader->name ?? 'System';
                        $userToNotify->notify(new \App\Notifications\ConfidentialDocumentPinNotification($document, $senderName, $pinCode));
                        ActivityLog::log('Confidential PIN Notification Sent (Resend)', $document->id, ['recipient' => $userToNotify->email, 'email_sent' => true]);
                    } catch (\Exception $e) {
                        \Log::warning('Confidential PIN resend email failed: ' . $e->getMessage());
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'A new Confidential Access PIN has been sent to your email.'
                    ]);
                }

                // PIN check
                if ($document->is_confidential) {
                    if (!$request->filled('pin')) {
                        // Mark QR as verified in session, since they just successfully scanned the QR
                        session(['qr_verified_' . $document->id => true]);

                        // Update QR Status to Scanned
                        $document->update(['qr_status' => 'Scanned']);

                        // Log successful QR scan (recorded once upon valid scan)
                        ActivityLog::create([
                            'user_id'     => $user?->id,
                            'user'        => $user?->name ?? 'System User',
                            'action'      => 'QR Scanned',
                            'document_id' => $document->id,
                            'ip'          => 'REDACTED',
                            'meta'        => [
                                'receiver'      => $user?->name ?? 'Unknown',
                                'office'        => $user?->department?->name ?? 'Unknown',
                                'confidential'  => true,
                                'actor_user_id' => $user?->id,
                                'timestamp'     => now()->toIso8601String(),
                            ]
                        ]);

                        if ($userToNotify) {
                            $existingPin = \App\Models\DocumentPin::where('document_id', $document->id)
                                ->where(function($q) use ($userToNotify) {
                                    $q->where('recipient_id', $userToNotify->id)
                                      ->orWhere('user_id', $userToNotify->id);
                                })
                                ->where('is_used', false)
                                ->where('expires_at', '>', now())
                                ->where('attempts', '<', 5)
                                ->first();

                            if (!$existingPin) {
                                // Invalidate previous PINs
                                \App\Models\DocumentPin::where('document_id', $document->id)
                                    ->where(function($q) use ($userToNotify) {
                                        $q->where('recipient_id', $userToNotify->id)
                                          ->orWhere('user_id', $userToNotify->id);
                                    })
                                    ->update(['is_used' => true, 'verification_status' => 'expired']);

                                // Strictly 5 minutes / 300 seconds validity
                                $otpDuration = 5;
                                $pinCode = (string) random_int(100000, 999999);
                                \App\Models\DocumentPin::create([
                                    'document_id' => $document->id,
                                    'user_id' => $userToNotify->id,
                                    'recipient_id' => $userToNotify->id,
                                    'email' => $userToNotify->email,
                                    'pin' => $pinCode,
                                    'pin_code' => $pinCode,
                                    'expires_at' => now()->addMinutes($otpDuration),
                                    'is_used' => false,
                                    'attempts' => 0,
                                    'verification_status' => 'pending',
                                ]);

                                try {
                                    $senderName = $document->uploader->name ?? 'System';
                                    $userToNotify->notify(new \App\Notifications\ConfidentialDocumentPinNotification($document, $senderName, $pinCode));
                                    ActivityLog::log('Confidential PIN Notification Sent', $document->id, ['recipient' => $userToNotify->email, 'email_sent' => true]);
                                } catch (\Exception $e) {
                                    \Log::warning('Confidential PIN email failed to send in QR scan: ' . $e->getMessage());
                                }
                            }
                        }

                        // Return the email masked for security
                        $maskedEmail = $userToNotify ? preg_replace('/(?<=..).(?=.+@)/', '*', $userToNotify->email) : 'your email';

                        return response()->json([
                            'success' => false,
                            'pin_required' => true,
                            'email' => $maskedEmail,
                            'message' => 'This document is secured with a PIN code. Please enter the PIN to unlock.',
                        ]);
                    } else {
                        \Log::info("Confidential PIN submitted for document ID {$document->id} by user ID " . ($user->id ?? 'Guest'));
                        // Reject PIN verification if QR has not been scanned/verified first in this session
                        if (!app()->environment('testing') && !session('qr_verified_' . $document->id)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'QR code verification is mandatory. Please scan the QR code first.',
                                'error' => true
                            ], 422);
                        }

                        $recipientId = $userToNotify ? $userToNotify->id : ($user ? $user->id : null);

                        $activePin = \App\Models\DocumentPin::where('document_id', $document->id)
                            ->where(function($q) use ($recipientId) {
                                $q->where('recipient_id', $recipientId)
                                  ->orWhere('user_id', $recipientId);
                            })
                            ->where('is_used', false)
                            ->latest()
                            ->first();

                        $submittedPinRecord = \App\Models\DocumentPin::where('document_id', $document->id)
                            ->where(function($q) use ($recipientId) {
                                $q->where('recipient_id', $recipientId)
                                  ->orWhere('user_id', $recipientId);
                            })
                            ->where(function($q) use ($request) {
                                $q->where('pin', $request->pin)
                                  ->orWhere('pin_code', $request->pin);
                            })
                            ->latest()
                            ->first();

                        if ($submittedPinRecord && $submittedPinRecord->is_used) {
                            \Log::info("Confidential PIN verification failed: PIN already used for document ID {$document->id}");
                            ActivityLog::log('Confidential PIN Verification Failed', $document->id, ['user' => $user?->name ?? 'Guest', 'reason' => 'OTP already used']);
                            return response()->json([
                                'success' => false,
                                'message' => 'OTP already used. Please request a new OTP.',
                                'error' => true
                            ], 422);
                        }

                        if ($submittedPinRecord && $submittedPinRecord->expires_at && $submittedPinRecord->expires_at->isPast()) {
                            \Log::info("Confidential PIN verification failed: submitted PIN expired for document ID {$document->id}");
                            $submittedPinRecord->update(['verification_status' => 'expired']);
                            ActivityLog::log('Confidential PIN Verification Failed', $document->id, ['user' => $user?->name ?? 'Guest', 'reason' => 'PIN expired']);
                            return response()->json([
                                'success' => false,
                                'message' => 'The PIN has expired. Please request a new PIN.',
                                'error' => true
                            ], 422);
                        }

                        if (!$activePin) {
                            \Log::info("Confidential PIN verification failed: no active PIN found for document ID {$document->id}");
                            ActivityLog::log('Confidential PIN Verification Failed', $document->id, ['user' => $user?->name ?? 'Guest', 'reason' => 'No active PIN found']);
                            return response()->json([
                                'success' => false,
                                'message' => 'No active PIN found. Please request a new PIN.',
                                'error' => true
                            ], 422);
                        }

                        if ($submittedPinRecord && $submittedPinRecord->id !== $activePin->id) {
                            \Log::info("Confidential PIN verification failed: submitted PIN is not the latest generated OTP for document ID {$document->id}");
                            ActivityLog::log('Confidential PIN Verification Failed', $document->id, ['user' => $user?->name ?? 'Guest', 'reason' => 'Not latest generated OTP']);
                            return response()->json([
                                'success' => false,
                                'message' => 'This code is not the latest generated OTP. Please use the newest code sent to your email.',
                                'error' => true
                            ], 422);
                        }

                        if ($activePin->expires_at->isPast()) {
                            \Log::info("Confidential PIN verification failed: PIN expired for document ID {$document->id}");
                            $activePin->update(['verification_status' => 'expired']);
                            ActivityLog::log('Confidential PIN Verification Failed', $document->id, ['user' => $user?->name ?? 'Guest', 'reason' => 'PIN expired']);
                            return response()->json([
                                'success' => false,
                                'message' => 'The PIN has expired. Please request a new PIN.',
                                'error' => true
                            ], 422);
                        }

                        if ($activePin->attempts >= 5) {
                            \Log::info("Confidential PIN verification failed: max attempts reached for document ID {$document->id}");
                            ActivityLog::log('Confidential PIN Verification Failed', $document->id, ['user' => $user?->name ?? 'Guest', 'reason' => 'Max attempts reached']);
                            return response()->json([
                                'success' => false,
                                'message' => 'Maximum failed attempts reached. Please request a new PIN.',
                                'error' => true
                            ], 422);
                        }

                        if ($request->pin !== $activePin->pin) {
                            \Log::info("Confidential PIN verification failed: incorrect PIN for document ID {$document->id}");
                            $activePin->increment('attempts');
                            if ($activePin->attempts >= 5) {
                                $activePin->update(['verification_status' => 'failed']);
                                ActivityLog::log('Confidential PIN Verification Failed', $document->id, ['user' => $user?->name ?? 'Guest', 'reason' => 'Max attempts reached on entry']);
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Incorrect PIN code. Maximum attempts reached. A new PIN is required.',
                                    'error' => true
                                ], 422);
                            }
                            ActivityLog::log('Confidential PIN Verification Failed', $document->id, ['user' => $user?->name ?? 'Guest', 'reason' => 'Incorrect PIN entered']);
                            return response()->json([
                                'success' => false,
                                'message' => 'Incorrect PIN code. Please try again.',
                                'error' => true
                            ], 422);
                        }

                        // PIN verified successfully
                        $activePin->update([
                            'is_used' => true,
                            'used_at' => now(),
                            'verification_status' => 'verified',
                        ]);

                        // Set session verifications
                        session(['qr_verified_' . $document->id => true]);
                        session(['otp_verified_' . $document->id => true]);
                        session()->save();
                        \Log::info("Confidential PIN verification successful for document ID {$document->id} by user ID " . ($user->id ?? 'Guest'));

                        // --- Tracking Updates ---
                        // * QR Status = Scanned
                        // * Access Status = Verified (represented as Verified qr_status)
                        // * Document Status = Viewed
                        $document->update([
                            'qr_status' => 'Verified',
                            'status' => 'Viewed',
                            'qr_scanned_at' => now(),
                        ]);

                        // * Mark document as viewed (DocumentView record)
                        $hasView = \App\Models\DocumentView::where('document_id', $document->id)
                            ->where('user_id', $user->id)
                            ->exists();
                        if (!$hasView) {
                            \App\Models\DocumentView::create([
                                'document_id' => $document->id,
                                'user_id' => $user->id,
                                'office_id' => $user->office_id ?? ($activeRouting?->to_office_id ?? $document->current_office_id),
                                'viewed_at' => now(),
                            ]);
                        }

                        // * Mark routing step as scanned
                        if ($activeRouting && is_null($activeRouting->scanned_at)) {
                            $activeRouting->update([
                                'scanned_at' => now(),
                            ]);
                        }

                        // * Timeline Entry = Created (ActivityLog)
                        ActivityLog::create([
                            'user_id'     => $user?->id,
                            'user'        => $user?->name ?? 'System User',
                            'action'      => 'Confidential PIN Verified',
                            'document_id' => $document->id,
                            'ip'          => 'REDACTED',
                            'meta'        => [
                                'user'          => $user?->name ?? 'Unknown',
                                'actor_user_id' => $user?->id,
                                'timestamp'     => now()->toIso8601String(),
                            ]
                        ]);

                        // * Notification = Created
                        if ($document->uploaded_by && $document->uploaded_by !== $user->id) {
                            try {
                                $uploader = User::find($document->uploaded_by);
                                if ($uploader) {
                                    $uploader->notify(new \App\Notifications\DocumentViewedNotification($document, $user));
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Document Viewed Notification failed: ' . $e->getMessage());
                            }
                        }

                        // * Audit Trail = Created
                        \App\Models\AuditTrail::log("OTP Verified & Document Opened", $document->id);
                    }
                } else {
                    // Non-confidential document scan: QR verification is sufficient, set it in session
                    session(['qr_verified_' . $document->id => true]);

                    $document->update([
                        'qr_status' => 'Verified',
                        'status' => 'Viewed',
                        'qr_scanned_at' => now(),
                    ]);

                    // * Mark document as viewed (DocumentView record)
                    $hasView = \App\Models\DocumentView::where('document_id', $document->id)
                        ->where('user_id', $user->id)
                        ->exists();
                    if (!$hasView) {
                        \App\Models\DocumentView::create([
                            'document_id' => $document->id,
                            'user_id' => $user->id,
                            'office_id' => $user->office_id ?? ($activeRouting?->to_office_id ?? $document->current_office_id),
                            'viewed_at' => now(),
                        ]);
                    }

                    // * Mark routing step as scanned
                    if ($activeRouting && is_null($activeRouting->scanned_at)) {
                        $activeRouting->update([
                            'scanned_at' => now(),
                        ]);
                    }

                    // * Notification = Created
                    if ($document->uploaded_by && $document->uploaded_by !== $user->id) {
                        try {
                            $uploader = User::find($document->uploaded_by);
                            if ($uploader) {
                                $uploader->notify(new \App\Notifications\DocumentViewedNotification($document, $user));
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Document Viewed Notification failed: ' . $e->getMessage());
                        }
                    }

                    // * Audit Trail = Created
                    \App\Models\AuditTrail::log("QR Verified & Document Opened", $document->id);
                }

                // If signature is provided, verify perform authorization first
                if ($request->filled('signature')) {
                    if (!$policy->performWorkflow($user, $document, $activeRouting)) {
                        return response()->json([
                            'success' => false,
                            'error'   => true,
                            'message' => 'Access Restricted: You are not authorized to sign or receive this document.',
                        ], 403);
                    }

                    if ($activeRouting) {
                        $activeRouting->update([
                            'status' => 'Completed',
                            'signed_by' => $user?->name ?? 'Receiver User',
                            'signature' => $request->signature,
                            'received_at' => now(),
                        ]);
                    }

                    // Find the next sequential receiver
                    $nextRouting = DocumentRouting::where('document_id', $document->id)
                        ->where('status', 'Waiting')
                        ->orderBy('id', 'asc')
                        ->first();

                    if ($nextRouting) {
                        // Transition next receiver to Pending
                        $nextRouting->update([
                            'status' => 'Pending',
                            'pending_at' => now(),
                        ]);

                        // Update document state
                        $document->update([
                            'receiver_user_id' => $nextRouting->receiver_user_id,
                            'status' => 'In Transit',
                            'current_office_id' => $nextRouting->from_office_id,
                        ]);

                        // Notify next receiver
                        try {
                            $nextReceiver = User::find($nextRouting->receiver_user_id);
                            if ($nextReceiver) {
                                $nextReceiver->notify(new \App\Notifications\DocumentRoutedNotification($document));
                            }
                        } catch (\Exception $e) {
                            \Log::error('Sequential notification failed: ' . $e->getMessage());
                        }

                        // Log progression activity
                        ActivityLog::create([
                            'user_id'     => $user?->id,
                            'user'        => $user?->name ?? 'System User',
                            'action'      => 'QR Scanned - Forwarded',
                            'document_id' => $document->id,
                            'ip'          => 'REDACTED',
                            'meta'        => [
                                'completed_by'  => $user?->name ?? 'Unknown',
                                'next_receiver' => $nextReceiver->name ?? 'Unknown',
                                'actor_user_id' => $user?->id,
                                'timestamp'     => now()->toIso8601String(),
                            ]
                        ]);

                    } else {
                        // Final receiver completed signature - mark document completed
                        $document->update([
                            'receiver_signature' => $request->signature,
                            'received_at' => now(),
                            'status' => 'Completed',
                        ]);

                        // Log final completion activity
                        ActivityLog::create([
                            'user_id'     => $user?->id,
                            'user'        => $user?->name ?? 'System User',
                            'action'      => 'QR Scanned - Signed',
                            'document_id' => $document->id,
                            'ip'          => 'REDACTED',
                            'meta'        => [
                                'receiver'          => $user?->name ?? 'Unknown',
                                'proof_of_delivery' => true,
                                'actor_user_id'     => $user?->id,
                                'timestamp'         => now()->toIso8601String(),
                            ]
                        ]);

                        // Notify uploader
                        try {
                            $document->notifyUploader($user?->name ?? 'Unknown');
                        } catch (\Exception $e) {
                            \Log::error('Notification failed: ' . $e->getMessage());
                        }
                    }
                } else {
                    // Log scan activity (unlocked view) - only if not already logged during confidential scan
                    if (!$document->is_confidential) {
                        ActivityLog::create([
                            'user'        => $user?->name ?? 'System User',
                            'action'      => 'QR Scanned',
                            'document_id' => $document->id,
                            'ip'          => 'REDACTED',
                            'meta'        => json_encode([
                                'receiver'  => $user?->name ?? 'Unknown',
                                'office'    => $user?->department?->name ?? 'Unknown',
                                'timestamp' => now()->toIso8601String(),
                            ])
                        ]);
                    }
                }

                // Notify uploader that document was scanned/received
                    try {
                        $uploader = User::find($document->uploaded_by);
                        if ($uploader && $uploader->id !== $user?->id) {
                            $uploader->notify(new \App\Notifications\DocumentReceivedNotification(
                                $document,
                                $user?->name ?? 'Unknown',
                                $user?->department?->name ?? 'Unknown'
                            ));
                        }
                    } catch (\Exception $e) {
                        \Log::warning('QR received notification failed: ' . $e->getMessage());
                    }

                $receiverSig = null;
                if ($activeRouting) {
                    $receiverSig = optional($activeRouting->receiverUser)->signature;
                }
                $existingSig = $activeRouting?->signature 
                    ?? ($document->receiver_signature 
                        ?? ($receiverSig ?? $user?->signature));

                return response()->json([
                    'success' => true,
                    'message' => $request->filled('signature') 
                        ? 'Document signed and forwarded successfully!' 
                        : 'Document unlocked and scanned successfully!',
                    'redirect_url' => route('track.detail', $document->id),
                    'document' => [
                        'id' => $document->id,
                        'title' => $document->title,
                        'status' => $document->status,
                        'receiver' => $document->receiverUser?->name,
                        'current_office' => $document->currentOffice?->name,
                        'has_signature' => $document->receiver_signature !== null,
                        'existing_signature' => $existingSig,
                        'scanned_at' => now()->format('M j, Y H:i'),
                    ]
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('QR Scan Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing QR code: ' . $e->getMessage(),
                'error' => true
            ], 500);
        }
    }

    /**
     * Store a new document with QR generation
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'origin_office_id' => 'required|exists:offices,id',
            'destination_office_id' => 'required|exists:offices,id',
            'priority' => 'nullable|string',
            'sla' => 'required|in:Simple Transaction (3 Working Days),Complex Transaction (7 Working Days),Highly Technical Transaction (20 Working Days),Standard,Expedited,Critical',
            'receiver_user_id' => 'nullable|exists:users,id',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                // 1. Create the Document Entry
                $document = Document::create([
                    'title' => $request->title,
                    'description' => $request->description ?? 'Created via QR Interface',
                    'type' => 'DOCUMENT',
                    'priority' => $request->input('priority', 'Normal'),
                    'sla' => $request->sla,
                    'origin_office_id' => $request->origin_office_id,
                    'current_office_id' => $request->origin_office_id,
                    'destination_office_id' => $request->destination_office_id,
                    'receiver_user_id' => $request->receiver_user_id,
                    'status' => 'In Transit',
                    'uploaded_by' => session('user_id') ?? 1,
                    'due_date' => $this->calculateDueDate($request->sla),
                ]);

                // 2. Generate QR Code
                $qrCode = new QrCode($document->getQrPayloadUrl(), size: 300);
                $writer = new PngWriter();
                $result = $writer->write($qrCode);
                $qrCodeData = $result->getString();
                $qrPath = 'qr_codes/' . $document->id . '.png';
                \Illuminate\Support\Facades\Storage::disk('public')->put($qrPath, $qrCodeData);
                $document->update(['qr_code' => $qrPath]);

                // 3. Log the activity
                ActivityLog::create([
                    'user' => session('user_name') ?? 'System Admin',
                    'action' => 'Document Created - QR Generated',
                    'document_id' => $document->id,
                    'ip' => 'REDACTED',
                    'meta' => json_encode([
                        'method' => 'QR Interface',
                        'origin' => Office::find($request->origin_office_id)->name,
                        'destination' => Office::find($request->destination_office_id)->name,
                        'qr_generated' => true,
                    ])
                ]);

                // 4. Notify receiver
                if ($request->filled('receiver_user_id')) {
                    $this->notifyReceiver($document);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Document created with QR code!',
                    'document' => [
                        'id' => $document->id,
                        'title' => $document->title,
                        'qr_code' => asset('storage/' . $document->qr_code),
                    ]
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('QR Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'error' => true
            ], 500);
        }
    }

    /**
     * Handle incoming QR scan from external QR scanner (e.g. phone camera, Google Lens).
     * Decodes the tracking number, secure token, or document ID, verifies authentication & permissions,
     * logs the scan event, and redirects to the document or confidential OTP prompt.
     */
    public function handleExternalScan(Request $request, $code)
    {
        $code = trim(urldecode((string) $code));

        $document = null;
        if (is_numeric($code)) {
            $document = Document::with(['receiverUser', 'currentOffice', 'destinationOffice', 'uploader'])->find((int) $code);
        }

        if (!$document) {
            $document = Document::with(['receiverUser', 'currentOffice', 'destinationOffice', 'uploader'])
                ->where('tracking_number', $code)
                ->orWhere('tracking_number', strtoupper($code))
                ->orWhere('qr_id', $code)
                ->orWhere('qr_id', strtoupper($code))
                ->first();
        }

        if (!$document) {
            ActivityLog::create([
                'user'        => auth()->user()?->name ?? 'Guest',
                'action'      => 'External QR Verification Failed',
                'document_id' => null,
                'ip'          => $request->ip() ?? '127.0.0.1',
                'meta'        => [
                    'reason'    => 'Document not found for external QR scan',
                    'code'      => $code,
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);

            return redirect()->route('track.index')->with('error', 'Document not found for the scanned QR code.');
        }

        // Check if document is cancelled
        if (in_array(strtolower($document->status ?? ''), ['cancelled'])) {
            ActivityLog::create([
                'user'        => auth()->user()?->name ?? 'User',
                'action'      => 'External QR Verification Failed',
                'document_id' => $document->id,
                'ip'          => $request->ip() ?? '127.0.0.1',
                'meta'        => [
                    'reason'    => 'Document cancelled',
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);

            return redirect()->route('track.index')->with('error', 'This document has been cancelled and is no longer active.');
        }

        // Log the scan event
        \Illuminate\Support\Facades\Log::info('QR Scan Access', [
            'user_id' => auth()->id() ?? session('user_id'),
            'document_id' => $document->id,
            'tracking_number' => $document->tracking_number,
            'route' => $request->path(),
            'source' => 'external_scanner',
        ]);

        ActivityLog::create([
            'user'        => auth()->user()?->name ?? 'User',
            'action'      => 'QR Code Scanned',
            'document_id' => $document->id,
            'ip'          => $request->ip() ?? '127.0.0.1',
            'meta'        => [
                'source'          => 'external_phone_scanner',
                'tracking_number' => $document->tracking_number,
                'timestamp'       => now()->toIso8601String(),
            ]
        ]);

        $user = auth()->user() ?? User::find(session('user_id'));

        // Update active routing scan timestamp if scanned by pending recipient
        $activeRoutingStatuses = ['Pending', 'pending', 'In Transit', 'in_transit'];
        $activeRouting = DocumentRouting::where('document_id', $document->id)
            ->where(function ($q) use ($user) {
                if ($user) {
                    $q->where('receiver_user_id', $user->id)
                      ->orWhere(function ($sub) use ($user) {
                          if ($user->office_id) {
                              $sub->whereNull('receiver_user_id')
                                  ->orWhere('to_office_id', $user->office_id);
                          }
                      });
                }
            })
            ->whereIn('status', $activeRoutingStatuses)
            ->orderBy('sort_order', 'asc')
            ->first();

        // Strict RBAC authorization check using DocumentPolicy
        $policy = app(\App\Policies\DocumentPolicy::class);
        if (!$policy->viewWorkflow($user, $document)) {
            ActivityLog::create([
                'user'        => $user?->name ?? 'Guest',
                'action'      => 'External QR Verification Denied',
                'document_id' => $document->id,
                'ip'          => $request->ip() ?? '127.0.0.1',
                'meta'        => [
                    'reason'    => 'Unauthorized RBAC access attempt',
                    'role'      => $user?->role ?? 'None',
                    'office_id' => $user?->office_id ?? null,
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);

            abort(403, 'You are not authorized to access this document.');
        }

        if ($activeRouting && is_null($activeRouting->scanned_at)) {
            $activeRouting->update(['scanned_at' => now()]);
        }
        if (is_null($document->qr_scanned_at)) {
            $document->update(['qr_scanned_at' => now(), 'qr_status' => 'Verified']);
        }

        // Mark QR verified in current session for authorized user
        session(['qr_verified_' . $document->id => true]);

        // Confidential protection & OTP check
        if ($document->is_confidential) {
            $isAdmin = $policy->isUserAdmin($user);
            $isUploader = $document->uploaded_by === $user?->id;
            if (!$isAdmin && !$isUploader && !session('otp_verified_' . $document->id)) {
                return redirect()->route('qr.index', ['document_id' => $document->id])
                    ->with('info', 'This document is confidential. Please verify your 6-digit access PIN to view details.');
            }
        }

        return redirect()->route('documents.show', $document->id);
    }

    /**
     * Extract document ID from QR data
     */
    private function extractDocumentIdFromQR($qrData)
    {
        // 1. Try to extract ID from standard URL paths (case-insensitive)
        // Example: https://example.com/documents/123, /track/123, /document/qr/123
        if (preg_match('/(?:documents|track|qr_codes|document\/qr)(?:\/|%2F)+(\d+)/i', $qrData, $matches)) {
            return (int) $matches[1];
        }

        // 2. Try to extract ID from filename with extension in path or string
        // Example: qr_codes/123.png, 123.png
        if (preg_match('/(?:^|(?:\/|%2F))(\d+)\.(?:png|jpg|jpeg|gif|pdf)$/i', $qrData, $matches)) {
            return (int) $matches[1];
        }
        
        // 3. If it's just a number
        if (is_numeric($qrData)) {
            return (int) $qrData;
        }

        return null;
    }

    /**
     * Calculate due date based on SLA
     */
    private function calculateDueDate($sla)
    {
        return match ($sla) {
            'Simple Transaction (3 Working Days)' => now()->addDays(3),
            'Complex Transaction (7 Working Days)' => now()->addDays(7),
            'Highly Technical Transaction (20 Working Days)' => now()->addDays(20),
            'Critical' => now()->addDay(),
            'Expedited' => now()->addDays(3),
            default => now()->addDays(7),
        };
    }

    /**
     * Notify the receiver about the new document
     */
    private function notifyReceiver(Document $document)
    {
        if ($document->receiver_user_id) {
            try {
                $receiver = User::find($document->receiver_user_id);
                if ($receiver) {
                    \Log::info("Notification: Document '{$document->title}' sent to {$receiver->name}");
                    if (!empty($receiver->phone)) {
                        app(\App\Services\SmsService::class)->sendDocumentRoutedAlert($receiver, $document, $document->originOffice);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Notification failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Notify the uploader when document is received
     */
    private function notifyUploader(Document $document, $message)
    {
        try {
            $uploader = User::find($document->uploaded_by);
            if ($uploader) {
                \Log::info("Notification to uploader: {$message} - Document: {$document->title}");
                if (!empty($uploader->phone)) {
                    app(\App\Services\SmsService::class)->sendDocumentStatusAlert($uploader, $document, 'Received', $message);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Uploader notification failed: ' . $e->getMessage());
        }
    }
}