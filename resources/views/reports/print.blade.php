<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>NAAP Document Routing System - System Report</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 20px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #1e3a8a; padding-bottom: 15px; }
        .header h1 { margin: 0; color: #1e3a8a; font-size: 26px; font-weight: 800; }
        .header p { margin: 5px 0 0 0; color: #4b5563; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .meta { margin-bottom: 25px; font-size: 13px; color: #4b5563; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e5e7eb; padding: 10px 14px; text-align: left; font-size: 12px; }
        th { background-color: #f9fafb; font-weight: 700; color: #374151; text-transform: uppercase; }
        .badge { padding: 4px 10px; border-radius: 9999px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .priority-Urgent { background-color: #ffe4e6; color: #b91c1c; border: 1px solid #fecdd3; }
        .priority-High { background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
        .priority-Normal { background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .priority-Low { background-color: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
        .btn-print { display: block; width: 140px; margin: 0 auto 25px auto; padding: 12px; text-align: center; background-color: #1e3a8a; color: white !important; text-decoration: none; font-weight: 700; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 6px rgba(30, 58, 138, 0.15); transition: 0.2s; }
        @media print {
            .btn-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<a href="#" class="btn-print" onclick="window.print(); return false;">Print to PDF</a>

<div class="header">
    <h1>NAAP DOCUMENT ROUTING SYSTEM</h1>
    <p>System Documents Routing & Execution Report</p>
</div>

<div class="meta">
    Report Generated: <strong>{{ now()->format('F d, Y h:i A') }}</strong><br>
    Total Filtered Documents: <strong>{{ $documents->count() }}</strong>
</div>

<table>
    <thead>
        <tr>
            <th>Tracking No</th>
            <th>Document Title</th>
            <th>Uploader</th>
            <th>Origin Office</th>
            <th>Current Location</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Date Created</th>
        </tr>
    </thead>
    <tbody>
        @forelse($documents as $doc)
        <tr>
            <td><strong>{{ $doc->tracking_number ?? $doc->qr_id }}</strong></td>
            <td>{{ $doc->title }}</td>
            <td>{{ $doc->uploader?->name ?? 'System' }}</td>
            <td>{{ $doc->originOffice?->name ?? 'N/A' }}</td>
            <td>{{ $doc->currentOffice?->name ?? 'In Transit' }}</td>
            <td><strong>{{ $doc->status }}</strong></td>
            <td>
                <span class="badge priority-{{ $doc->priority }}">
                    {{ $doc->priority }}
                </span>
            </td>
            <td>{{ $doc->created_at->format('Y-m-d H:i') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8" style="text-align: center; color: #6b7280; padding: 30px;">No documents found matching the filter criteria.</td>
        </tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
