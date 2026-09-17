<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\Office;
use App\Models\ActivityLog;
use App\Models\DocumentRouting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Carbon\Carbon;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class DocumentController extends Controller
{
    /**
     * Display a listing of documents (Main Documents Page).
     */
    public function index(Request $request)
    {
        $query = Document::with(['currentOffice', 'originOffice', 'destinationOffice', 'receiverUser.department', 'receiverUsers.department'])->latest();

        if (session('user_role') !== 'ADMIN') {
            $userId = (int) session('user_id');
            // Include documents uploaded by user OR routed to them via document_routings
            $query->where(function ($q) use ($userId) {
                $q->where('uploaded_by', $userId)
                  ->orWhereHas('routings', function ($rq) use ($userId) {
                      $rq->where('receiver_user_id', $userId);
                  });
            });
        }

        $documents = $query->paginate(15);
        $offices = Office::orderBy('name', 'asc')->get();
        $users = User::with('department')->orderBy('name', 'asc')->get();

        return view('documents.index', compact('documents', 'offices', 'users'));
    }

    /**
     * Store a newly created document in storage.
     */
    public function store(Request $request)
    {
        // 1. Pre-validate PHP Upload Configuration Limits
        if ($request->isMethod('post')) {
            if (empty($_POST) && empty($_FILES) && $request->headers->get('content-length') > 0) {
                $maxPost = ini_get('post_max_size');
                $errorMsg = "The uploaded file exceeds the PHP configuration limit (post_max_size: {$maxPost}). Please upload a smaller file.";
                \Log::error('Upload post_max_size Exceeded: content-length = ' . $request->headers->get('content-length'));
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $errorMsg], 422);
                }
                return back()->with('error', $errorMsg);
            }
            
            if (isset($_FILES['file'])) {
                $fileError = $_FILES['file']['error'];
                if ($fileError !== UPLOAD_ERR_OK) {
                    $errorMsg = match ($fileError) {
                        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini (' . ini_get('upload_max_filesize') . ').',
                        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder for file uploads.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
                        default => 'Unknown file upload error.'
                    };
                    \Log::error('File Upload Error: ' . $errorMsg);
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $errorMsg], 422);
                    }
                    return back()->with('error', $errorMsg);
                }
            }
        }

        $request->validate([
            'title'                 => 'required|string|max:255',
            'priority'              => 'nullable|in:Low,Normal,High,Urgent',
            'sla'                   => 'required|in:Simple Transaction (3 Working Days),Complex Transaction (7 Working Days),Highly Technical Transaction (20 Working Days),Standard,Expedited,Critical',
            'origin_office_id'      => 'required|exists:offices,id',
            'destination_office_id' => 'nullable|exists:offices,id',
            'routing_office_ids'    => 'required|array|min:1',
            'routing_office_ids.*'  => 'exists:offices,id',
            'routing_user_ids'      => 'required|array|min:1',
            'routing_user_ids.*'    => 'exists:users,id',
            'file'                  => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png',
            ],
            'is_confidential'       => 'nullable|boolean',
            'category'              => 'required|string|max:255',
            'custom_category'       => 'required_if:category,Others|nullable|string|max:255',
            'description'           => 'required|string|max:1000',
            'tags'                  => 'nullable|string|max:255',
        ], [
            'routing_office_ids.required' => 'Please build a routing sequence with at least one step.',
            'routing_office_ids.min'      => 'Please build a routing sequence with at least one step.',
            'routing_user_ids.required'   => 'Please build a routing sequence with at least one step.',
            'routing_user_ids.min'        => 'Please build a routing sequence with at least one step.',
            'file.required'               => 'Please select a file to upload.',
            'file.mimes'                  => 'Unsupported file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG.',
            'file.max'                    => 'File size must not exceed 10MB.',
            'title.required'              => 'Document title is required.',
        ]);

        // Custom Routing office/user validation check
        $routingOfficeIds = $request->input('routing_office_ids', []);
        $routingUserIds = $request->input('routing_user_ids', []);

        foreach ($routingOfficeIds as $index => $officeId) {
            $userId = $routingUserIds[$index] ?? null;
            if ($userId) {
                $user = User::find($userId);
                if (!$user || $user->office_id != $officeId) {
                    $officeName = Office::find($officeId)?->name ?? 'Selected Office';
                    $userName = $user?->name ?? 'Selected User';
                    $errorMsg = "Routing validation failed: Receiver '{$userName}' does not belong to the office '{$officeName}' in step " . ($index + 1) . ".";
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $errorMsg], 422);
                    }
                    return back()->withErrors(['routing_user_ids' => $errorMsg])->withInput();
                }
            }
        }

        try {
            $file = $request->file('file');
            $fileHash = hash_file('sha256', $file->getRealPath());
            $duplicate = Document::where('file_hash', $fileHash)->first();
            
            $duplicateAction = $request->input('duplicate_action');
            $duplicateDocId = $request->input('duplicate_doc_id');

            // 2. Intelligent Duplicate Upload Warning & Flow
            if ($duplicate && !$duplicateAction) {
                return response()->json([
                    'success' => false,
                    'duplicate' => true,
                    'document_id' => $duplicate->id,
                    'title' => $duplicate->title,
                    'tracking_number' => $duplicate->tracking_number ?? $duplicate->qr_id,
                    'message' => 'Duplicate file detected. This exact file already exists in the system.'
                ], 409); // Conflict HTTP status
            }

            return DB::transaction(function () use ($request, $file, $fileHash, $duplicate, $duplicateAction, $duplicateDocId) {
                $uniqueName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('documents', $uniqueName, 'public');

                $senderName = session('user_name') ?? 'System';
                $uploader = User::find(session('user_id') ?? 1);

                // Handle Overwrite or Versioning
                if ($duplicate && ($duplicateAction === 'overwrite' || $duplicateAction === 'version')) {
                    $document = Document::findOrFail($duplicateDocId);
                    
                    if ($duplicateAction === 'overwrite') {
                        if ($document->file_path) {
                            Storage::disk('public')->delete($document->file_path);
                        }
                        $document->update([
                            'file_path' => $path,
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'file_hash' => $fileHash,
                            'uploaded_at' => now(),
                            'processed_at' => now(),
                        ]);
                        ActivityLog::log('Document Overwritten (Duplicate Upload)', $document->id);
                        $msg = 'Document overwritten successfully!';
                    } else {
                        $newVersion = $document->version + 1;
                        if ($document->file_path) {
                            Storage::disk('public')->delete($document->file_path);
                        }
                        $document->update([
                            'file_path' => $path,
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'file_hash' => $fileHash,
                            'version' => $newVersion,
                            'uploaded_at' => now(),
                            'processed_at' => now(),
                        ]);
                        ActivityLog::log("New Version Created (Version {$newVersion})", $document->id);
                        $msg = "New document version (v{$newVersion}) uploaded successfully!";
                    }

                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success' => true,
                            'message' => $msg,
                            'redirect' => route('documents.index')
                        ]);
                    }
                    return redirect()->route('documents.index')->with('success', $msg);
                }

                // Normal Upload Path
                $dueDate = null;
                if (Schema::hasColumn('documents', 'due_date')) {
                    $dueDate = match ($request->sla) {
                        'Simple Transaction (3 Working Days)' => Carbon::now()->addDays(3),
                        'Complex Transaction (7 Working Days)' => Carbon::now()->addDays(7),
                        'Highly Technical Transaction (20 Working Days)' => Carbon::now()->addDays(20),
                        'Critical' => Carbon::now()->addDay(),
                        'Expedited' => Carbon::now()->addDays(3),
                        default => Carbon::now()->addDays(7),
                    };
                }

                $qrId = 'QR-' . strtoupper(uniqid('DOC-', true));
                $trackingNumber = 'TRK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

                $isConfidential = $request->has('is_confidential');
                $plainOtp = null;
                $hashedPin = null;

                $routingOfficeIds = $request->input('routing_office_ids', []);
                $routingUserIds   = $request->input('routing_user_ids', []);

                $destinationOfficeId = !empty($routingOfficeIds) ? end($routingOfficeIds) : ($request->destination_office_id ?? $request->final_office_id);
                $activeReceiverId = !empty($routingUserIds) ? $routingUserIds[0] : null;

                $destinationOffices = [];
                for ($i = 0; $i < count($routingOfficeIds); $i++) {
                    $destinationOffices[] = [
                        'office_id' => (int) $routingOfficeIds[$i],
                        'user_id'   => (int) $routingUserIds[$i],
                    ];
                }

                $categoryValue = $request->category;
                if ($categoryValue === 'Others') {
                    $categoryValue = $request->custom_category;
                }

                $documentData = [
                    'title' => $request->title,
                    'description' => $request->description ?? 'No description provided',
                    'type' => strtoupper($file->getClientOriginalExtension()),
                    'priority' => $request->input('priority', 'Normal'),
                    'origin_office_id' => $request->origin_office_id,
                    'current_office_id' => $request->origin_office_id,
                    'destination_office_id' => $destinationOfficeId,
                    'destination_offices' => $destinationOffices,
                    'receiver_user_id' => $activeReceiverId,
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'file_hash' => $fileHash,
                    'version' => 1,
                    'tracking_number' => $trackingNumber,
                    'is_confidential' => $isConfidential,
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'category' => $categoryValue,
                    'tags' => $request->tags,
                    'status' => 'Pending',
                    'uploaded_by' => session('user_id') ?? 1,
                    'access_pin' => $hashedPin,
                    'uploaded_at' => now(),
                    'processed_at' => now(),
                ];

                if (Schema::hasColumn('documents', 'sla')) {
                    $documentData['sla'] = $request->sla;
                }

                if (Schema::hasColumn('documents', 'due_date') && $dueDate) {
                    $documentData['due_date'] = $dueDate;
                }

                if (Schema::hasColumn('documents', 'qr_id')) {
                    $documentData['qr_id'] = $qrId;
                }

                $document = Document::create($documentData);

                // Generate QR Code
                $qrCode = new QrCode(route('documents.show', $document->id), size: 300);
                $writer = new PngWriter();
                $result = $writer->write($qrCode);
                $qrCodeData = $result->getString();
                $qrPath = 'qr_codes/' . $document->id . '.png';
                Storage::disk('public')->put($qrPath, $qrCodeData);
                $document->update(['qr_code' => $qrPath]);

                // Initial Routing History
                if (Schema::hasColumn('documents', 'routing_history')) {
                    $routingHistory = [
                        [
                            'office_id' => $request->origin_office_id,
                            'status' => 'Origin',
                            'timestamp' => now()->toIso8601String(),
                        ]
                    ];
                    $document->update(['routing_history' => $routingHistory]);
                }

                // Add Sequence Hops (with SLA and sender tracking)
                $routingApprovalTypes = $request->input('routing_approval_types', []);
                $routingSignaturesRequired = $request->input('routing_signatures_required', []);

                // Compute SLA hours from document SLA type
                $slaHours = match ($request->sla) {
                    'Simple Transaction (3 Working Days)'           => 72,   // 3 days
                    'Complex Transaction (7 Working Days)'          => 168,  // 7 days
                    'Highly Technical Transaction (20 Working Days)' => 480,  // 20 days
                    'Critical'                                      => 24,
                    'Expedited'                                     => 72,
                    default                                         => 168,  // Standard = 7 days
                };

                if (!empty($routingUserIds)) {
                    foreach ($routingUserIds as $index => $receiverId) {
                        $fromOfficeId = ($index === 0) ? $request->origin_office_id : $routingOfficeIds[$index - 1];
                        $toOfficeId = $routingOfficeIds[$index];

                        $appType = isset($routingApprovalTypes[$index]) ? $routingApprovalTypes[$index] : 'sequential';
                        $sigReq = isset($routingSignaturesRequired[$index]) ? (bool) $routingSignaturesRequired[$index] : true;

                        DocumentRouting::create([
                            'document_id'        => $document->id,
                            'from_office_id'     => $fromOfficeId,
                            'to_office_id'       => $toOfficeId,
                            'receiver_user_id'   => $receiverId,
                            'sender_user_id'     => session('user_id'),  // uploader is the sender for step 0
                            'status'             => ($index === 0) ? 'Pending' : 'Waiting',
                            'pending_at'         => ($index === 0) ? now() : null,
                            'approval_type'      => $appType,
                            'signature_required' => $sigReq,
                            'sort_order'         => $index + 1,
                            'notes'              => 'Initial upload receiver',
                            'action_label'       => 'Document Submitted',
                            'sla_hours'          => $slaHours,
                            'sla_due_at'         => now()->addHours($slaHours),
                            'sla_status'         => 'on_time',
                        ]);
                    }
                }

                ActivityLog::log('Document Created', $document->id, ['filename' => $file->getClientOriginalName(), 'file_hash' => $fileHash]);

                // 10. Notifications: Notify Uploader, Active Receiver, and Admins (safely handled so mail transport errors do not abort document upload)
                try {
                    // 1. Notify Uploader
                    if ($uploader) {
                        $uploader->notify(new \App\Notifications\DocumentRoutedNotification($document, $senderName, 'uploader'));
                    }

                    // Get final recipient ID
                    $finalReceiverId = !empty($routingUserIds) ? end($routingUserIds) : null;

                    // 2. Notify Active Receiver
                    if ($activeReceiverId) {
                        $receiver = User::find($activeReceiverId);
                        if ($receiver) {
                            $receiver->notify(new \App\Notifications\DocumentRoutedNotification($document, $senderName, 'receiver', null));
                            ActivityLog::log('Routed Notification Sent', $document->id, ['recipient' => $receiver->email, 'email_sent' => true]);
                        }
                    }

                    // 3. Notify Administrators
                    $admins = User::where('role', 'ADMIN')->get();
                    foreach ($admins as $admin) {
                        $admin->notify(new \App\Notifications\DocumentRoutedNotification($document, $senderName, 'admin'));
                    }
                } catch (\Exception $ne) {
                    \Log::warning('Document notification dispatch encountered an issue: ' . $ne->getMessage(), [
                        'document_id' => $document->id,
                        'exception'   => $ne->getMessage(),
                    ]);
                }

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success'       => true,
                        'message'       => 'Document uploaded and initialized successfully!',
                        'redirect'      => route('documents.index'),
                        'qr_label_url'  => route('documents.qr-label', $document->id),
                        'document_id'   => $document->id,
                    ]);
                }

                return redirect()->route('documents.index')->with('success', 'Document uploaded and initialized successfully!');
            });
        } catch (\Exception $e) {
            \Log::error('Document Upload Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => session('user_id'),
                'file_name' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : null,
                'file_size' => $request->hasFile('file') ? $request->file('file')->getSize() : null,
                'request_inputs' => $request->except(['file', 'access_pin'])
            ]);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Display specific document tracking details.
     * Points to resources/views/documents/show.blade.php
     */
    public function show($id)
    {
        // Eager load activityLogs and receiver to show the routing history timeline
        $document = Document::with(['originOffice', 'currentOffice', 'destinationOffice', 'receiverUser.department', 'receiverUsers.department', 'activityLogs' => function($query) {
            $query->latest();
        }, 'routings.fromOffice', 'routings.toOffice', 'views.user'])->findOrFail($id);

        $user = auth()->user() ?? User::find(session('user_id'));
        
        $inRoutingHistory = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user?->id)
            ->exists();
            
        $isUploader = $document->uploaded_by === $user?->id;
        $isAdmin = $user?->role === 'ADMIN';
        $isVpaa = $user?->username === 'vpaa' || $user?->email === 'vpaa@naap.org';
        
        if (!$isAdmin && !$isVpaa && !$isUploader && !$inRoutingHistory) {
            abort(403, 'You are not authorized to view this document.');
        }

        $isLocked = false;

        // Find if this user is the active receiver
        $activeRouting = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user?->id)
            ->where('status', 'Pending')
            ->first();

        if ($activeRouting) {
            if (is_null($activeRouting->scanned_at)) {
                $isLocked = true;
            } else {
                // If confidential, require current session verification to prevent bypasses
                if ($document->is_confidential && !app()->environment('testing')) {
                    if (!session('qr_verified_' . $document->id) || !session('otp_verified_' . $document->id)) {
                        $isLocked = true;
                        // Reset scanned_at to force re-verification in DB
                        $activeRouting->update(['scanned_at' => null]);
                    }
                }
            }
        }

        if (!$isLocked && $activeRouting && $document->qr_status === 'Verified') {
            $document->update(['qr_status' => 'Accessed']);
        }

        \Log::info("Document loaded: ID {$document->id}, isLocked=" . ($isLocked ? "true" : "false"));


        // Record Seen View if recipient clicks the notification (represented by from_notification URL param)
        if ($user && request()->has('from_notification')) {
            $isRecipient = DocumentRouting::where('document_id', $document->id)
                ->where('receiver_user_id', $user->id)
                ->exists();

            if ($isRecipient) {
                $hasView = \App\Models\DocumentView::where('document_id', $document->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if (!$hasView) {
                    $absoluteFirstView = !\App\Models\DocumentView::where('document_id', $document->id)
                        ->exists();

                    $officeId = null;
                    $userRouting = DocumentRouting::where('document_id', $document->id)
                        ->where('receiver_user_id', $user->id)
                        ->first();
                    
                    if ($userRouting) {
                        $officeId = $userRouting->to_office_id;
                    } else {
                        $officeId = $user->office_id;
                    }

                    \App\Models\DocumentView::create([
                        'document_id' => $document->id,
                        'user_id' => $user->id,
                        'office_id' => $officeId,
                        'viewed_at' => now(),
                    ]);

                    // Notify uploader on first absolute view by a receiver in routing sequence
                    if ($absoluteFirstView && $userRouting && $document->uploaded_by && $document->uploaded_by !== $user->id) {
                        try {
                            $uploader = User::find($document->uploaded_by);
                            if ($uploader) {
                                $uploader->notify(new \App\Notifications\DocumentViewedNotification($document, $user));
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Document Viewed Notification failed: ' . $e->getMessage());
                        }
                    }
                }

                $userOfficeName = $user->office?->name ?? ($user->department?->name ?? 'Unknown');
                ActivityLog::log('DOCUMENT VIEWED', $document->id, [
                    'viewer' => $user->name,
                    'office' => $userOfficeName,
                ]);
            }
        }

        $views = \App\Models\DocumentView::with(['user', 'office'])
            ->where('document_id', $document->id)
            ->get();

        $activeStep = $document->routings->where('status', 'Pending')->first();

        return view('documents.show', compact('document', 'isLocked', 'views', 'activeStep'));
    }

    /**
     * Return the QR label print view for a document.
     */
    public function qrLabel($id)
    {
        $document = Document::with(['originOffice', 'destinationOffice', 'uploader', 'receiverUser'])
            ->findOrFail($id);

        $user = auth()->user() ?? User::find(session('user_id'));
        $isAdmin = $user?->role === 'ADMIN';
        $isUploader = $document->uploaded_by === $user?->id;
        $inHistory = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user?->id)
            ->exists();

        if (!$isAdmin && !$isUploader && !$inHistory) {
            abort(403, 'You are not authorized to view this label.');
        }

        return view('documents.qr_label', compact('document'));
    }

    /**
     * Download the document securely after scanning and authorization checks.
     */
    public function download($id)
    {
        $document = Document::findOrFail($id);
        $user = auth()->user() ?? User::find(session('user_id'));
        
        $inRoutingHistory = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user?->id)
            ->exists();
            
        $isUploader = $document->uploaded_by === $user?->id;
        $isAdmin = $user?->role === 'ADMIN';
        $isVpaa = $user?->username === 'vpaa' || $user?->email === 'vpaa@naap.org';
        
        if (!$isAdmin && !$isVpaa && !$isUploader && !$inRoutingHistory) {
            abort(403, 'You are not authorized to download this document.');
        }
        
        $activeRouting = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user?->id)
            ->where('status', 'Pending')
            ->first();

        if ($activeRouting) {
            if (is_null($activeRouting->scanned_at)) {
                return back()->with('error', 'You must scan the document QR code before you can download it.');
            }
            if ($document->is_confidential && !app()->environment('testing')) {
                if (!session('qr_verified_' . $document->id) || !session('otp_verified_' . $document->id)) {
                    $activeRouting->update(['scanned_at' => null]);
                    return back()->with('error', 'Access denied. Both QR code and OTP verification are required to download.');
                }
            }
        }
        
        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found in storage.');
        }

        // Record Seen View on download (first intentional open file interaction)
        if ($user) {
            $isRecipient = DocumentRouting::where('document_id', $document->id)
                ->where('receiver_user_id', $user->id)
                ->exists();

            if ($isRecipient) {
                $hasView = \App\Models\DocumentView::where('document_id', $document->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if (!$hasView) {
                    $absoluteFirstView = !\App\Models\DocumentView::where('document_id', $document->id)
                        ->exists();

                    $officeId = null;
                    $userRouting = DocumentRouting::where('document_id', $document->id)
                        ->where('receiver_user_id', $user->id)
                        ->first();
                    
                    if ($userRouting) {
                        $officeId = $userRouting->to_office_id;
                    } else {
                        $officeId = $user->office_id;
                    }

                    \App\Models\DocumentView::create([
                        'document_id' => $document->id,
                        'user_id' => $user->id,
                        'office_id' => $officeId,
                        'viewed_at' => now(),
                    ]);

                    // Notify uploader on first absolute view by a receiver in routing sequence
                    if ($absoluteFirstView && $userRouting && $document->uploaded_by && $document->uploaded_by !== $user->id) {
                        try {
                            $uploader = User::find($document->uploaded_by);
                            if ($uploader) {
                                $uploader->notify(new \App\Notifications\DocumentViewedNotification($document, $user));
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Document Viewed Notification failed: ' . $e->getMessage());
                        }
                    }
                }

                $userOfficeName = $user->office?->name ?? ($user->department?->name ?? 'Unknown');
                ActivityLog::log('DOCUMENT VIEWED', $document->id, [
                    'viewer' => $user->name,
                    'office' => $userOfficeName,
                ]);
            }
        }
        
        return Storage::disk('public')->download($document->file_path, $document->title . '.' . pathinfo($document->file_path, PATHINFO_EXTENSION));
    }

    /**
     * Handle the Document Tracking page logic (Route: track.index).
     */
    public function trackIndex(Request $request)
    {
        $query = Document::with(['originOffice', 'currentOffice', 'destinationOffice', 'uploader', 'receiverUser.department', 'views.user']);

        if (session('user_role') !== 'ADMIN') {
            $userId = (int) session('user_id');
            $query->where(function ($q) use ($userId) {
                $q->where('uploaded_by', $userId)
                  ->orWhere('receiver_user_id', $userId)
                  ->orWhereHas('routings', function ($rq) use ($userId) {
                      $rq->where('receiver_user_id', $userId);
                  });
            });
        }

        // Search logic for Title, Tracking ID, Category, Tags
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('tracking_number', 'like', "%$search%")
                  ->orWhere('qr_id', 'like', "%$search%")
                  ->orWhere('category', 'like', "%$search%")
                  ->orWhere('tags', 'like', "%$search%");
            });
        }

        // Date range filtering
        $dateField = $request->input('date_type', 'created_at') === 'due_date' ? 'due_date' : 'created_at';
        if ($request->filled('from_date')) {
            $fromDate = \Carbon\Carbon::parse($request->from_date)->startOfDay();
            $query->whereDate($dateField, '>=', $fromDate);
        }

        if ($request->filled('to_date')) {
            $toDate = \Carbon\Carbon::parse($request->to_date)->endOfDay();
            $query->whereDate($dateField, '<=', $toDate);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // QR Status filter
        if ($request->filled('qr_status')) {
            $query->where('qr_status', $request->qr_status);
        }

        // Department filter (searches offices corresponding to department string or ID)
        if ($request->filled('department_id')) {
            $deptId = $request->department_id;
            $query->where(function($q) use ($deptId) {
                $q->whereHas('currentOffice', function($co) use ($deptId) {
                    $co->where('department', $deptId)->orWhere('id', $deptId);
                })->orWhereHas('destinationOffice', function($do) use ($deptId) {
                    $do->where('department', $deptId)->orWhere('id', $deptId);
                });
            });
        }

        // Sender filter
        if ($request->filled('uploader_id')) {
            $query->where('uploaded_by', $request->uploader_id);
        }

        // Recipient filter
        if ($request->filled('receiver_id')) {
            $query->where('receiver_user_id', $request->receiver_id);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSortFields = ['title', 'created_at', 'due_date', 'status', 'qr_status'];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $documents = $query->paginate(10);
        $offices = Office::orderBy('name', 'asc')->get();
        $users = User::orderBy('name', 'asc')->get();
        
        return view('track', compact('documents', 'offices', 'users'));
    }

    /**
     * Handle the Activity Logs page logic (Route: activity.index).
     */
    public function activityIndex(Request $request)
    {
        $this->authorizeAdmin();

        $query = ActivityLog::with(['document.originOffice', 'document.destinationOffice']);

        // Search by document title or user
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('document', function($d) use ($search) {
                    $d->where('title', 'like', "%$search%");
                })->orWhere('user', 'like', "%$search%");
            });
        }

        // Date range filtering
        if ($request->filled('from_date')) {
            $fromDate = \Carbon\Carbon::parse($request->from_date)->startOfDay();
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($request->filled('to_date')) {
            $toDate = \Carbon\Carbon::parse($request->to_date)->endOfDay();
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Action filter
        if ($request->filled('action')) {
            $action = $request->action;
            $query->whereRaw('LOWER(action) LIKE ?', ["%$action%"]);
        }

        $logs = $query->latest()->paginate(15);
        
        return view('activity', compact('logs'));
    }

    protected function authorizeAdmin()
    {
        if (session('user_role') !== 'ADMIN') {
            abort(403, 'Administrator privileges are required to access this page.');
        }
    }

    /**
     * Remove the specified document from storage.
     */
    public function destroy($id)
    {
        $document = Document::findOrFail($id);

        if (session('user_role') !== 'ADMIN' && $document->uploaded_by !== session('user_id')) {
            abort(403, 'You are not authorized to delete this document.');
        }

        // Delete the physical file from storage
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('documents.index')->with('success', 'Document deleted successfully.');
    }

    /**
     * Check document status for real-time updates (API endpoint)
     */
    public function checkStatus($id)
    {
        $document = Document::with(['routings' => function($query) {
            $query->latest()->limit(1);
        }])->findOrFail($id);

        // Get the last routing update
        $lastRouting = $document->routings()->latest()->first();
        
        return response()->json([
            'status' => $document->status,
            'current_office' => $document->currentOffice?->name,
            'last_update' => $lastRouting?->updated_at ?? $document->updated_at,
            'received_at' => $document->received_at,
            'updated' => $document->updated_at->diffInMinutes(now()) < 1 // True if updated in last minute
        ]);
    }

    /**
     * Regenerate the secure temporary access PIN/OTP for a confidential document.
     */
    public function regeneratePin($id)
    {
        $user = auth()->user() ?? User::find(session('user_id'));
        $document = Document::findOrFail($id);

        if ($document->is_confidential && !session('qr_verified_' . $document->id)) {
            return back()->with('error', 'QR code verification is mandatory. Please scan the QR code first.');
        }
        
        $isActiveReceiver = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user?->id)
            ->where('status', 'Pending')
            ->exists();
            
        $isAdmin = $user?->role === 'ADMIN';

        if (!$isAdmin && !$isActiveReceiver) {
            abort(403, 'You are not authorized to regenerate/resend the PIN.');
        }

        $recipientId = $isActiveReceiver ? $user->id : $document->receiver_user_id;
        $recipient = User::find($recipientId);
        if (!$recipient) {
            return back()->with('error', 'No active recipient found to resend PIN.');
        }

        // Invalidate previous PINs for this user and document
        \App\Models\DocumentPin::where('document_id', $document->id)
            ->where(function($q) use ($recipient) {
                $q->where('user_id', $recipient->id)
                  ->orWhere('recipient_id', $recipient->id);
            })
            ->update(['is_used' => true, 'verification_status' => 'expired']);

        $otp = (string) random_int(100000, 999999);
        \App\Models\DocumentPin::create([
            'document_id' => $document->id,
            'user_id' => $recipient->id,
            'recipient_id' => $recipient->id,
            'email' => $recipient->email,
            'pin' => $otp,
            'pin_code' => $otp,
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,
            'attempts' => 0,
            'verification_status' => 'pending',
        ]);

        ActivityLog::log('Document Access PIN Regenerated', $document->id);

        try {
            $senderName = $user->name ?? 'System';
            $recipient->notify(new \App\Notifications\ConfidentialDocumentPinNotification($document, $senderName, $otp));
            ActivityLog::log('Confidential PIN Notification Sent (Resend)', $document->id, ['recipient' => $recipient->email, 'email_sent' => true]);
        } catch (\Exception $e) {
            \Log::warning('PIN regeneration notification failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Confidential Access PIN regenerated successfully! A new notification has been sent to the recipient.');
    }

    /**
     * Handle workflow status actions (Approve, Reject, Under Review, etc.)
     */
    public function workflowAction(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Pending,Received,Under Review,Approved,Accepted,Endorsed,Rejected,Completed,Cancelled,Archived,Returned,Reverted',
            'notes'  => 'nullable|string|max:1000',
            'signature_option' => 'nullable|in:profile,draw,upload',
            'signature_data'   => 'nullable|string', // base64 representation
            'signature_file'   => 'nullable|image|max:2048', // file upload
            'save_to_profile'  => 'nullable|boolean',
        ]);

        $newStatus = $request->input('status');
        $remarks = $request->input('notes') ?? '';

        if (in_array($newStatus, ['Rejected', 'Returned', 'Reverted']) && empty($remarks)) {
            return back()->withErrors(['notes' => 'Remarks or reason is mandatory for rejecting, returning, or reverting a document.']);
        }

        $document = Document::findOrFail($id);
        $user = auth()->user() ?? User::find(session('user_id'));

        // Check auth: Admin or active receiver in document routing
        $activeRouting = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user?->id)
            ->where('status', 'Pending')
            ->first();

        $isAdmin = $user?->role === 'ADMIN';

        if (!$isAdmin && !$activeRouting) {
            abort(403, 'You are not the active receiver or authorized to perform workflow actions.');
        }

        $signaturePath = null;
        $requiresSig = ($newStatus === 'Accepted') || ($activeRouting && $activeRouting->signature_required && in_array($newStatus, ['Completed', 'Approved', 'Accepted', 'Endorsed']));

        if ($requiresSig || $request->filled('signature_option')) {
            $option = $request->input('signature_option');
            
            if ($requiresSig && !$option) {
                return back()->with('error', 'A digital signature is required to proceed with this workflow action.');
            }

            if ($option === 'profile') {
                if (!$user || empty($user->signature)) {
                    return back()->with('error', 'No profile signature found. Please choose another signature option or save one in your profile.');
                }
                $signaturePath = $user->signature;
            } elseif ($option === 'draw') {
                $base64Data = $request->input('signature_data');
                if (empty($base64Data) || !str_contains($base64Data, 'data:image')) {
                    return back()->with('error', 'Blank drawn signature. Please sign on the canvas first.');
                }
                // Save drawn signature base64 as a file
                try {
                    $imageData = explode(',', $base64Data)[1];
                    $decodedImage = base64_decode($imageData);
                    $filename = 'signatures/doc_' . $document->id . '_' . time() . '.png';
                    Storage::disk('public')->put($filename, $decodedImage);
                    $signaturePath = $filename;
                } catch (\Exception $e) {
                    return back()->with('error', 'Failed to save drawn signature: ' . $e->getMessage());
                }
            } elseif ($option === 'upload') {
                if (!$request->hasFile('signature_file')) {
                    return back()->with('error', 'Please upload a signature image file.');
                }
                try {
                    $file = $request->file('signature_file');
                    $filename = 'signatures/doc_' . $document->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('signatures', basename($filename), 'public');
                    $signaturePath = $path;
                } catch (\Exception $e) {
                    return back()->with('error', 'Failed to upload signature image: ' . $e->getMessage());
                }
            }

            // Save to profile if checked
            if ($request->boolean('save_to_profile') && $signaturePath && $user) {
                $user->update(['signature' => $signaturePath]);
            }
        }

        DB::transaction(function () use ($document, $activeRouting, $newStatus, $remarks, $user, $isAdmin, $signaturePath) {
            // Map status to human-readable action label
            $actionLabel = match($newStatus) {
                'Received'     => 'Document Received',
                'Under Review' => 'Under Review',
                'Approved'     => 'Approved & Forwarded',
                'Accepted'     => 'Accepted',
                'Endorsed'     => 'Endorsed',
                'Rejected'     => 'Rejected',
                'Returned'     => 'Returned for Revision',
                'Reverted'     => 'Reverted',
                'Completed'    => 'Completed',
                'Cancelled'    => 'Cancelled',
                'Archived'     => 'Archived',
                default        => $newStatus,
            };

            // Update routing step if user is the active receiver
            if ($activeRouting) {
                $routingUpdate = [
                    'status'       => $newStatus,
                    'notes'        => $remarks,
                    'received_at'  => now(),
                    'action_label' => $actionLabel,
                ];
                if (in_array($newStatus, ['Completed', 'Approved', 'Accepted', 'Endorsed', 'Rejected', 'Returned', 'Reverted'])) {
                    $routingUpdate['released_at'] = now();
                }
                if (in_array($newStatus, ['Completed', 'Approved', 'Accepted', 'Endorsed'])) {
                    $routingUpdate['signed_by'] = $user->name;
                    if ($signaturePath) {
                        $routingUpdate['signature'] = $signaturePath;
                    } elseif ($user->signature) {
                        $routingUpdate['signature'] = $user->signature;
                    }
                }
                $activeRouting->update($routingUpdate);
            }


            // Update document timestamps based on new status
            $docUpdate = [
                'status' => $newStatus,
                'routing_notes' => $remarks,
            ];

            switch ($newStatus) {
                case 'Approved':
                    $docUpdate['approved_at'] = now();
                    break;
                case 'Rejected':
                    $docUpdate['rejected_at'] = now();
                    break;
                case 'Completed':
                    $docUpdate['completed_at'] = now();
                    $docUpdate['received_at'] = now();
                    if ($signaturePath) {
                        $docUpdate['receiver_signature'] = $signaturePath;
                    } elseif ($user && $user->signature) {
                        $docUpdate['receiver_signature'] = $user->signature;
                    }
                    break;
                case 'Accepted':
                    $docUpdate['received_at'] = now();
                    if ($signaturePath) {
                        $docUpdate['receiver_signature'] = $signaturePath;
                    } elseif ($user && $user->signature) {
                        $docUpdate['receiver_signature'] = $user->signature;
                    }
                    break;
                case 'Archived':
                    $docUpdate['archived_at'] = now();
                    break;
                case 'Pending':
                case 'Received':
                case 'Under Review':
                    break;
            }

            // Always synchronize current_office_id with the active routing step's to_office
            // so the Document Route tracking map reflects the latest location immediately
            if ($activeRouting && $activeRouting->to_office_id) {
                $docUpdate['current_office_id'] = $activeRouting->to_office_id;
            }

            // Handle "Returned" or "Reverted" - automatically return only to the office that requires changes
            if ($newStatus === 'Returned' || $newStatus === 'Reverted') {
                $currentOrder = $activeRouting ? $activeRouting->sort_order : 1;
                $prevRouting = DocumentRouting::where('document_id', $document->id)
                    ->where('sort_order', '<', $currentOrder)
                    ->orderBy('sort_order', 'desc')
                    ->first();

                if ($prevRouting) {
                    $prevRouting->update([
                        'status'         => 'Pending',
                        'pending_at'     => now(),
                        'sender_user_id' => $user->id,
                        'action_label'   => $newStatus === 'Reverted' ? 'Reverted' : 'Returned for Revision',
                    ]);
                    if ($activeRouting) {
                        $activeRouting->update(['status' => 'Waiting', 'released_at' => now(), 'pending_at' => null]);
                    }

                    $docUpdate['receiver_user_id'] = $prevRouting->receiver_user_id;
                    $docUpdate['current_office_id'] = $prevRouting->to_office_id;
                    $docUpdate['status'] = $newStatus;
                    
                    $document->update($docUpdate);

                    ActivityLog::log($newStatus === 'Reverted' ? 'Document Reverted' : 'Document Returned to Previous Step', $document->id, [
                        'returned_by'   => $user->name,
                        'to_receiver'   => $prevRouting->receiverUser?->name ?? 'Unknown',
                        'from_office'   => $activeRouting?->toOffice?->name ?? 'Unknown',
                        'to_office'     => $prevRouting->fromOffice?->name ?? 'Unknown',
                        'notes'         => $remarks,
                        'timestamp'     => now()->toIso8601String(),
                    ]);

                    try {
                        $prevReceiver = User::find($prevRouting->receiver_user_id);
                        if ($prevReceiver) {
                            $prevReceiver->notify(
                                new \App\Notifications\DocumentRevertedNotification(
                                    $document,
                                    $user->name,
                                    $activeRouting?->toOffice?->name ?? 'Office',
                                    $prevRouting->fromOffice?->name ?? 'Previous Office',
                                    $remarks
                                )
                            );
                        }
                        // Notify uploader about revert
                        if ($document->uploaded_by && $document->uploaded_by !== $user->id) {
                            $uploader = User::find($document->uploaded_by);
                            if ($uploader) {
                                $uploader->notify(
                                    new \App\Notifications\DocumentRevertedNotification(
                                        $document,
                                        $user->name,
                                        $activeRouting?->toOffice?->name ?? 'Office',
                                        $prevRouting->fromOffice?->name ?? 'Previous Office',
                                        $remarks
                                    )
                                );
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Revert/Return notification failed: ' . $e->getMessage());
                    }

                    return;
                }
            }

            // Handle Approved & Parallel Approval Hops check (also handles Accepted / Endorsed)
            if (in_array($newStatus, ['Approved', 'Accepted', 'Endorsed'])) {
                $currentOrder = $activeRouting ? $activeRouting->sort_order : 0;
                
                // Are there other parallel steps at the same sort_order that are NOT approved/completed?
                $pendingParallel = DocumentRouting::where('document_id', $document->id)
                    ->where('sort_order', $currentOrder)
                    ->where('id', '!=', $activeRouting ? $activeRouting->id : 0)
                    ->whereNotIn('status', ['Approved', 'Completed', 'Accepted', 'Endorsed'])
                    ->exists();

                if ($pendingParallel) {
                    $docUpdate['status'] = 'In Transit';
                    $document->update($docUpdate);
                    
                    ActivityLog::log("Document {$newStatus} by Parallel Receiver", $document->id, [
                        'action_by' => $user->name,
                        'notes' => $remarks
                    ]);
                    return;
                }

                // If all parallel steps at current level are approved/accepted/endorsed, find the next minimum sort_order
                $nextRoutingGroup = DocumentRouting::where('document_id', $document->id)
                    ->where('sort_order', '>', $currentOrder)
                    ->where('status', 'Waiting')
                    ->orderBy('sort_order', 'asc')
                    ->get();

                if ($nextRoutingGroup->isNotEmpty()) {
                    $nextOrder = $nextRoutingGroup->first()->sort_order;
                    $nextSteps = DocumentRouting::where('document_id', $document->id)
                        ->where('sort_order', $nextOrder)
                        ->get();

                    foreach ($nextSteps as $step) {
                        $step->update([
                            'status'         => 'Pending',
                            'pending_at'     => now(),
                            'sender_user_id' => $user->id,
                            'action_label'   => 'Pending Review',
                        ]);
                    }

                    $firstNext = $nextSteps->first();
                    $docUpdate['receiver_user_id'] = $firstNext->receiver_user_id;
                    $docUpdate['current_office_id'] = $firstNext->from_office_id;
                    $docUpdate['status'] = $newStatus === 'Accepted' ? 'Accepted' : 'In Transit';
                    $docUpdate['forwarded_at'] = now();

                    $document->update($docUpdate);

                    ActivityLog::log("Document {$newStatus} & Forwarded to Next Step", $document->id, [
                        'action_by'     => $user->name,
                        'from_office'   => $activeRouting?->toOffice?->name ?? 'Unknown',
                        'to_office'     => $firstNext->toOffice?->name ?? 'Unknown',
                        'to_receiver'   => $firstNext->receiverUser?->name ?? 'Unknown',
                        'notes'         => $remarks,
                        'timestamp'     => now()->toIso8601String(),
                    ]);

                    foreach ($nextSteps as $step) {
                        try {
                            $nextReceiver = User::find($step->receiver_user_id);
                            if ($nextReceiver) {
                                $nextReceiver->notify(
                                    new \App\Notifications\DocumentRoutedNotification($document, $user->name, null, null)
                                );
                            }
                        } catch (\Exception $e) {}
                    }
                    return;
                } else {
                    $docUpdate['status'] = 'Completed';
                    $docUpdate['completed_at'] = now();
                    $docUpdate['received_at'] = now();
                    if ($signaturePath) {
                        $docUpdate['receiver_signature'] = $signaturePath;
                    } elseif ($user && $user->signature) {
                        $docUpdate['receiver_signature'] = $user->signature;
                    }
                }
            }

            $document->update($docUpdate);

            $metaData = [
                'action_by'     => $user->name,
                'user_id'       => $user->id,
                'from_office'   => $activeRouting?->toOffice?->name ?? ($document->currentOffice?->name ?? 'Unknown'),
                'department'    => $activeRouting?->toOffice?->name ?? ($document->currentOffice?->name ?? 'Unknown'),
                'new_status'    => $newStatus,
                'notes'         => $remarks,
                'timestamp'     => now()->toIso8601String(),
            ];
            if ($signaturePath) {
                $metaData['signature'] = $signaturePath;
            } elseif ($user && $user->signature) {
                $metaData['signature'] = $user->signature;
            }

            // Timeline Entry = Added
            $logAction = ($newStatus === 'Accepted') ? 'Document Accepted' : ('Workflow Action: ' . $newStatus);
            ActivityLog::log($logAction, $document->id, $metaData);

            // Audit Trail = Added
            \App\Models\AuditTrail::log("Document " . $newStatus . ": " . $document->title, $document->id);

            // Notify uploader on final completion/rejection
            if (in_array($newStatus, ['Completed', 'Rejected', 'Cancelled', 'Archived'])) {
                try {
                    if ($newStatus === 'Rejected') {
                        $uploader = User::find($document->uploaded_by);
                        if ($uploader) {
                            $uploader->notify(new \App\Notifications\DocumentRejectedNotification($document, $user->name, $activeRouting?->toOffice?->name ?? 'Office', $remarks));
                        }
                        
                        // Notify all previous receivers
                        $prevReceiverIds = DocumentRouting::where('document_id', $document->id)
                            ->where('sort_order', '<', $activeRouting?->sort_order ?? 99)
                            ->pluck('receiver_user_id')
                            ->unique();
                        foreach ($prevReceiverIds as $prevId) {
                            $rUser = User::find($prevId);
                            if ($rUser && $rUser->id !== $user->id) {
                                $rUser->notify(new \App\Notifications\DocumentRejectedNotification($document, $user->name, $activeRouting?->toOffice?->name ?? 'Office', $remarks));
                            }
                        }
                    } else {
                        $document->notifyUploader($user->name);
                    }
                } catch (\Exception $e) {
                    \Log::error('Uploader notification failed: ' . $e->getMessage());
                }
            }

            // Notify uploader on acceptance/endorsement
            if (in_array($newStatus, ['Accepted', 'Endorsed'])) {
                try {
                    $uploader = User::find($document->uploaded_by);
                    if ($uploader) {
                        $uploader->notify(new \App\Notifications\DocumentAcceptedNotification($document, $user->name, $activeRouting?->toOffice?->name ?? 'Office', $remarks));
                    }
                } catch (\Exception $e) {
                    \Log::error('Uploader acceptance notification failed: ' . $e->getMessage());
                }
            }

            // Notify receiver when status is Received or Under Review (ARTA transparency)
            if (in_array($newStatus, ['Received', 'Under Review'])) {
                try {
                    $uploader = User::find($document->uploaded_by);
                    if ($uploader && $uploader->id !== $user->id) {
                        $uploader->notify(new \App\Notifications\DocumentReceivedNotification(
                            $document,
                            $user->name,
                            $activeRouting?->toOffice?->name ?? 'Unknown'
                        ));
                    }
                } catch (\Exception $e) {
                    \Log::warning('Received notification failed: ' . $e->getMessage());
                }
            }
        });

        return back()->with('success', 'Document status updated successfully to ' . $newStatus . '!');
    }

    /**
     * Smart category recommendations
     */
    public function suggestCategory(Request $request)
    {
        $category = $request->input('category');
        
        $suggestions = [
            'Class Schedule' => [
                'priority' => 'Normal',
                'sla' => 'Simple Transaction (3 Working Days)',
                'offices' => ['Program Chairs', 'Office of the Deans'],
                'notes' => 'Suggested sequence: Program Chairs -> Office of the Deans'
            ],
            'Faculty Workload' => [
                'priority' => 'Normal',
                'sla' => 'Simple Transaction (3 Working Days)',
                'offices' => ['Office of the Deans', 'Registrar'],
                'notes' => 'Suggested sequence: Office of the Deans -> Registrar'
            ],
            'Operational Plans' => [
                'priority' => 'High',
                'sla' => 'Complex Transaction (7 Working Days)',
                'offices' => ['Campus Directors', 'Office of the President'],
                'notes' => 'Suggested sequence: Campus Directors -> Office of the President'
            ],
            'Endorsements' => [
                'priority' => 'High',
                'sla' => 'Simple Transaction (3 Working Days)',
                'offices' => ['Office of the Deans', 'Office of the President'],
                'notes' => 'Suggested sequence: Office of the Deans -> Office of the President'
            ],
            'Payrolls' => [
                'priority' => 'Urgent',
                'sla' => 'Simple Transaction (3 Working Days)',
                'offices' => ['Human Resources (HR)', 'Budget Office', 'Accounting Office', 'Vice President of Finance'],
                'notes' => 'Suggested sequence: HR -> Budget -> Accounting -> VP Finance'
            ],
            'Finance Related Documents' => [
                'priority' => 'High',
                'sla' => 'Complex Transaction (7 Working Days)',
                'offices' => ['Budget Office', 'Accounting Office', 'Vice President of Finance'],
                'notes' => 'Suggested sequence: Budget Office -> Accounting Office -> VP Finance'
            ]
        ];

        $suggestion = $suggestions[$category] ?? [
            'priority' => 'Normal',
            'sla' => 'Simple Transaction (3 Working Days)',
            'offices' => [],
            'notes' => ''
        ];

        $officeData = [];
        foreach ($suggestion['offices'] as $officeName) {
            $office = Office::where('name', $officeName)->first();
            if ($office) {
                // Get users in this office department
                $users = User::where('department_id', $office->id)->orWhere('role', 'USER')->take(3)->get();
                $officeData[] = [
                    'id' => $office->id,
                    'name' => $office->name,
                    'users' => $users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
                ];
            }
        }
        $suggestion['office_details'] = $officeData;

        return response()->json($suggestion);
    }

    public function forwardDocument(Request $request, $id)
    {
        $request->validate([
            'new_receiver_user_id' => 'required|exists:users,id',
            'reason'               => 'required|string|max:500',
        ]);

        $document = Document::findOrFail($id);
        $user = auth()->user() ?? User::find(session('user_id'));

        $activeRouting = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user?->id)
            ->where('status', 'Pending')
            ->first();

        $isAdmin = $user?->role === 'ADMIN';

        if (!$isAdmin && !$activeRouting) {
            abort(403, 'You are not authorized to forward this document.');
        }

        $newReceiver = User::findOrFail($request->new_receiver_user_id);
        $targetOfficeId = $activeRouting ? $activeRouting->to_office_id : $document->current_office_id;
        if ($targetOfficeId && $newReceiver->office_id != $targetOfficeId) {
            $officeName = Office::find($targetOfficeId)?->name ?? 'the current office';
            return back()->withErrors([
                'new_receiver_user_id' => "Routing validation failed: Forwarded receiver '{$newReceiver->name}' does not belong to the target office '{$officeName}'."
            ])->withInput();
        }

        $oldReceiverName = $activeRouting ? $user->name : ($document->receiverUser->name ?? 'System');

        DB::transaction(function () use ($document, $activeRouting, $newReceiver, $oldReceiverName, $request, $user) {
            if ($activeRouting) {
                $activeRouting->update([
                    'status' => 'Forwarded',
                    'notes'  => $request->reason,
                ]);

                DocumentRouting::create([
                    'document_id'            => $document->id,
                    'from_office_id'         => $activeRouting->from_office_id,
                    'to_office_id'           => $activeRouting->to_office_id,
                    'receiver_user_id'       => $newReceiver->id,
                    'forwarded_from_user_id' => $user->id,
                    'status'                 => 'Pending',
                    'approval_type'          => $activeRouting->approval_type,
                    'signature_required'     => $activeRouting->signature_required,
                    'sort_order'             => $activeRouting->sort_order,
                    'notes'                  => 'Forwarded: ' . $request->reason,
                ]);
            }

            $document->update([
                'receiver_user_id' => $newReceiver->id,
                'status'           => 'In Transit',
            ]);

            ActivityLog::create([
                'user'        => $user->name ?? 'System',
                'action'      => 'Document Forwarded',
                'document_id' => $document->id,
                'ip'          => 'REDACTED',
                'meta'        => json_encode([
                    'original_receiver' => $oldReceiverName,
                    'forwarded_to'      => $newReceiver->name,
                    'reason'            => $request->reason,
                    'timestamp'         => now()->toIso8601String(),
                ]),
            ]);

            try {
                $newReceiver->notify(
                    new \App\Notifications\DocumentRoutedNotification($document, $user->name ?? 'System', 'receiver')
                );
            } catch (\Exception $e) {
                \Log::warning('Forwarding notification failed: ' . $e->getMessage());
            }
        });

        return redirect()->route('documents.show', $document->id)->with('success', 'Document forwarded to ' . $newReceiver->name . ' successfully!');
    }
}