<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

class UserDashboardWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $staffUser;
    protected $otherStaffUser;
    protected $officeA;
    protected $officeB;
    protected $officeC;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();

        $this->officeA = Office::create([
            'name'       => 'Finance Office',
            'department' => 'Finance',
        ]);
        $this->officeB = Office::create([
            'name'       => 'Accounting Office',
            'department' => 'Accounting',
        ]);
        $this->officeC = Office::create([
            'name'       => 'Legal Division',
            'department' => 'Legal',
        ]);

        $this->admin = User::create([
            'name'                  => 'System Administrator',
            'username'              => 'sysadmin',
            'email'                 => 'admin@naap.gov.ph',
            'password'              => bcrypt('SecurePassword123!'),
            'role'                  => 'Administrator',
            'office_id'             => $this->officeA->id,
            'is_active'             => true,
            'needs_password_change' => false,
        ]);

        $this->staffUser = User::create([
            'name'                  => 'Staff Officer Maria',
            'username'              => 'mariastaff',
            'email'                 => 'maria@naap.gov.ph',
            'password'              => bcrypt('SecurePassword123!'),
            'role'                  => 'Staff',
            'office_id'             => $this->officeB->id,
            'is_active'             => true,
            'needs_password_change' => false,
        ]);

        $this->otherStaffUser = User::create([
            'name'                  => 'Other Staff John',
            'username'              => 'johnstaff',
            'email'                 => 'john@naap.gov.ph',
            'password'              => bcrypt('SecurePassword123!'),
            'role'                  => 'Staff',
            'office_id'             => $this->officeC->id,
            'is_active'             => true,
            'needs_password_change' => false,
        ]);
    }

    public function test_user_dashboard_renders_for_staff_user_with_operational_kpis()
    {
        // Create an assigned document for staffUser
        $doc = Document::create([
            'title'              => 'Quarterly Budget Review',
            'tracking_number'    => 'DOC-FIN-2026-001',
            'file_path'          => 'documents/test1.pdf',
            'file_name'          => 'test1.pdf',
            'file_type'          => 'pdf',
            'file_size'          => 1024,
            'status'             => 'Pending',
            'uploaded_by'        => $this->admin->id,
            'origin_office_id'   => $this->officeA->id,
            'current_office_id'  => $this->officeB->id,
            'receiver_user_id'   => $this->staffUser->id,
            'destination_office_id' => $this->officeB->id,
            'due_date'           => now()->addHours(24),
        ]);

        $response = $this->actingAs($this->staffUser)
            ->withSession(['user_id' => $this->staffUser->id, 'user_role' => 'Staff'])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Personal Workspace Header
        $response->assertSee('My Workspace');
        $response->assertSee('Personal operational workspace overview');

        // Operational Work Titles
        $response->assertSee('My Work');
        $response->assertSee('My Documents');
        $response->assertSee('For My Action');
        $response->assertSee('In Process');
        $response->assertSee('Completed');
        $response->assertSee('Overdue');
        $response->assertSee('Due Soon');

        // Workspace sections
        $response->assertSee('ACTION REQUIRED');
        $response->assertSee('Quarterly Budget Review');
        $response->assertSee('DOC-FIN-2026-001');
        $response->assertSee('My Active Documents');
        $response->assertSee('Where are my documents right now?');
        $response->assertSee('My Deadlines');
        $response->assertSee('Recent Activity');

        // Ensure heavy analytics and BI charts are NOT present on User Dashboard
        $response->assertDontSee('userStatusChart');
        $response->assertDontSee('My Document Activity Calendar');
        $response->assertDontSee('My Processing Time');
    }

    public function test_for_my_action_section_displays_assigned_urgent_documents()
    {
        // 1 Overdue document awaiting receipt
        $overdueDoc = Document::create([
            'title'              => 'Urgent Audit Report',
            'tracking_number'    => 'DOC-AUD-001',
            'file_path'          => 'documents/audit.pdf',
            'file_name'          => 'audit.pdf',
            'file_type'          => 'pdf',
            'file_size'          => 2048,
            'status'             => 'Pending',
            'uploaded_by'        => $this->admin->id,
            'origin_office_id'   => $this->officeA->id,
            'current_office_id'  => $this->officeB->id,
            'receiver_user_id'   => $this->staffUser->id,
            'due_date'           => now()->subHours(5),
        ]);

        // 1 Nearing SLA document
        $nearingDoc = Document::create([
            'title'              => 'Payroll Voucher',
            'tracking_number'    => 'DOC-PAY-002',
            'file_path'          => 'documents/pay.pdf',
            'file_name'          => 'pay.pdf',
            'file_type'          => 'pdf',
            'file_size'          => 1024,
            'status'             => 'Received',
            'received_at'        => now()->subHours(2),
            'uploaded_by'        => $this->admin->id,
            'origin_office_id'   => $this->officeA->id,
            'current_office_id'  => $this->officeB->id,
            'receiver_user_id'   => $this->staffUser->id,
            'due_date'           => now()->addHours(12),
        ]);

        $response = $this->actingAs($this->staffUser)
            ->withSession(['user_id' => $this->staffUser->id, 'user_role' => 'Staff'])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Verify items in Action Required table
        $response->assertSee('Urgent Audit Report');
        $response->assertSee('DOC-AUD-001');
        $response->assertSee('Payroll Voucher');
        $response->assertSee('DOC-PAY-002');
        $response->assertSee('Awaiting Receipt / Acceptance');
        $response->assertSee('Awaiting Processing / Review');
        $response->assertSee('sla-overdue');
        $response->assertSee('sla-nearing');
    }

    public function test_workflow_pipeline_and_status_distribution_are_accurate()
    {
        // Received doc
        Document::create([
            'title'              => 'Doc Received',
            'tracking_number'    => 'DOC-REC-01',
            'file_path'          => 'doc.pdf',
            'file_name'          => 'doc.pdf',
            'file_type'          => 'pdf',
            'file_size'          => 100,
            'status'             => 'Received',
            'uploaded_by'        => $this->staffUser->id,
            'origin_office_id'   => $this->officeB->id,
            'current_office_id'  => $this->officeB->id,
            'receiver_user_id'   => $this->staffUser->id,
        ]);

        // Processing doc
        Document::create([
            'title'              => 'Doc In Process',
            'tracking_number'    => 'DOC-PRC-02',
            'file_path'          => 'doc.pdf',
            'file_name'          => 'doc.pdf',
            'file_type'          => 'pdf',
            'file_size'          => 100,
            'status'             => 'Processing',
            'uploaded_by'        => $this->staffUser->id,
            'origin_office_id'   => $this->officeB->id,
            'current_office_id'  => $this->officeB->id,
            'receiver_user_id'   => $this->staffUser->id,
        ]);

        // Completed doc
        Document::create([
            'title'              => 'Doc Completed',
            'tracking_number'    => 'DOC-CMP-03',
            'file_path'          => 'doc.pdf',
            'file_name'          => 'doc.pdf',
            'file_type'          => 'pdf',
            'file_size'          => 100,
            'status'             => 'Completed',
            'created_at'         => now()->subDays(2),
            'received_at'        => now()->subDays(1),
            'completed_at'       => now()->subHours(2),
            'uploaded_by'        => $this->staffUser->id,
            'origin_office_id'   => $this->officeB->id,
            'current_office_id'  => $this->officeB->id,
            'receiver_user_id'   => $this->staffUser->id,
        ]);

        $response = $this->actingAs($this->staffUser)
            ->withSession(['user_id' => $this->staffUser->id, 'user_role' => 'Staff'])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Operational work metrics and documents are rendered
        $response->assertSee('Doc Received');
        $response->assertSee('Doc In Process');
        $response->assertSee('Completed');
        $this->assertEquals(1, $response->viewData('userCompletedCount'));
    }

    public function test_my_routing_activity_and_location_tracking()
    {
        $doc = Document::create([
            'title'              => 'Routed Legal Memo',
            'tracking_number'    => 'DOC-ROU-999',
            'file_path'          => 'memo.pdf',
            'file_name'          => 'memo.pdf',
            'file_type'          => 'pdf',
            'file_size'          => 100,
            'status'             => 'In Transit',
            'uploaded_by'        => $this->admin->id,
            'origin_office_id'   => $this->officeA->id,
            'current_office_id'  => $this->officeB->id,
            'receiver_user_id'   => $this->staffUser->id,
            'destination_office_id' => $this->officeB->id,
        ]);

        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->officeA->id,
            'to_office_id'     => $this->officeB->id,
            'sender_user_id'   => $this->admin->id,
            'receiver_user_id' => $this->staffUser->id,
            'status'           => 'Forwarded',
            'action_taken'     => 'Transmitted for staff action',
        ]);

        $response = $this->actingAs($this->staffUser)
            ->withSession(['user_id' => $this->staffUser->id, 'user_role' => 'Staff'])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Routed Legal Memo');
        $response->assertSee('DOC-ROU-999');
        $response->assertSee('Accounting Office');
        $response->assertSee('Finance Office');
    }

    public function test_user_privacy_and_rbac_scoping()
    {
        // Confidential document belonging solely to other staff in Legal Division
        Document::create([
            'title'              => 'Confidential Legal Disciplinary Case',
            'tracking_number'    => 'DOC-PRIV-SECRET',
            'file_path'          => 'secret.pdf',
            'file_name'          => 'secret.pdf',
            'file_type'          => 'pdf',
            'file_size'          => 500,
            'status'             => 'Processing',
            'uploaded_by'        => $this->otherStaffUser->id,
            'origin_office_id'   => $this->officeC->id,
            'current_office_id'  => $this->officeC->id,
            'receiver_user_id'   => $this->otherStaffUser->id,
            'destination_office_id' => $this->officeC->id,
        ]);

        $response = $this->actingAs($this->staffUser)
            ->withSession(['user_id' => $this->staffUser->id, 'user_role' => 'Staff'])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Ensure other office's confidential document is NOT exposed to Maria
        $response->assertDontSee('Confidential Legal Disciplinary Case');
        $response->assertDontSee('DOC-PRIV-SECRET');
    }

    public function test_empty_states_show_zero_without_hardcoded_or_fake_data()
    {
        // Maria has no documents
        $response = $this->actingAs($this->staffUser)
            ->withSession(['user_id' => $this->staffUser->id, 'user_role' => 'Staff'])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Check empty states
        $response->assertSee('No documents currently require your action');
        $response->assertSee('No active document locations tracked');
        $response->assertSee('No recent activity logged for your account');
        $response->assertSee('No QR scans recorded');
    }

    public function test_calendar_activity_api_scoped_for_staff_user()
    {
        $response = $this->actingAs($this->staffUser)
            ->withSession(['user_id' => $this->staffUser->id, 'user_role' => 'Staff'])
            ->get(route('api.dashboard.calendarActivity', [
                'year'  => now()->year,
                'month' => now()->month,
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'year',
                'month',
                'month_name',
                'days_in_month',
                'first_day_of_week',
                'activity',
            ],
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_user_dashboard_handles_activity_logs_and_qr_queries_cleanly_without_exceptions()
    {
        // Create activity logs matching user's name
        ActivityLog::create([
            'user'        => $this->staffUser->name,
            'action'      => 'QR Code Verified',
            'document_id' => null,
            'ip'          => '127.0.0.1',
            'browser'     => 'Chrome',
            'os'          => 'Windows',
        ]);

        ActivityLog::create([
            'user'        => $this->staffUser->name,
            'action'      => 'Document Viewed',
            'document_id' => null,
            'ip'          => '127.0.0.1',
            'browser'     => 'Chrome',
            'os'          => 'Windows',
        ]);

        $response = $this->actingAs($this->staffUser)
            ->withSession(['user_id' => $this->staffUser->id, 'user_role' => 'Staff', 'user_name' => $this->staffUser->name])
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Recent Activity');
        $response->assertSee('Document Viewed');
        $response->assertSee('QR Code Verified');
    }
}

