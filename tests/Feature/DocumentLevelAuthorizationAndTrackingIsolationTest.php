<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocumentLevelAuthorizationAndTrackingIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected $officeA;
    protected $officeB;

    protected $adminUser;
    protected $userA; // Creator
    protected $userB; // Newly created user
    protected $userC; // Unrelated user

    protected $doc1;
    protected $doc2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officeA = Office::create(['name' => 'Finance Office', 'department' => 'Administration']);
        $this->officeB = Office::create(['name' => 'Accounting Office', 'department' => 'Administration']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Administrator',
            'office_id' => $this->officeA->id,
            'status' => 'Active',
            'needs_password_change' => false,
        ]);

        $this->userA = User::create([
            'name' => 'User A Creator',
            'username' => 'user.a',
            'email' => 'usera@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Staff',
            'office_id' => $this->officeA->id,
            'status' => 'Active',
            'needs_password_change' => false,
        ]);

        $this->userB = User::create([
            'name' => 'User B New Account',
            'username' => 'user.b',
            'email' => 'userb@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Employee',
            'office_id' => $this->officeA->id, // Same office as User A!
            'status' => 'Active',
            'needs_password_change' => false,
        ]);

        $this->userC = User::create([
            'name' => 'User C Unrelated',
            'username' => 'user.c',
            'email' => 'userc@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Staff',
            'office_id' => $this->officeB->id,
            'status' => 'Active',
            'needs_password_change' => false,
        ]);

        // Create 2 existing documents created by User A in Office A
        $this->doc1 = Document::create([
            'title' => 'Operating Budget Plan 2026',
            'tracking_number' => 'DOC-FIN-001',
            'qr_code' => 'QR-FIN-001',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeA->id,
            'destination_office_id' => $this->officeA->id,
            'uploaded_by' => $this->userA->id,
            'status' => 'Pending',
            'file_path' => 'documents/plan2026.pdf',
        ]);

        $this->doc2 = Document::create([
            'title' => 'Procurement Request 88',
            'tracking_number' => 'DOC-FIN-002',
            'qr_code' => 'QR-FIN-002',
            'origin_office_id' => $this->officeA->id,
            'current_office_id' => $this->officeA->id,
            'destination_office_id' => $this->officeA->id,
            'uploaded_by' => $this->userA->id,
            'status' => 'Pending',
            'file_path' => 'documents/procurement88.pdf',
        ]);
    }

    /**
     * Test 1: Brand-new normal user sees ZERO existing documents and ZERO tracking records,
     * even though they share the same office as the creator.
     */
    public function test_newly_created_user_sees_zero_documents_and_zero_tracking(): void
    {
        $sessionB = [
            'authenticated' => true,
            'user_id' => $this->userB->id,
            'user_role' => $this->userB->role,
            'user_name' => $this->userB->name,
            'office_id' => $this->userB->office_id,
        ];

        // 1. Documents list (/documents)
        $docResponse = $this->actingAs($this->userB)->withSession($sessionB)->get(route('documents.index'));
        $docResponse->assertStatus(200);
        $docResponse->assertDontSee('Operating Budget Plan 2026');
        $docResponse->assertDontSee('Procurement Request 88');
        $docResponse->assertDontSee('DOC-FIN-001');
        $docResponse->assertDontSee('DOC-FIN-002');

        // 2. Tracking list (/track)
        $trackResponse = $this->actingAs($this->userB)->withSession($sessionB)->get(route('track.index'));
        $trackResponse->assertStatus(200);
        $trackResponse->assertDontSee('Operating Budget Plan 2026');
        $trackResponse->assertDontSee('Procurement Request 88');
        $trackResponse->assertDontSee('DOC-FIN-001');
        $trackResponse->assertDontSee('DOC-FIN-002');
    }

    /**
     * Test 2: Direct URL / API attempts by unauthorized user are denied with 403 Access Restricted.
     */
    public function test_user_cannot_access_unrelated_documents_via_direct_url_or_api(): void
    {
        $sessionB = [
            'authenticated' => true,
            'user_id' => $this->userB->id,
            'user_role' => $this->userB->role,
            'user_name' => $this->userB->name,
            'office_id' => $this->userB->office_id,
        ];

        // 1. Direct document view
        $viewRes = $this->actingAs($this->userB)->withSession($sessionB)->get('/documents/' . $this->doc1->id);
        $viewRes->assertStatus(403);
        $viewRes->assertSee('Access Restricted');
        $viewRes->assertSee('You are not authorized to view this document.');

        // 2. Direct tracking view
        $trackRes = $this->actingAs($this->userB)->withSession($sessionB)->get('/track/' . $this->doc1->id);
        $trackRes->assertStatus(403);
        $trackRes->assertSee('Access Restricted');
        $trackRes->assertSee('You are not authorized to view the tracking information for this document.');

        // 3. Direct document download
        $dlRes = $this->actingAs($this->userB)->withSession($sessionB)->get('/documents/' . $this->doc1->id . '/download');
        $dlRes->assertStatus(403);
        $dlRes->assertSee('Access Restricted');
        $dlRes->assertSee('You are not authorized to download this document.');

        // 4. Direct status API
        $statusRes = $this->actingAs($this->userB)->withSession($sessionB)->get('/api/documents/' . $this->doc1->id . '/status');
        $statusRes->assertStatus(403);

        // 5. QR Scan
        $qrRes = $this->actingAs($this->userB)->withSession($sessionB)->post('/scan-qr/process', [
            'qr_data' => $this->doc1->qr_code,
        ]);
        $qrRes->assertStatus(403);
        $qrRes->assertJson([
            'success' => false,
            'unauthorized' => true,
            'title' => 'Access Restricted',
        ]);
    }

    /**
     * Test 3: Routing a document to User B legitimately grants access in Documents and Tracking.
     */
    public function test_routed_document_becomes_visible_to_recipient_in_documents_and_tracking(): void
    {
        $sessionB = [
            'authenticated' => true,
            'user_id' => $this->userB->id,
            'user_role' => $this->userB->role,
            'user_name' => $this->userB->name,
            'office_id' => $this->userB->office_id,
        ];

        // Legitimate routing: User A routes doc1 to User B
        $this->doc1->update([
            'current_office_id' => $this->userB->office_id,
            'receiver_user_id' => $this->userB->id,
            'status' => 'In Transit',
        ]);
        DocumentRouting::create([
            'document_id' => $this->doc1->id,
            'from_office_id' => $this->officeA->id,
            'to_office_id' => $this->officeA->id,
            'sender_user_id' => $this->userA->id,
            'receiver_user_id' => $this->userB->id,
            'status' => 'In Transit',
            'sort_order' => 1,
        ]);

        // User B can now see doc1 in Documents list
        $docRes = $this->actingAs($this->userB)->withSession($sessionB)->get(route('documents.index'));
        $docRes->assertStatus(200);
        $docRes->assertSee('Operating Budget Plan 2026');
        // Still cannot see doc2!
        $docRes->assertDontSee('Procurement Request 88');

        // User B can now see doc1 in Tracking list
        $trackRes = $this->actingAs($this->userB)->withSession($sessionB)->get(route('track.index'));
        $trackRes->assertStatus(200);
        $trackRes->assertSee('Operating Budget Plan 2026');
        $trackRes->assertDontSee('Procurement Request 88');

        // User B can view doc1 details
        $detailRes = $this->actingAs($this->userB)->withSession($sessionB)->get('/documents/' . $this->doc1->id);
        $detailRes->assertStatus(200);
        $detailRes->assertSee('Operating Budget Plan 2026');

        // User B can view doc1 tracking detail
        $trackDetailRes = $this->actingAs($this->userB)->withSession($sessionB)->get('/track/' . $this->doc1->id);
        $trackDetailRes->assertStatus(200);
        $trackDetailRes->assertSee('Operating Budget Plan 2026');
    }

    /**
     * Test 4: Unrelated User C remains blocked from viewing or tracking the document.
     */
    public function test_unrelated_user_c_still_cannot_see_routed_document_or_tracking(): void
    {
        $sessionC = [
            'authenticated' => true,
            'user_id' => $this->userC->id,
            'user_role' => $this->userC->role,
            'user_name' => $this->userC->name,
            'office_id' => $this->userC->office_id,
        ];

        // doc1 is routed between User A and User B
        $this->doc1->update([
            'current_office_id' => $this->userB->office_id,
            'receiver_user_id' => $this->userB->id,
            'status' => 'In Transit',
        ]);
        DocumentRouting::create([
            'document_id' => $this->doc1->id,
            'from_office_id' => $this->officeA->id,
            'to_office_id' => $this->officeA->id,
            'sender_user_id' => $this->userA->id,
            'receiver_user_id' => $this->userB->id,
            'status' => 'In Transit',
            'sort_order' => 1,
        ]);

        // User C cannot see doc1 in Documents
        $docRes = $this->actingAs($this->userC)->withSession($sessionC)->get(route('documents.index'));
        $docRes->assertDontSee('Operating Budget Plan 2026');

        // User C cannot see doc1 in Tracking
        $trackRes = $this->actingAs($this->userC)->withSession($sessionC)->get(route('track.index'));
        $trackRes->assertDontSee('Operating Budget Plan 2026');

        // User C direct access blocked
        $trackDetailRes = $this->actingAs($this->userC)->withSession($sessionC)->get('/track/' . $this->doc1->id);
        $trackDetailRes->assertStatus(403);
        $trackDetailRes->assertSee('Access Restricted');
    }

    /**
     * Test 5: Administrator retains system-wide visibility.
     */
    public function test_admin_retains_system_wide_documents_and_tracking_access(): void
    {
        $sessionAdmin = [
            'authenticated' => true,
            'user_id' => $this->adminUser->id,
            'user_role' => $this->adminUser->role,
            'user_name' => $this->adminUser->name,
            'office_id' => $this->adminUser->office_id,
        ];

        // Admin sees both documents in /documents
        $docRes = $this->actingAs($this->adminUser)->withSession($sessionAdmin)->get(route('documents.index'));
        $docRes->assertStatus(200);
        $docRes->assertSee('Operating Budget Plan 2026');
        $docRes->assertSee('Procurement Request 88');

        // Admin sees both documents in /track
        $trackRes = $this->actingAs($this->adminUser)->withSession($sessionAdmin)->get(route('track.index'));
        $trackRes->assertStatus(200);
        $trackRes->assertSee('Operating Budget Plan 2026');
        $trackRes->assertSee('Procurement Request 88');
    }
}
