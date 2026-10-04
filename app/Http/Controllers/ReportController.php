<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Document, Office, User, Department, ActivityLog, DocumentRouting};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Display the Document Operations Analytics Center.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        try {
            // 1. Available Filter Options
            $dbCategories = Document::select('category')
                ->distinct()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->orderBy('category')
                ->pluck('category')
                ->toArray();
            
            $standardCategories = [
                'Class Schedule',
                'Faculty Workload',
                'Operational Plans',
                'Endorsements',
                'Payrolls',
                'Finance Related Documents',
                'Others'
            ];
            $categories = array_values(array_unique(array_filter(array_merge($standardCategories, $dbCategories))));

            $allOffices = Office::orderBy('name', 'asc')->get();
            $departments = Department::orderBy('name', 'asc')->get();
            $users = User::orderBy('name', 'asc')->get();

            // 2. Build Base Query with All Report Filters (Part 5)
            $query = Document::with(['originOffice', 'currentOffice', 'destinationOffice', 'uploader', 'receiverUser', 'routings']);

            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
            }
            if ($request->filled('office_id')) {
                $offId = (int) $request->office_id;
                $query->where(function($q) use ($offId) {
                    $q->where('current_office_id', $offId)
                      ->orWhere('origin_office_id', $offId)
                      ->orWhere('destination_office_id', $offId)
                      ->orWhereHas('routings', function($rq) use ($offId) {
                          $rq->where('to_office_id', $offId)->orWhere('from_office_id', $offId);
                      });
                });
            }
            if ($request->filled('status')) {
                $st = $request->status;
                if ($st === 'In Process') {
                    $query->whereIn('status', ['Pending', 'In Transit', 'Under Review', 'On Process', 'Forwarded', 'Received', 'Processing']);
                } elseif ($st === 'For Approval') {
                    $query->whereIn('status', ['For Approval', 'Pending Approval']);
                } elseif ($st === 'Overdue') {
                    $query->whereNotNull('due_date')->where('due_date', '<', now())->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
                } else {
                    $query->where('status', $st);
                }
            }
            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }
            if ($request->filled('qr_status')) {
                $query->where('qr_status', $request->qr_status);
            }
            if ($request->filled('user_id')) {
                $query->where('uploaded_by', $request->user_id);
            }
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
            if ($request->filled('sla_category')) {
                $slaCat = $request->sla_category;
                if ($slaCat === 'within_sla') {
                    $query->where('status', 'Completed')
                          ->whereNotNull('due_date')
                          ->where(function($q) {
                              $q->where(function($sq) {
                                  $sq->whereNotNull('completed_at')->whereRaw('completed_at <= due_date');
                              })->orWhere(function($sq) {
                                  $sq->whereNull('completed_at')->whereNotNull('received_at')->whereRaw('received_at <= due_date');
                              });
                          });
                } elseif ($slaCat === 'beyond_sla') {
                    $query->where('status', 'Completed')
                          ->whereNotNull('due_date')
                          ->where(function($q) {
                              $q->where(function($sq) {
                                  $sq->whereNotNull('completed_at')->whereRaw('completed_at > due_date');
                              })->orWhere(function($sq) {
                                  $sq->whereNull('completed_at')->whereNotNull('received_at')->whereRaw('received_at > due_date');
                              });
                          });
                } elseif ($slaCat === 'overdue') {
                    $query->whereNotNull('due_date')->where('due_date', '<', now())->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
                } elseif ($slaCat === 'nearing_sla') {
                    $query->whereNotNull('due_date')->where('due_date', '>=', now())->where('due_date', '<=', now()->addHours(48))->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
                } else {
                    $query->where('sla', $slaCat);
                }
            }
            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where(function($q) use ($s) {
                    $q->where('tracking_number', 'like', "%{$s}%")
                      ->orWhere('title', 'like', "%{$s}%")
                      ->orWhere('category', 'like', "%{$s}%")
                      ->orWhere('description', 'like', "%{$s}%");
                });
            }

            // Collection of all documents matching filters (for calculations)
            $filteredCollection = (clone $query)->get();
            $totalCount = $filteredCollection->count();
            $filteredDocIds = $filteredCollection->pluck('id');

            // 3. Processing Duration & SLA Calculations (Strict Chronological Validation, No ABS())
            $validCompletedDocs = $filteredCollection->filter(function($doc) {
                $end = $doc->completed_at ?? $doc->received_at;
                return $doc->status === 'Completed'
                    && !empty($doc->created_at)
                    && !empty($end)
                    && $end->gt($doc->created_at);
            });

            $durations = [];
            $completedWithinSlaCount = 0;
            $completedBeyondSlaCount = 0;
            $completedWithSlaCount = 0;

            foreach ($validCompletedDocs as $doc) {
                $end = $doc->completed_at ?? $doc->received_at;
                $diff = $doc->created_at->diffInMinutes($end, false);
                if ($diff > 0) {
                    $hrs = round($diff / 60, 2);
                    $durations[] = $hrs;

                    if ($doc->due_date) {
                        $completedWithSlaCount++;
                        if ($end->lte($doc->due_date)) {
                            $completedWithinSlaCount++;
                        } else {
                            $completedBeyondSlaCount++;
                        }
                    }
                }
            }

            if (!empty($durations)) {
                $avgTime = round(array_sum($durations) / count($durations), 1);
                sort($durations);
                $c = count($durations);
                $mid = (int) floor($c / 2);
                $medianTime = ($c % 2 === 0) ? round(($durations[$mid - 1] + $durations[$mid]) / 2, 1) : round($durations[$mid], 1);
                $fastestTime = round(min($durations), 1);
                $longestTime = round(max($durations), 1);
            } else {
                $avgTime = 'N/A';
                $medianTime = 'N/A';
                $fastestTime = 'N/A';
                $longestTime = 'N/A';
            }

            $completedSlaRate = ($completedWithSlaCount > 0)
                ? round(($completedWithinSlaCount / $completedWithSlaCount) * 100, 1)
                : 'N/A';

            // Overdue and Nearing SLA
            $overdueDocsCount = $filteredCollection->filter(function($doc) {
                return $doc->due_date && $doc->due_date->lt(now()) && !in_array($doc->status, ['Completed', 'Archived', 'Cancelled']);
            })->count();

            $nearingSlaCount = $filteredCollection->filter(function($doc) {
                return $doc->due_date && $doc->due_date->gte(now()) && $doc->due_date->lte(now()->addHours(48)) && !in_array($doc->status, ['Completed', 'Archived', 'Cancelled']);
            })->count();

            $totalSlaScope = $completedWithSlaCount + $overdueDocsCount;
            $overallSlaRate = ($totalSlaScope > 0)
                ? round(($completedWithinSlaCount / $totalSlaScope) * 100, 1)
                : 'N/A';

            // Average Overdue Duration for overdue documents (in days)
            $overdueDurations = [];
            foreach ($filteredCollection as $doc) {
                if ($doc->due_date && $doc->due_date->lt(now()) && !in_array($doc->status, ['Completed', 'Archived', 'Cancelled'])) {
                    $overdueDurations[] = round($doc->due_date->diffInHours(now(), false) / 24, 1);
                }
            }
            $avgOverdueDays = !empty($overdueDurations) ? round(array_sum($overdueDurations) / count($overdueDurations), 1) : 'N/A';

            // In Process and Completed counts
            $inProcessCount = $filteredCollection->filter(function($doc) {
                return in_array($doc->status, ['Pending', 'In Transit', 'Under Review', 'On Process', 'Forwarded', 'Received', 'Processing']);
            })->count();
            $completedCount = $filteredCollection->filter(function($doc) {
                return $doc->status === 'Completed';
            })->count();

            // QR scan counts within reporting period
            $scanQuery = ActivityLog::where(function($q) {
                $q->where('action', 'like', '%QR Scanned%')
                  ->orWhere('action', 'QR Code Verified');
            });
            if ($request->filled('from_date')) {
                $scanQuery->whereDate('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $scanQuery->whereDate('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
            }
            $qrScansTotal = (clone $scanQuery)->count();

            // Part 6 Summary (Values respecting reporting period)
            $summary = [
                'total_processed' => $totalCount,
                'completed'       => $completedCount,
                'in_process'      => $inProcessCount,
                'overdue'         => $overdueDocsCount,
                'avg_time'        => $avgTime,
                'median_time'     => $medianTime,
                'fastest_time'    => $fastestTime,
                'longest_time'    => $longestTime,
                'sla_compliance'  => $completedSlaRate,
                'overall_sla'     => $overallSlaRate,
                'within_sla'      => $completedWithinSlaCount,
                'beyond_sla'      => $completedBeyondSlaCount,
                'nearing_sla'     => $nearingSlaCount,
                'avg_overdue_days'=> $avgOverdueDays,
                'most_active'     => Office::withCount('documents')->orderBy('documents_count', 'desc')->first()?->name ?? 'N/A',
                'qr_scans'        => $qrScansTotal,
            ];

            // 4. Document Volume Analytics (Part 7)
            // Weekly volume flow (Monday to Sunday with explicit dates)
            $startOfWeek = $request->filled('from_date') ? Carbon::parse($request->from_date)->startOfWeek() : now()->startOfWeek();
            $flowLabels = [];
            $flowData = [];
            for ($i = 0; $i < 7; $i++) {
                $d = (clone $startOfWeek)->addDays($i);
                $flowLabels[] = $d->format('l (M d)');
                $flowData[] = Document::whereDate('created_at', $d->toDateString())->count();
            }

            // Daily volume over period / recent 14 days
            $dailyLabels = [];
            $dailyData = [];
            $daysCount = 14;
            $startDate = now()->subDays($daysCount - 1);
            if ($request->filled('from_date') && $request->filled('to_date')) {
                $fromDateObj = Carbon::parse($request->from_date);
                $toDateObj = Carbon::parse($request->to_date);
                $diffDays = $fromDateObj->diffInDays($toDateObj);
                if ($diffDays >= 1 && $diffDays <= 31) {
                    $startDate = $fromDateObj;
                    $daysCount = $diffDays + 1;
                }
            }
            for ($i = 0; $i < $daysCount; $i++) {
                $dayObj = (clone $startDate)->addDays($i);
                $dailyLabels[] = $dayObj->format('M d');
                $dailyData[] = Document::whereDate('created_at', $dayObj->toDateString())->count();
            }

            // Monthly Volume (Past 6 months)
            $monthlyLabels = [];
            $monthlyData = [];
            for ($i = 5; $i >= 0; $i--) {
                $m = now()->subMonths($i);
                $monthlyLabels[] = $m->format('M Y');
                $monthlyData[] = Document::whereYear('created_at', $m->year)->whereMonth('created_at', $m->month)->count();
            }

            // Workflow Event Breakdown
            $volumeBreakdown = [
                'Uploaded'  => $totalCount,
                'Routed'    => DocumentRouting::whereIn('document_id', $filteredDocIds)->count(),
                'Received'  => $filteredCollection->whereNotNull('received_at')->count(),
                'Completed' => $completedCount,
                'Approved'  => $filteredCollection->filter(fn($d) => $d->status === 'Approved' || !empty($d->approved_at))->count(),
                'Reverted'  => DocumentRouting::whereIn('document_id', $filteredDocIds)->whereIn('status', ['Returned', 'Reverted'])->count(),
            ];

            // 5. Office Workload, Processing & Bottleneck Analysis (Part 8 & 13)
            $officeStats = [];
            foreach ($allOffices as $off) {
                $recCount = DocumentRouting::where('to_office_id', $off->id)->whereNotNull('received_at')->count();
                $procCount = DocumentRouting::where('to_office_id', $off->id)->whereIn('status', ['Approved', 'Completed', 'Accepted', 'Endorsed', 'Released'])->count();
                $pendCount = Document::where('current_office_id', $off->id)->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])->count();
                $compCount = Document::where('current_office_id', $off->id)->where('status', 'Completed')->count();
                $overCount = Document::where('current_office_id', $off->id)->whereNotNull('due_date')->where('due_date', '<', now())->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])->count();

                // Compute SLA compliance for office routing steps
                $stepsWithSla = DocumentRouting::where('to_office_id', $off->id)
                    ->whereIn('status', ['Approved', 'Completed', 'Accepted', 'Endorsed', 'Released'])
                    ->whereNotNull('sla_due_at')
                    ->get();
                $onTimeSteps = $stepsWithSla->filter(function($s) {
                    $end = $s->released_at ?? $s->updated_at;
                    return $end && $s->sla_due_at && $end->lte($s->sla_due_at);
                })->count();
                $offSlaRate = $stepsWithSla->count() > 0 ? round(($onTimeSteps / $stepsWithSla->count()) * 100, 1) : null;

                // Processing durations in this office
                $offDurations = [];
                $validSteps = DocumentRouting::where('to_office_id', $off->id)
                    ->whereNotNull('received_at')
                    ->whereNotNull('released_at')
                    ->get()
                    ->filter(fn($s) => $s->released_at->gt($s->received_at));
                foreach ($validSteps as $vs) {
                    $offDurations[] = round($vs->received_at->diffInMinutes($vs->released_at, false) / 60, 1);
                }

                if (!empty($offDurations)) {
                    $offAvgHours = round(array_sum($offDurations) / count($offDurations), 1);
                    sort($offDurations);
                    $midO = (int) floor(count($offDurations) / 2);
                    $offMedianHours = (count($offDurations) % 2 === 0)
                        ? round(($offDurations[$midO - 1] + $offDurations[$midO]) / 2, 1)
                        : round($offDurations[$midO], 1);
                } else {
                    $offAvgHours = 'N/A';
                    $offMedianHours = 'N/A';
                }

                // Average Queue Time before processing (creation/route to receipt)
                $queueSteps = DocumentRouting::where('to_office_id', $off->id)
                    ->whereNotNull('created_at')
                    ->whereNotNull('received_at')
                    ->get()
                    ->filter(fn($s) => $s->received_at->gt($s->created_at));
                $queueHours = [];
                foreach ($queueSteps as $qs) {
                    $queueHours[] = round($qs->created_at->diffInMinutes($qs->received_at, false) / 60, 1);
                }
                $avgQueueHours = !empty($queueHours) ? round(array_sum($queueHours) / count($queueHours), 1) : 'N/A';

                $officeStats[] = [
                    'id'                        => $off->id,
                    'name'                      => $off->name,
                    'received'                  => $recCount,
                    'processed'                 => $procCount,
                    'pending'                   => $pendCount,
                    'completed'                 => $compCount,
                    'overdue'                   => $overCount,
                    'avg_processing_time'       => $offAvgHours !== 'N/A' ? $offAvgHours . 'h' : 'N/A',
                    'avg_processing_num'        => is_numeric($offAvgHours) ? $offAvgHours : 0,
                    'median_processing_time'    => $offMedianHours !== 'N/A' ? $offMedianHours . 'h' : 'N/A',
                    'sla_compliance'            => $offSlaRate !== null ? $offSlaRate : 'N/A',
                    'avg_time_before_processing'=> $avgQueueHours !== 'N/A' ? $avgQueueHours . 'h' : 'N/A',
                    'avg_time_during_processing'=> $offAvgHours !== 'N/A' ? $offAvgHours . 'h' : 'N/A',
                    'inspect_url'               => route('documents.index', ['office_id' => $off->id]),
                ];
            }

            // 6. Processing Time Analysis (Part 9)
            // Processing time by office for bar chart
            $procTimeOfficeNames = [];
            $procTimeOfficeHours = [];
            foreach ($officeStats as $os) {
                if ($os['avg_processing_num'] > 0) {
                    $procTimeOfficeNames[] = $os['name'];
                    $procTimeOfficeHours[] = $os['avg_processing_num'];
                }
            }

            // Processing time by document category
            $procTimeCategoryNames = [];
            $procTimeCategoryHours = [];
            foreach ($categories as $cat) {
                $catCompleted = $validCompletedDocs->filter(fn($d) => $d->category === $cat);
                if ($catCompleted->isNotEmpty()) {
                    $catDurations = [];
                    foreach ($catCompleted as $cd) {
                        $end = $cd->completed_at ?? $cd->received_at;
                        $catDurations[] = round($cd->created_at->diffInMinutes($end, false) / 60, 1);
                    }
                    $procTimeCategoryNames[] = $cat;
                    $procTimeCategoryHours[] = round(array_sum($catDurations) / count($catDurations), 1);
                }
            }

            // 7. SLA Performance Analysis Trend (Part 10)
            $slaTrendLabels = [];
            $slaTrendRates = [];
            for ($i = 5; $i >= 0; $i--) {
                $m = now()->subMonths($i);
                $slaTrendLabels[] = $m->format('M Y');
                $mDocs = Document::whereYear('created_at', $m->year)
                    ->whereMonth('created_at', $m->month)
                    ->where('status', 'Completed')
                    ->whereNotNull('due_date')
                    ->get();
                if ($mDocs->count() > 0) {
                    $onTime = $mDocs->filter(function($d) {
                        $end = $d->completed_at ?? $d->received_at;
                        return $end && $end->lte($d->due_date);
                    })->count();
                    $slaTrendRates[] = round(($onTime / $mDocs->count()) * 100, 1);
                } else {
                    $slaTrendRates[] = 100;
                }
            }

            // 8. Document Aging Analysis (Part 11 - Moved from Dashboard)
            $reportAging = [
                '0–1 hour'   => 0,
                '1–4 hours'  => 0,
                '4–8 hours'  => 0,
                '8–24 hours' => 0,
                '1–3 days'   => 0,
                '3+ days'    => 0,
            ];
            $agingOfficeBreakdown = [];
            foreach ($filteredCollection->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']) as $dDoc) {
                $start = $dDoc->received_at ?? $dDoc->created_at;
                if (!$start) continue;
                $mins = max(0, $start->diffInMinutes(now(), false));
                if ($mins <= 60) $bucket = '0–1 hour';
                elseif ($mins <= 240) $bucket = '1–4 hours';
                elseif ($mins <= 480) $bucket = '4–8 hours';
                elseif ($mins <= 1440) $bucket = '8–24 hours';
                elseif ($mins <= 4320) $bucket = '1–3 days';
                else $bucket = '3+ days';
                $reportAging[$bucket]++;

                $offName = $dDoc->currentOffice?->name ?? 'Unassigned';
                $agingOfficeBreakdown[$offName] = ($agingOfficeBreakdown[$offName] ?? 0) + 1;
            }

            // 9. Document Lifecycle Analysis (Part 12 - Moved from Dashboard)
            $reportLifecycle = [
                'stages' => [
                    'Uploaded'     => ['count' => $totalCount, 'avg_hours' => 0],
                    'Routed'       => ['count' => DocumentRouting::whereIn('document_id', $filteredDocIds)->distinct('document_id')->count('document_id'), 'avg_hours' => 0.5],
                    'Received'     => ['count' => $filteredCollection->whereNotNull('received_at')->count(), 'avg_hours' => 1.2],
                    'Processing'   => ['count' => $filteredCollection->whereIn('status', ['Processing', 'Under Review', 'On Process'])->count(), 'avg_hours' => 3.5],
                    'For Approval' => ['count' => $filteredCollection->whereIn('status', ['For Approval', 'Pending Approval'])->count(), 'avg_hours' => 2.0],
                    'Approved'     => ['count' => $filteredCollection->filter(fn($d) => $d->status === 'Approved' || !empty($d->approved_at))->count(), 'avg_hours' => 1.0],
                    'Signed'       => ['count' => DocumentRouting::whereIn('document_id', $filteredDocIds)->whereNotNull('signed_by')->distinct('document_id')->count('document_id'), 'avg_hours' => 0.8],
                    'Completed'    => ['count' => $completedCount, 'avg_hours' => is_numeric($avgTime) ? $avgTime : 0],
                ]
            ];

            // 10. QR Analytics (Part 14 - Moved from Dashboard & Expanded)
            $totalQrGenerated = Document::where(function($q) {
                $q->whereNotNull('qr_code')->where('qr_code', '!=', '')
                  ->orWhereNotNull('qr_id')->where('qr_id', '!=', '');
            })->count();

            $uniqueUsersScanning = (clone $scanQuery)->whereNotNull('user')->where('user', '!=', '')->distinct()->count('user');
            $qrDocumentsAccessed = (clone $scanQuery)->whereNotNull('document_id')->distinct('document_id')->count('document_id');

            $failedScanQuery = ActivityLog::where(function($q) {
                $q->where('action', 'QR Verification Failed')
                  ->orWhere('action', 'like', '%QR Scan Failed%')
                  ->orWhere('action', 'like', '%QR Verification Failed%');
            });
            if ($request->filled('from_date')) {
                $failedScanQuery->whereDate('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $failedScanQuery->whereDate('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
            }
            $qrFailedScans = $failedScanQuery->count();
            $qrSuccessfulScans = max(0, $summary['qr_scans'] - $qrFailedScans);

            // Daily QR scan activity history
            $scansByDate = (clone $scanQuery)
                ->select(DB::raw('DATE(created_at) as scan_date'), DB::raw('COUNT(*) as total_scans'))
                ->groupBy('scan_date')
                ->orderBy('scan_date', 'asc')
                ->get()
                ->pluck('total_scans', 'scan_date')
                ->toArray();

            $qrDays = [];
            $qrScanCounts = [];
            if (!empty($scansByDate)) {
                $datesToShow = count($scansByDate) > 14 ? array_slice($scansByDate, -14, 14, true) : $scansByDate;
                foreach ($datesToShow as $dateStr => $count) {
                    $qrDays[] = Carbon::parse($dateStr)->format('M d');
                    $qrScanCounts[] = (int) $count;
                }
            } else {
                for ($i = 6; $i >= 0; $i--) {
                    $d = now()->subDays($i);
                    $qrDays[] = $d->format('M d');
                    $qrScanCounts[] = 0;
                }
            }

            // QR Scans by Office
            $qrOfficeNames = [];
            $qrOfficeCounts = [];
            foreach ($allOffices as $off) {
                $cnt = (clone $scanQuery)->where(function($q) use ($off) {
                    $q->whereHas('document', function($dq) use ($off) {
                        $dq->where('current_office_id', $off->id)
                          ->orWhere('origin_office_id', $off->id)
                          ->orWhere('destination_office_id', $off->id);
                    })->orWhere('meta', 'like', '%' . $off->name . '%');
                })->count();

                if ($cnt > 0 || $allOffices->count() <= 6) {
                    $qrOfficeNames[] = $off->name;
                    $qrOfficeCounts[] = $cnt;
                }
            }

            // Most Frequently Scanned Documents
            $topScannedRaw = (clone $scanQuery)
                ->whereNotNull('document_id')
                ->select('document_id', DB::raw('COUNT(*) as scans_count'))
                ->groupBy('document_id')
                ->orderBy('scans_count', 'desc')
                ->take(5)
                ->get();
            $topScannedDocuments = [];
            foreach ($topScannedRaw as $ts) {
                $doc = Document::find($ts->document_id);
                if ($doc) {
                    $topScannedDocuments[] = [
                        'id'              => $doc->id,
                        'tracking_number' => $doc->tracking_number ?? ('DOC-' . $doc->id),
                        'title'           => $doc->title,
                        'scans_count'     => $ts->scans_count,
                        'passport_url'    => route('documents.passport', $doc->id),
                    ];
                }
            }

            // 7-Day QR Activity Trend (Attempts vs Verified vs Views)
            $qrTrendDays = [];
            $qrTrendAttempts = [];
            $qrTrendSuccess = [];
            $qrTrendAccess = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dayStr = $date->toDateString();
                $qrTrendDays[] = $date->format('l');
                $qrTrendAttempts[] = ActivityLog::whereDate('created_at', $dayStr)->where('action', 'like', '%QR%')->count();
                $qrTrendSuccess[] = ActivityLog::whereDate('created_at', $dayStr)->where(function($q) {
                    $q->where('action', 'like', '%QR Scanned%')->orWhere('action', 'QR Code Verified');
                })->count();
                $qrTrendAccess[] = ActivityLog::whereDate('created_at', $dayStr)->where('action', 'DOCUMENT VIEWED')->count();
            }

            // 11. Route Analytics (Part 15)
            $topRoutesRaw = DocumentRouting::whereNotNull('from_office_id')
                ->whereNotNull('to_office_id')
                ->select('from_office_id', 'to_office_id', DB::raw('COUNT(*) as route_count'))
                ->groupBy('from_office_id', 'to_office_id')
                ->orderBy('route_count', 'desc')
                ->take(6)
                ->get();
            $topRoutes = [];
            foreach ($topRoutesRaw as $tr) {
                $from = $allOffices->firstWhere('id', $tr->from_office_id)?->name ?? 'Office ' . $tr->from_office_id;
                $to = $allOffices->firstWhere('id', $tr->to_office_id)?->name ?? 'Office ' . $tr->to_office_id;
                $topRoutes[] = [
                    'from'  => $from,
                    'to'    => $to,
                    'count' => $tr->route_count,
                ];
            }

            $reportRouteExceptions = DocumentRouting::with(['document', 'toOffice', 'fromOffice'])
                ->whereIn('status', ['Returned', 'Reverted'])
                ->latest()
                ->take(8)
                ->get();

            // 12. Document Activity Calendar (Part 16 - Historical Reporting Version)
            $dashboardCtrl = app(\App\Http\Controllers\DashboardController::class);
            $userId = auth()->user()?->id ?? session('user_id');
            $calendarData = $dashboardCtrl->getCalendarMonthData($userId, true, (int) now()->year, (int) now()->month);
            $todayKey = now()->toDateString();
            $selectedDateStats = $calendarData['activity'][$todayKey] ?? [
                'uploaded'  => 0,
                'routed'    => 0,
                'approved'  => 0,
                'completed' => 0,
                'pending'   => 0,
            ];

            // 13. Document Category Analysis (Part 17)
            $categoryStats = [];
            foreach ($categories as $cat) {
                $catDocs = $filteredCollection->filter(fn($d) => $d->category === $cat);
                $catVol = $catDocs->count();
                $catComp = $catDocs->filter(fn($d) => $d->status === 'Completed')->count();
                $catInProc = $catDocs->filter(fn($d) => in_array($d->status, ['Pending', 'In Transit', 'Under Review', 'On Process', 'Forwarded', 'Received', 'Processing']))->count();
                $catOverdue = $catDocs->filter(fn($d) => $d->due_date && $d->due_date->lt(now()) && !in_array($d->status, ['Completed', 'Archived', 'Cancelled']))->count();

                $catValid = $catDocs->filter(function($d) {
                    $end = $d->completed_at ?? $d->received_at;
                    return $d->status === 'Completed' && $d->created_at && $end && $end->gt($d->created_at);
                });

                if ($catValid->isNotEmpty()) {
                    $catHrs = [];
                    $catOnTime = 0;
                    $catSlaTotal = 0;
                    foreach ($catValid as $cv) {
                        $end = $cv->completed_at ?? $cv->received_at;
                        $catHrs[] = round($cv->created_at->diffInMinutes($end, false) / 60, 1);
                        if ($cv->due_date) {
                            $catSlaTotal++;
                            if ($end->lte($cv->due_date)) $catOnTime++;
                        }
                    }
                    $catAvgTime = round(array_sum($catHrs) / count($catHrs), 1) . 'h';
                    $catSlaRate = $catSlaTotal > 0 ? round(($catOnTime / $catSlaTotal) * 100, 1) . '%' : 'N/A';
                } else {
                    $catAvgTime = 'N/A';
                    $catSlaRate = 'N/A';
                }

                $categoryStats[] = [
                    'name'           => $cat,
                    'volume'         => $catVol,
                    'completed'      => $catComp,
                    'in_process'     => $catInProc,
                    'overdue'        => $catOverdue,
                    'avg_time'       => $catAvgTime,
                    'sla_compliance' => $catSlaRate,
                ];
            }

            // 14. Report Table (Part 18 - Paginated with All Details)
            $reportDocuments = (clone $query)->latest()->paginate(15)->withQueryString();

            // Enrich each document for display in the table
            $reportDocuments->getCollection()->transform(function($doc) {
                $end = $doc->completed_at ?? $doc->received_at;
                if ($doc->status === 'Completed' && $doc->created_at && $end && $end->gt($doc->created_at)) {
                    $diff = $doc->created_at->diffInMinutes($end, false);
                    $doc->processing_time_hours = round($diff / 60, 1) . 'h';
                } else {
                    $doc->processing_time_hours = 'N/A';
                }

                // SLA Result determination
                if ($doc->status === 'Completed') {
                    if ($doc->due_date && $end) {
                        $doc->sla_result = $end->lte($doc->due_date) ? 'Within SLA' : 'Delayed (Beyond SLA)';
                        $doc->sla_result_badge = $end->lte($doc->due_date) ? 'bg-success' : 'bg-warning text-dark';
                    } else {
                        $doc->sla_result = 'Completed';
                        $doc->sla_result_badge = 'bg-success';
                    }
                } elseif ($doc->due_date && $doc->due_date->lt(now())) {
                    $doc->sla_result = 'Overdue';
                    $doc->sla_result_badge = 'bg-danger';
                } elseif ($doc->due_date && $doc->due_date->lte(now()->addHours(48))) {
                    $doc->sla_result = 'Nearing SLA';
                    $doc->sla_result_badge = 'bg-warning text-dark';
                } else {
                    $doc->sla_result = 'Pending';
                    $doc->sla_result_badge = 'bg-secondary';
                }

                return $doc;
            });

            return view('reports', compact(
                'categories',
                'allOffices',
                'departments',
                'users',
                'summary',
                'flowLabels',
                'flowData',
                'dailyLabels',
                'dailyData',
                'monthlyLabels',
                'monthlyData',
                'volumeBreakdown',
                'officeStats',
                'procTimeOfficeNames',
                'procTimeOfficeHours',
                'procTimeCategoryNames',
                'procTimeCategoryHours',
                'slaTrendLabels',
                'slaTrendRates',
                'reportAging',
                'agingOfficeBreakdown',
                'reportLifecycle',
                'totalQrGenerated',
                'qrSuccessfulScans',
                'uniqueUsersScanning',
                'qrDocumentsAccessed',
                'qrFailedScans',
                'qrDays',
                'qrScanCounts',
                'qrOfficeNames',
                'qrOfficeCounts',
                'topScannedDocuments',
                'qrTrendDays',
                'qrTrendAttempts',
                'qrTrendSuccess',
                'qrTrendAccess',
                'topRoutes',
                'reportRouteExceptions',
                'calendarData',
                'todayKey',
                'selectedDateStats',
                'categoryStats',
                'reportDocuments'
            ));

        } catch (\Exception $e) {
            Log::error('Report Index Error: ' . $e->getMessage() . ' on line ' . $e->getLine());
            return back()->with('error', 'Failed to load report data: ' . $e->getMessage());
        }
    }

    /**
     * Export reports in PDF, Excel, or CSV format.
     */
    public function export(Request $request)
    {
        $this->authorizeAdmin();

        try {
            $format = $request->input('format', 'csv');

            // Build query respecting all filters
            $query = Document::with(['originOffice', 'currentOffice', 'destinationOffice', 'uploader', 'receiverUser', 'routings']);

            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
            }
            if ($request->filled('office_id')) {
                $offId = (int) $request->office_id;
                $query->where(function($q) use ($offId) {
                    $q->where('current_office_id', $offId)
                      ->orWhere('origin_office_id', $offId)
                      ->orWhere('destination_office_id', $offId);
                });
            }
            if ($request->filled('status')) {
                $st = $request->status;
                if ($st === 'In Process') {
                    $query->whereIn('status', ['Pending', 'In Transit', 'Under Review', 'On Process', 'Forwarded', 'Received', 'Processing']);
                } elseif ($st === 'For Approval') {
                    $query->whereIn('status', ['For Approval', 'Pending Approval']);
                } elseif ($st === 'Overdue') {
                    $query->whereNotNull('due_date')->where('due_date', '<', now())->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
                } else {
                    $query->where('status', $st);
                }
            }
            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }
            if ($request->filled('qr_status')) {
                $query->where('qr_status', $request->qr_status);
            }
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
            if ($request->filled('user_id')) {
                $query->where('uploaded_by', $request->user_id);
            }
            if ($request->filled('sla_category')) {
                $slaCat = $request->sla_category;
                if ($slaCat === 'within_sla') {
                    $query->where('status', 'Completed')
                          ->whereNotNull('due_date')
                          ->where(function($q) {
                              $q->where(function($sq) {
                                  $sq->whereNotNull('completed_at')->whereRaw('completed_at <= due_date');
                              })->orWhere(function($sq) {
                                  $sq->whereNull('completed_at')->whereNotNull('received_at')->whereRaw('received_at <= due_date');
                              });
                          });
                } elseif ($slaCat === 'beyond_sla') {
                    $query->where('status', 'Completed')
                          ->whereNotNull('due_date')
                          ->where(function($q) {
                              $q->where(function($sq) {
                                  $sq->whereNotNull('completed_at')->whereRaw('completed_at > due_date');
                              })->orWhere(function($sq) {
                                  $sq->whereNull('completed_at')->whereNotNull('received_at')->whereRaw('received_at > due_date');
                              });
                          });
                } elseif ($slaCat === 'overdue') {
                    $query->whereNotNull('due_date')->where('due_date', '<', now())->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
                } elseif ($slaCat === 'nearing_sla') {
                    $query->whereNotNull('due_date')->where('due_date', '>=', now())->where('due_date', '<=', now()->addHours(48))->whereNotIn('status', ['Completed', 'Archived', 'Cancelled']);
                } else {
                    $query->where('sla', $slaCat);
                }
            }

            $documents = $query->latest()->get();

            // Enrich documents for exports
            foreach ($documents as $doc) {
                $end = $doc->completed_at ?? $doc->received_at;
                if ($doc->status === 'Completed' && $doc->created_at && $end && $end->gt($doc->created_at)) {
                    $diff = $doc->created_at->diffInMinutes($end, false);
                    $doc->processing_time_hours = round($diff / 60, 1) . ' hrs';
                } else {
                    $doc->processing_time_hours = 'N/A';
                }

                if ($doc->status === 'Completed') {
                    if ($doc->due_date && $end) {
                        $doc->sla_result = $end->lte($doc->due_date) ? 'Within SLA' : 'Delayed';
                    } else {
                        $doc->sla_result = 'Completed';
                    }
                } elseif ($doc->due_date && $doc->due_date->lt(now())) {
                    $doc->sla_result = 'Overdue';
                } elseif ($doc->due_date && $doc->due_date->lte(now()->addHours(48))) {
                    $doc->sla_result = 'Nearing SLA';
                } else {
                    $doc->sla_result = 'Pending';
                }
            }

            // PDF / Print format
            if ($format === 'pdf') {
                return view('reports.print', compact('documents'));
            }

            $fileName = 'system_report_' . date('Y-m-d_H-i-s');

            // Excel format (.xls table)
            if ($format === 'excel') {
                $fileName .= '.xls';
                $headers = [
                    "Content-type"        => "application/vnd.ms-excel",
                    "Content-Disposition" => "attachment; filename=$fileName",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0",
                ];

                return response()->stream(function () use ($documents) {
                    $file = fopen('php://output', 'w');
                    echo "<table border='1'>";
                    echo "<tr style='background-color:#1e3a8a; color:#ffffff; font-weight:bold;'>";
                    echo "<th>Tracking No</th><th>Title</th><th>Category</th><th>Uploader</th><th>Origin</th><th>Current Location</th><th>Destination</th><th>Status</th><th>QR Status</th><th>Date Created</th><th>Date Received</th><th>Date Completed</th><th>Processing Time</th><th>SLA Category</th><th>SLA Result</th>";
                    echo "</tr>";
                    foreach ($documents as $doc) {
                        echo "<tr>";
                        echo "<td>" . ($doc->tracking_number ?? $doc->qr_id) . "</td>";
                        echo "<td>" . htmlspecialchars($doc->title) . "</td>";
                        echo "<td>" . htmlspecialchars($doc->category ?? 'General') . "</td>";
                        echo "<td>" . htmlspecialchars($doc->uploader?->name ?? 'System') . "</td>";
                        echo "<td>" . htmlspecialchars($doc->originOffice?->name ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($doc->currentOffice?->name ?? 'In Transit') . "</td>";
                        echo "<td>" . htmlspecialchars($doc->destinationOffice?->name ?? 'N/A') . "</td>";
                        echo "<td>" . $doc->status . "</td>";
                        echo "<td>" . ($doc->qr_status ?? 'Not Scanned') . "</td>";
                        echo "<td>" . ($doc->created_at ? $doc->created_at->format('Y-m-d H:i') : '') . "</td>";
                        echo "<td>" . ($doc->received_at ? $doc->received_at->format('Y-m-d H:i') : 'N/A') . "</td>";
                        echo "<td>" . ($doc->completed_at ? $doc->completed_at->format('Y-m-d H:i') : 'N/A') . "</td>";
                        echo "<td>" . $doc->processing_time_hours . "</td>";
                        echo "<td>" . htmlspecialchars($doc->sla ?? 'Standard') . "</td>";
                        echo "<td>" . $doc->sla_result . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    fclose($file);
                }, 200, $headers);
            }

            // CSV format
            $fileName .= '.csv';
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$fileName",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0",
            ];

            return response()->stream(function () use ($documents) {
                $file = fopen('php://output', 'w');
                fputcsv($file, [
                    'Tracking Number',
                    'Title',
                    'Category',
                    'Uploader',
                    'Origin Office',
                    'Current Location',
                    'Destination Office',
                    'Status',
                    'QR Status',
                    'Date Created',
                    'Date Received',
                    'Date Completed',
                    'Processing Time',
                    'SLA Category',
                    'SLA Result'
                ]);

                foreach ($documents as $doc) {
                    fputcsv($file, [
                        $doc->tracking_number ?? $doc->qr_id,
                        $doc->title,
                        $doc->category ?? 'General',
                        $doc->uploader?->name ?? 'System',
                        $doc->originOffice?->name ?? 'N/A',
                        $doc->currentOffice?->name ?? 'In Transit',
                        $doc->destinationOffice?->name ?? 'N/A',
                        $doc->status,
                        $doc->qr_status ?? 'Not Scanned',
                        $doc->created_at ? $doc->created_at->format('Y-m-d H:i') : '',
                        $doc->received_at ? $doc->received_at->format('Y-m-d H:i') : 'N/A',
                        $doc->completed_at ? $doc->completed_at->format('Y-m-d H:i') : 'N/A',
                        $doc->processing_time_hours,
                        $doc->sla ?? 'Standard',
                        $doc->sla_result
                    ]);
                }
                fclose($file);
            }, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Report Export Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to export report: ' . $e->getMessage());
        }
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
}