<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\DocumentPin;
use App\Models\DocumentView;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class EndToEndSecurityAndUniversalQrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Office $officeA;
    protected Office $officeB;

    protected User $adminUser;
    protected User $creatorUser;
    protected User $recipientUser;
    protected User $unrelatedUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->officeA = Office::create(['name' => 'Registrar Office', 'code' => 'REG', 'department' => 'Academic Affairs']);
        $this->officeB = Office::create(['name' => 'Finance Office', 'code' => 'FIN', 'department' => 'Administration']);

        $this->adminUser = User::create([
            'name' => 'System Admin',
            'username' => 'sysadmin',
            'email' => 'admin@naap.org',
            'password' => bcrypt('AdminPass123!'),
            'role' => 'Administrator',
            'office_id' => $this->officeA->id,
            'needs_password_change' => false,
            'status' => 'Active',
        ]);

        $this->creatorUser = User::create([
            'name' => 'Document Creator',
            'username' => 'creator.user',
            'email' => 'creator@naap.org',
            'password' => bcrypt('UserPass123!'),
            'role' => 'Staff',
            'office_id' => $this->officeA->id,
            'needs_password_change' => false,
            'status' => 'Active',
        ]);

        $this->recipientUser = User::create([
            'name' => 'Designated Recipient',
            'username' => 'recipient.user',
            'email' => 'recipient@naap.org',
            'password' => bcrypt('UserPass123!'),
            'role' => 'Staff',
            'office_id' => $this->officeB->id,
            'needs_password_change' => false,
            'status' => 'Active',
        ]);

        $this->unrelatedUser = User::create([
            'name' => 'Unrelated User',
            'username' => 'unrelated.user',
            'email' => 'unrelated@naap.org',
            'password' => bcrypt('UserPass123!'),
            'role' => 'Staff',
            'office_id' => $this->officeB->id,
            'needs_password_change' => false,
            'status' => 'Active',
        ]);
    }

    protected function asUser(User $user)
    {
        return $this->actingAs($user)->withSession([
            'authenticated' => true,
            'user_id'       => $user->id,
            'user_role'     => $user->role,
            'user_name'     => $user->name,
            'office_id'     => $user->office_id,
        ]);
    }

    /**
     * Requirement: Data Isolation
     * - New user starts with zero documents.
     * - User creates document and sees only their own document.
     */
    public function test_new_user_sees_zero_documents_until_they_create_or_receive_one(): void
    {
        $newUser = User::create([
            'name' => 'Brand New User',
            'username' => 'brandnew',
            'email' => 'brandnew@naap.org',
            'password' => bcrypt('Pass123!'),
            'role' => 'Employee',
            'office_id' => $this->officeA->id,
            'needs_password_change' => false,
            'status' => 'Active',
        ]);

        // Existing document by someone else
        Document::create([
            'title' => 'Unrelated Historical Contract',
            'tracking_number' => 'DOC-HIST-001',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeA->id,
            'destination_office_id' => $this->officeA->id,
            'uploaded_by' => $this->creatorUser->id,
            'status' => 'Pending',
        ]);

        // 1. New user has ZERO documents in Documents and Tracking
        $docList = $this->asUser($newUser)->get(route('documents.index'));
        $docList->assertStatus(200);
        $docList->assertDontSee('Unrelated Historical Contract');

        $trackList = $this->asUser($newUser)->get(route('track.index'));
        $trackList->assertStatus(200);
        $trackList->assertDontSee('Unrelated Historical Contract');

        // 2. User creates document
        Storage::disk('public')->put('documents/created_doc.pdf', 'file content');
        $createdDoc = Document::create([
            'title' => 'My First Uploaded Memo',
            'tracking_number' => 'DOC-MEMO-100',
            'qr_code' => 'QR-MEMO-100',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeA->id,
            'destination_office_id' => $this->officeA->id,
            'uploaded_by' => $newUser->id,
            'file_path' => 'documents/created_doc.pdf',
            'status' => 'Pending',
        ]);

        // Creator can see their own document and download their own file
        $myDocList = $this->asUser($newUser)->get(route('documents.index'));
        $myDocList->assertSee('My First Uploaded Memo');

        $downloadRes = $this->asUser($newUser)->get(route('documents.download', $createdDoc->id));
        $downloadRes->assertStatus(200);
    }

    /**
     * Acceptance Test 1 — Admin receives document
     * Admin receives document
     * → Document locked
     * → QR required (NO ADMIN BYPASS)
     * → Admin scans QR
     * → QR verified
     * → Document marked Viewed
     * → Tracking updated
     * → Admin can access document
     * → Sender sees Viewed
     */
    public function test_acceptance_1_admin_receives_document_and_scans_qr(): void
    {
        Storage::disk('public')->put('documents/for_admin.pdf', 'admin review content');
        $doc = Document::create([
            'title' => 'Executive Report for Administrator Review',
            'tracking_number' => 'DOC-ADM-999',
            'qr_code' => 'QR-ADM-999',
            'origin_office_id' => $this->officeB->id,
            'current_office_id' => $this->officeA->id,
            'destination_office_id' => $this->officeA->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->adminUser->id,
            'file_path' => 'documents/for_admin.pdf',
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $doc->id,
            'from_office_id' => $this->officeB->id,
            'to_office_id' => $this->officeA->id,
            'sender_user_id' => $this->creatorUser->id,
            'receiver_user_id' => $this->adminUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'scanned_at' => null,
        ]);

        // Admin receives document -> Document locked
        $detailBeforeScan = $this->asUser($this->adminUser)->get(route('documents.show', $doc->id));
        $detailBeforeScan->assertStatus(200);
        $detailBeforeScan->assertSee('QR Verification Required');

        // Admin attempts download before QR verification -> 403 Forbidden! NO bypass!
        $adminDlBeforeQr = $this->asUser($this->adminUser)->get(route('documents.download', $doc->id));
        $adminDlBeforeQr->assertStatus(403);
        $adminDlBeforeQr->assertSee('Access Restricted');
        $adminDlBeforeQr->assertSee('You must complete QR verification');

        // Sender views tracking before scan -> Document is NOT viewed
        $senderTrackBefore = $this->asUser($this->creatorUser)->get(route('track.detail', $doc->id));
        $senderTrackBefore->assertStatus(200);
        $senderTrackBefore->assertSee('No read receipts recorded yet.');
        $senderIndexBefore = $this->asUser($this->creatorUser)->get(route('track.index'));
        $senderIndexBefore->assertSee('Never Viewed');

        // Admin scans QR
        $adminScan = $this->asUser($this->adminUser)->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
            'target_document_id' => $doc->id,
        ]);
        $adminScan->assertStatus(200);
        $adminScan->assertJson(['success' => true]);

        // Document marked Viewed
        $doc->refresh();
        $this->assertEquals('Viewed', $doc->status);
        $this->assertEquals('Verified', $doc->qr_status);
        $this->assertNotNull($doc->qr_scanned_at);
        $this->assertTrue(DocumentView::where('document_id', $doc->id)->where('user_id', $this->adminUser->id)->exists());

        // Admin can access document after QR scan
        $adminDlAfterQr = $this->actingAs($this->adminUser)->withSession([
            'authenticated' => true,
            'user_id' => $this->adminUser->id,
            'user_role' => $this->adminUser->role,
            'qr_verified_' . $doc->id => true,
        ])->get(route('documents.download', $doc->id));
        $adminDlAfterQr->assertStatus(200);

        // Sender sees Viewed with Admin name and QR Scan verification
        $senderTrackAfter = $this->asUser($this->creatorUser)->get(route('track.detail', $doc->id));
        $senderTrackAfter->assertStatus(200);
        $senderTrackAfter->assertSee($this->adminUser->name);
        $senderTrackAfter->assertSee('QR Scan');
    }

    /**
     * Acceptance Test 2 — Regular user receives document
     * User receives document
     * → Document locked
     * → QR required
     * → User scans QR
     * → Document marked Viewed
     * → Tracking updated
     * → User can access document
     * → Sender sees Viewed
     */
    public function test_acceptance_2_regular_user_receives_document_and_scans_qr(): void
    {
        Storage::disk('public')->put('documents/staff_doc.pdf', 'staff content');
        $doc = Document::create([
            'title' => 'Staff Task Delegation Memo',
            'tracking_number' => 'DOC-STAFF-101',
            'qr_code' => 'QR-STAFF-101',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeB->id,
            'destination_office_id' => $this->officeB->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'file_path' => 'documents/staff_doc.pdf',
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $doc->id,
            'from_office_id' => $this->officeA->id,
            'to_office_id' => $this->officeB->id,
            'sender_user_id' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'scanned_at' => null,
        ]);

        // User receives document -> locked
        $detailRes = $this->asUser($this->recipientUser)->get(route('documents.show', $doc->id));
        $detailRes->assertStatus(200);
        $detailRes->assertSee('QR Verification Required');

        // Download blocked
        $dlBefore = $this->asUser($this->recipientUser)->get(route('documents.download', $doc->id));
        $dlBefore->assertStatus(403);

        // Sender sees No read receipts on detail and Never Viewed on tracking index
        $senderTrack = $this->asUser($this->creatorUser)->get(route('track.detail', $doc->id));
        $senderTrack->assertSee('No read receipts recorded yet.');
        $senderIndex = $this->asUser($this->creatorUser)->get(route('track.index'));
        $senderIndex->assertSee('Never Viewed');

        // User scans QR
        $scan = $this->asUser($this->recipientUser)->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
            'target_document_id' => $doc->id,
        ]);
        $scan->assertStatus(200);

        // Document marked Viewed
        $doc->refresh();
        $this->assertEquals('Viewed', $doc->status);
        $this->assertEquals('Verified', $doc->qr_status);

        // User can access document
        $dlAfter = $this->actingAs($this->recipientUser)->withSession([
            'authenticated' => true,
            'user_id' => $this->recipientUser->id,
            'user_role' => $this->recipientUser->role,
            'qr_verified_' . $doc->id => true,
        ])->get(route('documents.download', $doc->id));
        $dlAfter->assertStatus(200);

        // Sender sees Viewed
        $senderTrackAfter = $this->asUser($this->creatorUser)->get(route('track.detail', $doc->id));
        $senderTrackAfter->assertSee($this->recipientUser->name);
        $senderTrackAfter->assertSee('QR Scan');
    }

    /**
     * Acceptance Test 3 — Document without OTP
     * Receive → QR required → QR verified → Viewed → Access
     */
    public function test_acceptance_3_document_without_otp(): void
    {
        Storage::disk('public')->put('documents/standard.pdf', 'standard content');
        $doc = Document::create([
            'title' => 'Standard Policy Memo',
            'tracking_number' => 'DOC-STD-001',
            'qr_code' => 'QR-STD-001',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeB->id,
            'destination_office_id' => $this->officeB->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'is_confidential' => false,
            'file_path' => 'documents/standard.pdf',
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $doc->id,
            'from_office_id' => $this->officeA->id,
            'to_office_id' => $this->officeB->id,
            'sender_user_id' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'scanned_at' => null,
        ]);

        // Attempting download before QR is blocked
        $dlRes = $this->asUser($this->recipientUser)->get(route('documents.download', $doc->id));
        $dlRes->assertStatus(403);
        $dlRes->assertSee('You must complete QR verification');

        // Scans QR
        $scanRes = $this->asUser($this->recipientUser)->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
            'target_document_id' => $doc->id,
        ]);
        $scanRes->assertStatus(200);

        // Status is Viewed
        $doc->refresh();
        $this->assertEquals('Viewed', $doc->status);

        // Download succeeds
        $dlAfterScan = $this->actingAs($this->recipientUser)->withSession([
            'authenticated' => true,
            'user_id' => $this->recipientUser->id,
            'user_role' => $this->recipientUser->role,
            'qr_verified_' . $doc->id => true,
        ])->get(route('documents.download', $doc->id));
        $dlAfterScan->assertStatus(200);
    }

    /**
     * Acceptance Test 4 — Document with OTP
     * Receive → QR required → Existing OTP workflow → Required verification completed → Viewed → Access
     */
    public function test_acceptance_4_document_with_otp(): void
    {
        Storage::disk('public')->put('documents/confidential.pdf', 'confidential content');
        $doc = Document::create([
            'title' => 'Confidential Financial Evaluation',
            'tracking_number' => 'DOC-CONF-777',
            'qr_code' => 'QR-CONF-777',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeB->id,
            'destination_office_id' => $this->officeB->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'is_confidential' => true,
            'file_path' => 'documents/confidential.pdf',
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $doc->id,
            'from_office_id' => $this->officeA->id,
            'to_office_id' => $this->officeB->id,
            'sender_user_id' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'scanned_at' => null,
        ]);

        // Before QR scan: Download is blocked
        $dlBeforeQr = $this->asUser($this->recipientUser)->get(route('documents.download', $doc->id));
        $dlBeforeQr->assertStatus(403);

        // Scan QR: Returns pin_required
        $scanQr = $this->asUser($this->recipientUser)->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
            'target_document_id' => $doc->id,
        ]);
        $scanQr->assertStatus(200);
        $scanQr->assertJson(['pin_required' => true]);

        // After QR scan but BEFORE OTP verification: Download is still blocked!
        $dlWithOnlyQr = $this->actingAs($this->recipientUser)->withSession([
            'authenticated' => true,
            'user_id' => $this->recipientUser->id,
            'user_role' => $this->recipientUser->role,
            'qr_verified_' . $doc->id => true, // QR verified, but OTP NOT verified
        ])->get(route('documents.download', $doc->id));
        $dlWithOnlyQr->assertStatus(403);
        $dlWithOnlyQr->assertSee('Both QR verification and OTP verification are required');

        // Create active PIN and verify it
        DocumentPin::create([
            'document_id' => $doc->id,
            'user_id' => $this->recipientUser->id,
            'recipient_id' => $this->recipientUser->id,
            'email' => $this->recipientUser->email,
            'pin' => '654321',
            'pin_code' => '654321',
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,
            'attempts' => 0,
            'verification_status' => 'pending',
        ]);

        // Submit PIN
        $pinRes = $this->actingAs($this->recipientUser)->withSession([
            'authenticated' => true,
            'user_id' => $this->recipientUser->id,
            'user_role' => $this->recipientUser->role,
            'qr_verified_' . $doc->id => true,
        ])->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
            'target_document_id' => $doc->id,
            'pin' => '654321',
        ]);
        $pinRes->assertStatus(200);

        // Verification completed -> Viewed
        $doc->refresh();
        $this->assertEquals('Viewed', $doc->status);

        // Now BOTH are verified: Download succeeds
        $dlBothVerified = $this->actingAs($this->recipientUser)->withSession([
            'authenticated' => true,
            'user_id' => $this->recipientUser->id,
            'user_role' => $this->recipientUser->role,
            'qr_verified_' . $doc->id => true,
            'otp_verified_' . $doc->id => true,
        ])->get(route('documents.download', $doc->id));
        $dlBothVerified->assertStatus(200);
    }

    /**
     * Acceptance Test 5 — Wrong QR
     * Recipient receives Document A
     * → Scans QR for Document B
     * → Verification fails
     * → Document A remains locked
     * → Document A NOT marked Viewed
     */
    public function test_acceptance_5_wrong_qr_rejected_and_remains_locked(): void
    {
        $docA = Document::create([
            'title' => 'Document Alpha',
            'tracking_number' => 'DOC-ALPHA-01',
            'qr_code' => 'QR-ALPHA-01',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeB->id,
            'destination_office_id' => $this->officeB->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'Pending',
        ]);

        $docB = Document::create([
            'title' => 'Document Beta',
            'tracking_number' => 'DOC-BETA-02',
            'qr_code' => 'QR-BETA-02',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeB->id,
            'destination_office_id' => $this->officeB->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'Pending',
        ]);

        // Scanning QR of Document B while targeting Document A is rejected
        $wrongQrRes = $this->asUser($this->recipientUser)->post(route('qr.scan'), [
            'qr_data' => $docB->qr_code,
            'target_document_id' => $docA->id,
        ]);
        $wrongQrRes->assertStatus(422);
        $wrongQrRes->assertJson([
            'success' => false,
            'error'   => true,
            'message' => 'QR Code mismatch. The scanned QR code does not belong to the document you are trying to verify. Please scan the correct QR code.',
        ]);

        // Document A remains locked and NOT marked Viewed
        $docA->refresh();
        $this->assertEquals('Pending', $docA->status);
        $this->assertNull($docA->qr_scanned_at);
        $this->assertFalse(DocumentView::where('document_id', $docA->id)->exists());
    }

    /**
     * Acceptance Test 6 — Unauthorized user
     * User is not a legitimate recipient
     * → Cannot access document
     * → Cannot use QR to bypass document authorization
     */
    public function test_acceptance_6_unauthorized_user_blocked_across_all_endpoints(): void
    {
        Storage::disk('public')->put('documents/restricted.pdf', 'restricted content');
        $doc = Document::create([
            'title' => 'Restricted Document',
            'tracking_number' => 'DOC-REST-01',
            'qr_code' => 'QR-REST-01',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeA->id,
            'destination_office_id' => $this->officeA->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'file_path' => 'documents/restricted.pdf',
            'status' => 'Pending',
        ]);

        // Direct document view blocked
        $viewRes = $this->asUser($this->unrelatedUser)->get(route('documents.show', $doc->id));
        $viewRes->assertStatus(403);

        // Direct tracking view blocked
        $trackRes = $this->asUser($this->unrelatedUser)->get(route('track.detail', $doc->id));
        $trackRes->assertStatus(403);

        // Direct download blocked
        $dlRes = $this->asUser($this->unrelatedUser)->get(route('documents.download', $doc->id));
        $dlRes->assertStatus(403);

        // Direct status API blocked
        $statusRes = $this->asUser($this->unrelatedUser)->get(route('documents.status', $doc->id));
        $statusRes->assertStatus(403);

        // QR scan by unauthorized user blocked
        $qrScanRes = $this->asUser($this->unrelatedUser)->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
        ]);
        $qrScanRes->assertStatus(403);
    }

    /**
     * Acceptance Test 7 — Direct API bypass
     * Recipient has not scanned QR
     * → Direct download request
     * → Backend rejects request
     */
    public function test_acceptance_7_direct_api_bypass_rejected(): void
    {
        Storage::disk('public')->put('documents/protected_file.pdf', 'protected binary data');
        $doc = Document::create([
            'title' => 'Protected API File',
            'tracking_number' => 'DOC-PROT-100',
            'qr_code' => 'QR-PROT-100',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeB->id,
            'destination_office_id' => $this->officeB->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'file_path' => 'documents/protected_file.pdf',
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $doc->id,
            'from_office_id' => $this->officeA->id,
            'to_office_id' => $this->officeB->id,
            'sender_user_id' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'scanned_at' => null,
        ]);

        // Direct GET download request before QR scan is rejected with 403
        $bypassRes = $this->asUser($this->recipientUser)->get(route('documents.download', $doc->id));
        $bypassRes->assertStatus(403);
        $bypassRes->assertSee('Access Restricted: You must complete QR verification before downloading or accessing this document.');

        // Direct workflow execution is also rejected with 403
        $workflowBypass = $this->asUser($this->recipientUser)->post(route('documents.workflowAction', $doc->id), [
            'status' => 'Completed',
        ]);
        $workflowBypass->assertStatus(403);
    }

    /**
     * Acceptance Test 8 — Sender tracking
     * Sender sends document
     * → Status = Sent/Received
     * → Recipient has not scanned
     * → NOT Viewed
     * Recipient scans QR
     * → Status = Viewed
     * → Timestamp recorded
     * → Sender can see Viewed event
     */
    public function test_acceptance_8_sender_tracking_shows_viewed_only_after_qr_scan(): void
    {
        $doc = Document::create([
            'title' => 'Inter-Office Dispatch ABC-001',
            'tracking_number' => 'DOC-DISP-001',
            'qr_code' => 'QR-DISP-001',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeB->id,
            'destination_office_id' => $this->officeB->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'In Transit',
        ]);

        DocumentRouting::create([
            'document_id' => $doc->id,
            'from_office_id' => $this->officeA->id,
            'to_office_id' => $this->officeB->id,
            'sender_user_id' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'In Transit',
            'sort_order' => 1,
            'scanned_at' => null,
        ]);

        // 1. Before scan: Sender inspects tracking -> Not viewed
        $senderCheck1 = $this->asUser($this->creatorUser)->get(route('track.detail', $doc->id));
        $senderCheck1->assertStatus(200);
        $senderCheck1->assertSee('No read receipts recorded yet.');
        $senderIndex1 = $this->asUser($this->creatorUser)->get(route('track.index'));
        $senderIndex1->assertSee('Never Viewed');

        // Opening details by recipient does NOT mark it viewed (Rule 3)
        $this->asUser($this->recipientUser)->get(route('documents.show', $doc->id));
        $senderCheck2 = $this->asUser($this->creatorUser)->get(route('track.detail', $doc->id));
        $senderCheck2->assertSee('No read receipts recorded yet.');

        // 2. Recipient scans QR
        $scan = $this->asUser($this->recipientUser)->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
            'target_document_id' => $doc->id,
        ]);
        $scan->assertStatus(200);

        // 3. Sender inspects tracking -> Status = Viewed, Viewed by Recipient, Verification: QR Scan
        $senderCheck3 = $this->asUser($this->creatorUser)->get(route('track.detail', $doc->id));
        $senderCheck3->assertStatus(200);
        $senderCheck3->assertSee($this->recipientUser->name);
        $senderCheck3->assertSee('QR Scan');
        $senderCheck3->assertDontSee('No read receipts recorded yet.');
    }

    /**
     * Acceptance Test 9 — Repeated scan
     * First QR scan
     * → First Viewed timestamp created
     * Second scan
     * → Does not overwrite original First Viewed timestamp
     */
    public function test_acceptance_9_repeated_scan_does_not_overwrite_first_viewed_timestamp(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 16, 15, 0));

        $doc = Document::create([
            'title' => 'Timestamp Verification Document',
            'tracking_number' => 'DOC-TIME-001',
            'qr_code' => 'QR-TIME-001',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeB->id,
            'destination_office_id' => $this->officeB->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $doc->id,
            'from_office_id' => $this->officeA->id,
            'to_office_id' => $this->officeB->id,
            'sender_user_id' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'scanned_at' => null,
        ]);

        // First scan at 16:15:00
        $firstScan = $this->asUser($this->recipientUser)->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
            'target_document_id' => $doc->id,
        ]);
        $firstScan->assertStatus(200);

        $doc->refresh();
        $firstViewedTimestamp = $doc->qr_scanned_at;
        $this->assertNotNull($firstViewedTimestamp);
        $this->assertEquals('2026-10-08 16:15:00', $firstViewedTimestamp->toDateTimeString());

        $firstDocView = DocumentView::where('document_id', $doc->id)->where('user_id', $this->recipientUser->id)->first();
        $this->assertNotNull($firstDocView);
        $this->assertEquals('2026-10-08 16:15:00', $firstDocView->viewed_at->toDateTimeString());

        // 10 minutes later (16:25:00)
        Carbon::setTestNow(Carbon::create(2026, 10, 8, 16, 25, 0));

        // Second scan
        $secondScan = $this->asUser($this->recipientUser)->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
            'target_document_id' => $doc->id,
        ]);
        $secondScan->assertStatus(200);

        $doc->refresh();
        // Original First Viewed timestamp must remain completely intact!
        $this->assertEquals('2026-10-08 16:15:00', $doc->qr_scanned_at->toDateTimeString());

        $firstDocView->refresh();
        $this->assertEquals('2026-10-08 16:15:00', $firstDocView->viewed_at->toDateTimeString());

        // ActivityLog records 'QR Verified Again'
        $this->assertTrue(ActivityLog::where('document_id', $doc->id)->where('action', 'QR Verified Again')->exists());

        Carbon::setTestNow();
    }

    /**
     * Acceptance Test 10 — Routing regression
     * Verify that normal routing, receiving, status updates, and tracking continue to work exactly as intended.
     */
    public function test_acceptance_10_routing_regression_lifecycle_continues_to_work(): void
    {
        Storage::disk('public')->put('documents/lifecycle.pdf', 'lifecycle content');
        $doc = Document::create([
            'title' => 'Lifecycle Integration Document',
            'tracking_number' => 'DOC-LIFE-001',
            'qr_code' => 'QR-LIFE-001',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeB->id,
            'destination_office_id' => $this->officeB->id,
            'uploaded_by' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'file_path' => 'documents/lifecycle.pdf',
            'status' => 'In Transit',
        ]);

        DocumentRouting::create([
            'document_id' => $doc->id,
            'from_office_id' => $this->officeA->id,
            'to_office_id' => $this->officeB->id,
            'sender_user_id' => $this->creatorUser->id,
            'receiver_user_id' => $this->recipientUser->id,
            'status' => 'In Transit',
            'sort_order' => 1,
            'signature_required' => false,
            'scanned_at' => null,
        ]);

        // Dashboard renders cleanly
        $dashboardRes = $this->asUser($this->recipientUser)->get(route('dashboard'));
        $dashboardRes->assertStatus(200);

        // Documents page renders cleanly
        $docsPage = $this->asUser($this->recipientUser)->get(route('documents.index'));
        $docsPage->assertStatus(200);
        $docsPage->assertSee('Lifecycle Integration Document');

        // Track page renders cleanly
        $trackPage = $this->asUser($this->recipientUser)->get(route('track.index'));
        $trackPage->assertStatus(200);
        $trackPage->assertSee('Lifecycle Integration Document');

        // Routing page renders cleanly
        $routingPage = $this->asUser($this->recipientUser)->get(route('routing.index'));
        $routingPage->assertStatus(200);

        // QR scan completes lifecycle step
        $scan = $this->asUser($this->recipientUser)->post(route('qr.scan'), [
            'qr_data' => $doc->qr_code,
            'target_document_id' => $doc->id,
        ]);
        $scan->assertStatus(200);

        // Workflow action updates status to Completed
        $workflow = $this->actingAs($this->recipientUser)->withSession([
            'authenticated' => true,
            'user_id' => $this->recipientUser->id,
            'user_role' => $this->recipientUser->role,
            'qr_verified_' . $doc->id => true,
        ])->post(route('documents.workflowAction', $doc->id), [
            'status' => 'Completed',
            'notes'  => 'Document verified and finalized successfully.',
        ]);
        $workflow->assertStatus(302);

        $doc->refresh();
        $this->assertEquals('Completed', $doc->status);
    }
}
