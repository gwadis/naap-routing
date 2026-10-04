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
     * Test 1: Dashboard has Document Activity Calendar, Action Required, and Reports has detailed metrics.
     */
    public function test_dashboard_streamlined_and_reports_has_calendar_and_detailed_qr_metrics(): void
    {
        $dashResponse = $this->actingAsAdmin()->get(route('dashboard'));
        $dashResponse->assertStatus(200);

        // Assert Document Activity Calendar is present on main dashboard
        $dashResponse->assertSee('Document Activity Calendar');
        $dashResponse->assertSee('id="calDaysGrid"', false);
        $dashResponse->assertSee('Action Required');
        $dashResponse->assertDontSee('System Security & Access Counters', false);
        $dashResponse->assertDontSee('id="securityChart"', false);

        // Assert primary enterprise sections exist on dashboard
        $dashResponse->assertSee('System Analytics Dashboard');
        $dashResponse->assertSee('Total Documents');
        $dashResponse->assertSee('In Process');
        $dashResponse->assertSee('For Approval');
        $dashResponse->assertSee('Completed');
        $dashResponse->assertSee('Overdue Items');
        $dashResponse->assertSee('Active QR Documents');
        $dashResponse->assertSee('QR Code Scan Activity');
        $dashResponse->assertSee('Document Operations');
        $dashResponse->assertSee('Document Aging');
        $dashResponse->assertSee('Recent Activity');

        // Assert Reports has calendar widget and detailed QR metrics
        $reportsResponse = $this->actingAsAdmin()->get(route('reports.index'));
        $reportsResponse->assertStatus(200);
        $reportsResponse->assertSee('Document Activity Calendar');
        $reportsResponse->assertSee('Total Uploaded Documents');
        $reportsResponse->assertSee('Total Routed Documents');
        $reportsResponse->assertSee('id="calDaysGrid"', false);
        $reportsResponse->assertSee('Total QR Generated');
        $reportsResponse->assertSee('Unique Users Scanning');
        $reportsResponse->assertSee('QR Accessed Documents');
    }

    /**
     * Test 1b: Calendar date events API returns real events for a selected date.
     */
    public function test_calendar_date_events_api_returns_documents_and_grouped_events(): void
    {
        $todayStr = now()->toDateString();

        $response = $this->actingAsAdmin()->getJson(route('api.dashboard.calendarDateEvents', [
            'date' => $todayStr,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'date',
                'formatted_date',
                'full_date_label',
                'total_events',
                'stats' => [
                    'uploaded',
                    'routed',
                    'received',
                    'approved',
                    'completed',
                ],
                'documents',
            ],
        ]);

        $docs = $response->json('data.documents');
        $this->assertNotEmpty($docs);
        $this->assertEquals($this->document->id, $docs[0]['id']);
        $this->assertNotEmpty($docs[0]['events']);
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
     * Test 6: Security and access analytics belong in Security Console, not Dashboard.
     */
    public function test_security_and_access_belongs_in_security_console(): void
    {
        // 1. Dashboard does not have security counters or security charts
        $dashResponse = $this->actingAsAdmin()->get(route('dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertDontSee('System Security & Access Counters', false);
        $dashResponse->assertDontSee('System Security & Access', false);
        $dashResponse->assertDontSee('id="securityChart"', false);

        // 2. Security Dashboard is active and authorized for admin
        $secResponse = $this->actingAsAdmin()->get(route('security.dashboard'));
        $secResponse->assertStatus(200);
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
        $lifecyclePos = strpos($content, 'id="lifecycleChart"');
        $agingPos = strpos($content, 'id="agingChart"');
        $recentActivityPos = strpos($content, 'Recent Activity Logs');

        $this->assertNotFalse($qrTrendPos);
        $this->assertNotFalse($lifecyclePos);
        $this->assertNotFalse($agingPos);
        $this->assertNotFalse($recentActivityPos);

        // All operational charts must appear BEFORE Recent Activity Logs
        $this->assertLessThan($recentActivityPos, $qrTrendPos, 'qrTrendChart should be above Recent Activity Logs');
        $this->assertLessThan($recentActivityPos, $lifecyclePos, 'lifecycleChart should be above Recent Activity Logs');
        $this->assertLessThan($recentActivityPos, $agingPos, 'agingChart should be above Recent Activity Logs');
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
     * Test 9: Office Bottleneck & Workload Intelligence and Activity Feed are active and scrollable.
     */
    public function test_dashboard_workloads_and_activity_feed_side_by_side_and_scrollable(): void
    {
        $response = $this->actingAsAdmin()->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Office Bottleneck & Workload Intelligence', false);
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

    /**
     * Test 19: Weekly Volume Flow chart displays Monday–Sunday day labels in chronological order with 0-padded days.
     */
    public function test_reports_weekly_volume_flow_has_monday_to_sunday_day_labels_and_correct_mapping(): void
    {
        // Place a document on this week's Wednesday
        $startOfWeek = now()->startOfWeek();
        $wednesday = $startOfWeek->copy()->addDays(2)->setHour(10);

        $wedDoc = Document::create([
            'title' => 'Wednesday Document',
            'type' => 'Invoice',
            'priority' => 'Normal',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->originOffice->id,
            'destination_office_id' => $this->destinationOffice->id,
            'uploaded_by' => $this->adminUser->id,
            'status' => 'Pending',
        ]);
        $wedDoc->timestamps = false;
        $wedDoc->created_at = $wednesday;
        $wedDoc->save();

        $response = $this->actingAsAdmin()->get(route('reports.index'));
        $response->assertStatus(200);

        $flowLabels = $response->viewData('flowLabels');
        $flowData = $response->viewData('flowData');

        $this->assertIsArray($flowLabels);
        $this->assertCount(7, $flowLabels);
        $this->assertStringContainsString('Monday', $flowLabels[0]);
        $this->assertStringContainsString('Wednesday', $flowLabels[2]);
        $this->assertStringContainsString('Sunday', $flowLabels[6]);

        $this->assertIsArray($flowData);
        $this->assertCount(7, $flowData);

        // Wednesday is index 2
        $this->assertGreaterThanOrEqual(1, $flowData[2]);

        // Assert chart canvas and JS labels are in view output
        $response->assertSee('flowChart', false);
        $response->assertSee('Weekly Volume Flow', false);
    }

    /**
     * Test 21: Mutual synchronization between User/Staff workflow actions and Admin Dashboard analytics.
     */
    public function test_user_workflow_actions_synchronize_with_admin_dashboard_and_calendar(): void
    {
        // 1. Initially document is Pending, completed is 0
        $dash1 = $this->actingAsAdmin()->get(route('dashboard'));
        $dash1->assertStatus(200);
        $dash1->assertViewHas('completedDocs', 0);

        // 2. Staff user receives and completes document
        $this->document->update([
            'status' => 'Completed',
            'completed_at' => now(),
            'received_at' => now()->subMinutes(30),
        ]);

        ActivityLog::create([
            'user' => 'Staff Officer',
            'action' => 'Document Completed',
            'document_id' => $this->document->id,
            'ip' => '127.0.0.1',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'meta' => ['department' => 'Registrar Office'],
        ]);

        // 3. Admin dashboard immediately reflects completed count = 1 and recent activity
        $dash2 = $this->actingAsAdmin()->get(route('dashboard'));
        $dash2->assertStatus(200);
        $dash2->assertViewHas('completedDocs', 1);
        $dash2->assertSee('Document Completed');
        $dash2->assertSee('Staff Officer');

        // 4. Calendar date events API reflects the event for today
        $calResponse = $this->actingAsAdmin()->getJson(route('api.dashboard.calendarDateEvents', [
            'date' => now()->toDateString(),
        ]));
        $calResponse->assertStatus(200);
        $events = $calResponse->json('data.documents.0.events');
        $this->assertNotEmpty($events);
        $actionNames = array_column($events, 'action');
        $this->assertTrue(in_array('Document Completed', $actionNames) || in_array('Document Uploaded', $actionNames));
    }
}
