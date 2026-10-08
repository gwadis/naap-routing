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
        $user = auth()->user() ?? User::find(session('user_id'));
        $role = session('user_role') ?? $user?->role;
        $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($role);
        $query = Document::accessibleBy($user)->with([
            'currentOffice',
            'originOffice',
            'destinationOffice',
            'receiverUser.department',
            'receiverUsers.department',
            'uploader',
            'routings' => function ($rq) {
                $rq->with(['toOffice', 'fromOffice', 'receiverUser.department'])->orderBy('sort_order', 'asc');
            }
        ]);

        // 1. Comprehensive Server-Side Search (Case-Insensitive across relevant fields)
        if ($request->filled('search') || $request->filled('q')) {
            $search = trim((string) ($request->input('search') ?: $request->input('q')));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhereHas('uploader', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('receiverUser', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('receiverUsers', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('originOffice', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('currentOffice', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('destinationOffice', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // 2. Real Workflow Status Filter (Supporting Drill-downs from Analytics)
        if ($request->filled('status')) {
            $rawStatus = strtolower(trim((string) $request->input('status')));
            $statusMap = [
                'pending'      => ['Pending'],
                'in_transit'   => ['In Transit'],
                'received'     => ['Received'],
                'processing'   => ['Processing', 'Under Review', 'On Process'],
                'for_approval' => ['For Approval', 'Pending Approval'],
                'approved'     => ['Approved', 'Accepted', 'Endorsed'],
                'completed'    => ['Completed'],
                'reverted'     => ['Reverted', 'Returned'],
                'rejected'     => ['Rejected'],
                'archived'     => ['Archived'],
            ];

            if ($rawStatus === 'overdue') {
                $query->whereNotNull('due_date')
                      ->where('due_date', '<', now())
                      ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
            } elseif ($rawStatus === 'in_process') {
                $query->whereIn('status', ['Pending', 'In Transit', 'Under Review', 'On Process', 'Forwarded', 'Received', 'Processing']);
            } elseif ($rawStatus === 'for_approval') {
                $query->whereIn('status', ['For Approval', 'Pending Approval']);
            } elseif ($rawStatus === 'viewed') {
                $query->where(function($q) {
                    $q->where('status', 'Viewed')->orWhereHas('views');
                });
            } elseif ($rawStatus === 'active_qr') {
                $query->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                      ->where(function($q) {
                          $q->whereNotNull('qr_code')->where('qr_code', '!=', '')
                            ->orWhereNotNull('qr_id')->where('qr_id', '!=', '');
                      });
            } elseif (isset($statusMap[$rawStatus])) {
                $query->whereIn('status', $statusMap[$rawStatus]);
            } else {
                $query->where('status', $request->input('status'));
            }
        }

        // 3. Priority Filter
        if ($request->filled('priority')) {
            $rawPriority = ucfirst(strtolower(trim((string) $request->input('priority'))));
            $query->where('priority', $rawPriority);
        }

        // 4. Office Filter (Drill-down from Office Bottlenecks / Workload)
        if ($request->filled('office_id')) {
            $officeFilterId = (int) $request->input('office_id');
            $query->where(function ($q) use ($officeFilterId) {
                $q->where('current_office_id', $officeFilterId)
                  ->orWhere('origin_office_id', $officeFilterId)
                  ->orWhere('destination_office_id', $officeFilterId)
                  ->orWhereHas('routings', function ($rq) use ($officeFilterId) {
                      $rq->where('to_office_id', $officeFilterId)
                        ->orWhere('from_office_id', $officeFilterId);
                  });
            });
        }
        if ($request->filled('current_office_id')) {
            $query->where('current_office_id', (int) $request->input('current_office_id'));
        }
        if ($request->filled('origin_office_id')) {
            $query->where('origin_office_id', (int) $request->input('origin_office_id'));
        }
        if ($request->filled('destination_office_id')) {
            $query->where('destination_office_id', (int) $request->input('destination_office_id'));
        }

        // Receiver Filter
        if ($request->filled('receiver_id')) {
            $receiverId = (int) $request->input('receiver_id');
            $query->where(function($q) use ($receiverId) {
                $q->where('receiver_user_id', $receiverId)
                  ->orWhereHas('routings', fn($rq) => $rq->where('receiver_user_id', $receiverId));
            });
        }

        // Category Filter (Drill-down from Reports Category Analytics)
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Confidential / Restricted Filter
        if ($request->filled('is_confidential') || $request->filled('confidential')) {
            $confVal = $request->input('is_confidential', $request->input('confidential'));
            if ($confVal === '1' || $confVal === 'true' || $confVal === 'yes') {
                $query->where('is_confidential', true);
            } elseif ($confVal === '0' || $confVal === 'false' || $confVal === 'no') {
                $query->where(function($q) {
                    $q->where('is_confidential', false)->orWhereNull('is_confidential');
                });
            }
        }

        // SLA Condition Filter
        if ($request->filled('sla_condition') || $request->filled('sla_status')) {
            $slaCond = strtolower(trim((string) ($request->input('sla_condition') ?: $request->input('sla_status'))));
            if ($slaCond === 'overdue') {
                $query->whereNotNull('due_date')
                      ->where('due_date', '<', now())
                      ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
            } elseif ($slaCond === 'nearing_sla' || $slaCond === 'nearing') {
                $query->whereNotNull('due_date')
                      ->where('due_date', '>', now())
                      ->where('due_date', '<=', now()->addHours(48))
                      ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
            } elseif ($slaCond === 'on_track') {
                $query->whereNotNull('due_date')
                      ->where('due_date', '>', now()->addHours(48))
                      ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
            } elseif ($slaCond === 'na' || $slaCond === 'none') {
                $query->whereNull('due_date');
            }
        }

        // Date Range Filter
        if ($request->filled('created_from') || $request->filled('date_from')) {
            $from = $request->input('created_from', $request->input('date_from'));
            $query->whereDate('created_at', '>=', $from);
        }
        if ($request->filled('created_to') || $request->filled('date_to')) {
            $to = $request->input('created_to', $request->input('date_to'));
            $query->whereDate('created_at', '<=', $to);
        }

        // Filter by Action Required categories
        if ($request->filled('filter')) {
            $filter = strtolower(trim((string) $request->input('filter')));
            if ($filter === 'awaiting_receipt') {
                $query->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])->whereNull('received_at');
            } elseif ($filter === 'awaiting_processing') {
                $query->whereNotNull('received_at')->whereIn('status', ['Pending', 'Under Review', 'Processing', 'Received', 'In Transit']);
            } elseif ($filter === 'awaiting_approval') {
                $query->whereIn('status', ['For Approval', 'Pending Approval']);
            } elseif ($filter === 'awaiting_signature') {
                $query->whereHas('routings', function($rq) {
                    $rq->where('signature_required', true)->whereNull('signed_by')->whereNull('released_at');
                });
            } elseif ($filter === 'nearing_sla') {
                $query->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                      ->whereNotNull('due_date')
                      ->where('due_date', '>', now())
                      ->where('due_date', '<=', now()->addHours(24));
            } elseif ($filter === 'overdue') {
                $query->whereNotNull('due_date')
                      ->where('due_date', '<', now())
                      ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
            }
        }

        // Filter by Document Aging category
        if ($request->filled('aging')) {
            $aging = strtolower(trim((string) $request->input('aging')));
            $query->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
            if ($aging === '0_1h' || $aging === '0-1 hour') {
                $query->where(DB::raw('COALESCE(received_at, created_at)'), '>=', now()->subHours(1));
            } elseif ($aging === '1_4h' || $aging === '1-4 hours') {
                $query->where(DB::raw('COALESCE(received_at, created_at)'), '<', now()->subHours(1))
                      ->where(DB::raw('COALESCE(received_at, created_at)'), '>=', now()->subHours(4));
            } elseif ($aging === '4_8h' || $aging === '4-8 hours') {
                $query->where(DB::raw('COALESCE(received_at, created_at)'), '<', now()->subHours(4))
                      ->where(DB::raw('COALESCE(received_at, created_at)'), '>=', now()->subHours(8));
            } elseif ($aging === '8_24h' || $aging === '8-24 hours') {
                $query->where(DB::raw('COALESCE(received_at, created_at)'), '<', now()->subHours(8))
                      ->where(DB::raw('COALESCE(received_at, created_at)'), '>=', now()->subHours(24));
            } elseif ($aging === '1_3d' || $aging === '1-3 days') {
                $query->where(DB::raw('COALESCE(received_at, created_at)'), '<', now()->subHours(24))
                      ->where(DB::raw('COALESCE(received_at, created_at)'), '>=', now()->subDays(3));
            } elseif ($aging === '3plus' || $aging === '3+ days') {
                $query->where(DB::raw('COALESCE(received_at, created_at)'), '<', now()->subDays(3));
            }
        }

        // 5. Meaningful Real Database Sorting
        $sortBy = $request->input('sort_by', 'updated_at');
        $order = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        switch ($sortBy) {
            case 'title':
            case 'name':
                $query->orderBy('title', $order);
                break;
            case 'tracking_number':
            case 'tracking':
                $query->orderBy('tracking_number', $order);
                break;
            case 'status':
                $query->orderBy('status', $order);
                break;
            case 'priority':
                $query->orderByRaw("FIELD(priority, 'Urgent', 'High', 'Normal', 'Low') " . ($order === 'asc' ? 'DESC' : 'ASC'));
                break;
            case 'due_date':
            case 'sla':
            case 'sla_remaining':
                $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ' . $order);
                break;
            case 'overdue':
            case 'most_overdue':
                $query->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < NOW() AND status NOT IN ("Completed","Archived") THEN 0 ELSE 1 END')
                      ->orderBy('due_date', 'asc');
                break;
            case 'office':
            case 'current_office':
                $query->leftJoin('offices', 'documents.current_office_id', '=', 'offices.id')
                      ->orderBy('offices.name', $order)
                      ->select('documents.*');
                break;
            case 'created_at':
            case 'recently_created':
                $query->orderBy('documents.created_at', $order);
                break;
            case 'updated_at':
            case 'recently_updated':
            default:
                $query->orderBy('documents.updated_at', $order);
                break;
        }

        $documents = $query->paginate(15)->withQueryString();

        // 6. Active Workflow Location & Receiver Synchronization
        // Ensure displayed Current Office and Receiver always represent the active workflow position
        $activeStatuses = ['Pending', 'Received', 'Under Review', 'Processing', 'In Transit', 'On Process', 'For Approval'];
        foreach ($documents as $doc) {
            if ($doc->routings && $doc->routings->isNotEmpty()) {
                $activeStep = $doc->routings->first(function ($r) use ($activeStatuses) {
                    return in_array($r->status, $activeStatuses);
                });

                if ($activeStep) {
                    if ($activeStep->to_office_id && $activeStep->toOffice) {
                        $doc->setRelation('currentOffice', $activeStep->toOffice);
                    }
                    if ($activeStep->receiver_user_id && $activeStep->receiverUser) {
                        $doc->setRelation('receiverUser', $activeStep->receiverUser);
                    }
                }
            }
        }

        $offices = Office::orderBy('name', 'asc')->get();
        $users = User::with('department')->orderBy('name', 'asc')->get();
        $categories = Document::whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category');

        return view('documents.index', compact('documents', 'offices', 'users', 'user', 'isAdmin', 'categories'));
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
            $duplicate = Schema::hasColumn('documents', 'file_hash') 
                ? Document::where('file_hash', $fileHash)->first() 
                : null;
            
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

                $senderName = auth()->user()?->name ?? session('user_name') ?? 'System';
                $uploader = auth()->user() ?? User::find(session('user_id') ?? 1);

                // Handle Overwrite or Versioning
                if ($duplicate && ($duplicateAction === 'overwrite' || $duplicateAction === 'version')) {
                    $document = Document::findOrFail($duplicateDocId);
                    
                    if ($duplicateAction === 'overwrite') {
                        if ($document->file_path) {
                            Storage::disk('public')->delete($document->file_path);
                        }
                        $updateData = [
                            'file_path' => $path,
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'file_hash' => $fileHash,
                            'uploaded_at' => now(),
                            'processed_at' => now(),
                        ];
                        $docCols = Schema::getColumnListing('documents');
                        $document->update(array_intersect_key($updateData, array_flip($docCols)));
                        ActivityLog::log('Document Overwritten (Duplicate Upload)', $document->id);
                        $msg = 'Document overwritten successfully!';
                    } else {
                        $newVersion = ($document->version ?? 1) + 1;
                        if ($document->file_path) {
                            Storage::disk('public')->delete($document->file_path);
                        }
                        $updateData = [
                            'file_path' => $path,
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'file_hash' => $fileHash,
                            'version' => $newVersion,
                            'uploaded_at' => now(),
                            'processed_at' => now(),
                        ];
                        $docCols = Schema::getColumnListing('documents');
                        $document->update(array_intersect_key($updateData, array_flip($docCols)));
                        ActivityLog::log("New Version Created (Version {$newVersion})", $document->id);
                        $msg = "New document version (v{$newVersion}) uploaded successfully!";
                    }

                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json([
                            'success'    => true,
                            'message'    => $msg,
                            'redirect'   => route('documents.index'),
                            'csrf_token' => csrf_token(),
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

                $uploaderUserId = auth()->id() ?? session('user_id');
                if ($uploaderUserId && !User::where('id', $uploaderUserId)->exists()) {
                    $uploaderUserId = null;
                }

                // Double-click rapid submission protection
                $recentDuplicate = Document::where('uploaded_by', $uploaderUserId)
                    ->where('file_hash', $fileHash)
                    ->where('created_at', '>=', now()->subSeconds(3))
                    ->first();
                if ($recentDuplicate) {
                    return $recentDuplicate;
                }

                $qrId = 'QR-' . strtoupper(uniqid('DOC-', true));
                do {
                    $trackingNumber = 'TRK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                } while (Document::where('tracking_number', $trackingNumber)->exists());

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

                $initialCurrentOfficeId = !empty($routingOfficeIds) ? (int)$routingOfficeIds[0] : (int)$request->origin_office_id;

                $documentData = [
                    'title' => $request->title,
                    'description' => $request->description ?? 'No description provided',
                    'type' => strtoupper($file->getClientOriginalExtension()),
                    'priority' => $request->input('priority', 'Normal'),
                    'origin_office_id' => $request->origin_office_id,
                    'current_office_id' => $initialCurrentOfficeId,
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
                    'uploaded_by' => $uploaderUserId,
                    'access_pin' => $hashedPin,
                    'uploaded_at' => now(),
                    'processed_at' => null,
                ];

                if (Schema::hasColumn('documents', 'sla')) {
                    $slaVal = $request->sla;
                    try {
                        $colType = Schema::getColumnType('documents', 'sla');
                        if ($colType === 'enum') {
                            $slaVal = match ($request->sla) {
                                'Simple Transaction (3 Working Days)'           => 'Standard',
                                'Complex Transaction (7 Working Days)'          => 'Expedited',
                                'Highly Technical Transaction (20 Working Days)' => 'Critical',
                                default                                         => in_array($request->sla, ['Standard', 'Expedited', 'Critical']) ? $request->sla : 'Standard',
                            };
                        }
                    } catch (\Throwable $e) {
                        // Fallback if column inspection fails and value is too long for legacy enum
                        if (strlen($slaVal) > 15) {
                            $slaVal = match ($request->sla) {
                                'Simple Transaction (3 Working Days)'           => 'Standard',
                                'Complex Transaction (7 Working Days)'          => 'Expedited',
                                'Highly Technical Transaction (20 Working Days)' => 'Critical',
                                default                                         => 'Standard',
                            };
                        }
                    }
                    $documentData['sla'] = $slaVal;
                }

                if (Schema::hasColumn('documents', 'due_date') && $dueDate) {
                    $documentData['due_date'] = $dueDate;
                }

                if (Schema::hasColumn('documents', 'qr_id')) {
                    $documentData['qr_id'] = $qrId;
                }

                // Dynamically filter $documentData to actual existing columns in MySQL
                $docCols = Schema::getColumnListing('documents');
                $documentData = array_intersect_key($documentData, array_flip($docCols));

                $document = Document::create($documentData);

                // Generate QR Code safely (do not fail upload if QR code storage/driver fails)
                try {
                    $qrCode = new QrCode($document->getQrPayloadUrl(), size: 300);
                    $writer = new PngWriter();
                    $result = $writer->write($qrCode);
                    $qrCodeData = $result->getString();
                    $qrPath = 'qr_codes/' . $document->id . '.png';
                    Storage::disk('public')->put($qrPath, $qrCodeData);
                    if (in_array('qr_code', $docCols)) {
                        $document->update(['qr_code' => $qrPath]);
                    }
                } catch (\Throwable $qe) {
                    \Log::warning('QR Code Generation / Storage failed: ' . $qe->getMessage(), [
                        'document_id' => $document->id
                    ]);
                }

                // Initial Routing History
                if (in_array('routing_history', $docCols)) {
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
                    $routingCols = Schema::getColumnListing('document_routings');
                    foreach ($routingUserIds as $index => $receiverId) {
                        $fromOfficeId = ($index === 0) ? $request->origin_office_id : $routingOfficeIds[$index - 1];
                        $toOfficeId = $routingOfficeIds[$index];

                        // Foreign key existence check
                        if ($fromOfficeId && !Office::where('id', $fromOfficeId)->exists()) {
                            $fromOfficeId = null;
                        }
                        if ($toOfficeId && !Office::where('id', $toOfficeId)->exists()) {
                            $toOfficeId = null;
                        }
                        if ($receiverId && !User::where('id', $receiverId)->exists()) {
                            $receiverId = null;
                        }

                        $appType = isset($routingApprovalTypes[$index]) ? $routingApprovalTypes[$index] : 'sequential';
                        $sigReq = isset($routingSignaturesRequired[$index]) ? (bool) $routingSignaturesRequired[$index] : true;

                        $routingItem = [
                            'document_id'        => $document->id,
                            'from_office_id'     => $fromOfficeId,
                            'to_office_id'       => $toOfficeId,
                            'receiver_user_id'   => $receiverId,
                            'sender_user_id'     => $uploaderUserId,  // uploader is the sender for step 0
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
                        ];

                        $routingItem = array_intersect_key($routingItem, array_flip($routingCols));
                        DocumentRouting::create($routingItem);
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
                    $admins = User::whereIn('role', ['ADMIN', 'Administrator', 'Super Administrator'])->get();
                    foreach ($admins as $admin) {
                        $admin->notify(new \App\Notifications\DocumentRoutedNotification($document, $senderName, 'admin'));
                    }
                } catch (\Throwable $ne) {
                    \Log::warning('Document notification dispatch encountered an issue: ' . $ne->getMessage(), [
                        'document_id' => $document->id,
                        'exception'   => $ne->getMessage(),
                    ]);
                }

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success'         => true,
                        'message'         => 'Document uploaded and initialized successfully!',
                        'redirect'        => route('documents.index'),
                        'qr_label_url'    => route('documents.qr-label', $document->id),
                        'document_id'     => $document->id,
                        'tracking_number' => $document->tracking_number ?? ('DOC-' . str_pad($document->id, 6, '0', STR_PAD_LEFT)),
                        'title'           => $document->title,
                        'csrf_token'      => csrf_token(),
                    ]);
                }

                return redirect()->route('documents.index')->with('success', 'Document uploaded and initialized successfully!');
            });
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage() ?: class_basename($e);
            \Log::error('Document Upload Exception: ' . $errorMsg, [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? session('user_id'),
                'file_name' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : null,
                'file_size' => $request->hasFile('file') ? $request->file('file')->getSize() : null,
                'request_inputs' => $request->except(['file', 'access_pin'])
            ]);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Upload failed: ' . $errorMsg], 500);
            }
            return back()->with('error', 'Upload failed: ' . $errorMsg);
        }
    }

    /**
     * Helper to get DocumentPolicy instance.
     */
    protected function getDocumentPolicy(): \App\Policies\DocumentPolicy
    {
        return app(\App\Policies\DocumentPolicy::class);
    }

    /**
     * Display the document details and verification page for a document (GET /documents/{id}/workflow).
     * Opens the document verification page (/documents/{id}) so users see document details first.
     */
    public function workflowView($id)
    {
        \Illuminate\Support\Facades\Log::info('QR Scan Access', [
            'user_id' => auth()->id() ?? session('user_id'),
            'document_id' => (int) $id,
            'route' => request()->path(),
        ]);

        return $this->show($id);
    }

    /**
     * Display specific document tracking details.
     * Points to resources/views/documents/show.blade.php
     */
    public function show($id)
    {
        // Eager load all relations to provide complete workspace data without N+1 queries
        $document = Document::with([
            'originOffice',
            'currentOffice',
            'destinationOffice',
            'uploader.department',
            'uploader.office',
            'receiverUser.department',
            'receiverUser.office',
            'receiverUsers.department',
            'receiverUsers.office',
            'activityLogs' => function($query) {
                $query->latest();
            },
            'routings.fromOffice',
            'routings.toOffice',
            'routings.receiverUser.department',
            'routings.receiverUser.office',
            'routings.senderUser.department',
            'routings.senderUser.office',
            'routings.forwardedFromUser',
            'views.user',
            'views.office',
        ])->findOrFail($id);

        \Illuminate\Support\Facades\Log::info('QR Scan Access', [
            'user_id' => auth()->id() ?? session('user_id'),
            'document_id' => $document->id,
            'route' => request()->path(),
        ]);

        $user = auth()->user() ?? User::find(session('user_id'));
        $policy = $this->getDocumentPolicy();
        
        // 1. Check view permission: Document creator, current receiver, office members, admin, history participants
        $canViewWorkflow = $policy->viewWorkflow($user, $document);
        
        if (!$canViewWorkflow) {
            \Log::warning("Workflow view authorization denied: User ID " . ($user?->id ?? 'guest') . " attempted to view document ID {$document->id}");
            if (request()->routeIs('track.*') || request()->is('track*')) {
                abort(403, 'You are not authorized to view the tracking information for this document.');
            }
            abort(403, 'You are not authorized to view this document.');
        }

        // 2. Resolve active routing step (handles pending user-assigned, office-assigned, or latest hop)
        $activeRouting = $policy->resolveActiveRouting($user, $document);

        // 3. Check perform workflow action permission: Active receiver, authorized approver, admin
        $canPerformWorkflowAction = $policy->performWorkflow($user, $document, $activeRouting);

        $isLocked = false;
        $isUploader = $user && ((int) $document->uploaded_by === (int) $user->id);

        if (!$isUploader) {
            // UNIVERSAL QR REQUIREMENT:
            // Every recipient (regardless of role: Admin, Staff, Manager, etc.) must complete QR verification
            // before accessing protected document/file or executing workflow actions.
            $qrVerified = session('qr_verified_' . $document->id, false);

            $isPendingReceiver = $activeRouting && (
                ($activeRouting->receiver_user_id && $activeRouting->receiver_user_id == $user?->id) ||
                (!$activeRouting->receiver_user_id && $user?->office_id && $activeRouting->to_office_id == $user->office_id)
            );

            if (!$qrVerified || ($activeRouting && $activeRouting->status === 'Pending' && is_null($activeRouting->scanned_at) && $isPendingReceiver)) {
                $isLocked = true;
            }

            // OTP verification requirement (if document is confidential)
            if ($document->is_confidential && !session('otp_verified_' . $document->id, false)) {
                $isLocked = true;
            }
        }

        if (!$isLocked && $activeRouting && $document->qr_status === 'Verified') {
            $document->update(['qr_status' => 'Accessed']);
        }

        \Log::info("Document loaded: ID {$document->id}, isLocked=" . ($isLocked ? "true" : "false"));


        $views = \App\Models\DocumentView::with(['user', 'office'])
            ->where('document_id', $document->id)
            ->get();

        $activeStep = $activeRouting ?? $document->routings->where('status', 'Pending')->first();

        return view('documents.show', compact('document', 'isLocked', 'views', 'activeStep', 'activeRouting', 'canPerformWorkflowAction', 'canViewWorkflow'));
    }

    /**
     * Return the QR label print view for a document.
     */
    public function qrLabel($id)
    {
        $document = Document::with(['originOffice', 'destinationOffice', 'currentOffice', 'uploader', 'receiverUser', 'routings.toOffice', 'routings.fromOffice'])
            ->findOrFail($id);

        $user = auth()->user() ?? User::find(session('user_id'));
        $policy = $this->getDocumentPolicy();

        if (!$policy->viewWorkflow($user, $document)) {
            abort(403, 'You are not authorized to view this document.');
        }

        return view('documents.qr_label', compact('document'));
    }

    /**
     * NAAP Document Passport - Complete traceable lifecycle record,
     * combining physical routing, QR verification, office handoffs,
     * workflow actions, approvals, security events, and processing analytics.
     */
    public function passport($id)
    {
        $document = Document::with([
            'originOffice',
            'currentOffice',
            'destinationOffice',
            'uploader',
            'receiverUser.department',
            'routings' => function ($q) {
                $q->with(['fromOffice', 'toOffice', 'senderUser', 'receiverUser.department'])
                  ->orderBy('sort_order', 'asc')
                  ->orderBy('id', 'asc');
            },
            'activityLogs' => function ($q) {
                $q->orderBy('created_at', 'asc')->orderBy('id', 'asc');
            },
            'views.user'
        ])->findOrFail($id);

        $user = auth()->user() ?? User::find(session('user_id'));
        $policy = $this->getDocumentPolicy();

        // 1. Authorization: Document creator, current receiver, office members, admin, history participants
        if (!$policy->viewWorkflow($user, $document)) {
            abort(403, 'You are not authorized to view this document.');
        }

        // 2. Confidential check: Universal rule, no admin bypass
        $isUploader = $user && ((int) $document->uploaded_by === (int) $user->id);
        if ($document->is_confidential && !$isUploader) {
            if (!session('qr_verified_' . $document->id) || !session('otp_verified_' . $document->id)) {
                return redirect()->route('documents.show', $document->id)
                    ->with('error', 'Confidential Document Passport requires completed QR and OTP verification.');
            }
        }

        // 3. QR Code Data generation
        $qrCodeData = null;
        if ($document->qr_code && str_contains($document->qr_code, 'qr_codes/')) {
            $qrCodeData = asset('storage/' . $document->qr_code);
        } else {
            try {
                $qrObj = new \Endroid\QrCode\QrCode(route('documents.passport', $document->id), size: 280);
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $qrCodeData = 'data:image/png;base64,' . base64_encode($writer->write($qrObj)->getString());
            } catch (\Throwable $e) {
                $qrCodeData = null;
            }
        }

        // 4. Build Complete Chronological Document Journey
        $journeyEvents = collect();

        // A. Document Registration / Upload Event
        if ($document->created_at) {
            $journeyEvents->push([
                'type'        => 'registration',
                'action'      => 'Document Registered & Uploaded',
                'badge'       => 'Registration',
                'badge_class' => 'bg-primary text-white',
                'icon'        => 'bi-file-earmark-arrow-up',
                'user'        => $document->uploader->name ?? 'System',
                'office'      => $document->originOffice->name ?? 'Origin Office',
                'timestamp'   => $document->created_at,
                'details'     => "File version v" . ($document->version ?? 1) . " (" . strtoupper($document->type ?? 'FILE') . ") registered with tracking #{$document->tracking_number}.",
            ]);
        }

        // B. QR Code Generation Event
        if ($document->tracking_number) {
            $journeyEvents->push([
                'type'        => 'qr_generated',
                'action'      => 'QR Tracking Assigned',
                'badge'       => 'QR Identity',
                'badge_class' => 'bg-info text-white',
                'icon'        => 'bi-qr-code',
                'user'        => $document->uploader->name ?? 'System',
                'office'      => $document->originOffice->name ?? 'Origin Office',
                'timestamp'   => $document->created_at ? $document->created_at->copy()->addSecond() : now(),
                'details'     => "Unique QR verification token initialized for tracking #{$document->tracking_number}.",
            ]);
        }

        // C. Routing Steps Events
        foreach ($document->routings as $routing) {
            $dispatchTime = $routing->pending_at ?? $routing->created_at;
            $journeyEvents->push([
                'type'        => 'routing_dispatch',
                'action'      => "Routed to " . ($routing->toOffice?->name ?? 'Office'),
                'badge'       => 'Routing',
                'badge_class' => 'bg-secondary text-white',
                'icon'        => 'bi-send',
                'user'        => $routing->senderUser?->name ?? 'System',
                'office'      => $routing->fromOffice?->name ?? 'Office',
                'timestamp'   => $dispatchTime,
                'details'     => "Step #{$routing->sort_order}: Dispatched to " . ($routing->toOffice?->name ?? 'Office') . ($routing->receiverUser ? " (Assigned to {$routing->receiverUser->name})" : "") . ($routing->notes ? " — Remarks: {$routing->notes}" : ""),
            ]);

            if ($routing->received_at) {
                $journeyEvents->push([
                    'type'        => 'routing_received',
                    'action'      => 'Physical Document Received',
                    'badge'       => 'Received',
                    'badge_class' => 'bg-info text-dark',
                    'icon'        => 'bi-inbox',
                    'user'        => $routing->receiverUser?->name ?? ($routing->signed_by ?? 'Office Staff'),
                    'office'      => $routing->toOffice?->name ?? 'Destination Office',
                    'timestamp'   => $routing->received_at,
                    'details'     => "Acknowledged in " . ($routing->toOffice?->name ?? 'office') . " by " . ($routing->receiverUser?->name ?? 'authorized personnel') . ".",
                ]);
            }

            if ($routing->released_at && !in_array($routing->status, ['Waiting', 'Pending'])) {
                $badgeClass = match($routing->status) {
                    'Approved', 'Completed', 'Accepted', 'Endorsed' => 'bg-success text-white',
                    'Returned', 'Reverted' => 'bg-warning text-dark',
                    'Rejected' => 'bg-danger text-white',
                    default => 'bg-primary text-white'
                };
                $journeyEvents->push([
                    'type'        => 'routing_action',
                    'action'      => $routing->action_label ?: "Action: {$routing->status}",
                    'badge'       => $routing->status,
                    'badge_class' => $badgeClass,
                    'icon'        => 'bi-check2-circle',
                    'user'        => $routing->signed_by ?? ($routing->receiverUser?->name ?? 'Staff'),
                    'office'      => $routing->toOffice?->name ?? 'Office',
                    'timestamp'   => $routing->released_at,
                    'details'     => ($routing->notes ? "Remarks: {$routing->notes}" : "Workflow step marked as {$routing->status}."),
                ]);
            }
        }

        // D. Activity Logs (QR Scans, Views, Security Events)
        foreach ($document->activityLogs as $log) {
            $meta = is_string($log->meta) ? json_decode($log->meta, true) : (array) $log->meta;
            $act = $log->action;

            if (str_contains($act, 'QR')) {
                $isFailed = str_contains($act, 'Failed');
                $journeyEvents->push([
                    'type'        => 'qr_scan',
                    'action'      => $act,
                    'badge'       => $isFailed ? 'QR Failed' : 'QR Verified',
                    'badge_class' => $isFailed ? 'bg-danger text-white' : 'bg-success text-white',
                    'icon'        => 'bi-qr-code-scan',
                    'user'        => $log->user ?: 'Scanner',
                    'office'      => $meta['office'] ?? ($meta['from_office'] ?? ($document->currentOffice->name ?? 'Office')),
                    'timestamp'   => $log->created_at,
                    'details'     => $isFailed ? ("Reason: " . ($meta['reason'] ?? 'Verification rejected')) : "Scanned and verified through secure portal.",
                ]);
            } elseif (str_contains($act, 'DOCUMENT VIEWED') || str_contains($act, 'Document Viewed')) {
                $journeyEvents->push([
                    'type'        => 'view',
                    'action'      => 'Document Accessed & Viewed',
                    'badge'       => 'View',
                    'badge_class' => 'bg-light text-dark border',
                    'icon'        => 'bi-eye',
                    'user'        => $log->user ?: 'Viewer',
                    'office'      => $document->currentOffice->name ?? 'Office',
                    'timestamp'   => $log->created_at,
                    'details'     => "Record reviewed through authenticated session.",
                ]);
            }
        }

        // E. Document Completion Event
        if ($document->status === 'Completed' && $document->completed_at) {
            $journeyEvents->push([
                'type'        => 'completion',
                'action'      => 'Document Lifecycle Completed',
                'badge'       => 'Completed',
                'badge_class' => 'bg-success text-white',
                'icon'        => 'bi-award-fill',
                'user'        => $document->receiverUser?->name ?? 'Admin',
                'office'      => $document->currentOffice?->name ?? 'Terminal Office',
                'timestamp'   => $document->completed_at,
                'details'     => "All routing steps and required signatures fulfilled. Final archive generated.",
            ]);
        }

        // Sort all journey events chronologically
        $journeyEvents = $journeyEvents->sortBy(function ($item) {
            $ts = $item['timestamp'];
            return $ts instanceof \Carbon\Carbon ? $ts->timestamp : strtotime((string) $ts);
        })->values();

        // 5. Stage & Delay Analysis
        $delayBreakdown = [];
        $created = $document->created_at;
        $received = $document->received_at;
        $processed = $document->processed_at ?? $document->completed_at;
        $completed = $document->completed_at;

        if ($created && $received && $received->greaterThanOrEqualTo($created)) {
            $delayBreakdown['Intake to Acknowledged'] = [
                'hours' => round($created->diffInMinutes($received) / 60, 1),
                'desc'  => 'Time between upload registration and first receiver acknowledgement.',
            ];
        }

        if ($received && $processed && $processed->greaterThanOrEqualTo($received)) {
            $delayBreakdown['Office Processing & Review'] = [
                'hours' => round($received->diffInMinutes($processed) / 60, 1),
                'desc'  => 'Active evaluation and processing duration by designated offices.',
            ];
        }

        if ($processed && $completed && $completed->greaterThanOrEqualTo($processed)) {
            $delayBreakdown['Final Signatures & Completion'] = [
                'hours' => round($processed->diffInMinutes($completed) / 60, 1),
                'desc'  => 'Time to fulfill required electronic signatures and formal archival.',
            ];
        }

        // 6. Route Deviations / Exceptions
        $routeDeviations = [];
        $reverts = $document->routings->filter(fn($r) => in_array($r->status, ['Returned', 'Reverted']));
        if ($reverts->isNotEmpty()) {
            foreach ($reverts as $rev) {
                $routeDeviations[] = [
                    'type'     => 'Revision Reversal',
                    'office'   => $rev->toOffice?->name ?? 'Office',
                    'date'     => $rev->released_at ?? $rev->updated_at,
                    'notes'    => $rev->notes ?: 'Document returned for revisions before forward progress could resume.',
                ];
            }
        }

        // 7. Document Completion Record (Internal Certificate of Traceability)
        $completionRecord = null;
        if (in_array($document->status, ['Completed', 'Archived']) && $document->completed_at) {
            $completionRecord = [
                'certificate_id'    => 'NAAP-PASSPORT-' . ($document->tracking_number ?: 'TRK-' . $document->id),
                'verification_hash' => strtoupper(substr(hash('sha256', $document->tracking_number . '|' . ($document->completed_at?->timestamp ?? time()) . '|' . ($document->uuid ?? $document->id)), 0, 32)),
                'completed_at'      => $document->completed_at,
                'final_office'      => $document->currentOffice?->name ?? ($document->destinationOffice?->name ?? 'NAAP Central'),
                'signer_name'       => $document->receiverUser?->name ?? ($document->uploader?->name ?? 'Authorized Authority'),
                'total_hops'        => $document->routings->count(),
                'verified_scans'    => $document->activityLogs->filter(fn($l) => str_contains($l->action, 'QR'))->count(),
            ];
        }

        return view('documents.passport', compact(
            'document',
            'journeyEvents',
            'delayBreakdown',
            'routeDeviations',
            'completionRecord',
            'qrCodeData'
        ));
    }

    /**
     * Download the document securely after scanning and authorization checks.
     */
    public function download($id)
    {
        $document = Document::findOrFail($id);
        $user = auth()->user() ?? User::find(session('user_id'));
        
        $policy = $this->getDocumentPolicy();
        if (!$policy->viewWorkflow($user, $document)) {
            abort(403, 'You are not authorized to download this document.');
        }
        
        $isUploader = $user && ((int) $document->uploaded_by === (int) $user->id);

        if (!$isUploader) {
            // UNIVERSAL QR REQUIREMENT:
            // EVERY recipient must complete QR verification before accessing the protected document/file.
            // Applies to EVERY role (Admin, Super Admin, Staff, Registrar, Manager, etc.) without exception.
            // NO administrator bypass, NO elevated-role bypass, NO special-case bypass.
            $qrVerified = session('qr_verified_' . $document->id, false);

            if (!$qrVerified) {
                abort(403, 'Access Restricted: You must complete QR verification before downloading or accessing this document.');
            }

            // OTP requirement (if document is confidential): OTP does not replace QR; BOTH must be completed.
            if ($document->is_confidential && !session('otp_verified_' . $document->id, false)) {
                abort(403, 'Access Restricted: Both QR verification and OTP verification are required to download this confidential document.');
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
        $user = auth()->user() ?? User::find(session('user_id'));
        $query = Document::accessibleBy($user)->with(['originOffice', 'currentOffice', 'destinationOffice', 'uploader', 'receiverUser.department', 'views.user']);

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

    /**
     * Handle personal user activity history (Route: activity.my).
     * Scoped strictly to the authenticated user's actions and accessible documents under RBAC.
     */
    public function myActivityIndex(Request $request)
    {
        $user = auth()->user() ?? User::find(session('user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $query = ActivityLog::with(['document.originOffice', 'document.destinationOffice'])
            ->where(function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere(function($legacy) use ($user) {
                      $legacy->whereNull('user_id')
                             ->where(function($nq) use ($user) {
                                 $nq->where('user', $user->name);
                                 if (!empty($user->username)) {
                                     $nq->orWhere('user', $user->username);
                                 }
                             })
                             ->where('created_at', '>=', $user->created_at);
                  });
            });

        // Search by document title or user action
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('document', function($d) use ($search) {
                    $d->where('title', 'like', "%$search%");
                })->orWhere('action', 'like', "%$search%");
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

        return view('my_activity', compact('logs'));
    }

    protected function authorizeAdmin()
    {
        $role = session('user_role') ?? auth()->user()?->role;
        $user = auth()->user() ?? User::find(session('user_id'));
        $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($role);

        if (!$isAdmin) {
            abort(403, 'Administrator privileges are required to access this page.');
        }
    }

    /**
     * Execute enterprise bulk actions on selected documents.
     */
    public function bulkAction(Request $request)
    {
        $user = auth()->user() ?? User::find(session('user_id'));
        if (!$user) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login')->with('error', 'Please log in to perform this action.');
        }

        $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin(session('user_role'));

        // Handle document IDs from either array (POST) or comma-separated string (GET / export)
        $ids = $request->input('document_ids', []);
        if (empty($ids) && $request->filled('ids')) {
            $ids = explode(',', $request->input('ids'));
        }
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $ids = array_filter(array_map('intval', $ids));

        $action = strtolower(trim((string) $request->input('action', '')));

        // 1. Bulk Export (CSV) - Supports selected documents OR current search/filter criteria
        if ($action === 'export') {
            $exportQuery = Document::accessibleBy($user)->with(['originOffice', 'destinationOffice', 'uploader', 'receiverUser']);

            if (!empty($ids)) {
                $exportQuery->whereIn('id', $ids);
            } else {
                if ($request->filled('search') || $request->filled('q')) {
                    $search = trim((string) ($request->input('search') ?: $request->input('q')));
                    $exportQuery->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                          ->orWhere('tracking_number', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhereHas('receiverUser', fn($sub) => $sub->where('name', 'like', "%{$search}%"))
                          ->orWhereHas('originOffice', fn($sub) => $sub->where('name', 'like', "%{$search}%"))
                          ->orWhereHas('currentOffice', fn($sub) => $sub->where('name', 'like', "%{$search}%"))
                          ->orWhereHas('destinationOffice', fn($sub) => $sub->where('name', 'like', "%{$search}%"));
                    });
                }
                if ($request->filled('status')) {
                    $rawStatus = strtolower(trim((string) $request->input('status')));
                    $statusMap = [
                        'pending'      => ['Pending'],
                        'in_transit'   => ['In Transit'],
                        'received'     => ['Received'],
                        'processing'   => ['Processing', 'Under Review', 'On Process'],
                        'for_approval' => ['For Approval'],
                        'approved'     => ['Approved', 'Accepted', 'Endorsed'],
                        'completed'    => ['Completed'],
                        'reverted'     => ['Reverted', 'Returned'],
                    ];
                    if (isset($statusMap[$rawStatus])) {
                        $exportQuery->whereIn('status', $statusMap[$rawStatus]);
                    } else {
                        $exportQuery->where('status', $request->input('status'));
                    }
                }
                if ($request->filled('priority')) {
                    $exportQuery->where('priority', ucfirst(strtolower(trim((string) $request->input('priority')))));
                }
            }

            if (!$isAdmin) {
                $userId = (int) $user->id;
                $exportQuery->where(function ($q) use ($userId) {
                    $q->where('uploaded_by', $userId)
                      ->orWhereHas('routings', fn($rq) => $rq->where('receiver_user_id', $userId));
                });
            }

            $documents = $exportQuery->get();

            $fileName = 'documents_export_' . date('Ymd_His') . '.csv';
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Pragma'              => 'no-cache',
                'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
                'Expires'             => '0'
            ];

            return response()->stream(function () use ($documents) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Tracking Number', 'Title', 'Type', 'Priority', 'Status', 'Origin Office', 'Destination Office', 'Receiver', 'Uploader', 'Created At', 'Completed At']);
                foreach ($documents as $doc) {
                    fputcsv($file, [
                        $doc->id,
                        $doc->tracking_number ?: $doc->qr_id,
                        $doc->title,
                        $doc->type,
                        $doc->priority,
                        $doc->status,
                        $doc->originOffice?->name ?? 'N/A',
                        $doc->destinationOffice?->name ?? 'N/A',
                        $doc->receiverUser?->name ?? 'Unassigned',
                        $doc->uploader?->name ?? 'System',
                        $doc->created_at?->format('Y-m-d H:i:s'),
                        $doc->completed_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        if (empty($ids)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'No documents selected.'], 422);
            }
            return back()->with('warning', 'No documents selected.');
        }

        // 2. Fetch documents to operate on
        $query = Document::whereIn('id', $ids);
        if (!$isAdmin) {
            // Non-admin can only alter documents they uploaded or are assigned as receiver
            $query->where(function($q) use ($user) {
                $q->where('uploaded_by', $user->id)
                  ->orWhere('receiver_user_id', $user->id);
            });
        }
        $targetDocs = $query->get();

        $unauthorizedCount = count($ids) - $targetDocs->count();

        return DB::transaction(function () use ($action, $targetDocs, $request, $isAdmin, $user, $unauthorizedCount) {
            $processedCount = 0;
            $failedDocs = [];

            switch ($action) {
                case 'delete':
                    if (!$isAdmin) {
                        if ($request->wantsJson() || $request->ajax()) {
                            return response()->json(['success' => false, 'message' => 'Administrator privileges are required to delete documents.'], 403);
                        }
                        return back()->with('error', 'Administrator privileges are required to delete documents.');
                    }
                    foreach ($targetDocs as $doc) {
                        $hasHistory = $doc->routings()->exists() || $doc->activityLogs()->exists();
                        if ($hasHistory) {
                            // Preserve historical audit trail and routing records by archiving safely
                            $doc->update([
                                'status'       => 'Archived',
                                'archived_at'  => now(),
                            ]);
                            ActivityLog::log('Document Safely Archived on Delete Request', $doc->id, ['user' => $user->name]);
                        } else {
                            if ($doc->file_path) {
                                Storage::disk('public')->delete($doc->file_path);
                            }
                            if ($doc->qr_code) {
                                Storage::disk('public')->delete($doc->qr_code);
                            }
                            $doc->delete();
                        }
                        $processedCount++;
                    }
                    $message = "Successfully deleted {$processedCount} document(s).";
                    break;

                case 'archive':
                    foreach ($targetDocs as $doc) {
                        $doc->update([
                            'status'      => 'Archived',
                            'archived_at' => now(),
                        ]);
                        ActivityLog::log('Document Archived (Bulk)', $doc->id, ['user' => $user->name]);
                        $processedCount++;
                    }
                    $message = "Successfully archived {$processedCount} document(s).";
                    break;

                case 'completed':
                case 'mark_completed':
                    foreach ($targetDocs as $doc) {
                        $doc->update([
                            'status'       => 'Completed',
                            'received_at'  => $doc->received_at ?? now(),
                            'completed_at' => now(),
                        ]);
                        $doc->routings()
                            ->whereIn('status', ['Pending', 'Received', 'Under Review', 'Processing', 'In Transit'])
                            ->update([
                                'status'       => 'Completed',
                                'released_at'  => now(),
                                'action_label' => 'Completed',
                            ]);
                        ActivityLog::log('Document Marked Completed (Bulk)', $doc->id, ['user' => $user->name]);
                        $processedCount++;
                    }
                    $message = "Successfully marked {$processedCount} document(s) as completed.";
                    break;

                case 'assign_receiver':
                    $receiverId = (int) $request->input('receiver_user_id');
                    $newReceiver = User::find($receiverId);
                    if (!$newReceiver) {
                        if ($request->wantsJson() || $request->ajax()) {
                            return response()->json(['success' => false, 'message' => 'Valid receiver must be selected.'], 422);
                        }
                        return back()->with('error', 'Valid receiver must be selected.');
                    }
                    foreach ($targetDocs as $doc) {
                        if (in_array(strtolower($doc->status), ['completed', 'archived', 'cancelled'])) {
                            continue;
                        }
                        $oldReceiverName = $doc->receiverUser?->name ?? 'Unassigned';
                        $doc->update([
                            'receiver_user_id' => $newReceiver->id,
                        ]);
                        $activeRouting = $doc->routings()->whereIn('status', ['Pending', 'In Transit'])->latest()->first();
                        if ($activeRouting) {
                            $activeRouting->update([
                                'receiver_user_id' => $newReceiver->id,
                            ]);
                        }
                        ActivityLog::log("Receiver Assigned (Bulk): {$oldReceiverName} -> {$newReceiver->name}", $doc->id, [
                            'assigned_by' => $user->name,
                            'new_receiver' => $newReceiver->name,
                        ]);
                        try {
                            $newReceiver->notify(new \App\Notifications\DocumentRoutedNotification($doc, $user->name ?? 'System', 'receiver'));
                        } catch (\Throwable $e) {}
                        $processedCount++;
                    }
                    $message = "Successfully assigned {$newReceiver->name} to {$processedCount} document(s).";
                    break;

                case 'mark_viewed':
                    foreach ($targetDocs as $doc) {
                        $hasView = \App\Models\DocumentView::where('document_id', $doc->id)
                            ->where('user_id', $user->id)
                            ->exists();
                        if (!$hasView) {
                            \App\Models\DocumentView::create([
                                'document_id' => $doc->id,
                                'user_id'     => $user->id,
                                'office_id'   => $user->office_id ?? $doc->current_office_id,
                                'viewed_at'   => now(),
                            ]);
                            ActivityLog::log('Document Marked Viewed (Bulk)', $doc->id, ['user' => $user->name]);
                        }
                        $processedCount++;
                    }
                    $message = "Successfully marked {$processedCount} document(s) as viewed.";
                    break;

                case 'change_status':
                    $newStatus = trim((string) $request->input('new_status', $request->input('status')));
                    $allowedStatuses = ['Pending', 'In Transit', 'Received', 'Under Review', 'Processing', 'For Approval', 'Approved', 'Completed', 'Archived', 'Reverted'];
                    $matchedStatus = null;
                    foreach ($allowedStatuses as $allowed) {
                        if (strcasecmp($allowed, $newStatus) === 0) {
                            $matchedStatus = $allowed;
                            break;
                        }
                    }
                    if (!$matchedStatus) {
                        if ($request->wantsJson() || $request->ajax()) {
                            return response()->json(['success' => false, 'message' => "Invalid status '{$newStatus}'."], 422);
                        }
                        return back()->with('error', "Invalid status specified.");
                    }
                    foreach ($targetDocs as $doc) {
                        $updateFields = ['status' => $matchedStatus];
                        if ($matchedStatus === 'Completed') {
                            $updateFields['completed_at'] = now();
                            if (!$doc->received_at) $updateFields['received_at'] = now();
                        } elseif ($matchedStatus === 'Archived') {
                            $updateFields['archived_at'] = now();
                        } elseif ($matchedStatus === 'Received' && !$doc->received_at) {
                            $updateFields['received_at'] = now();
                        }
                        $doc->update($updateFields);
                        ActivityLog::log("Document Status Changed (Bulk): {$matchedStatus}", $doc->id, ['user' => $user->name]);
                        $processedCount++;
                    }
                    $message = "Successfully updated status of {$processedCount} document(s) to {$matchedStatus}.";
                    break;

                default:
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json(['success' => false, 'message' => "Invalid bulk action '{$action}'."], 422);
                    }
                    return back()->with('error', 'Invalid bulk action specified.');
            }

            if ($unauthorizedCount > 0) {
                $message .= " ({$unauthorizedCount} document(s) skipped due to insufficient authorization).";
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'count'   => $processedCount,
                    'skipped' => $unauthorizedCount,
                ]);
            }

            return redirect()->route('documents.index')->with($unauthorizedCount > 0 ? 'warning' : 'success', $message);
        });
    }

    /**
     * Remove the specified document from storage.
     */
    public function destroy($id)
    {
        $document = Document::findOrFail($id);

        $user = auth()->user() ?? User::find(session('user_id'));
        $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin(session('user_role'));

        if (!$isAdmin && $document->uploaded_by !== session('user_id')) {
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

        $user = auth()->user() ?? User::find(session('user_id'));
        $policy = $this->getDocumentPolicy();

        if (!$policy->viewWorkflow($user, $document)) {
            abort(403, 'You are not authorized to view this document.');
        }

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
            
        $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($user?->role ?? session('user_role'));

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
            'expires_at' => now()->addMinutes(5),
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
        $policy = $this->getDocumentPolicy();

        $activeRouting = $policy->resolveActiveRouting($user, $document);
        $canPerform = $policy->performWorkflow($user, $document, $activeRouting);
        $isAdmin = $policy->isUserAdmin($user);

        if (!$canPerform) {
            \Log::warning("Workflow action authorization denied: User ID " . ($user?->id ?? 'guest') . " (office: " . ($user?->office_id ?? 'none') . ") attempted action '{$newStatus}' on document ID {$document->id}");
            abort(403, 'You are not authorized to perform this action.');
        }

        $isUploader = $user && ((int) $document->uploaded_by === (int) $user->id);
        if (!$isUploader) {
            $qrVerified = session('qr_verified_' . $document->id, false) || ($activeRouting && !is_null($activeRouting->scanned_at));
            if (!$qrVerified) {
                abort(403, 'Access Restricted: You must complete QR verification before performing workflow actions on this document.');
            }
            if ($document->is_confidential && !session('otp_verified_' . $document->id, false)) {
                abort(403, 'Access Restricted: Both QR verification and OTP verification are required to perform workflow actions on this confidential document.');
            }
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
                    if (!$document->processed_at) {
                        $docUpdate['processed_at'] = now();
                    }
                    break;
                case 'Rejected':
                    $docUpdate['rejected_at'] = now();
                    break;
                case 'Completed':
                    $docUpdate['completed_at'] = now();
                    if (!$document->received_at) {
                        $docUpdate['received_at'] = now();
                    }
                    if (!$document->processed_at) {
                        $docUpdate['processed_at'] = now();
                    }
                    if ($signaturePath) {
                        $docUpdate['receiver_signature'] = $signaturePath;
                    } elseif ($user && $user->signature) {
                        $docUpdate['receiver_signature'] = $user->signature;
                    }
                    break;
                case 'Received':
                case 'Accepted':
                    if (!$document->received_at) {
                        $docUpdate['received_at'] = now();
                    }
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
                    $docUpdate['current_office_id'] = $firstNext->to_office_id;
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
                    if (!$document->received_at) {
                        $docUpdate['received_at'] = now();
                    }
                    if (!$document->processed_at) {
                        $docUpdate['processed_at'] = now();
                    }
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
            $logAction = match($newStatus) {
                'Completed' => 'Document Completed',
                'Accepted'  => 'Document Accepted',
                'Approved'  => 'Document Approved',
                'Received'  => 'Document Received',
                'Rejected'  => 'Document Rejected',
                'Returned'  => 'Document Returned',
                'Reverted'  => 'Document Reverted',
                default     => 'Workflow Action: ' . $newStatus,
            };
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
        $policy = $this->getDocumentPolicy();

        $activeRouting = $policy->resolveActiveRouting($user, $document);
        $canPerform = $policy->performWorkflow($user, $document, $activeRouting);
        $isAdmin = $policy->isUserAdmin($user);

        if (!$canPerform) {
            \Log::warning("Forward document authorization denied: User ID " . ($user?->id ?? 'guest') . " attempted to forward document ID {$document->id}");
            abort(403, 'You are not authorized to perform this action.');
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