<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AdminDocumentsModuleUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff1;
    private User $staff2;
    private Office $officeOrigin;
    private Office $officeMiddle;
    private Office $officeDest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officeOrigin = Office::create([
            'name' => 'Office of the President',
            'department' => 'OP',
        ]);

        $this->officeMiddle = Office::create([
            'name' => 'Human Resources',
            'department' => 'HR',
        ]);

        $this->officeDest = Office::create([
            'name' => 'Academic Affairs',
            'department' => 'VPAA',
        ]);

        $this->admin = User::create([
            'name' => 'System Admin',
            'username' => 'sysadmin',
            'email' => 'admin@naap.org',
            'password' => bcrypt('AdminPass123!'),
            'role' => 'Administrator',
            'office_id' => $this->officeOrigin->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);

        $this->staff1 = User::create([
            'name' => 'HR Receiver',
            'username' => 'hr_staff',
            'email' => 'hr@naap.org',
            'password' => bcrypt('StaffPass123!'),
            'role' => 'Staff',
            'office_id' => $this->officeMiddle->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);

        $this->staff2 = User::create([
            'name' => 'Academic Receiver',
            'username' => 'acad_staff',
            'email' => 'acad@naap.org',
            'password' => bcrypt('StaffPass123!'),
            'role' => 'Staff',
            'office_id' => $this->officeDest->id,
            'is_active' => true,
            'needs_password_change' => false,
        ]);
    }

    private function actingAsAdmin()
    {
        return $this->withSession([
            'authenticated'    => true,
            'user_id'          => $this->admin->id,
            'user_role'        => 'Administrator',
            'user_name'        => $this->admin->name,
            'otp_verified'     => true,
            'password_changed' => true,
        ])->actingAs($this->admin);
    }

    private function actingAsStaff(User $user)
    {
        return $this->withSession([
            'authenticated'    => true,
            'user_id'          => $user->id,
            'user_role'        => 'Staff',
            'user_name'        => $user->name,
            'otp_verified'     => true,
            'password_changed' => true,
        ])->actingAs($user);
    }

    /**
     * Test 1: Document list displays real database relationships and active workflow office/receiver.
     */
    public function test_document_list_displays_real_information_and_active_office(): void
    {
        $doc = Document::create([
            'title'                 => 'Budget Proposal 2027',
            'tracking_number'       => 'TRK-20260930-BGT01',
            'type'                  => 'PDF',
            'priority'              => 'High',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeOrigin->id,
            'destination_office_id' => $this->officeDest->id,
            'receiver_user_id'      => $this->staff1->id,
            'uploaded_by'           => $this->admin->id,
            'due_date'              => now()->addDays(5),
            'qr_code'               => 'qr_codes/bgt01.png',
        ]);

        // Hop 1: routed to HR Receiver
        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->officeOrigin->id,
            'to_office_id'     => $this->officeMiddle->id,
            'receiver_user_id' => $this->staff1->id,
            'status'           => 'Pending',
            'sort_order'       => 1,
            'sla_hours'        => 72,
            'sla_due_at'       => now()->addHours(72),
        ]);

        $response = $this->actingAsAdmin()->get(route('documents.index'));

        $response->assertStatus(200);
        $response->assertSee('Budget Proposal 2027');
        $response->assertSee('TRK-20260930-BGT01');
        $response->assertSee('Office of the President');
        $response->assertSee('Human Resources'); // Active current office resolved from hop
        $response->assertSee('Academic Affairs'); // Destination
        $response->assertSee('HR Receiver');
        $response->assertSee('On Track');
    }

    /**
     * Test 2: Server-side search finds documents across title, tracking, receiver, and offices.
     */
    public function test_server_side_search_across_multiple_fields(): void
    {
        $doc1 = Document::create([
            'title'                 => 'Curriculum Revision Plan',
            'tracking_number'       => 'TRK-CURR-001',
            'description'           => 'Annual department curriculum review',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeOrigin->id,
            'destination_office_id' => $this->officeDest->id,
            'receiver_user_id'      => $this->staff2->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        $doc2 = Document::create([
            'title'                 => 'HR Employee Evaluation',
            'tracking_number'       => 'TRK-EVAL-002',
            'description'           => 'Performance ratings',
            'status'                => 'In Transit',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeMiddle->id,
            'destination_office_id' => $this->officeMiddle->id,
            'receiver_user_id'      => $this->staff1->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // Search by tracking number
        $resp1 = $this->actingAsAdmin()->get(route('documents.index', ['search' => 'TRK-CURR']));
        $resp1->assertSee('Curriculum Revision Plan');
        $resp1->assertDontSee('HR Employee Evaluation');

        // Search by receiver name
        $resp2 = $this->actingAsAdmin()->get(route('documents.index', ['search' => 'HR Receiver']));
        $resp2->assertSee('HR Employee Evaluation');
        $resp2->assertDontSee('Curriculum Revision Plan');

        // Search by description
        $resp3 = $this->actingAsAdmin()->get(route('documents.index', ['search' => 'department curriculum']));
        $resp3->assertSee('Curriculum Revision Plan');
        $resp3->assertDontSee('HR Employee Evaluation');
    }

    /**
     * Test 3: Status and Priority filters work correctly.
     */
    public function test_status_and_priority_filters(): void
    {
        Document::create([
            'title'                 => 'Pending Document',
            'tracking_number'       => 'TRK-PND-001',
            'status'                => 'Pending',
            'priority'              => 'Normal',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeOrigin->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        Document::create([
            'title'                 => 'Completed Document',
            'tracking_number'       => 'TRK-CMP-002',
            'status'                => 'Completed',
            'priority'              => 'Urgent',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeDest->id,
            'uploaded_by'           => $this->admin->id,
            'completed_at'          => now(),
        ]);

        // Filter status: completed
        $respCompleted = $this->actingAsAdmin()->get(route('documents.index', ['status' => 'completed']));
        $respCompleted->assertSee('Completed Document');
        $respCompleted->assertDontSee('Pending Document');

        // Filter priority: urgent
        $respPriority = $this->actingAsAdmin()->get(route('documents.index', ['priority' => 'urgent']));
        $respPriority->assertSee('Completed Document');
        $respPriority->assertDontSee('Pending Document');
    }

    /**
     * Test 4: SLA Remaining calculation and timestamp validation.
     */
    public function test_sla_remaining_and_timestamp_validation(): void
    {
        // 1. Overdue document
        $docOverdue = Document::create([
            'title'                 => 'Overdue Report',
            'tracking_number'       => 'TRK-OVD-01',
            'status'                => 'Pending',
            'due_date'              => now()->subDays(2),
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeOrigin->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // 2. Nearing SLA document (due in 24 hours)
        $docNearing = Document::create([
            'title'                 => 'Nearing SLA Report',
            'tracking_number'       => 'TRK-NRG-02',
            'status'                => 'Pending',
            'due_date'              => now()->addHours(24),
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeOrigin->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // 3. Invalid timestamps document (received before created)
        $docInvalid = Document::create([
            'title'                 => 'Invalid Timestamp Doc',
            'tracking_number'       => 'TRK-INV-03',
            'status'                => 'Pending',
            'due_date'              => now()->addDays(5),
            'created_at'            => now(),
            'received_at'           => now()->subDays(5), // Invalid: received before created
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeOrigin->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        $response = $this->actingAsAdmin()->get(route('documents.index'));
        $response->assertStatus(200);
        $response->assertSee('Overdue');
        $response->assertSee('Nearing SLA');
        $response->assertSee('N/A');
    }

    /**
     * Test 5: Bulk Actions (completed, archive, export, and delete protection).
     */
    public function test_bulk_actions_functionality(): void
    {
        $doc1 = Document::create([
            'title'                 => 'Doc For Bulk 1',
            'tracking_number'       => 'TRK-BLK-01',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeOrigin->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        $doc2 = Document::create([
            'title'                 => 'Doc For Bulk 2',
            'tracking_number'       => 'TRK-BLK-02',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeOrigin->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        // Bulk Completed
        $resp = $this->actingAsAdmin()->postJson(route('documents.bulk-action'), [
            'action'       => 'completed',
            'document_ids' => [$doc1->id, $doc2->id],
        ]);
        $resp->assertJson(['success' => true]);
        $this->assertEquals('Completed', $doc1->fresh()->status);
        $this->assertEquals('Completed', $doc2->fresh()->status);

        // Bulk Archive
        $respArchive = $this->actingAsAdmin()->postJson(route('documents.bulk-action'), [
            'action'       => 'archive',
            'document_ids' => [$doc1->id],
        ]);
        $respArchive->assertJson(['success' => true]);
        $this->assertEquals('Archived', $doc1->fresh()->status);

        // Bulk Export (CSV Stream)
        $respExport = $this->actingAsAdmin()->get(route('documents.bulk-action', [
            'action'       => 'export',
            'document_ids' => [$doc2->id],
        ]));
        $respExport->assertStatus(200);
        $respExport->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /**
     * Test 6: Legitimate recipient of routed document is authorized to perform workflow actions without 403.
     */
    public function test_legitimate_recipient_authorized_without_403(): void
    {
        $doc = Document::create([
            'title'                 => 'Travel Clearance Request',
            'tracking_number'       => 'TRK-TVL-99',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeMiddle->id,
            'destination_office_id' => $this->officeDest->id,
            'receiver_user_id'      => $this->staff1->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        $routing = DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->officeOrigin->id,
            'to_office_id'     => $this->officeMiddle->id,
            'receiver_user_id' => $this->staff1->id,
            'status'           => 'Pending',
            'sort_order'       => 1,
            'signature_required' => false,
        ]);

        // 1. Staff 1 (legitimate receiver) marks document as Received
        $respReceive = $this->actingAsStaff($this->staff1)->post(route('documents.workflowAction', $doc->id), [
            'status' => 'Received',
            'notes'  => 'Document safely received by HR',
        ]);
        $respReceive->assertSessionHasNoErrors();
        $this->assertEquals('Received', $doc->fresh()->status);
        $this->assertEquals('Received', $routing->fresh()->status);

        // 2. Staff 1 is STILL authorized to advance or approve the document
        $respApprove = $this->actingAsStaff($this->staff1)->post(route('documents.workflowAction', $doc->id), [
            'status' => 'Approved',
            'notes'  => 'HR requirements verified and approved',
        ]);
        $respApprove->assertSessionHasNoErrors();
        $this->assertEquals('Completed', $doc->fresh()->status);

        // 3. Unauthorized user (Staff 2) attempting action on unrelated document returns 403
        $unrelatedDoc = Document::create([
            'title'                 => 'Confidential OP Memo',
            'tracking_number'       => 'TRK-MEMO-OP',
            'status'                => 'Pending',
            'origin_office_id'      => $this->officeOrigin->id,
            'current_office_id'     => $this->officeOrigin->id,
            'receiver_user_id'      => $this->admin->id,
            'uploaded_by'           => $this->admin->id,
        ]);

        $respForbidden = $this->actingAsStaff($this->staff2)->post(route('documents.workflowAction', $unrelatedDoc->id), [
            'status' => 'Approved',
            'notes'  => 'Should fail with 403',
        ]);
        $respForbidden->assertStatus(403);
    }

    /**
     * Test 7: Section 43 - Complete Real Document Workflow Lifecycle (Steps 1 to 10).
     */
    public function test_complete_real_document_workflow_steps_1_to_10(): void
    {
        // STEP 1: Admin uploads a document
        $storage = Storage::fake('public');
        $file = UploadedFile::fake()->create('procurement_plan.pdf', 500, 'application/pdf');

        $uploadResponse = $this->actingAsAdmin()->post(route('documents.store'), [
            'title'                 => 'FY2027 Equipment Procurement Plan',
            'description'           => 'Strategic acquisition plan for laboratories',
            'category'              => 'Procurement',
            'sla'                   => 'Simple Transaction (3 Working Days)',
            'origin_office_id'      => $this->officeOrigin->id,
            'routing_office_ids'    => [$this->officeMiddle->id, $this->officeDest->id],
            'routing_user_ids'      => [$this->staff1->id, $this->staff2->id],
            'file'                  => $file,
        ]);

        $uploadResponse->assertSessionHasNoErrors();
        $doc = Document::where('title', 'FY2027 Equipment Procurement Plan')->first();
        $this->assertNotNull($doc, 'Step 1: Document must be saved in database');

        // VERIFY Step 1: Document appears in Documents module
        $listResponse = $this->actingAsAdmin()->get(route('documents.index'));
        $listResponse->assertSee('FY2027 Equipment Procurement Plan');

        // STEP 2: System creates unique tracking number
        $this->assertNotEmpty($doc->tracking_number);
        $this->assertStringStartsWith('TRK-', $doc->tracking_number);
        $this->assertEquals(1, Document::where('tracking_number', $doc->tracking_number)->count(), 'Step 2: Tracking number must be unique');

        // STEP 3: QR code is generated and points to document
        $this->assertNotNull($doc->qr_code, 'Step 3: QR code must be generated');
        $this->assertTrue($storage->exists($doc->qr_code), 'Step 3: QR PNG file must exist in storage');

        // STEP 4: Admin routes / checks active routing step
        // Verified: Current Office is HR, Current Receiver is HR Receiver
        $this->assertEquals($this->officeMiddle->id, $doc->current_office_id, 'Step 4: Current office must be HR');
        $this->assertEquals($this->staff1->id, $doc->receiver_user_id, 'Step 4: Current receiver must be HR Staff');
        $this->assertTrue($doc->routings()->exists(), 'Step 4: Routing history must be created');
        $this->assertTrue(ActivityLog::where('document_id', $doc->id)->exists(), 'Step 4: Recent Activity Logs must update');

        // STEP 5: Receiver scans QR
        $scanResponse = $this->actingAsStaff($this->staff1)->postJson(route('qr.scan'), [
            'qr_data' => route('documents.show', $doc->id),
        ]);
        $scanResponse->assertJson(['success' => true]);
        $this->assertTrue(
            ActivityLog::where('document_id', $doc->id)
                ->where(fn($q) => $q->where('action', 'like', '%QR%')->orWhere('action', 'like', '%scan%'))
                ->exists(),
            'Step 5: QR scan must be recorded in Activity Logs'
        );

        // STEP 6: Authorized user accesses the document
        $showResponse = $this->actingAsStaff($this->staff1)->get(route('documents.show', $doc->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('FY2027 Equipment Procurement Plan');

        // STEP 8: Document is received / processed
        $receiveResponse = $this->actingAsStaff($this->staff1)->post(route('documents.workflowAction', $doc->id), [
            'status' => 'Received',
            'notes'  => 'Document checked and in review by HR',
        ]);
        $receiveResponse->assertSessionHasNoErrors();
        $this->assertEquals('Received', $doc->fresh()->status, 'Step 8: Document status must update to Received');

        // STEP 9: Document is approved by active receiver with digital signature and forwarded to Hop 2
        $sampleSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $approveResponse = $this->actingAsStaff($this->staff1)->post(route('documents.workflowAction', $doc->id), [
            'status'           => 'Approved',
            'notes'            => 'Approved by HR Director, forwarding to VPAA',
            'signature_option' => 'draw',
            'signature_data'   => $sampleSignature,
        ]);
        $approveResponse->assertSessionHasNoErrors();
        $doc = $doc->fresh();
        $this->assertEquals('In Transit', $doc->status, 'Step 9: Document status is In Transit to final hop');
        $this->assertEquals($this->officeDest->id, $doc->current_office_id, 'Step 9: Current office advances to Academic Affairs');
        $this->assertEquals($this->staff2->id, $doc->receiver_user_id, 'Step 9: Current receiver advances to Academic Staff');

        // STEP 10: Final receiver completes the document with digital signature
        $completeResponse = $this->actingAsStaff($this->staff2)->post(route('documents.workflowAction', $doc->id), [
            'status'           => 'Completed',
            'notes'            => 'Final endorsement and completion confirmed',
            'signature_option' => 'draw',
            'signature_data'   => $sampleSignature,
        ]);
        $completeResponse->assertSessionHasNoErrors();
        $doc = $doc->fresh();
        $this->assertEquals('Completed', $doc->status, 'Step 10: Document status must be Completed');
        $this->assertNotNull($doc->completed_at, 'Step 10: completed_at must be populated');

        // VERIFY: SLA result is On Track (completed before due_date)
        $this->assertTrue($doc->completed_at <= $doc->due_date, 'Step 10: SLA result must be on time');

        // VERIFY: Document list reflects Completed
        $finalList = $this->actingAsAdmin()->get(route('documents.index', ['status' => 'completed']));
        $finalList->assertSee('FY2027 Equipment Procurement Plan');
        $finalList->assertSee('On Track');
    }
}
