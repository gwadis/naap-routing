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
    /**
     * Display the system executive dashboard.
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user() ?? User::find(session('user_id'));
            $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($user?->role ?? session('user_role'));
            $userId = $user?->id;
            $deptId = $user?->department_id;

            $departmentName = $deptId ? Department::find($deptId)?->name : 'My Department';

            // 1. Base query scope enforcing RBAC
            $docScope = Document::accessibleBy($user);
            $routeScope = DocumentRouting::whereHas('document', function ($dq) use ($user) {
                $dq->accessibleBy($user);
            });

            // Global Filter Support (Dashboard and Reports consistency)
            if ($request->filled('from_date')) {
                $docScope->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
                $routeScope->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $docScope->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
                $routeScope->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            }
            if ($request->filled('status')) {
                $docScope->where('status', $request->status);
            }
            if ($request->filled('category')) {
                $docScope->where('category', $request->category);
            }
            if ($request->filled('office_id')) {
                $offId = $request->office_id;
                $docScope->where(function($q) use ($offId) {
                    $q->where('current_office_id', $offId)
                      ->orWhere('origin_office_id', $offId)
                      ->orWhere('destination_office_id', $offId);
                });
            }
            if ($request->filled('department_id')) {
                $deptFilter = $request->department_id;
                $docScope->where(function($q) use ($deptFilter) {
                    $q->whereHas('currentOffice', function($co) use ($deptFilter) {
                        $co->where('department', $deptFilter)->orWhere('id', $deptFilter);
                    })->orWhereHas('destinationOffice', function($do) use ($deptFilter) {
                        $do->where('department', $deptFilter)->orWhere('id', $deptFilter);
                    });
                });
            }
            if ($request->filled('user_id')) {
                $docScope->where('uploaded_by', $request->user_id);
            }

            // 2. Summary count stats
            $totalDocs = (clone $docScope)->count();
            $docsToday = (clone $docScope)->whereDate('created_at', now()->toDateString())->count();
            $inProcessDocs = (clone $docScope)->whereIn('status', ['Pending', 'In Transit', 'Under Review', 'On Process', 'Forwarded', 'Received'])->count();
            $forApprovalDocs = (clone $docScope)->whereIn('status', ['For Approval', 'Pending Approval'])->count();
            $pendingDocs = (clone $docScope)->where('status', 'Pending')->count();
            $approvedDocs = (clone $docScope)->where(function($q) {
                $q->where('status', 'Approved')->orWhereNotNull('approved_at');
            })->count();
            $rejectedDocs = (clone $docScope)->where('status', 'Rejected')->count();
            $completedDocs = (clone $docScope)->where('status', 'Completed')->count();
            $archivedDocs = (clone $docScope)->where('status', 'Archived')->count();

            // Priority counts
            $urgentDocs = (clone $docScope)->where('priority', 'Urgent')->count();
            $highDocs = (clone $docScope)->where('priority', 'High')->count();
            $normalDocs = (clone $docScope)->where('priority', 'Normal')->count();
            $lowDocs = (clone $docScope)->where('priority', 'Low')->count();

            // Overdue and SLA Tracking
            $overdueDocs = (clone $docScope)
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                ->count();

            $nearingSlaDocs = (clone $docScope)
                ->whereNotNull('due_date')
                ->where('due_date', '>=', now())
                ->where('due_date', '<=', now()->addHours(48))
                ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                ->count();

            $activeQrDocs = (clone $docScope)
                ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                ->where(function($q) {
                    $q->whereNotNull('qr_code')->where('qr_code', '!=', '')
                      ->orWhereNotNull('qr_id')->where('qr_id', '!=', '');
                })->count();

            $totalQrGenerated = (clone $docScope)
                ->where(function($q) {
                    $q->whereNotNull('qr_code')->where('qr_code', '!=', '')
                      ->orWhereNotNull('qr_id')->where('qr_id', '!=', '');
                })->count();

            // Average & Median Processing Duration (Strict Chronological Validation)
            // Rule: created_at < received_at (or completed_at). Inverted or missing timestamps yield 'N/A'
            $validCompletedDocs = (clone $docScope)
                ->where('status', 'Completed')
                ->whereNotNull('created_at')
                ->where(function($q) {
                    $q->whereNotNull('received_at')->orWhereNotNull('completed_at');
                })
                ->get()
                ->filter(function($doc) {
                    $end = $doc->completed_at ?? $doc->received_at;
                    return $end && $doc->created_at && $end->gt($doc->created_at);
                });

            $durationsInHours = [];
            $completedOnTimeCount = 0;
            $completedWithSlaCount = 0;

            foreach ($validCompletedDocs as $doc) {
                $end = $doc->completed_at ?? $doc->received_at;
                $diffMinutes = $doc->created_at->diffInMinutes($end, false);
                if ($diffMinutes > 0) {
                    $hrs = round($diffMinutes / 60, 2);
                    $durationsInHours[] = $hrs;

                    if ($doc->due_date) {
                        $completedWithSlaCount++;
                        if ($end->lte($doc->due_date)) {
                            $completedOnTimeCount++;
                        }
                    }
                }
            }

            if (!empty($durationsInHours)) {
                $avgProcessingHours = round(array_sum($durationsInHours) / count($durationsInHours), 1);
                sort($durationsInHours);
                $countD = count($durationsInHours);
                $midD = (int) floor($countD / 2);
                $medianProcessingHours = ($countD % 2 === 0)
                    ? round(($durationsInHours[$midD - 1] + $durationsInHours[$midD]) / 2, 1)
                    : round($durationsInHours[$midD], 1);
                $fastestProcessingHours = round(min($durationsInHours), 1);
                $longestProcessingHours = round(max($durationsInHours), 1);
            } else {
                $avgProcessingHours = 'N/A';
                $medianProcessingHours = 'N/A';
                $fastestProcessingHours = 'N/A';
                $longestProcessingHours = 'N/A';
            }

            $completedSlaComplianceRate = ($completedWithSlaCount > 0)
                ? round(($completedOnTimeCount / $completedWithSlaCount) * 100, 1)
                : 'N/A';

            $totalSlaScopeCount = $completedWithSlaCount + $overdueDocs;
            $overallSlaComplianceRate = ($totalSlaScopeCount > 0)
                ? round(($completedOnTimeCount / $totalSlaScopeCount) * 100, 1)
                : 'N/A';

            $slaComplianceRate = $completedSlaComplianceRate;

            // Route Exceptions / Deviations Count (for Action Required)
            $routeExceptionsCount = DocumentRouting::whereIn('status', ['Returned', 'Reverted'])->distinct('document_id')->count('document_id');

            // Action Required counts (Real operational action indicators - Part 2.C)
            $actionRequired = [
                'awaiting_receipt'    => (clone $docScope)->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])->whereNull('received_at')->count(),
                'awaiting_processing' => (clone $docScope)->whereNotNull('received_at')->whereIn('status', ['Pending', 'Under Review', 'Processing', 'Received', 'In Transit'])->count(),
                'awaiting_approval'   => (clone $docScope)->whereIn('status', ['For Approval', 'Pending Approval'])->count(),
                'awaiting_signature'  => (clone $docScope)->whereHas('routings', function($rq) {
                    $rq->where('signature_required', true)->whereNull('signed_by')->whereNull('released_at');
                })->count(),
                'nearing_sla'         => $nearingSlaDocs,
                'overdue'             => $overdueDocs,
                'route_exceptions'    => $routeExceptionsCount,
            ];

            // Simplified Office Workload (Part 2.D: Office, Pending, Overdue, Inspect)
            $allOffices = Office::orderBy('name', 'asc')->get();
            $officeWorkload = [];
            foreach ($allOffices as $off) {
                $offPending = Document::where('current_office_id', $off->id)
                    ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                    ->count();
                $offOverdue = Document::where('current_office_id', $off->id)
                    ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now())
                    ->count();

                if ($offPending > 0 || $offOverdue > 0 || $allOffices->count() <= 5) {
                    $officeWorkload[] = [
                        'id'      => $off->id,
                        'name'    => $off->name,
                        'pending' => $offPending,
                        'overdue' => $offOverdue,
                        'total'   => $offPending + $offOverdue,
                    ];
                }
            }
            usort($officeWorkload, fn($a, $b) => $b['total'] <=> $a['total']);
            $officeWorkload = array_slice($officeWorkload, 0, 6);

            // Compact QR Summary (Part 2.E: Today, Successful, Failed, 7-Day Trend)
            $qrScansQuery = ActivityLog::where(function($q) {
                $q->where('action', 'like', '%QR Scanned%')
                  ->orWhere('action', 'QR Code Verified');
            });
            if ($request->filled('from_date')) {
                $qrScansQuery->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $qrScansQuery->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            }

            $qrScansToday = (clone $qrScansQuery)->whereDate('created_at', now()->toDateString())->count();
            $qrSuccessfulScans = (clone $qrScansQuery)->count();

            $qrFailedScansQuery = ActivityLog::where(function($q) {
                $q->where('action', 'QR Verification Failed')
                  ->orWhere('action', 'like', '%QR Scan Failed%')
                  ->orWhere('action', 'like', '%QR Verification Failed%');
            });
            if ($request->filled('from_date')) {
                $qrFailedScansQuery->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $qrFailedScansQuery->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            }
            $qrFailedScans = $qrFailedScansQuery->count();

            // 7-Day Trend for Compact QR summary
            $qrTrendDays = [];
            $qrTrendAttempts = [];
            $qrTrendSuccess = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dayStr = $date->toDateString();
                $qrTrendDays[] = $date->format('D'); // Mon, Tue, etc.
                $qrTrendAttempts[] = ActivityLog::whereDate('created_at', $dayStr)
                    ->where('action', 'like', '%QR%')
                    ->count();
                $qrTrendSuccess[] = ActivityLog::whereDate('created_at', $dayStr)
                    ->where(function($q) {
                        $q->where('action', 'like', '%QR Scanned%')
                          ->orWhere('action', 'QR Code Verified')
                          ->orWhere('action', 'Confidential PIN Verified');
                    })->count();
            }

            // Document Activity Calendar (Part 2.F - Operational drill-down)
            $calendarData = $this->getCalendarMonthData($userId, $isAdmin, (int) now()->year, (int) now()->month);
            $todayKey = now()->toDateString();
            $selectedDateEventsData = $this->getCalendarDateEvents($todayKey, $userId, $isAdmin);

            // Recent Activity (Part 2.G - Short list of 5-10 recent important events)
            $recentActivities = ActivityLog::with(['document.currentOffice', 'document.originOffice', 'document.destinationOffice'])
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->take(8)
                ->get();
            $recentUploads = (clone $docScope)->with(['uploader', 'routings'])->latest()->take(6)->get();

            // User Workspace Specific Analytics (Scoped strictly to authenticated user)
            $myDocumentsCount = 0;
            $forMyActionCount = 0;
            $userInProcessCount = 0;
            $userCompletedCount = 0;
            $userOverdueCount = 0;
            $userNearingSlaCount = 0;
            $myActionRequiredList = [];
            $workflowStages = [
                'received'     => 0,
                'processing'   => 0,
                'for_approval' => 0,
                'approved'     => 0,
                'signed'       => 0,
                'reverted'     => 0,
                'completed'    => 0,
            ];
            $userStatusChartData = [
                'in_process'   => 0,
                'for_approval' => 0,
                'approved'     => 0,
                'completed'    => 0,
                'reverted'     => 0,
                'overdue'      => 0,
            ];
            $myRoutingActivityStats = [
                'received_by_me'  => 0,
                'routed_by_me'    => 0,
                'processed_by_me' => 0,
                'completed_by_me' => 0,
            ];
            $userSlaComplianceRate = 'N/A';
            $myRoutingActivities = collect();
            $myDocumentLocations = collect();
            $userSlaMonitoring = [
                'on_track'             => 0,
                'nearing_sla'          => 0,
                'overdue'              => 0,
                'completed_within_sla' => 0,
                'completed_beyond_sla' => 0,
                'na'                   => 0,
            ];
            $userRecentActivities = collect();
            $userRecentDocuments = collect();
            $userQrStats = [
                'total_scans'         => 0,
                'successful_scans'    => 0,
                'failed_scans'        => 0,
                'recent_scanned_docs' => collect(),
            ];
            $userTimingMetrics = [
                'avg_receive_time'    => 'N/A',
                'avg_processing_time' => 'N/A',
                'avg_completion_time' => 'N/A',
            ];
            $userUnreadNotifications = collect();

            if ($user) {
                $officeId = $user->office_id;

                // 1. User Scoped Documents Query enforcing RBAC
                $userDocsQuery = Document::accessibleBy($user);

                $myDocumentsCount = (clone $userDocsQuery)->count();

                // 2. For My Action: Documents awaiting current action by this user
                $actionRawDocs = (clone $userDocsQuery)
                    ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled', 'Rejected'])
                    ->where(function($q) use ($userId, $officeId) {
                        $q->where('receiver_user_id', $userId)
                          ->orWhereHas('routings', function($rq) use ($userId) {
                              $rq->where('receiver_user_id', $userId)
                                 ->whereIn('status', ['Pending', 'Received', 'In Transit']);
                          });
                        if ($officeId) {
                            $q->orWhere(function($sub) use ($officeId) {
                                $sub->where('current_office_id', $officeId)
                                    ->whereIn('status', ['Pending', 'In Transit']);
                            });
                        }
                    })
                    ->with(['currentOffice', 'destinationOffice', 'originOffice', 'activityLogs', 'routings'])
                    ->get();

                foreach ($actionRawDocs as $ad) {
                    $st = strtolower($ad->status ?? '');
                    $isOverdue = $ad->due_date && $ad->due_date->isPast();
                    $isNearing = $ad->due_date && !$isOverdue && now()->diffInHours($ad->due_date, false) <= 48;

                    $reqAction = 'Action Required';
                    if (in_array($st, ['reverted', 'returned'])) {
                        $reqAction = 'Revision / Correction Required';
                    } elseif ($ad->routings->where('signature_required', true)->whereNull('signed_by')->isNotEmpty()) {
                        $reqAction = 'Awaiting Digital Signature';
                    } elseif (in_array($st, ['for approval', 'for_approval', 'pending approval'])) {
                        $reqAction = 'Awaiting Approval / Endorsement';
                    } elseif (is_null($ad->received_at) || in_array($st, ['pending', 'in transit', 'in_transit'])) {
                        $reqAction = 'Awaiting Receipt / Acceptance';
                    } elseif (in_array($st, ['received', 'under review', 'under_review', 'processing', 'on process'])) {
                        $reqAction = 'Awaiting Processing / Review';
                    } elseif ($isOverdue) {
                        $reqAction = 'Overdue Action Required';
                    } elseif ($isNearing) {
                        $reqAction = 'Nearing SLA Deadline';
                    }

                    // SLA Remaining badge and text
                    $slaRemaining = 'N/A';
                    $slaBadge = 'sla-na';
                    if (!$ad->due_date) {
                        $slaRemaining = 'No SLA';
                        $slaBadge = 'sla-na';
                    } elseif ($isOverdue) {
                        $diffH = abs((int) now()->diffInHours($ad->due_date, false));
                        $slaRemaining = "{$diffH}h Overdue";
                        $slaBadge = 'sla-overdue';
                    } elseif ($isNearing) {
                        $diffH = (int) now()->diffInHours($ad->due_date, false);
                        $diffM = abs((int) (now()->diffInMinutes($ad->due_date, false) % 60));
                        $slaRemaining = "{$diffH}h {$diffM}m left";
                        $slaBadge = 'sla-nearing';
                    } else {
                        $diffD = (int) now()->diffInDays($ad->due_date, false);
                        $slaRemaining = "{$diffD}d left";
                        $slaBadge = 'sla-on-track';
                    }

                    $lastLog = $ad->activityLogs->first();
                    $lastUpdatedTs = $lastLog ? $lastLog->created_at : ($ad->updated_at ?? $ad->created_at);

                    $myActionRequiredList[] = [
                        'id' => $ad->id,
                        'title' => $ad->title,
                        'tracking_number' => $ad->tracking_number ?? ('DOC-' . $ad->id),
                        'current_office' => $ad->currentOffice?->name ?? 'Unassigned',
                        'origin_office' => $ad->originOffice?->name ?? 'Origin',
                        'status' => $ad->status,
                        'required_action' => $reqAction,
                        'sla_remaining' => $slaRemaining,
                        'sla_badge' => $slaBadge,
                        'last_updated' => $lastUpdatedTs ? $lastUpdatedTs->format('M d, Y h:i A') : 'N/A',
                        'is_overdue' => $isOverdue,
                        'is_nearing' => $isNearing,
                    ];
                }

                // Sort: overdue first, then nearing SLA, then by recency
                usort($myActionRequiredList, function($a, $b) {
                    if ($a['is_overdue'] !== $b['is_overdue']) {
                        return $a['is_overdue'] ? -1 : 1;
                    }
                    if ($a['is_nearing'] !== $b['is_nearing']) {
                        return $a['is_nearing'] ? -1 : 1;
                    }
                    return 0;
                });

                $forMyActionCount = count($myActionRequiredList);

                // 3. In Process (assigned to user and being processed)
                $userInProcessCount = (clone $userDocsQuery)
                    ->whereIn('status', ['Received', 'Under Review', 'Processing', 'On Process', 'In Transit'])
                    ->where(function($q) use ($userId, $officeId) {
                        $q->where('receiver_user_id', $userId);
                        if ($officeId) {
                            $q->orWhere('current_office_id', $officeId);
                        }
                    })
                    ->count();

                // 4. Completed (completed through user's workflow)
                $userCompletedCount = (clone $userDocsQuery)
                    ->whereIn('status', ['Completed', 'Archived'])
                    ->count();

                // 5. Overdue (assigned to user / office)
                $userOverdueCount = (clone $userDocsQuery)
                    ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now())
                    ->where(function($q) use ($userId, $officeId) {
                        $q->where('receiver_user_id', $userId);
                        if ($officeId) {
                            $q->orWhere('current_office_id', $officeId);
                        }
                    })
                    ->count();

                // 6. Nearing SLA
                $userNearingSlaCount = (clone $userDocsQuery)
                    ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                    ->whereNotNull('due_date')
                    ->where('due_date', '>=', now())
                    ->where('due_date', '<=', now()->addHours(48))
                    ->where(function($q) use ($userId, $officeId) {
                        $q->where('receiver_user_id', $userId);
                        if ($officeId) {
                            $q->orWhere('current_office_id', $officeId);
                        }
                    })
                    ->count();

                // 7. Workflow Stages (Received, Processing, For Approval, Approved, Signed, Reverted, Completed)
                $workflowStages['received'] = (clone $userDocsQuery)->where('status', 'Received')->count();
                $workflowStages['processing'] = (clone $userDocsQuery)->whereIn('status', ['Processing', 'Under Review', 'On Process'])->count();
                $workflowStages['for_approval'] = (clone $userDocsQuery)->whereIn('status', ['For Approval', 'Pending Approval'])->count();
                $workflowStages['approved'] = (clone $userDocsQuery)->where('status', 'Approved')->count();
                $workflowStages['signed'] = (clone $userDocsQuery)->whereHas('routings', function($rq) use ($userId) {
                    $rq->where('receiver_user_id', $userId)->whereNotNull('signature');
                })->count();
                $workflowStages['reverted'] = (clone $userDocsQuery)->whereIn('status', ['Reverted', 'Returned'])->count();
                $workflowStages['completed'] = $userCompletedCount;

                // 8. User Status Distribution for Chart
                $userStatusChartData['in_process'] = $userInProcessCount;
                $userStatusChartData['for_approval'] = $workflowStages['for_approval'];
                $userStatusChartData['approved'] = $workflowStages['approved'];
                $userStatusChartData['completed'] = $userCompletedCount;
                $userStatusChartData['reverted'] = $workflowStages['reverted'];
                $userStatusChartData['overdue'] = $userOverdueCount;

                // 9. My Routing Activity
                $myRoutingActivityStats = [
                    'received_by_me'  => DocumentRouting::where('receiver_user_id', $userId)->whereIn('status', ['Received', 'Accepted', 'Completed', 'Approved', 'Signed'])->count(),
                    'routed_by_me'    => DocumentRouting::where(function($q) use ($userId) {
                        $q->where('sender_user_id', $userId)->orWhere('forwarded_from_user_id', $userId);
                    })->count(),
                    'processed_by_me' => (clone $userDocsQuery)->whereIn('status', ['Processing', 'Under Review', 'On Process', 'Completed', 'Approved'])->count(),
                    'completed_by_me' => $userCompletedCount,
                ];

                $myRoutingActivities = DocumentRouting::with(['document.currentOffice', 'fromOffice', 'toOffice', 'senderUser', 'receiverUser'])
                    ->where(function($q) use ($userId, $officeId) {
                        $q->where('receiver_user_id', $userId)
                          ->orWhere('sender_user_id', $userId)
                          ->orWhere('forwarded_from_user_id', $userId);
                        if ($officeId) {
                            $q->orWhere('to_office_id', $officeId)
                              ->orWhere('from_office_id', $officeId);
                        }
                    })
                    ->orderByDesc('id')
                    ->take(6)
                    ->get();

                // 10. Track My Documents / My Document Locations
                $myDocumentLocations = (clone $userDocsQuery)
                    ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
                    ->with(['currentOffice', 'destinationOffice', 'receiverUser', 'routings.toOffice', 'activityLogs'])
                    ->latest('updated_at')
                    ->take(6)
                    ->get()
                    ->map(function($doc) {
                        // Find latest meaningful workflow event from logs or routings
                        $latestLog = $doc->activityLogs->sortByDesc('id')->first();
                        $latestRouting = $doc->routings->sortByDesc('id')->first();

                        $lastEvent = 'Document Active';
                        $lastTime = $doc->updated_at ?? $doc->created_at;

                        if ($latestRouting && (!$latestLog || $latestRouting->created_at->gte($latestLog->created_at))) {
                            $lastEvent = $latestRouting->action_taken ?: ($latestRouting->status ? "Routed: {$latestRouting->status}" : 'Document Routed');
                            $lastTime = $latestRouting->created_at;
                        } elseif ($latestLog) {
                            $lastEvent = $latestLog->action;
                            $lastTime = $latestLog->created_at;
                        } elseif ($doc->status) {
                            $lastEvent = "Status: {$doc->status}";
                        }

                        return [
                            'id' => $doc->id,
                            'title' => $doc->title,
                            'tracking_number' => $doc->tracking_number ?? ('DOC-' . $doc->id),
                            'status' => $doc->status,
                            'current_office' => $doc->currentOffice?->name ?? 'Unassigned',
                            'current_receiver' => $doc->receiverUser?->name ?? 'Unassigned',
                            'last_event' => $lastEvent,
                            'last_event_time' => $lastTime ? $lastTime->format('M d, Y h:i A') : 'N/A',
                            'updated_at_formatted' => $doc->updated_at ? $doc->updated_at->diffForHumans() : ($lastTime ? $lastTime->diffForHumans() : 'N/A'),
                            'next_destination' => $doc->destinationOffice?->name ?? 'Final Office',
                        ];
                    });

                // 11. Timing Metrics (Strict Chronological Validation)
                $userCompletedDocs = (clone $userDocsQuery)
                    ->where('status', 'Completed')
                    ->whereNotNull('created_at')
                    ->whereNotNull('completed_at')
                    ->get()
                    ->filter(fn($d) => $d->completed_at->gt($d->created_at));

                if ($userCompletedDocs->isNotEmpty()) {
                    $totalMins = $userCompletedDocs->sum(fn($d) => $d->created_at->diffInMinutes($d->completed_at));
                    $avgMins = round($totalMins / $userCompletedDocs->count());
                    $dh = floor($avgMins / 60);
                    $dm = $avgMins % 60;
                    $userTimingMetrics['avg_completion_time'] = $dh > 0 ? "{$dh}h {$dm}m" : "{$dm}m";
                }

                $userReceivedDocs = (clone $userDocsQuery)
                    ->whereNotNull('created_at')
                    ->whereNotNull('received_at')
                    ->get()
                    ->filter(fn($d) => $d->received_at->gte($d->created_at));

                if ($userReceivedDocs->isNotEmpty()) {
                    $totalMins = $userReceivedDocs->sum(fn($d) => $d->created_at->diffInMinutes($d->received_at));
                    $avgMins = round($totalMins / $userReceivedDocs->count());
                    $dh = floor($avgMins / 60);
                    $dm = $avgMins % 60;
                    $userTimingMetrics['avg_receive_time'] = $dh > 0 ? "{$dh}h {$dm}m" : "{$dm}m";
                }

                $userProcessingDocs = (clone $userDocsQuery)
                    ->whereNotNull('received_at')
                    ->where(function($q) {
                        $q->whereNotNull('completed_at')->orWhereNotNull('processed_at');
                    })
                    ->get()
                    ->filter(function($d) {
                        $end = $d->completed_at ?? $d->processed_at;
                        return $end && $d->received_at && $end->gte($d->received_at);
                    });

                if ($userProcessingDocs->isNotEmpty()) {
                    $totalMins = $userProcessingDocs->sum(function($d) {
                        $end = $d->completed_at ?? $d->processed_at;
                        return $d->received_at->diffInMinutes($end);
                    });
                    $avgMins = round($totalMins / $userProcessingDocs->count());
                    $dh = floor($avgMins / 60);
                    $dm = $avgMins % 60;
                    $userTimingMetrics['avg_processing_time'] = $dh > 0 ? "{$dh}h {$dm}m" : "{$dm}m";
                }

                // 12. SLA Monitoring Breakdown
                $allUserDocs = (clone $userDocsQuery)->get();
                foreach ($allUserDocs as $ud) {
                    if (!$ud->due_date) {
                        $userSlaMonitoring['na']++;
                    } elseif (in_array(strtolower($ud->status), ['completed', 'archived'])) {
                        $end = $ud->completed_at ?? $ud->updated_at;
                        if ($end && $end->gt($ud->due_date)) {
                            $userSlaMonitoring['completed_beyond_sla']++;
                        } else {
                            $userSlaMonitoring['completed_within_sla']++;
                        }
                    } else {
                        if ($ud->due_date->isPast()) {
                            $userSlaMonitoring['overdue']++;
                        } elseif (now()->diffInHours($ud->due_date, false) <= 48) {
                            $userSlaMonitoring['nearing_sla']++;
                        } else {
                            $userSlaMonitoring['on_track']++;
                        }
                    }
                }

                $userTotalSlaEvaluated = $userSlaMonitoring['completed_within_sla'] + $userSlaMonitoring['completed_beyond_sla'] + $userSlaMonitoring['overdue'];
                $userSlaComplianceRate = ($userTotalSlaEvaluated > 0)
                    ? round(($userSlaMonitoring['completed_within_sla'] / $userTotalSlaEvaluated) * 100, 1)
                    : 'N/A';

                // 13. Recent Activity (User-scoped: strictly authenticated user's own operational actions)
                $userRecentActivities = ActivityLog::where(function($q) use ($user) {
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
                })
                ->with(['document.currentOffice'])
                ->latest()
                ->take(8)
                ->get();

                // 14. Recent Documents
                $userRecentDocuments = (clone $userDocsQuery)
                    ->with(['currentOffice', 'receiverUser', 'uploader'])
                    ->latest('updated_at')
                    ->take(6)
                    ->get();

                // 15. User QR Analytics (Strictly authenticated user's own scans)
                $userQrQuery = ActivityLog::where(function($q) use ($user) {
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
                })->where(function($q) {
                    $q->where('action', 'like', '%QR%')
                      ->orWhere('action', 'like', '%Scan%');
                });

                $userQrStats['total_scans'] = (clone $userQrQuery)->count();
                $userQrStats['successful_scans'] = (clone $userQrQuery)->where(function($q) {
                    $q->where('action', 'like', '%QR Scanned%')
                      ->orWhere('action', 'QR Code Verified');
                })->count();
                $userQrStats['failed_scans'] = (clone $userQrQuery)->where(function($q) {
                    $q->where('action', 'like', '%Failed%');
                })->count();
                $userQrStats['recent_scanned_docs'] = (clone $userQrQuery)
                    ->whereNotNull('document_id')
                    ->with('document')
                    ->latest()
                    ->take(4)
                    ->get();

                // 16. User Unread Notifications
                $userUnreadNotifications = $user->unreadNotifications()->latest()->take(5)->get();
            }

            return view('dashboard', compact(
                'isAdmin',
                'departmentName',
                'actionRequired',
                'totalDocs',
                'docsToday',
                'inProcessDocs',
                'forApprovalDocs',
                'pendingDocs',
                'approvedDocs',
                'rejectedDocs',
                'completedDocs',
                'archivedDocs',
                'urgentDocs',
                'overdueDocs',
                'nearingSlaDocs',
                'avgProcessingHours',
                'medianProcessingHours',
                'fastestProcessingHours',
                'longestProcessingHours',
                'slaComplianceRate',
                'completedSlaComplianceRate',
                'overallSlaComplianceRate',
                'officeWorkload',
                'qrScansToday',
                'qrSuccessfulScans',
                'qrFailedScans',
                'qrTrendDays',
                'qrTrendAttempts',
                'qrTrendSuccess',
                'calendarData',
                'selectedDateEventsData',
                'todayKey',
                'recentActivities',
                'recentUploads',
                // New User Operational Workspace variables
                'myDocumentsCount',
                'forMyActionCount',
                'userInProcessCount',
                'userCompletedCount',
                'userOverdueCount',
                'userNearingSlaCount',
                'myActionRequiredList',
                'workflowStages',
                'userStatusChartData',
                'myRoutingActivityStats',
                'myRoutingActivities',
                'myDocumentLocations',
                'userSlaMonitoring',
                'userSlaComplianceRate',
                'userTimingMetrics',
                'userRecentActivities',
                'userRecentDocuments',
                'userQrStats',
                'userUnreadNotifications'
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

    /**
     * AJAX API: Get document activity breakdown for a given year & month.
     */
    public function calendarActivity(Request $request)
    {
        try {
            $user = auth()->user() ?? User::find(session('user_id'));
            $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($user?->role ?? session('user_role'));
            $userId = $user?->id;

            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);

            if ($month < 1 || $month > 12) $month = (int) now()->month;
            if ($year < 2000 || $year > 2100) $year = (int) now()->year;

            $activityData = $this->getCalendarMonthData($userId, $isAdmin, $year, $month);

            return response()->json([
                'success' => true,
                'data'    => $activityData,
            ]);
        } catch (\Exception $e) {
            Log::error('Calendar Activity Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch calendar activity'], 500);
        }
    }

    /**
     * Helper to compute calendar document activity by date for a given month.
     */
    public function getCalendarMonthData($userId, bool $isAdmin, int $year, int $month): array
    {
        $startOfMonth = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();

        $targetUser = $userId ? User::find($userId) : null;
        $docScope = Document::accessibleBy($targetUser);
        $routeScope = DocumentRouting::whereHas('document', function ($dq) use ($targetUser) {
            $dq->accessibleBy($targetUser);
        });

        $startStr = $startOfMonth->copy()->startOfDay()->toDateTimeString();
        $endStr = $endOfMonth->copy()->endOfDay()->toDateTimeString();

        // 1. Uploaded documents
        $uploadedByDate = (clone $docScope)
            ->whereBetween('created_at', [$startStr, $endStr])
            ->select(DB::raw('DATE(created_at) as date_val'), DB::raw('count(*) as count'))
            ->groupBy('date_val')
            ->pluck('count', 'date_val')
            ->toArray();

        // 2. Routed documents (unique documents routed or routing records on that date)
        $routedByDate = (clone $routeScope)
            ->whereBetween('created_at', [$startStr, $endStr])
            ->select(DB::raw('DATE(created_at) as date_val'), DB::raw('count(DISTINCT document_id) as count'))
            ->groupBy('date_val')
            ->pluck('count', 'date_val')
            ->toArray();

        // 3. Approved documents
        $approvedByDate = (clone $docScope)
            ->where(function($q) {
                $q->where('status', 'Approved')
                  ->orWhereNotNull('approved_at');
            })
            ->where(function($q) use ($startStr, $endStr) {
                $q->whereBetween('approved_at', [$startStr, $endStr])
                  ->orWhere(function($sub) use ($startStr, $endStr) {
                      $sub->whereNull('approved_at')
                          ->whereBetween('updated_at', [$startStr, $endStr]);
                  });
            })
            ->select(DB::raw('DATE(COALESCE(approved_at, updated_at)) as date_val'), DB::raw('count(*) as count'))
            ->groupBy('date_val')
            ->pluck('count', 'date_val')
            ->toArray();

        // 4. Completed documents
        $completedByDate = (clone $docScope)
            ->where('status', 'Completed')
            ->where(function($q) use ($startStr, $endStr) {
                $q->whereBetween('completed_at', [$startStr, $endStr])
                  ->orWhereBetween('received_at', [$startStr, $endStr])
                  ->orWhere(function($sub) use ($startStr, $endStr) {
                      $sub->whereNull('completed_at')
                          ->whereNull('received_at')
                          ->whereBetween('updated_at', [$startStr, $endStr]);
                  });
            })
            ->select(DB::raw('DATE(COALESCE(completed_at, received_at, updated_at)) as date_val'), DB::raw('count(*) as count'))
            ->groupBy('date_val')
            ->pluck('count', 'date_val')
            ->toArray();

        // 5. Pending documents
        $pendingByDate = (clone $docScope)
            ->where('status', 'Pending')
            ->whereBetween('created_at', [$startStr, $endStr])
            ->select(DB::raw('DATE(created_at) as date_val'), DB::raw('count(*) as count'))
            ->groupBy('date_val')
            ->pluck('count', 'date_val')
            ->toArray();

        // 6. Received documents by date
        $receivedByDate = (clone $docScope)
            ->whereNotNull('received_at')
            ->whereBetween('received_at', [$startStr, $endStr])
            ->select(DB::raw('DATE(received_at) as date_val'), DB::raw('count(*) as count'))
            ->groupBy('date_val')
            ->pluck('count', 'date_val')
            ->toArray();

        // 7. QR scans by date
        $qrScansByDate = ActivityLog::where(function($q) {
                $q->where('action', 'like', '%QR Scanned%')
                  ->orWhere('action', 'QR Code Verified');
            })
            ->whereBetween('created_at', [$startStr, $endStr])
            ->select(DB::raw('DATE(created_at) as date_val'), DB::raw('count(*) as count'))
            ->groupBy('date_val')
            ->pluck('count', 'date_val')
            ->toArray();

        // 8. Overdue documents due on that date
        $overdueByDate = (clone $docScope)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$startStr, $endStr])
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
            ->select(DB::raw('DATE(due_date) as date_val'), DB::raw('count(*) as count'))
            ->groupBy('date_val')
            ->pluck('count', 'date_val')
            ->toArray();

        $daysInMonth = (int) $endOfMonth->day;
        $activityMap = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $u = (int) ($uploadedByDate[$dateKey] ?? 0);
            $r = (int) ($routedByDate[$dateKey] ?? 0);
            $a = (int) ($approvedByDate[$dateKey] ?? 0);
            $c = (int) ($completedByDate[$dateKey] ?? 0);
            $p = (int) ($pendingByDate[$dateKey] ?? 0);
            $recv = (int) ($receivedByDate[$dateKey] ?? 0);
            $scans = (int) ($qrScansByDate[$dateKey] ?? 0);
            $od = (int) ($overdueByDate[$dateKey] ?? 0);

            $tot = $u + $r + $a + $c + $recv + $scans;

            $activityMap[$dateKey] = [
                'date'         => $dateKey,
                'day'          => $d,
                'total_events' => $tot,
                'uploaded'     => $u,
                'routed'       => $r,
                'approved'     => $a,
                'completed'    => $c,
                'pending'      => $p,
                'received'     => $recv,
                'qr_scans'     => $scans,
                'overdue'      => $od,
                'has_activity' => ($tot > 0 || $p > 0 || $od > 0),
            ];
        }

        return [
            'year'              => $year,
            'month'             => $month,
            'month_name'        => $startOfMonth->format('F Y'),
            'days_in_month'     => $daysInMonth,
            'first_day_of_week' => (int) $startOfMonth->dayOfWeek, // 0 = Sunday, 1 = Monday, etc.
            'activity'          => $activityMap,
        ];
    }

    /**
     * AJAX API: Get real document events for a specific calendar date.
     */
    public function calendarDateEvents(Request $request)
    {
        try {
            $user = auth()->user() ?? User::find(session('user_id'));
            $isAdmin = ($user && $user->isAdmin()) || User::isRoleAdmin($user?->role ?? session('user_role'));
            $userId = $user?->id;

            $dateStr = $request->input('date', now()->toDateString());
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                $dateStr = now()->toDateString();
            }

            $data = $this->getCalendarDateEvents($dateStr, $userId, $isAdmin);

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Calendar Date Events API Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch date events: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get real document events for a given calendar date.
     */
    public function getCalendarDateEvents(string $dateStr, $userId, bool $isAdmin): array
    {
        $targetDate = \Carbon\Carbon::parse($dateStr);
        $user = $userId ? User::find($userId) : null;
        $officeId = $user?->office_id;

        $groupedDocs = [];
        $seenEvents = [];

        // Helper to check user access strictly according to DocumentPolicy
        $policy = app(\App\Policies\DocumentPolicy::class);
        $canAccess = function ($doc) use ($policy, $user, $isAdmin) {
            if ($isAdmin || !$doc) return true;
            return $policy->viewWorkflow($user, $doc);
        };

        // Helper to register document
        $initDoc = function ($doc) use (&$groupedDocs) {
            if (!$doc || isset($groupedDocs[$doc->id])) return;
            $groupedDocs[$doc->id] = [
                'id'                 => $doc->id,
                'title'              => $doc->title ?: 'Untitled Document',
                'tracking_number'    => $doc->tracking_number ?: ('DOC-' . $doc->id),
                'status'             => $doc->status ?: 'Active',
                'current_office'     => $doc->currentOffice?->name ?? 'Unassigned Office',
                'origin_office'      => $doc->originOffice?->name ?? null,
                'destination_office' => $doc->destinationOffice?->name ?? null,
                'passport_url'       => route('documents.passport', $doc->id),
                'details_url'        => route('documents.show', $doc->id),
                'events'             => [],
                'latest_timestamp'   => null,
            ];
        };

        // Helper to add an event
        $addEvent = function ($doc, $actionTitle, $badgeClass, $icon, $officeStr, $userStr, $carbonTime, $details = null) use (&$groupedDocs, &$seenEvents, $initDoc) {
            if (!$doc || !$carbonTime) return;
            $docId = $doc->id;
            $initDoc($doc);

            $timeStr = $carbonTime->format('h:i A');
            $isoStr = $carbonTime->toIso8601String();
            $dedupKey = $docId . '|' . strtolower(trim($actionTitle)) . '|' . $carbonTime->format('Y-m-d H:i');

            if (isset($seenEvents[$dedupKey])) {
                return;
            }
            $seenEvents[$dedupKey] = true;

            $eventItem = [
                'time'        => $timeStr,
                'timestamp'   => $isoStr,
                'action'      => $actionTitle,
                'badge_class' => $badgeClass,
                'icon'        => $icon,
                'office'      => $officeStr ?: ($doc->currentOffice?->name ?? 'General Office'),
                'user'        => $userStr ?: 'System',
                'details'     => $details ?: '',
            ];

            $groupedDocs[$docId]['events'][] = $eventItem;

            if (!$groupedDocs[$docId]['latest_timestamp'] || $carbonTime->gt(\Carbon\Carbon::parse($groupedDocs[$docId]['latest_timestamp']))) {
                $groupedDocs[$docId]['latest_timestamp'] = $isoStr;
            }
        };

        // 1. Activity Logs for this date
        $activityLogs = ActivityLog::with(['document.currentOffice', 'document.originOffice', 'document.destinationOffice'])
            ->whereDate('created_at', $dateStr)
            ->whereNotNull('document_id')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($activityLogs as $log) {
            $doc = $log->document;
            if (!$doc || !$canAccess($doc)) continue;

            $action = $log->action ?: 'Document Action';
            $badge = 'bg-secondary text-white';
            $icon = 'bi-circle';

            if (stripos($action, 'Upload') !== false || stripos($action, 'Created') !== false) {
                $badge = 'bg-primary text-white';
                $icon = 'bi-cloud-arrow-up';
            } elseif (stripos($action, 'Route') !== false || stripos($action, 'Forward') !== false) {
                $badge = 'bg-purple text-white';
                $icon = 'bi-arrow-left-right';
            } elseif (stripos($action, 'Receiv') !== false) {
                $badge = 'bg-info text-dark';
                $icon = 'bi-inbox';
            } elseif (stripos($action, 'Process') !== false || stripos($action, 'Review') !== false) {
                $badge = 'bg-warning text-dark';
                $icon = 'bi-gear';
            } elseif (stripos($action, 'Approv') !== false || stripos($action, 'Endors') !== false) {
                $badge = 'bg-success text-white';
                $icon = 'bi-check2-circle';
            } elseif (stripos($action, 'Complet') !== false) {
                $badge = 'bg-teal text-white';
                $icon = 'bi-patch-check';
            } elseif (stripos($action, 'Revert') !== false || stripos($action, 'Return') !== false || stripos($action, 'Reject') !== false) {
                $badge = 'bg-danger text-white';
                $icon = 'bi-arrow-counterclockwise';
            } elseif (stripos($action, 'Sign') !== false) {
                $badge = 'bg-indigo text-white';
                $icon = 'bi-pen';
            } elseif (stripos($action, 'QR') !== false) {
                $badge = 'bg-dark text-white';
                $icon = 'bi-qr-code-scan';
            }

            $officeName = null;
            if (is_array($log->meta) && !empty($log->meta['department'])) {
                $officeName = $log->meta['department'];
            } elseif ($doc->currentOffice) {
                $officeName = $doc->currentOffice->name;
            }

            $userName = $log->user ?: 'System';
            $details = '';
            if (is_array($log->meta) && !empty($log->meta['remarks'])) {
                $details = $log->meta['remarks'];
            }

            $addEvent($doc, $action, $badge, $icon, $officeName, $userName, $log->created_at, $details);
        }

        // 2. Document Routings Created, Received, or Released on this date
        $routings = DocumentRouting::with(['document.currentOffice', 'fromOffice', 'toOffice', 'senderUser', 'receiverUser'])
            ->where(function($q) use ($dateStr) {
                $q->whereDate('created_at', $dateStr)
                  ->orWhereDate('received_at', $dateStr)
                  ->orWhereDate('released_at', $dateStr);
            })
            ->get();

        foreach ($routings as $routing) {
            $doc = $routing->document;
            if (!$doc || !$canAccess($doc)) continue;

            $fromOffice = $routing->fromOffice?->name;
            $toOffice = $routing->toOffice?->name;
            $routePath = ($fromOffice && $toOffice) ? "{$fromOffice} → {$toOffice}" : ($toOffice ?: $fromOffice);

            // Created / Routed
            if ($routing->created_at && $routing->created_at->toDateString() === $dateStr) {
                $sender = $routing->senderUser?->name ?: 'Staff';
                $addEvent($doc, 'Document Routed', 'bg-purple text-white', 'bi-arrow-left-right', $routePath, $sender, $routing->created_at, $routing->notes);
            }

            // Received
            if ($routing->received_at && $routing->received_at->toDateString() === $dateStr) {
                $receiver = $routing->receiverUser?->name ?: 'Staff';
                $addEvent($doc, 'Document Received', 'bg-info text-dark', 'bi-inbox', $toOffice ?: $doc->currentOffice?->name, $receiver, $routing->received_at, $routing->notes);
            }

            // Released / Completed step
            if ($routing->released_at && $routing->released_at->toDateString() === $dateStr) {
                $signer = $routing->signed_by ?: ($routing->receiverUser?->name ?: 'Staff');
                $actionLabel = $routing->status === 'Approved' ? 'Document Approved' : ($routing->status === 'Completed' ? 'Document Completed' : 'Routing Completed');
                $badge = $routing->status === 'Approved' ? 'bg-success text-white' : 'bg-teal text-white';
                $addEvent($doc, $actionLabel, $badge, 'bi-check2-circle', $toOffice ?: $doc->currentOffice?->name, $signer, $routing->released_at, $routing->notes);
            }
        }

        // 3. Document master timestamps (Upload, Approval, Completion, Receiving)
        $docsMaster = Document::with(['currentOffice', 'originOffice', 'destinationOffice', 'uploader'])
            ->where(function($q) use ($dateStr) {
                $q->whereDate('created_at', $dateStr)
                  ->orWhereDate('approved_at', $dateStr)
                  ->orWhereDate('completed_at', $dateStr)
                  ->orWhereDate('received_at', $dateStr);
            })
            ->get();

        foreach ($docsMaster as $doc) {
            if (!$canAccess($doc)) continue;

            // Uploaded
            if ($doc->created_at && $doc->created_at->toDateString() === $dateStr) {
                $uploader = $doc->uploader?->name ?: 'Uploader';
                $addEvent($doc, 'Document Uploaded', 'bg-primary text-white', 'bi-cloud-arrow-up', $doc->originOffice?->name ?: $doc->currentOffice?->name, $uploader, $doc->created_at, 'Initial Document Registration');
            }

            // Received
            if ($doc->received_at && $doc->received_at->toDateString() === $dateStr) {
                $addEvent($doc, 'Document Received', 'bg-info text-dark', 'bi-inbox', $doc->currentOffice?->name, 'Office Staff', $doc->received_at, 'Received at current office');
            }

            // Approved
            if ($doc->approved_at && $doc->approved_at->toDateString() === $dateStr) {
                $addEvent($doc, 'Document Approved', 'bg-success text-white', 'bi-check2-circle', $doc->currentOffice?->name, 'Approving Authority', $doc->approved_at, 'Document approved');
            }

            // Completed
            if ($doc->completed_at && $doc->completed_at->toDateString() === $dateStr) {
                $addEvent($doc, 'Document Completed', 'bg-teal text-white', 'bi-patch-check', $doc->currentOffice?->name, 'System', $doc->completed_at, 'Document cycle completed');
            }
        }

        // Sort events inside each document chronologically (earliest to latest in the day)
        foreach ($groupedDocs as &$item) {
            usort($item['events'], function ($a, $b) {
                return strcmp($a['timestamp'], $b['timestamp']);
            });
        }
        unset($item);

        // Sort documents by their latest event timestamp descending (most recent document at the top)
        $docsList = array_values($groupedDocs);
        usort($docsList, function ($a, $b) {
            return strcmp($b['latest_timestamp'] ?? '', $a['latest_timestamp'] ?? '');
        });

        // Compute summary counts for the day
        $totalEventsCount = 0;
        $uploadedCount = 0;
        $routedCount = 0;
        $receivedCount = 0;
        $approvedCount = 0;
        $completedCount = 0;

        foreach ($docsList as $dItem) {
            $totalEventsCount += count($dItem['events']);
            foreach ($dItem['events'] as $ev) {
                $actLower = strtolower($ev['action']);
                if (str_contains($actLower, 'upload') || str_contains($actLower, 'created')) {
                    $uploadedCount++;
                } elseif (str_contains($actLower, 'route') || str_contains($actLower, 'forward')) {
                    $routedCount++;
                } elseif (str_contains($actLower, 'receiv')) {
                    $receivedCount++;
                } elseif (str_contains($actLower, 'approv') || str_contains($actLower, 'endors')) {
                    $approvedCount++;
                } elseif (str_contains($actLower, 'complet')) {
                    $completedCount++;
                }
            }
        }

        return [
            'date'            => $dateStr,
            'formatted_date'  => $targetDate->format('M d, Y'),
            'full_date_label' => $targetDate->format('l, F j, Y'),
            'total_events'    => $totalEventsCount,
            'stats'           => [
                'uploaded'  => $uploadedCount,
                'routed'    => $routedCount,
                'received'  => $receivedCount,
                'approved'  => $approvedCount,
                'completed' => $completedCount,
            ],
            'documents'       => $docsList,
        ];
    }
}