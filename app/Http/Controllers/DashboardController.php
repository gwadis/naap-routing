<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Document, User, Office, ActivityLog, Department, DocumentRouting};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the system executive dashboard.
     */
    public function index()
    {
        try {
            $user = auth()->user() ?? User::find(session('user_id'));
            $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($user?->role ?? session('user_role'));
            $userId = $user?->id;
            $deptId = $user?->department_id;

            $departmentName = $deptId ? Department::find($deptId)?->name : 'My Department';

            // 1. Base query scope
            $docScope = Document::query();
            $routeScope = DocumentRouting::query();

            if (!$isAdmin) {
                $docScope->where(function ($q) use ($userId) {
                    $q->where('uploaded_by', $userId)
                      ->orWhere('receiver_user_id', $userId)
                      ->orWhereHas('routings', function ($rq) use ($userId) {
                          $rq->where('receiver_user_id', $userId);
                      });
                });

                $routeScope->where(function ($q) use ($userId) {
                    $q->where('receiver_user_id', $userId)
                      ->orWhereHas('document', function ($dq) use ($userId) {
                          $dq->where('uploaded_by', $userId);
                      });
                });
            }

            // 2. Summary count stats
            $totalDocs = (clone $docScope)->count();
            $docsToday = (clone $docScope)->whereDate('created_at', now()->toDateString())->count();
            $pendingDocs = (clone $docScope)->where('status', 'Pending')->count();
            $approvedDocs = (clone $docScope)->where('status', 'Approved')->count();
            $rejectedDocs = (clone $docScope)->where('status', 'Rejected')->count();
            $completedDocs = (clone $docScope)->where('status', 'Completed')->count();
            $archivedDocs = (clone $docScope)->where('status', 'Archived')->count();

            // Priority counts
            $urgentDocs = (clone $docScope)->where('priority', 'Urgent')->count();
            $highDocs = (clone $docScope)->where('priority', 'High')->count();
            $normalDocs = (clone $docScope)->where('priority', 'Normal')->count();
            $lowDocs = (clone $docScope)->where('priority', 'Low')->count();

            // Overdue documents
            $overdueDocs = (clone $docScope)
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->where('status', '!=', 'Completed')
                ->count();

            // Average processing duration (in hours)
            $avgProcessingMinutes = (clone $docScope)
                ->where('status', 'Completed')
                ->whereNotNull('received_at')
                ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, received_at)) as avg_time'))
                ->first()
                ->avg_time ?? 0;
            $avgProcessingHours = round($avgProcessingMinutes / 60, 1);

            // 3. Monthly Trends (current year)
            $monthlyUploadsData = (clone $docScope)
                ->select(DB::raw('MONTH(created_at) as month'), DB::raw('COUNT(*) as count'))
                ->whereYear('created_at', now()->year)
                ->groupBy('month')
                ->pluck('count', 'month')
                ->toArray();

            $monthlyRoutingData = (clone $routeScope)
                ->select(DB::raw('MONTH(created_at) as month'), DB::raw('COUNT(*) as count'))
                ->whereYear('created_at', now()->year)
                ->groupBy('month')
                ->pluck('count', 'month')
                ->toArray();

            $monthlyUploads = [];
            $monthlyRouting = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthlyUploads[] = $monthlyUploadsData[$m] ?? 0;
                $monthlyRouting[] = $monthlyRoutingData[$m] ?? 0;
            }

            // 4. Department Performance (average processing hours)
            $deptPerformance = DB::table('documents')
                ->join('users', 'documents.uploaded_by', '=', 'users.id')
                ->join('departments', 'users.department_id', '=', 'departments.id')
                ->where('documents.status', 'Completed')
                ->whereNotNull('documents.received_at')
                ->select('departments.name', DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, documents.created_at, documents.received_at)) / 60, 1) as avg_hours'))
                ->groupBy('departments.name')
                ->orderBy('avg_hours', 'asc')
                ->get();

            // 5. User Performance (top 10 fastest document completions)
            $userPerformance = DB::table('documents')
                ->join('users', 'documents.uploaded_by', '=', 'users.id')
                ->where('documents.status', 'Completed')
                ->whereNotNull('documents.received_at')
                ->select('users.name', DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, documents.created_at, documents.received_at)) / 60, 1) as avg_hours'))
                ->groupBy('users.name')
                ->orderBy('avg_hours', 'asc')
                ->take(10)
                ->get();

            // 6. Top receiving/sending departments
            $topReceivingDepts = DB::table('document_routings')
                ->join('users', 'document_routings.receiver_user_id', '=', 'users.id')
                ->join('departments', 'users.department_id', '=', 'departments.id')
                ->select('departments.name', DB::raw('COUNT(*) as count'))
                ->groupBy('departments.name')
                ->orderBy('count', 'desc')
                ->take(5)
                ->get();

            $topSendingDepts = DB::table('documents')
                ->join('users', 'documents.uploaded_by', '=', 'users.id')
                ->join('departments', 'users.department_id', '=', 'departments.id')
                ->select('departments.name', DB::raw('COUNT(*) as count'))
                ->groupBy('departments.name')
                ->orderBy('count', 'desc')
                ->take(5)
                ->get();

            // 7. Recent lists
            $recentActivities = ActivityLog::with('document')->latest()->take(10)->get();
            $recentUploads = (clone $docScope)->with(['uploader', 'routings'])->latest()->take(10)->get();
            $recentRouting = (clone $routeScope)->with(['document', 'receiverUser', 'fromOffice', 'toOffice'])->latest()->take(10)->get();

            // Chart data: 7-day uploads count
            $flowData = [];
            $days = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $days[] = $date->format('D');
                $flowData[] = (clone $docScope)->whereDate('created_at', $date->toDateString())->count();
            }

            // Top Office Load (Current)
            $offices = Office::withCount(['documents' => function ($q) {
                $q->where('status', '!=', 'Completed');
            }])
            ->orderBy('documents_count', 'desc')
            ->take(5)
            ->get();

            // QR Scan analytics
            $qrScansToday = ActivityLog::whereDate('created_at', now()->toDateString())
                ->where('action', 'like', '%QR Scanned%')
                ->count();

            $qrScansWeek = ActivityLog::where('created_at', '>=', now()->subDays(7))
                ->where('action', 'like', '%QR Scanned%')
                ->count();

            $qrScansMonth = ActivityLog::where('created_at', '>=', now()->subDays(30))
                ->where('action', 'like', '%QR Scanned%')
                ->count();

            $uniqueUsersScanning = ActivityLog::where('action', 'like', '%QR Scanned%')
                ->distinct()
                ->count('user');

            $documentViews = ActivityLog::where('action', 'DOCUMENT VIEWED')->count();

            $otpVerifications = ActivityLog::where('action', 'Confidential PIN Verified')->count();

            $approvalActivities = ActivityLog::where(function($q) {
                $q->where('action', 'like', '%Approved%')
                  ->orWhere('action', 'like', '%Completed%')
                  ->orWhere('action', 'like', '%Accepted%')
                  ->orWhere('action', 'like', '%Endorsed%');
            })->count();

            $routingActivities = ActivityLog::where(function($q) {
                $q->where('action', 'like', '%Forwarded%')
                  ->orWhere('action', 'like', '%Routed%')
                  ->orWhere('action', 'like', '%In Transit%');
            })->count();

            $qrTrendData = [];
            $qrTrendDays = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $qrTrendDays[] = $date->format('D');
                $qrTrendData[] = ActivityLog::whereDate('created_at', $date->toDateString())
                    ->where('action', 'like', '%QR Scanned%')
                    ->count();
            }

            return view('dashboard', compact(
                'isAdmin',
                'departmentName',
                'totalDocs',
                'docsToday',
                'pendingDocs',
                'approvedDocs',
                'rejectedDocs',
                'completedDocs',
                'archivedDocs',
                'urgentDocs',
                'highDocs',
                'normalDocs',
                'lowDocs',
                'overdueDocs',
                'avgProcessingHours',
                'flowData',
                'days',
                'offices',
                'monthlyUploads',
                'monthlyRouting',
                'deptPerformance',
                'userPerformance',
                'topReceivingDepts',
                'topSendingDepts',
                'recentActivities',
                'recentUploads',
                'recentRouting',
                'qrScansToday',
                'qrScansWeek',
                'qrScansMonth',
                'uniqueUsersScanning',
                'documentViews',
                'otpVerifications',
                'approvalActivities',
                'routingActivities',
                'qrTrendData',
                'qrTrendDays'
            ));

        } catch (\Exception $e) {
            Log::error('Dashboard Error: ' . $e->getMessage());
            return back()->with('error', 'Unable to load dashboard. Please try again.');
        }
    }

    /**
     * Get user's notifications.
     */
    public function notifications(Request $request)
    {
        try {
            $user = auth()->user() ?? User::find(session('user_id'));
            
            if (!$user) {
                return response()->json([
                    'count' => 0,
                    'items' => [],
                ]);
            }

            $unreadNotifications = $user->unreadNotifications()->latest()->take(10)->get();
            
            $items = $unreadNotifications->map(function ($notification) {
                $data = $notification->data;
                return [
                    'id'      => $notification->id,
                    'title'   => $data['type'] ?? 'Notification',
                    'message' => $data['message'] ?? 'New updates in the system',
                    'time'    => $notification->created_at->diffForHumans(),
                    'details' => $data,
                ];
            });

            return response()->json([
                'count' => $user->unreadNotifications()->count(), // Full DB count, not capped by take(10)
                'items' => $items,
            ]);

        } catch (\Exception $e) {
            Log::error('Notification Error: ' . $e->getMessage());

            return response()->json([
                'count' => 0,
                'items' => [],
                'error' => 'Failed to load notifications'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request)
    {
        try {
            $user = auth()->user() ?? User::find(session('user_id'));
            if ($user) {
                $user->unreadNotifications->markAsRead();
            }
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return back()->with('success', 'All notifications marked as read.');
        } catch (\Exception $e) {
            Log::error('Notification mark read error: ' . $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Failed to mark notifications as read.');
        }
    }

    /**
     * Display the notifications history page.
     */
    public function notificationsPage(Request $request)
    {
        try {
            $user = auth()->user() ?? User::find(session('user_id'));

            if (!$user) {
                return redirect()->route('home');
            }

            $query = $user->notifications();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('data', 'like', '%' . $search . '%');
                });
            }

            if ($request->filled('type')) {
                $type = $request->type;
                $query->where(function($q) use ($type) {
                    $q->where('data', 'like', '%"type":"' . $type . '"%');
                });
            }

            if ($request->filled('status')) {
                $status = $request->status;
                if ($status === 'unread') {
                    $query->whereNull('read_at');
                } elseif ($status === 'read') {
                    $query->whereNotNull('read_at');
                }
            }

            $notifications = $query->latest()
                ->paginate(25)
                ->withQueryString();

            return view('notifications.index', compact('notifications'));

        } catch (\Exception $e) {
            Log::error('Notifications page error: ' . $e->getMessage());
            return back()->with('error', 'Unable to load notifications.');
        }
    }

    /**
     * Mark a single notification as read.
     */
    public function markSingleRead(Request $request, $id)
    {
        try {
            $user = auth()->user() ?? User::find(session('user_id'));
            $notification = $user?->notifications()->find($id);

            $docId = null;
            if ($notification) {
                $docId = $notification->data['document_id'] ?? null;
                $notification->markAsRead();
            }

            if ($request->isMethod('post')) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => true]);
                }
                return back()->with('success', 'Notification marked as read.');
            }

            // For GET request, redirect to the document details page if available
            if ($docId) {
                return redirect()->route('track.detail', $docId);
            }

            return redirect()->route('notifications.index')->with('success', 'Notification marked as read.');

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Failed to mark notification as read.');
        }
    }

    /**
     * Delete a single notification.
     */
    public function deleteNotification(Request $request, $id)
    {
        try {
            $user = auth()->user() ?? User::find(session('user_id'));
            $notification = $user?->notifications()->find($id);

            if ($notification) {
                $notification->delete();
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return back()->with('success', 'Notification deleted.');

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Failed to delete notification.');
        }
    }
}