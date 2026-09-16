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
}
