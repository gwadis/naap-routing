<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;

class UploadDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_document_upload_requires_authentication()
    {
        $response = $this->post(route('documents.store'), []);
        $response->assertRedirect(route('home'));
    }

    public function test_successful_document_upload_with_routing_sequence()
    {
        // 1. Setup offices and users
        $originOffice = Office::create(['name' => 'HR Office', 'department' => 'HR']);
        $stepOffice = Office::create(['name' => 'VPAA Office', 'department' => 'VPAA']);
        $finalOffice = Office::create(['name' => 'Finance Office', 'department' => 'Finance']);

        $uploader = User::create([
            'name' => 'Admin User',
            'username' => 'admin_test',
            'email' => 'admin_test@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'needs_password_change' => false,
            'office_id' => $originOffice->id,
        ]);

        $stepUser = User::create([
            'name' => 'VPAA Staff',
            'username' => 'vpaa_staff',
            'email' => 'vpaa_staff@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'VPAA',
            'office_id' => $stepOffice->id,
        ]);

        $finalUser = User::create([
            'name' => 'Finance Staff',
            'username' => 'finance_staff',
            'email' => 'finance_staff@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Finance',
            'office_id' => $finalOffice->id,
        ]);

        // 2. Perform authenticated request
        $mockFile = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $uploader->id,
            'user_role' => 'ADMIN',
            'user_name' => $uploader->name
        ])->post(route('documents.store'), [
            'title' => 'Test Document Title',
            'priority' => 'Normal',
            'slate' => 'Standard', // Wait, was it sla or slate? Let's check: it is sla!
            'sla' => 'Standard',
            'category' => 'Operational Plans',
            'description' => 'Test document description',
            'origin_office_id' => $originOffice->id,
            'destination_office_id' => $finalOffice->id,
            'final_office_id' => $finalOffice->id,
            'final_receiver_id' => $finalUser->id,
            'routing_office_ids' => [$stepOffice->id, $finalOffice->id],
            'routing_user_ids' => [$stepUser->id, $finalUser->id],
            'routing_approval_types' => ['sequential', 'sequential'],
            'routing_signatures_required' => [1, 1],
            'file' => $mockFile
        ]);

        // 3. Assert response redirection and DB status
        $response->assertRedirect(route('documents.index'));
        $this->assertDatabaseHas('documents', [
            'title' => 'Test Document Title',
            'uploaded_by' => $uploader->id,
            'origin_office_id' => $originOffice->id,
            'destination_office_id' => $finalOffice->id,
        ]);

        // Assert file storage
        $doc = Document::first();
        Storage::disk('public')->assertExists($doc->file_path);
    }

    public function test_duplicate_file_upload_returns_409_conflict()
    {
        $originOffice = Office::create(['name' => 'HR Office', 'department' => 'HR']);
        $finalOffice = Office::create(['name' => 'Finance Office', 'department' => 'Finance']);

        $uploader = User::create([
            'name' => 'Admin User',
            'username' => 'admin_test',
            'email' => 'admin_test@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'needs_password_change' => false,
            'office_id' => $originOffice->id,
        ]);

        $finalUser = User::create([
            'name' => 'Finance Staff',
            'username' => 'finance_staff',
            'email' => 'finance_staff@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'Finance',
            'office_id' => $finalOffice->id,
        ]);

        // Create initial document
        $mockFile = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');
        
        $sessionData = [
            'authenticated' => true,
            'user_id' => $uploader->id,
            'user_role' => 'ADMIN',
            'user_name' => $uploader->name
        ];

        // 1st Upload
        $this->withSession($sessionData)->post(route('documents.store'), [
            'title' => 'First Doc',
            'priority' => 'Normal',
            'sla' => 'Standard',
            'category' => 'Operational Plans',
            'description' => 'Test document description',
            'origin_office_id' => $originOffice->id,
            'destination_office_id' => $finalOffice->id,
            'final_office_id' => $finalOffice->id,
            'final_receiver_id' => $finalUser->id,
            'routing_office_ids' => [$finalOffice->id],
            'routing_user_ids' => [$finalUser->id],
            'routing_approval_types' => ['sequential'],
            'routing_signatures_required' => [1],
            'file' => $mockFile
        ]);

        // 2nd Upload (exact duplicate of file)
        $response = $this->withSession($sessionData)->post(route('documents.store'), [
            'title' => 'Second Doc (Same File)',
            'priority' => 'Normal',
            'sla' => 'Standard',
            'category' => 'Operational Plans',
            'description' => 'Test document description',
            'origin_office_id' => $originOffice->id,
            'destination_office_id' => $finalOffice->id,
            'final_office_id' => $finalOffice->id,
            'final_receiver_id' => $finalUser->id,
            'routing_office_ids' => [$finalOffice->id],
            'routing_user_ids' => [$finalUser->id],
            'routing_approval_types' => ['sequential'],
            'routing_signatures_required' => [1],
            'file' => $mockFile
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        // Verify status code is 409 Conflict
        $response->assertStatus(409);
        $response->assertJson([
            'duplicate' => true
        ]);
    }

    public function test_normal_pdf_ajax_upload_success()
    {
        $originOffice = Office::create(['name' => 'Registrar Office', 'department' => 'Registrar']);
        $finalOffice = Office::create(['name' => 'Dean Office', 'department' => 'Dean']);

        $uploader = User::create([
            'name' => 'Registrar Staff',
            'username' => 'registrar_staff',
            'email' => 'registrar@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'STAFF',
            'needs_password_change' => false,
            'office_id' => $originOffice->id,
        ]);

        $finalUser = User::create([
            'name' => 'Dean Officer',
            'username' => 'dean_officer',
            'email' => 'dean@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'office_id' => $finalOffice->id,
        ]);

        $mockFile = UploadedFile::fake()->create('report_sample.pdf', 1200, 'application/pdf');

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $uploader->id,
            'user_role' => 'STAFF',
            'user_name' => $uploader->name
        ])->post(route('documents.store'), [
            'title' => 'Quarterly Academic Report',
            'priority' => 'High',
            'sla' => 'Standard',
            'category' => 'Reports',
            'description' => 'Comprehensive academic performance report for Q3.',
            'origin_office_id' => $originOffice->id,
            'final_office_id' => $finalOffice->id,
            'final_receiver_id' => $finalUser->id,
            'destination_office_id' => $finalOffice->id,
            'routing_office_ids' => [$finalOffice->id],
            'routing_user_ids' => [$finalUser->id],
            'routing_approval_types' => ['sequential'],
            'routing_signatures_required' => [1],
            'file' => $mockFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('documents', [
            'title' => 'Quarterly Academic Report',
            'is_confidential' => false,
            'destination_office_id' => $finalOffice->id,
            'type' => 'PDF',
        ]);

        $doc = Document::where('title', 'Quarterly Academic Report')->first();
        $this->assertNotNull($doc);
        Storage::disk('public')->assertExists($doc->file_path);
        $this->assertDatabaseHas('document_routings', [
            'document_id' => $doc->id,
            'from_office_id' => $originOffice->id,
            'to_office_id' => $finalOffice->id,
            'receiver_user_id' => $finalUser->id,
        ]);
    }

    public function test_confidential_document_upload_success()
    {
        $originOffice = Office::create(['name' => 'Legal Office', 'department' => 'Legal']);
        $finalOffice = Office::create(['name' => 'Executive Office', 'department' => 'Executive']);

        $uploader = User::create([
            'name' => 'Legal Officer',
            'username' => 'legal_officer',
            'email' => 'legal@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'office_id' => $originOffice->id,
        ]);

        $finalUser = User::create([
            'name' => 'Executive Director',
            'username' => 'exec_dir',
            'email' => 'exec@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'office_id' => $finalOffice->id,
        ]);

        $mockFile = UploadedFile::fake()->create('confidential_memo.pdf', 800, 'application/pdf');

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $uploader->id,
            'user_role' => 'ADMIN',
            'user_name' => $uploader->name
        ])->post(route('documents.store'), [
            'title' => 'Restricted Compliance Audit',
            'priority' => 'Urgent',
            'sla' => 'Critical',
            'category' => 'Legal & Compliance',
            'description' => 'Strictly confidential legal findings.',
            'origin_office_id' => $originOffice->id,
            'final_office_id' => $finalOffice->id,
            'final_receiver_id' => $finalUser->id,
            'destination_office_id' => $finalOffice->id,
            'routing_office_ids' => [$finalOffice->id],
            'routing_user_ids' => [$finalUser->id],
            'routing_approval_types' => ['sequential'],
            'routing_signatures_required' => [1],
            'is_confidential' => '1',
            'file' => $mockFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('documents', [
            'title' => 'Restricted Compliance Audit',
            'is_confidential' => true,
        ]);
    }

    public function test_arta_processing_time_category_upload_success()
    {
        $originOffice = Office::create(['name' => 'ARTA Origin Office', 'department' => 'ARTA Dept']);
        $finalOffice = Office::create(['name' => 'ARTA Dest Office', 'department' => 'ARTA Dept']);

        $uploader = User::create([
            'name' => 'ARTA Officer',
            'username' => 'arta_officer',
            'email' => 'arta@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'STAFF',
            'office_id' => $originOffice->id,
        ]);

        $finalUser = User::create([
            'name' => 'ARTA Receiver',
            'username' => 'arta_receiver',
            'email' => 'artareceiver@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'STAFF',
            'office_id' => $finalOffice->id,
        ]);

        $mockFile = UploadedFile::fake()->create('arta_doc.pdf', 300, 'application/pdf');

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $uploader->id,
            'user_role' => 'STAFF',
            'user_name' => $uploader->name
        ])->post(route('documents.store'), [
            'title' => 'Simple ARTA Request',
            'priority' => 'High',
            'sla' => 'Simple Transaction (3 Working Days)',
            'category' => 'Operational Plans',
            'description' => 'Processing under ARTA 3 working days guideline.',
            'origin_office_id' => $originOffice->id,
            'final_office_id' => $finalOffice->id,
            'final_receiver_id' => $finalUser->id,
            'destination_office_id' => $finalOffice->id,
            'routing_office_ids' => [$finalOffice->id],
            'routing_user_ids' => [$finalUser->id],
            'routing_approval_types' => ['sequential'],
            'routing_signatures_required' => [1],
            'file' => $mockFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('documents', [
            'title' => 'Simple ARTA Request',
            'sla' => 'Simple Transaction (3 Working Days)',
        ]);
    }
}

