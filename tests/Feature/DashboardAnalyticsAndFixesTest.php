<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\ActivityLog;
use App\Models\DocumentRouting;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardAnalyticsAndFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Office $originOffice;
    private Office $destinationOffice;
    private Document $document;

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
            'name' => 'Admin User',
            'username' => 'admin_test',
            'email' => 'admin_test@antigravity.test',
            'password' => bcrypt('Password123!'),
            'role' => 'Administrator',
            'office_id' => $this->originOffice->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);

        $this->document = Document::create([
            'title' => 'Test Routing Document',
            'type' => 'Memorandum',
            'priority' => 'Normal',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'status' => 'Pending',
            'tracking_number' => 'DOC-999001',
            'qr_code' => 'DOC-999001',
            'qr_id' => 'DOC-999001',
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

    /**
     * Test 1: Dashboard loads calendar widget and does NOT contain removed charts.
     */
    public function test_dashboard_replaces_removed_charts_with_calendar_widget(): void
    {
        $response = $this->actingAsAdmin()->get(route('dashboard'));

        $response->assertStatus(200);

        // Assert removed charts are absent
        $response->assertDontSee('7-Day Upload Volume Flow');
        $response->assertDontSee('Monthly Upload vs Routing Activity');
        $response->assertDontSee('id="flowChart"', false);
        $response->assertDontSee('id="monthlyChart"', false);

        // Assert calendar analytics widget and date breakdown exist
        $response->assertSee('Document Activity Calendar');
        $response->assertSee('Total Uploaded Documents');
        $response->assertSee('Total Routed Documents');
        $response->assertSee('Total Approved Documents');
        $response->assertSee('Total Completed Documents');
        $response->assertSee('Total Pending Documents');
        $response->assertSee('id="calDaysGrid"', false);
        $response->assertSee('id="statUploadedDocs"', false);
    }

    /**
     * Test 2: Calendar activity API returns monthly breakdown.
     */
    public function test_calendar_activity_api_returns_correct_data(): void
    {
        $response = $this->actingAsAdmin()->getJson(route('api.dashboard.calendarActivity', [
            'year' => now()->year,
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

        $todayKey = now()->toDateString();
        $response->assertJsonPath("data.activity.{$todayKey}.uploaded", 1);
        $response->assertJsonPath("data.activity.{$todayKey}.pending", 1);
    }

    /**
     * Test 3: Daily QR scan activity is properly recorded and counted.
     */
    public function test_daily_qr_scan_activity_records_and_counts_accurately(): void
    {
        // 1. Initial scans count today should be 0
        $today = now()->toDateString();
        $initialScans = ActivityLog::whereDate('created_at', $today)
            ->where('action', 'like', '%QR Scanned%')
            ->count();
        $this->assertEquals(0, $initialScans);

        // 2. Perform a successful QR scan
        $response = $this->actingAsAdmin()->postJson(route('qr.scan'), [
            'qr_data' => (string) $this->document->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 3. Scan record should be logged exactly once
        $scansAfter = ActivityLog::whereDate('created_at', $today)
            ->where('action', 'like', '%QR Scanned%')
            ->count();
        $this->assertEquals(1, $scansAfter);

        // 4. Failed scan (invalid QR) must NOT be counted
        $badResponse = $this->actingAsAdmin()->postJson(route('qr.scan'), [
            'qr_data' => 'NON-EXISTENT-QR-DATA-999',
        ]);
        $badResponse->assertStatus(404);

        $scansAfterFailed = ActivityLog::whereDate('created_at', $today)
            ->where('action', 'like', '%QR Scanned%')
            ->count();
        $this->assertEquals(1, $scansAfterFailed); // still exactly 1

        // 5. Dashboard analytics displays the updated scan count
        $dashResponse = $this->actingAsAdmin()->get(route('dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertViewHas('qrScansToday', 1);
    }

    /**
     * Test 4: QR label print view has single page formatting rules.
     */
    public function test_qr_label_print_view_has_single_page_print_styles(): void
    {
        $response = $this->actingAsAdmin()->get(route('documents.qr-label', $this->document->id));

        $response->assertStatus(200);
        $response->assertSee('@page {', false);
        $response->assertSee('page-break-after: avoid', false);
        $response->assertSee('break-after: avoid', false);
        $response->assertSee('overflow: hidden', false);
    }

    /**
     * Test 5: Upload success modal has visible close (X) button.
     */
    public function test_upload_success_modal_has_close_button(): void
    {
        $view = $this->view('components.upload-modal', [
            'offices' => [$this->originOffice, $this->destinationOffice],
            'users' => [$this->adminUser],
        ]);

        $view->assertSee('id="btnCloseUploadSuccess"', false);
        $view->assertSee('btn-close position-absolute top-0 end-0', false);
        $view->assertSee('data-bs-dismiss="modal"', false);
    }

    /**
     * Test 6: Security & Access Counters is converted to a chart and excludes OTP verifications.
     */
    public function test_security_and_access_is_chart_and_omits_otp_verifications(): void
    {
        $response = $this->actingAsAdmin()->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="securityChart"', false);
        $response->assertSee('System Security & Access', false);
        $response->assertSee('Document File Views', false);
        $response->assertSee('Approval Events', false);
        $response->assertSee('Routing & Transit Movements', false);
        // Old text counter in security counters section is gone
        $response->assertDontSee('System Security & Access Counters', false);
        $response->assertDontSee('Active OTP/PIN Verifications:', false);
    }

    /**
     * Test 7: Dashboard layout places analytics and charts above Recent Activity Logs.
     */
    public function test_dashboard_order_places_charts_above_recent_activity_logs(): void
    {
        $response = $this->actingAsAdmin()->get(route('dashboard'));

        $response->assertStatus(200);
        $content = $response->getContent();

        $qrTrendPos = strpos($content, 'id="qrTrendChart"');
        $securityChartPos = strpos($content, 'id="securityChart"');
        $calGridPos = strpos($content, 'id="calDaysGrid"');
        $officeChartPos = strpos($content, 'id="officeChart"');
        $recentActivityPos = strpos($content, 'Recent Activity Logs');

        $this->assertNotFalse($qrTrendPos);
        $this->assertNotFalse($securityChartPos);
        $this->assertNotFalse($calGridPos);
        $this->assertNotFalse($officeChartPos);
        $this->assertNotFalse($recentActivityPos);

        // All charts must appear BEFORE Recent Activity Logs
        $this->assertLessThan($recentActivityPos, $qrTrendPos, 'qrTrendChart should be above Recent Activity Logs');
        $this->assertLessThan($recentActivityPos, $securityChartPos, 'securityChart should be above Recent Activity Logs');
        $this->assertLessThan($recentActivityPos, $calGridPos, 'calDaysGrid should be above Recent Activity Logs');
        $this->assertLessThan($recentActivityPos, $officeChartPos, 'officeChart should be above Recent Activity Logs');
    }

    /**
     * Test 8: QR scanner view includes image upload container, supported image formats, and jsQR decoder.
     */
    public function test_qr_scanner_has_image_upload_with_supported_formats(): void
    {
        $response = $this->actingAsAdmin()->get(route('qr.index'));

        $response->assertStatus(200);
        $response->assertSee('id="upload-mode"', false);
        $response->assertSee('id="upload-scanner"', false);
        $response->assertSee('id="qr-file-input"', false);
        $response->assertSee('image/png, image/jpeg, image/jpg, image/webp', false);
        $response->assertSee('jsqr', false);
    }

    /**
     * Test 9: Active Office Workloads and Activity Feed are side-by-side and scrollable.
     */
    public function test_dashboard_workloads_and_activity_feed_side_by_side_and_scrollable(): void
    {
        $response = $this->actingAsAdmin()->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('charts-main-grid mb-4', false);
        $response->assertSee('Active Office Workloads', false);
        $response->assertSee('Recent Activity Logs', false);
        $response->assertSee('overflow-y: auto;', false);
    }

    /**
     * Test 10: Reports page summary cards have identical sizing, height, and responsive grid.
     */
    public function test_reports_page_summary_cards_identical_sizing(): void
    {
        $response = $this->actingAsAdmin()->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Total Documents', false);
        $response->assertSee('Avg Processing Time', false);
        $response->assertSee('Most Active Office', false);
        $response->assertSee('System QR Scans', false);

        // Verify all 4 cards share identical grid and flex classes
        $content = $response->getContent();
        $this->assertEquals(4, substr_count($content, 'col-md-3 col-sm-6'));
        $this->assertEquals(4, substr_count($content, 'glass-card text-center h-100 d-flex flex-column justify-content-center'));
        $this->assertEquals(4, substr_count($content, 'min-height: 110px;'));
    }

    /**
     * Test 11: Average processing time displays N/A for invalid/chronologically inverted timestamps and computes accurately for valid timestamps.
     */
    public function test_average_processing_time_handles_chronological_validation(): void
    {
        // 1. Create a document with invalid chronological timestamps (received_at earlier than created_at)
        $invalidDoc = \App\Models\Document::create([
            'title' => 'Inverted Timestamp Doc',
            'status' => 'Completed',
            'received_at' => now(), // received_at < created_at
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
        ]);
        $invalidDoc->timestamps = false;
        $invalidDoc->created_at = now()->addHours(5);
        $invalidDoc->save();

        $dashResponse = $this->actingAsAdmin()->get(route('dashboard'));
        $dashResponse->assertStatus(200);
        // Dashboard should display N/A for avgProcessingHours because timestamps are invalid
        $this->assertEquals('N/A', $dashResponse->viewData('avgProcessingHours'));

        $reportsResponse = $this->actingAsAdmin()->get(route('reports.index'));
        $reportsResponse->assertStatus(200);
        $summary = $reportsResponse->viewData('summary');
        $this->assertEquals('N/A', $summary['avg_time']);

        // 2. Now add a document with valid chronological timestamps: created_at < received_at (2 hours diff)
        $validDoc = \App\Models\Document::create([
            'title' => 'Valid Chronological Doc',
            'status' => 'Completed',
            'received_at' => now(),
            'origin_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
        ]);
        $validDoc->timestamps = false;
        $validDoc->created_at = now()->subHours(2);
        $validDoc->save();

        $dashResponse2 = $this->actingAsAdmin()->get(route('dashboard'));
        $dashResponse2->assertStatus(200);
        $this->assertEquals(2.0, $dashResponse2->viewData('avgProcessingHours'));

        $reportsResponse2 = $this->actingAsAdmin()->get(route('reports.index'));
        $reportsResponse2->assertStatus(200);
        $summary2 = $reportsResponse2->viewData('summary');
        $this->assertEquals(2.0, $summary2['avg_time']);
    }

    /**
     * Test 12: Daily Scan Activity in Reports correctly queries real scan logs.
     */
    public function test_reports_daily_scan_activity_pipeline(): void
    {
        // Add a scan activity log
        \App\Models\ActivityLog::create([
            'user' => $this->adminUser->name,
            'action' => 'QR Scanned',
            'document_id' => $this->document->id,
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->actingAsAdmin()->get(route('reports.index'));
        $response->assertStatus(200);

        $summary = $response->viewData('summary');
        $this->assertGreaterThanOrEqual(1, $summary['qr_scans']);

        $scanCounts = $response->viewData('scanCounts');
        $this->assertIsArray($scanCounts);
        $this->assertGreaterThanOrEqual(1, array_sum($scanCounts));
    }

    /**
     * Test 13: Reports Documents Per Office chart contains responsive options, offset, and correct data.
     */
    public function test_reports_documents_per_office_chart_renders_with_responsive_container_and_offset(): void
    {
        // Create an office with a long name and several documents to test representation
        $longOffice = Office::create([
            'name' => 'Department of Computer Studies and Systems Development',
            'department' => 'CSSD',
        ]);

        Document::create([
            'title' => 'Long Office Document 1',
            'type' => 'Letter',
            'priority' => 'High',
            'origin_office_id' => $longOffice->id,
            'current_office_id' => $longOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'status' => 'Pending',
        ]);

        $response = $this->actingAsAdmin()->get(route('reports.index'));
        $response->assertStatus(200);

        // Assert chart options prevent cut off
        $response->assertSee('offset: true', false);
        $response->assertSee('maxBarThickness: 45', false);
        $response->assertSee('right: 25', false);
        $response->assertSee('reportBar', false);

        $officeNames = $response->viewData('officeNames');
        $processingTimes = $response->viewData('processingTimes');
        $this->assertIsArray($officeNames);
        $this->assertContains('Department of Computer Studies and Systems Development', $officeNames);
        $this->assertIsArray($processingTimes);
        $this->assertNotEmpty($processingTimes);
    }

    /**
     * Test 14: QR Label page auto-print template has single-page CSS and break-inside avoidance.
     */
    public function test_qr_label_autoprint_has_single_page_print_rules(): void
    {
        $response = $this->actingAsAdmin()->get(route('documents.qr-label', $this->document->id));
        $response->assertStatus(200);

        // Ensure single-sheet print rules
        $response->assertSee('break-inside: avoid !important', false);
        $response->assertSee('page-break-inside: avoid !important', false);
        $response->assertSee('margin: 0mm;', false);
        $response->assertSee('window.print()', false);
    }

    /**
     * Test 15: Bulk action returns 422 warning if no documents are selected.
     */
    public function test_bulk_action_validates_empty_selection(): void
    {
        $response = $this->actingAsAdmin()->postJson(route('documents.bulk-action'), [
            'action' => 'archive',
            'document_ids' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'No documents selected.',
        ]);
    }

    /**
     * Test 16: Bulk action marks documents as completed.
     */
    public function test_bulk_action_marks_documents_completed(): void
    {
        $doc2 = Document::create([
            'title' => 'Bulk Complete Target 2',
            'type' => 'Report',
            'priority' => 'Normal',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'status' => 'Pending',
        ]);

        $response = $this->actingAsAdmin()->postJson(route('documents.bulk-action'), [
            'action' => 'completed',
            'document_ids' => [$this->document->id, $doc2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count' => 2,
        ]);

        $this->assertEquals('Completed', $this->document->fresh()->status);
        $this->assertEquals('Completed', $doc2->fresh()->status);
    }

    /**
     * Test 17: Bulk action archives documents.
     */
    public function test_bulk_action_archives_documents(): void
    {
        $response = $this->actingAsAdmin()->postJson(route('documents.bulk-action'), [
            'action' => 'archive',
            'document_ids' => [$this->document->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count' => 1,
        ]);

        $this->assertEquals('Archived', $this->document->fresh()->status);
        $this->assertNotNull($this->document->fresh()->archived_at);
    }

    /**
     * Test 18: Bulk action deletes documents and exports CSV.
     */
    public function test_bulk_action_delete_and_export(): void
    {
        // Test CSV Export
        $exportResponse = $this->actingAsAdmin()->get(route('documents.bulk-action', [
            'action' => 'export',
            'ids' => (string) $this->document->id,
        ]));

        $exportResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $exportResponse->headers->get('Content-Type'));

        // Test Bulk Delete
        $deleteResponse = $this->actingAsAdmin()->postJson(route('documents.bulk-action'), [
            'action' => 'delete',
            'document_ids' => [$this->document->id],
        ]);

        $deleteResponse->assertStatus(200);
        $deleteResponse->assertJson([
            'success' => true,
            'count' => 1,
        ]);

        $this->assertNull(Document::find($this->document->id));
    }
}
