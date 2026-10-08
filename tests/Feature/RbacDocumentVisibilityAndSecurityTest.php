<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RbacDocumentVisibilityAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $adminOffice;
    protected $hrOffice;
    protected $accountingOffice;

    protected $adminUser;
    protected $officeHeadHr;
    protected $staffHr;
    protected $staffAccounting;

    protected $hrDoc;
    protected $accountingDoc;

    protected function setUp(): void
    {
        parent::setUp();

        // Offices
        $this->adminOffice = Office::create(['name' => 'Office of the President', 'department' => 'Administration']);
        $this->hrOffice = Office::create(['name' => 'Human Resources (HR)', 'department' => 'Administration']);
        $this->accountingOffice = Office::create(['name' => 'Accounting Office', 'department' => 'Administration']);

        // Users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Administrator',
            'office_id' => $this->adminOffice->id,
            'needs_password_change' => false,
        ]);

        $this->officeHeadHr = User::create([
            'name' => 'Shane Muesco',
            'username' => 'shanemuesco',
            'email' => 'shane@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Office Head',
            'position' => 'Office Head',
            'office_id' => $this->hrOffice->id,
            'needs_password_change' => false,
        ]);

        $this->staffHr = User::create([
            'name' => 'Staff HR User',
            'username' => 'staff.hr',
            'email' => 'staffhr@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Staff',
            'office_id' => $this->hrOffice->id,
            'needs_password_change' => false,
        ]);

        $this->staffAccounting = User::create([
            'name' => 'Ian Alba',
            'username' => 'ian.alba',
            'email' => 'ian@naap.org',
            'password' => bcrypt('Secret123!'),
            'role' => 'Staff',
            'office_id' => $this->accountingOffice->id,
            'needs_password_change' => false,
        ]);

        // Documents
        // 1. HR Document (Related to Shane's office)
        $this->hrDoc = Document::create([
            'title' => 'HR Policy Guidelines',
            'tracking_number' => 'DOC-HR-001',
            'uploaded_by' => $this->officeHeadHr->id,
            'current_office_id' => $this->hrOffice->id,
            'origin_office_id' => $this->hrOffice->id,
            'destination_office_id' => $this->hrOffice->id,
            'status' => 'Pending',
            'priority' => 'Normal',
            'category' => 'Policies',
        ]);

        // 2. Accounting Document (Unrelated to Shane - created by Ian Alba in Accounting Office)
        $this->accountingDoc = Document::create([
            'title' => 'Circa 2018 Financial Audit',
            'tracking_number' => 'DOC-ACC-001',
            'uploaded_by' => $this->staffAccounting->id,
            'current_office_id' => $this->accountingOffice->id,
            'origin_office_id' => $this->accountingOffice->id,
            'destination_office_id' => $this->accountingOffice->id,
            'status' => 'Pending',
            'priority' => 'Normal',
            'category' => 'Finance',
            'file_path' => 'documents/audit.pdf',
        ]);
    }

    public function test_office_head_sees_only_authorized_documents_in_my_documents()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHeadHr->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHeadHr->name,
            'office_id' => $this->officeHeadHr->office_id,
        ];

        $response = $this->actingAs($this->officeHeadHr)->withSession($session)->get('/documents');

        $response->assertStatus(200);
        $response->assertSee('HR Policy Guidelines');
        $response->assertDontSee('Circa 2018 Financial Audit');
    }

    public function test_office_head_sees_only_authorized_documents_in_recent_scope_qr_scanner()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHeadHr->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHeadHr->name,
            'office_id' => $this->officeHeadHr->office_id,
        ];

        $response = $this->actingAs($this->officeHeadHr)->withSession($session)->get('/scan-qr');

        $response->assertStatus(200);
        $response->assertSee('Recent Scope Documents');
        $response->assertSee('HR Policy Guidelines');
        $response->assertDontSee('Circa 2018 Financial Audit');
    }

    public function test_office_head_sees_only_authorized_documents_in_tracking()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHeadHr->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHeadHr->name,
            'office_id' => $this->officeHeadHr->office_id,
        ];

        $response = $this->actingAs($this->officeHeadHr)->withSession($session)->get('/track');

        $response->assertStatus(200);
        $response->assertSee('HR Policy Guidelines');
        $response->assertDontSee('Circa 2018 Financial Audit');
    }

    public function test_office_head_cannot_view_unauthorized_document_details()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHeadHr->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHeadHr->name,
            'office_id' => $this->officeHeadHr->office_id,
        ];

        $response = $this->actingAs($this->officeHeadHr)->withSession($session)->get('/documents/' . $this->accountingDoc->id);

        $response->assertStatus(403);
        $response->assertSee('Access Restricted');
        $response->assertSee('You are not authorized to view this document.');
        $response->assertSee('Go Back');
        $response->assertSee('Return to Dashboard');
        $response->assertDontSee('Circa 2018 Financial Audit');
    }

    public function test_office_head_cannot_view_unauthorized_document_tracking_details()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHeadHr->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHeadHr->name,
            'office_id' => $this->officeHeadHr->office_id,
        ];

        $response = $this->actingAs($this->officeHeadHr)->withSession($session)->get('/track/' . $this->accountingDoc->id);

        $response->assertStatus(403);
        $response->assertSee('Access Restricted');
        $response->assertSee('You are not authorized to view the tracking information for this document.');
        $response->assertSee('Return to Dashboard');
    }

    public function test_office_head_cannot_download_unauthorized_document()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHeadHr->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHeadHr->name,
            'office_id' => $this->officeHeadHr->office_id,
        ];

        $response = $this->actingAs($this->officeHeadHr)->withSession($session)->get('/documents/' . $this->accountingDoc->id . '/download');

        $response->assertStatus(403);
        $response->assertSee('Access Restricted');
        $response->assertSee('You are not authorized to download this document.');
    }

    public function test_qr_scan_of_unauthorized_document_returns_403_access_restricted()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHeadHr->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHeadHr->name,
            'office_id' => $this->officeHeadHr->office_id,
        ];

        $response = $this->actingAs($this->officeHeadHr)->withSession($session)->postJson('/scan-qr/process', [
            'qr_data' => $this->accountingDoc->tracking_number,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => true,
            'unauthorized' => true,
            'title' => 'Access Restricted',
            'message' => 'You are not authorized to access this document.',
        ]);
        $response->assertJsonMissing(['Circa 2018 Financial Audit']);
    }

    public function test_external_qr_scan_of_unauthorized_document_returns_403_access_restricted()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->officeHeadHr->id,
            'user_role' => 'Office Head',
            'user_name' => $this->officeHeadHr->name,
            'office_id' => $this->officeHeadHr->office_id,
        ];

        $response = $this->actingAs($this->officeHeadHr)->withSession($session)->get('/document/qr/' . $this->accountingDoc->tracking_number);

        $response->assertStatus(403);
        $response->assertSee('Access Restricted');
        $response->assertSee('You are not authorized to access this document.');
    }

    public function test_staff_cannot_access_unauthorized_documents_or_admin_pages()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->staffHr->id,
            'user_role' => 'Staff',
            'user_name' => $this->staffHr->name,
            'office_id' => $this->staffHr->office_id,
        ];

        // 1. Staff accessing admin activity page -> 403
        $adminRes = $this->actingAs($this->staffHr)->withSession($session)->get('/activity');
        $adminRes->assertStatus(403);
        $adminRes->assertSee('Access Restricted');
        $adminRes->assertSee('Administrator privileges are required to access this page.');

        // 2. Staff accessing unrelated document details -> 403
        $docRes = $this->actingAs($this->staffHr)->withSession($session)->get('/documents/' . $this->accountingDoc->id);
        $docRes->assertStatus(403);
        $docRes->assertSee('Access Restricted');
        $docRes->assertSee('You are not authorized to view this document.');

        // 3. Staff accessing unrelated download -> 403
        $dlRes = $this->actingAs($this->staffHr)->withSession($session)->get('/documents/' . $this->accountingDoc->id . '/download');
        $dlRes->assertStatus(403);
        $dlRes->assertSee('Access Restricted');
        $dlRes->assertSee('You are not authorized to download this document.');
    }

    public function test_admin_has_full_system_visibility()
    {
        $session = [
            'authenticated' => true,
            'user_id' => $this->adminUser->id,
            'user_role' => 'Administrator',
            'user_name' => $this->adminUser->name,
            'office_id' => $this->adminUser->office_id,
        ];

        // Admin sees both HR and Accounting documents in documents list
        $docRes = $this->actingAs($this->adminUser)->withSession($session)->get('/documents');
        $docRes->assertStatus(200);
        $docRes->assertSee('HR Policy Guidelines');
        $docRes->assertSee('Circa 2018 Financial Audit');

        // Admin can access full activity logs
        $actRes = $this->actingAs($this->adminUser)->withSession($session)->get('/activity');
        $actRes->assertStatus(200);
        $actRes->assertSee('Activity Logs');

        // Admin dashboard shows View All Logs
        $dashRes = $this->actingAs($this->adminUser)->withSession($session)->get('/dashboard');
        $dashRes->assertStatus(200);
        $dashRes->assertSee('View All Logs');
    }
}
