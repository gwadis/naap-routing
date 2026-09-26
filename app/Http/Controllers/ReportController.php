<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Document, Office, User, Department, ActivityLog};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Display reports and analytics.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        try {

            // 1. Build Query with Filters
            $query = Document::with(['originOffice', 'currentOffice', 'destinationOffice', 'uploader']);

            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
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

            // Get filtered results for statistics
            $documents = $query->get();
            $totalCount = $documents->count();

            // Calculate average processing time
            // Strictly enforce chronological order: created_at < received_at
            // Do NOT use ABS(). Only calculate if valid chronological timestamps exist; otherwise display 'N/A'
            $validCompletedDocs = $documents->filter(function($doc) {
                return $doc->status === 'Completed'
                    && !empty($doc->created_at)
                    && !empty($doc->received_at)
                    && $doc->received_at->gt($doc->created_at);
            });

            if ($validCompletedDocs->isNotEmpty()) {
                $totalMinutes = 0;
                foreach ($validCompletedDocs as $doc) {
                    $diff = $doc->created_at->diffInMinutes($doc->received_at, false);
                    if ($diff > 0) {
                        $totalMinutes += $diff;
                    }
                }
                $avgTime = round(($totalMinutes / $validCompletedDocs->count()) / 60, 1);
            } else {
                $avgTime = 'N/A';
            }

            // QR scan counts strictly from actual successful QR scan logs
            $scanQuery = ActivityLog::where(function($q) {
                $q->where('action', 'like', '%QR Scanned%')
                  ->orWhere('action', 'QR Code Verified');
            });
            if ($request->filled('from_date')) {
                $scanQuery->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $scanQuery->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            }
            $qrScansTotal = (clone $scanQuery)->count();

            // Stat summaries
            $summary = [
                'total_processed' => $totalCount,
                'avg_time' => $avgTime,
                'most_active' => Office::withCount('documents')->orderBy('documents_count', 'desc')->first()?->name ?? 'N/A',
                'qr_scans' => $qrScansTotal
            ];

            // Filter options for dropdowns
            $departments = Department::all();
            $users = User::orderBy('name', 'asc')->get();

            // Charts office load
            $offices = Office::withCount(['documents' => function($q) use ($request) {
                if ($request->filled('status')) $q->where('status', $request->status);
                if ($request->filled('from_date')) $q->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
                if ($request->filled('to_date')) $q->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            }])->orderBy('name', 'asc')->get();
            $officeNames = $offices->pluck('name')->toArray();
            $processingTimes = $offices->pluck('documents_count')->map(fn($c) => (int) $c)->toArray();

            // Daily scan activity history from real QR scan logs
            $baseScanQuery = ActivityLog::where(function($q) {
                $q->where('action', 'like', '%QR Scanned%')
                  ->orWhere('action', 'QR Code Verified');
            });

            if ($request->filled('from_date')) {
                $baseScanQuery->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $baseScanQuery->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            }

            $scansByDate = (clone $baseScanQuery)
                ->select(DB::raw('DATE(created_at) as scan_date'), DB::raw('COUNT(*) as total_scans'))
                ->groupBy('scan_date')
                ->orderBy('scan_date', 'asc')
                ->get()
                ->pluck('total_scans', 'scan_date')
                ->toArray();

            $days = [];
            $scanCounts = [];

            if (!empty($scansByDate)) {
                // If scan records exist, show each day accurately
                $datesToShow = count($scansByDate) > 14 ? array_slice($scansByDate, -14, 14, true) : $scansByDate;
                foreach ($datesToShow as $dateStr => $count) {
                    $days[] = \Carbon\Carbon::parse($dateStr)->format('M d');
                    $scanCounts[] = (int) $count;
                }
            } else {
                // Gracefully show last 7 days with 0 counts
                for ($i = 6; $i >= 0; $i--) {
                    $d = now()->subDays($i);
                    $days[] = $d->format('M d');
                    $scanCounts[] = 0;
                }
            }

            // Weekly document volume flow (Monday to Sunday for current week)
            $startOfWeek = now()->startOfWeek(); // Monday
            if ($request->filled('from_date')) {
                $startOfWeek = \Carbon\Carbon::parse($request->from_date)->startOfWeek();
            }

            $flowLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $flowData = [];

            for ($i = 0; $i < 7; $i++) {
                $dayDate = (clone $startOfWeek)->addDays($i);
                $flowDayQuery = Document::whereDate('created_at', $dayDate->toDateString());
                if ($request->filled('status')) {
                    $flowDayQuery->where('status', $request->status);
                }
                if ($request->filled('user_id')) {
                    $flowDayQuery->where('uploaded_by', $request->user_id);
                }
                $flowData[] = $flowDayQuery->count();
            }

            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $avgHoursRaw = $isSqlite
                ? 'ROUND(AVG(CASE WHEN document_routings.received_at IS NOT NULL AND document_routings.received_at > document_routings.created_at THEN (julianday(document_routings.received_at) - julianday(document_routings.created_at)) * 24 ELSE NULL END), 1) as avg_processing_hours'
                : 'ROUND(AVG(CASE WHEN document_routings.received_at IS NOT NULL AND document_routings.received_at > document_routings.created_at THEN TIMESTAMPDIFF(MINUTE, document_routings.created_at, document_routings.received_at) ELSE NULL END) / 60, 1) as avg_processing_hours';

            $officeSlaStats = DB::table('document_routings')
                ->join('offices', 'document_routings.to_office_id', '=', 'offices.id')
                ->select(
                    'offices.name as office_name',
                    DB::raw('COUNT(*) as total_steps'),
                    DB::raw('SUM(CASE WHEN document_routings.status IN ("Approved", "Completed", "Accepted", "Endorsed") THEN 1 ELSE 0 END) as completed_steps'),
                    DB::raw('SUM(CASE WHEN document_routings.status IN ("Approved", "Completed", "Accepted", "Endorsed") AND document_routings.received_at > document_routings.sla_due_at THEN 1 ELSE 0 END) as delayed_steps'),
                    DB::raw($avgHoursRaw)
                )
                ->groupBy('offices.id', 'offices.name')
                ->get()
                ->map(function($stat) {
                    $stat->compliance_rate = $stat->completed_steps > 0 
                        ? round((($stat->completed_steps - $stat->delayed_steps) / $stat->completed_steps) * 100, 1) 
                        : 100.0;
                    return $stat;
                });

            return view('reports', compact(
                'officeNames',
                'processingTimes',
                'days',
                'scanCounts',
                'flowLabels',
                'flowData',
                'summary',
                'departments',
                'users',
                'officeSlaStats'
            ));

        } catch (\Exception $e) {
            Log::error('Report Index Error: ' . $e->getMessage());
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

            // Build query
            $query = Document::with(['originOffice', 'currentOffice', 'destinationOffice', 'uploader', 'receiverUser']);

            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
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

            $documents = $query->latest()->get();

            // Format check
            if ($format === 'pdf') {
                // Printable HTML View that calls window.print()
                return view('reports.print', compact('documents'));
            }

            $fileName = 'system_report_' . date('Y-m-d_H-i-s');

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
                    // Add HTML block so Excel parses it with styles
                    echo "<table border='1'>";
                    echo "<tr style='background-color:#1e3a8a; color:#ffffff; font-weight:bold;'>";
                    echo "<th>Tracking No</th><th>Title</th><th>Uploader</th><th>Origin</th><th>Current Location</th><th>Status</th><th>QR Status</th><th>Date Uploaded</th>";
                    echo "</tr>";
                    foreach ($documents as $doc) {
                        echo "<tr>";
                        echo "<td>" . ($doc->tracking_number ?? $doc->qr_id) . "</td>";
                        echo "<td>" . htmlspecialchars($doc->title) . "</td>";
                        echo "<td>" . htmlspecialchars($doc->uploader?->name ?? 'System') . "</td>";
                        echo "<td>" . htmlspecialchars($doc->originOffice?->name ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($doc->currentOffice?->name ?? 'N/A') . "</td>";
                        echo "<td>" . $doc->status . "</td>";
                        echo "<td>" . ($doc->qr_status ?? 'Not Scanned') . "</td>";
                        echo "<td>" . $doc->created_at->format('Y-m-d H:i:s') . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    fclose($file);
                }, 200, $headers);
            }

            // Default CSV
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
                fputcsv($file, ['Tracking Number', 'Title', 'Uploader', 'Origin Office', 'Current Location', 'Status', 'QR Status', 'Date Uploaded']);

                foreach ($documents as $doc) {
                    fputcsv($file, [
                        $doc->tracking_number ?? $doc->qr_id,
                        $doc->title,
                        $doc->uploader?->name ?? 'System',
                        $doc->originOffice?->name ?? 'N/A',
                        $doc->currentOffice?->name ?? 'N/A',
                        $doc->status,
                        $doc->qr_status ?? 'Not Scanned',
                        $doc->created_at->format('Y-m-d H:i:s')
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