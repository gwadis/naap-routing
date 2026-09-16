<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to NAAP</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1f2937;
            padding: 40px 20px;
            margin: 0;
        }
        .container {
            max-width: 600px;
            background: #ffffff;
            margin: 0 auto;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #1e3a8a;
            padding: 30px;
            text-align: center;
            color: #ffffff;
        }
        .header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.025em;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #93c5fd;
            font-size: 14px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .content {
            padding: 40px 30px;
        }
        .welcome-msg {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
        }
        .details-table td.label {
            font-weight: 600;
            color: #64748b;
            width: 150px;
        }
        .details-table td.value {
            color: #1f2937;
        }
        .action-container {
            text-align: center;
            margin-top: 30px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background-color: #3b82f6;
            color: #ffffff !important;
            padding: 12px 30px;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            font-size: 15px;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
            transition: background 0.2s;
        }
        .btn:hover {
            background-color: #1d4ed8;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>NAAP</h2>
        <p>Document Routing System</p>
    </div>
    
    <div class="content">
        <p class="welcome-msg">Hello <strong>{{ $user->name }}</strong>,</p>
        <p class="welcome-msg">An administrator has created a new account for you in the NAAP Document Routing System. Below are your account and login credentials:</p>
        
        <table class="details-table">
            <tr>
                <td class="label">Employee ID</td>
                <td class="value"><em>{{ $user->employee_id ?? 'N/A' }}</em></td>
            </tr>
            <tr>
                <td class="label">Department</td>
                <td class="value">{{ $user->department->name ?? 'Unassigned' }}</td>
            </tr>
            <tr>
                <td class="label">Assigned Role</td>
                <td class="value">{{ $user->role }}</td>
            </tr>
            <tr>
                <td class="label">Username</td>
                <td class="value"><strong>{{ $user->username ?? $user->email }}</strong></td>
            </tr>
            <tr>
                <td class="label">Password</td>
                <td class="value"><code>{{ $password }}</code></td>
            </tr>
        </table>
        
        <div class="action-container">
            <a href="{{ $loginUrl }}" class="btn" target="_blank">Login to System</a>
        </div>
        
        <p class="welcome-msg" style="font-size: 13px; color: #ef4444; margin-top: 25px;"><strong>Note:</strong> This is a temporary password. We highly recommend changing your password after your first successful login.</p>
    </div>
    
    <div class="footer">
        This is an automated system email. Please do not reply to this email.<br>
        &copy; {{ date('Y') }} NAAP. All rights reserved.
    </div>
</div>

</body>
</html>
