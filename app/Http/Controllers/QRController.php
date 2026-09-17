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
        // Fetch real offices for the dropdowns
        $offices = Office::orderBy('name', 'asc')->get();
        
        // Fetch recent documents to show status in the scanner UI
        $documents = Document::with('receiverUser', 'currentOffice', 'destinationOffice')
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
                    $basename = basename($qrData);
                    $document = Document::with(['receiverUser', 'currentOffice', 'destinationOffice'])
                        ->where(function($q) use ($qrData, $basename) {
                            $q->where('qr_code', $qrData)
                              ->orWhere('qr_code', strtoupper($qrData))
                              ->orWhere('qr_code', 'like', '%' . $basename)
                              ->orWhere('tracking_number', $qrData)
                              ->orWhere('tracking_number', strtoupper($qrData))
                              ->orWhere('qr_id', $qrData)
                              ->orWhere('qr_id', strtoupper($qrData));
                        })
                        ->lockForUpdate()
                        ->first();
                }

                if (!$document) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid QR Code. Document not found.',
                        'error'   => true
                    ], 404);
                }

                // --- QR MISMATCH CHECK ---
                if ($request->filled('target_document_id')) {
                    $targetId = (int) $request->target_document_id;
                    if ((int) $document->id !== $targetId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'QR Code mismatch. The scanned QR code does not belong to the document you are trying to verify. Please scan the correct QR code.',
                            'error'   => true
                        ], 422);
                    }
                }

                // Resolve the authenticated user
                $user = auth()->user() ?? User::find(session('user_id'));

                // Find active Pending routing record
                $activeRouting = DocumentRouting::where('document_id', $document->id)
                    ->where('receiver_user_id', $user?->id)
                    ->where('status', 'Pending')
                    ->first();

                if (!$activeRouting && $user?->role !== 'ADMIN' && $document->uploaded_by !== $user?->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not the active receiver for this document.',
                        'error' => true
                    ], 403);
                }

                $userToNotify = $user;
                if (!$userToNotify && $activeRouting) {
                    $userToNotify = User::find($activeRouting->receiver_user_id);
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

                    // Generate new PIN
                    $otpDuration = (int) (\DB::table('settings')->where('key', 'otp_expiry')->value('value') ?? 10);
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

                                $otpDuration = (int) (\DB::table('settings')->where('key', 'otp_expiry')->value('value') ?? 10);
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
                        \Log::info("Confidential PIN submitted for document ID {$document->id} by user ID " . ($user->id ?? 'Guest') . " with PIN: {$request->pin}");
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

                        if (!$activePin) {
                            \Log::info("Confidential PIN verification failed: no active PIN found for document ID {$document->id}");
                            ActivityLog::log('Confidential PIN Verification Failed', $document->id, ['user' => $user?->name ?? 'Guest', 'reason' => 'No active PIN found']);
                            return response()->json([
                                'success' => false,
                                'message' => 'No active PIN found. Please request a new PIN.',
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
                        ActivityLog::log('Confidential PIN Verified', $document->id, ['user' => $user?->name ?? 'Guest']);

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
                            'user' => $user?->name ?? 'System User',
                            'action' => 'Confidential PIN Verified',
                            'document_id' => $document->id,
                            'ip' => 'REDACTED',
                            'meta' => json_encode([
                                'user' => $user?->name ?? 'Unknown',
                                'timestamp' => now()->toIso8601String(),
                            ])
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

                    // * Timeline Entry = Created (ActivityLog)
                    ActivityLog::create([
                        'user' => $user?->name ?? 'System User',
                        'action' => 'QR Code Verified',
                        'document_id' => $document->id,
                        'ip' => 'REDACTED',
                        'meta' => json_encode([
                            'user' => $user?->name ?? 'Unknown',
                            'timestamp' => now()->toIso8601String(),
                        ])
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
                    \App\Models\AuditTrail::log("QR Verified & Document Opened", $document->id);
                }

                // If signature is provided, mark as received
                if ($request->filled('signature')) {
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
                            'user' => $user?->name ?? 'System User',
                            'action' => 'Document Forwarded to Next Receiver',
                            'document_id' => $document->id,
                            'ip' => 'REDACTED',
                            'meta' => json_encode([
                                'completed_by' => $user?->name ?? 'Unknown',
                                'next_receiver' => $nextReceiver->name ?? 'Unknown',
                                'timestamp' => now()->toIso8601String(),
                            ])
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
                            'user' => $user?->name ?? 'System User',
                            'action' => 'QR Scanned - Signed',
                            'document_id' => $document->id,
                            'ip' => 'REDACTED',
                            'meta' => json_encode([
                                'receiver' => $user?->name ?? 'Unknown',
                                'proof_of_delivery' => true,
                                'timestamp' => now()->toIso8601String(),
                            ])
                        ]);

                        // Notify uploader
                        try {
                            $document->notifyUploader($user?->name ?? 'Unknown');
                        } catch (\Exception $e) {
                            \Log::error('Notification failed: ' . $e->getMessage());
                        }
                    }
                } else {
                    // Log scan activity (unlocked view)
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
                $qrCode = new QrCode(route('documents.show', $document->id), size: 300);
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
     * Extract document ID from QR data
     */
    private function extractDocumentIdFromQR($qrData)
    {
        // 1. Try to extract ID from standard URL paths (case-insensitive)
        // Example: https://example.com/documents/123 or track/123
        if (preg_match('/(?:documents|track|qr_codes)(?:\/|%2F)+(\d+)/i', $qrData, $matches)) {
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
                // TODO: Implement your notification system (email, SMS, database notification, etc.)
                \Log::info("Notification: Document '{$document->title}' sent to {$receiver->name}");
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
            // TODO: Implement your notification system
            \Log::info("Notification to uploader: {$message} - Document: {$document->title}");
        } catch (\Exception $e) {
            \Log::error('Uploader notification failed: ' . $e->getMessage());
        }
    }
}