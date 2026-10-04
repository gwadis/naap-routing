<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\ActivityLog;
use App\Models\DocumentRouting;
use App\Models\DocumentPin;
use App\Models\DocumentView;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminAnalyticsUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $staffUser;
    private Office $originOffice;
    private Office $destinationOffice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originOffice = Office::create([
            'name' => 'College of Engineering',
            'department' => 'COE',
        ]);

        $this->destinationOffice = Office::create([
            'name' => 'Registrar Office',
            'department' => 'RO',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin Officer',
            'username' => 'admin_analytics',
            'email' => 'admin_analytics@antigravity.test',
            'password' => bcrypt('Password123!'),
            'role' => 'Administrator',
            'office_id' => $this->originOffice->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);

        $this->staffUser = User::create([
            'name' => 'Staff Member',
            'username' => 'staff_user',
            'email' => 'staff_user@antigravity.test',
            'password' => bcrypt('Password123!'),
            'role' => 'Staff',
            'office_id' => $this->destinationOffice->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);
    }

    private function actingAsAdmin()
    {
        return $this->withSession([
            'authenticated' => true,
            'user_id' => $this->adminUser->id,
            'user_role' => 'Administrator',
            'user_name' => $this->adminUser->name,
            'otp_verified' => true,
            'password_changed' => true,
        ])->actingAs($this->adminUser);
    }

    private function actingAsStaff()
    {
        return $this->withSession([
            'authenticated' => true,
            'user_id' => $this->staffUser->id,
            'user_role' => 'Staff',
            'user_name' => $this->staffUser->name,
            'otp_verified' => true,
            'password_changed' => true,
        ])->actingAs($this->staffUser);
    }

    /**
     * Test 1: Admin Dashboard displays all Executive KPI cards with real calculations.
     */
    public function test_admin_dashboard_displays_executive_kpi_cards(): void
    {
        // Create documents across various real workflow states
        $docInProcess = Document::create([
            'title' => 'In Process Doc',
            'status' => 'In Transit',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'qr_code' => 'QR-001',
            'due_date' => now()->addDays(5),
        ]);

        $docForApproval = Document::create([
            'title' => 'Approval Doc',
            'status' => 'For Approval',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'qr_code' => 'QR-002',
            'due_date' => now()->addDays(2),
        ]);

        $docOverdue = Document::create([
            'title' => 'Overdue Doc',
            'status' => 'Pending',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'qr_code' => 'QR-003',
            'due_date' => now()->subDay(),
        ]);

        $docCompleted = Document::create([
            'title' => 'Completed Doc',
            'status' => 'Completed',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->destinationOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'qr_code' => 'QR-004',
            'received_at' => now(),
            'due_date' => now()->addDays(1),
        ]);
        $docCompleted->timestamps = false;
        $docCompleted->created_at = now()->subHours(4);
        $docCompleted->save();

        $response = $this->actingAsAdmin()->get(route('dashboard'));
        $response->assertStatus(200);

        // Verify view data
        $response->assertViewHas('totalDocs', 4);
        $response->assertViewHas('inProcessDocs', 2); // In Transit + Pending
        $response->assertViewHas('forApprovalDocs', 1);
        $response->assertViewHas('completedDocs', 1);
        $response->assertViewHas('overdueDocs', 1);
        $response->assertViewHas('activeQrDocs', 3); // 3 active docs with QR
        $response->assertViewHas('totalQrGenerated', 4);
        $response->assertViewHas('avgProcessingHours', 4.0);
        $response->assertViewHas('slaComplianceRate', 100.0); // 1 completed on time

        // Verify HTML contains the card labels
        $response->assertSee('Total Scope Documents', false);
        $response->assertSee('In Process', false);
        $response->assertSee('For Approval', false);
        $response->assertSee('Completed', false);
        $response->assertSee('Overdue Items', false);
        $response->assertSee('Avg Processing SLA', false);
        $response->assertSee('SLA Compliance Rate', false);
        $response->assertSee('Active QR Documents', false);
    }

    /**
     * Test 2: QR Code Analytics tracks generation, scans, success, failure, and unique accounts.
     */
    public function test_qr_code_analytics_accuracy(): void
    {
        $doc = Document::create([
            'title' => 'QR Test Document',
            'status' => 'In Transit',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'qr_code' => 'QR-TEST-888',
        ]);

        // 1 successful scan today
        ActivityLog::create([
            'user' => $this->adminUser->name,
            'action' => 'QR Scanned',
            'document_id' => $doc->id,
            'ip' => 'REDACTED',
            'created_at' => now(),
        ]);

        // 1 successful scan from staff user
        ActivityLog::create([
            'user' => $this->staffUser->name,
            'action' => 'QR Code Verified',
            'document_id' => $doc->id,
            'ip' => 'REDACTED',
            'created_at' => now(),
        ]);

        // 1 failed scan
        ActivityLog::create([
            'user' => 'Guest',
            'action' => 'QR Verification Failed',
            'document_id' => null,
            'ip' => 'REDACTED',
            'created_at' => now(),
        ]);

        $response = $this->actingAsAdmin()->get(route('dashboard'));
        $response->assertStatus(200);

        $response->assertViewHas('qrScansToday', 2);
        $response->assertViewHas('qrSuccessfulScans', 2);
        $response->assertViewHas('qrFailedScans', 1);
        $response->assertViewHas('uniqueUsersScanning', 2);
        $response->assertViewHas('qrDocumentsAccessed', 1);

        $response->assertSee('Successful QR Scans', false);
        $response->assertSee('Failed QR Scans', false);
        $response->assertSee('QR Accessed Documents', false);
    }

    /**
     * Test 3: Document Aging Analytics groups active documents accurately.
     */
    public function test_document_aging_analytics(): void
    {
        // 1 fresh doc (0-1h)
        $docFresh = Document::create([
            'title' => 'Fresh Doc',
            'status' => 'Pending',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
        ]);
        $docFresh->timestamps = false;
        $docFresh->created_at = now()->subMinutes(20);
        $docFresh->save();

        // 1 aged doc (2 days old -> 1-3 days)
        $docOld = Document::create([
            'title' => 'Old Doc',
            'status' => 'Pending',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
        ]);
        $docOld->timestamps = false;
        $docOld->created_at = now()->subDays(2);
        $docOld->save();

        $response = $this->actingAsAdmin()->get(route('dashboard'));
        $response->assertStatus(200);

        $agingBuckets = $response->viewData('agingBuckets');
        $this->assertEquals(1, $agingBuckets['0–1 hour']);
        $this->assertEquals(1, $agingBuckets['1–3 days']);
        $this->assertEquals(0, $agingBuckets['3+ days']);

        $response->assertSee('Active Document Aging Analysis', false);
        $response->assertSee('id="agingChart"', false);
    }

    /**
     * Test 4: Document Lifecycle Breakdown contains all real stages.
     */
    public function test_document_lifecycle_breakdown(): void
    {
        $response = $this->actingAsAdmin()->get(route('dashboard'));
        $response->assertStatus(200);

        $lifecycle = $response->viewData('lifecycleCounts');
        $this->assertArrayHasKey('Uploaded', $lifecycle);
        $this->assertArrayHasKey('Routed', $lifecycle);
        $this->assertArrayHasKey('Received', $lifecycle);
        $this->assertArrayHasKey('In Process', $lifecycle);
        $this->assertArrayHasKey('For Approval', $lifecycle);
        $this->assertArrayHasKey('Approved', $lifecycle);
        $this->assertArrayHasKey('Completed', $lifecycle);
        $this->assertArrayHasKey('Archived', $lifecycle);

        $response->assertSee('Document Lifecycle Breakdown', false);
        $response->assertSee('id="lifecycleChart"', false);
    }

    /**
     * Test 5: Recent Activity Logs are sorted newest first and contain rich details without exposing secrets.
     */
    public function test_recent_activity_logs_sorted_newest_first_and_secure(): void
    {
        $doc = Document::create([
            'title' => 'Audit Target Doc',
            'tracking_number' => 'DOC-AUDIT-999',
            'status' => 'In Transit',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
        ]);

        $firstLog = ActivityLog::create([
            'user' => 'First User',
            'action' => 'Document Created',
            'document_id' => $doc->id,
            'ip' => 'REDACTED',
        ]);
        $firstLog->timestamps = false;
        $firstLog->created_at = now()->subHours(2);
        $firstLog->save();

        $secondLog = ActivityLog::create([
            'user' => 'Second User',
            'action' => 'QR Scanned',
            'document_id' => $doc->id,
            'ip' => 'REDACTED',
        ]);
        $secondLog->timestamps = false;
        $secondLog->created_at = now()->subMinutes(10);
        $secondLog->save();

        $response = $this->actingAsAdmin()->get(route('dashboard'));
        $response->assertStatus(200);

        $recentActivities = $response->viewData('recentActivities');
        $this->assertGreaterThanOrEqual(2, $recentActivities->count());

        // Newest event must be first
        $this->assertEquals($secondLog->id, $recentActivities->first()->id);

        // Verify document tracking number is visible
        $response->assertSee('DOC-AUDIT-999', false);
        $response->assertSee('Second User', false);
        $response->assertSee('QR Scanned', false);

        // Sensitive credentials must NEVER be in view
        $response->assertDontSee('pin_code');
        $response->assertDontSee('password_hash');
    }

    /**
     * Test 6: Consistency between Dashboard totals and Reports totals.
     */
    public function test_dashboard_and_reports_consistency(): void
    {
        // 1 completed doc
        $doc = Document::create([
            'title' => 'Consistency Doc',
            'status' => 'Completed',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'received_at' => now(),
        ]);
        $doc->timestamps = false;
        $doc->created_at = now()->subHours(3);
        $doc->save();

        ActivityLog::create([
            'user' => $this->adminUser->name,
            'action' => 'QR Scanned',
            'document_id' => $doc->id,
            'ip' => 'REDACTED',
            'created_at' => now(),
        ]);

        $dashResponse = $this->actingAsAdmin()->get(route('dashboard'));
        $dashResponse->assertStatus(200);

        $reportsResponse = $this->actingAsAdmin()->get(route('reports.index'));
        $reportsResponse->assertStatus(200);

        $dashTotal = $dashResponse->viewData('totalDocs');
        $dashAvgTime = $dashResponse->viewData('avgProcessingHours');
        $dashQrScans = $dashResponse->viewData('qrScansToday');

        $reportsSummary = $reportsResponse->viewData('summary');

        $this->assertEquals($reportsSummary['total_processed'], $dashTotal);
        $this->assertEquals($reportsSummary['avg_time'], $dashAvgTime);
        $this->assertEquals(3.0, $dashAvgTime);
        $this->assertGreaterThanOrEqual(1, $reportsSummary['qr_scans']);
        $this->assertGreaterThanOrEqual(1, $dashQrScans);
    }

    /**
     * Test 7: Staff user sees standard user dashboard without Admin-only sections.
     */
    public function test_staff_dashboard_remains_unchanged(): void
    {
        $response = $this->actingAsStaff()->get(route('dashboard'));
        $response->assertStatus(200);

        // Staff sees "User Execution Dashboard"
        $response->assertSee('User Execution Dashboard', false);

        // Staff does NOT see admin QR monitoring or security chart
        $response->assertDontSee('QR Code Scan Activity Monitoring', false);
        $response->assertDontSee('id="qrTrendChart"', false);
        $response->assertDontSee('id="securityChart"', false);
        $response->assertDontSee('id="lifecycleChart"', false);
        $response->assertDontSee('id="agingChart"', false);
    }
}
