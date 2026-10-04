<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class EnterpriseQRInteroperabilityTest extends TestCase
{
    use RefreshDatabase;

    private $originOffice;
    private $destOffice;
    private $creatorUser;
    private $receiverUser;
    private $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originOffice = Office::create(['name' => 'Registrar Office', 'department' => 'Academics']);
        $this->destOffice = Office::create(['name' => 'Dean Office', 'department' => 'Academics']);

        $this->creatorUser = User::create([
            'name' => 'Prof. Author',
            'username' => 'prof_author',
            'email' => 'author@naap.edu',
            'password' => bcrypt('password'),
            'role' => 'FACULTY',
            'office_id' => $this->originOffice->id,
            'status' => 'Active',
            'email_verified_at' => now(),
        ]);

        $this->receiverUser = User::create([
            'name' => 'Dean Receiver',
            'username' => 'dean_receiver',
            'email' => 'dean@naap.edu',
            'password' => bcrypt('password'),
            'role' => 'DEAN',
            'office_id' => $this->destOffice->id,
            'status' => 'Active',
            'email_verified_at' => now(),
        ]);

        $this->document = Document::create([
            'title' => 'Faculty Tenure Review',
            'description' => 'Tenure review documentation',
            'type' => 'PDF',
            'priority' => 'High',
            'sla' => 'Standard',
            'tracking_number' => 'NAAP-2026-TRK001',
            'origin_office_id' => $this->originOffice->id,
            'current_office_id' => $this->destOffice->id,
            'destination_office_id' => $this->destOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'uploaded_by' => $this->creatorUser->id,
            'status' => 'Pending',
            'is_confidential' => false,
        ]);

        DocumentRouting::create([
            'document_id' => $this->document->id,
            'from_office_id' => $this->originOffice->id,
            'to_office_id' => $this->destOffice->id,
            'receiver_user_id' => $this->receiverUser->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'signature_required' => false,
        ]);
    }

    private function loginUser(User $user)
    {
        return $this->withSession([
            'authenticated' => true,
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_name' => $user->name,
        ])->actingAs($user);
    }

    /**
     * Test: Document generates standard external-compatible HTTPS QR URL.
     */
    public function test_document_generates_standard_external_https_qr_url()
    {
        $payloadUrl = $this->document->getQrPayloadUrl();

        $this->assertStringStartsWith('https://', $payloadUrl);
        $this->assertStringContainsString('/document/qr/NAAP-2026-TRK001', $payloadUrl);
        $this->assertStringNotContainsString('javascript:', $payloadUrl);
        $this->assertStringNotContainsString('localhost', $payloadUrl);
        $this->assertStringNotContainsString('127.0.0.1', $payloadUrl);
    }

    /**
     * Test: External phone scanner visiting HTTPS QR URL directly resolves document.
     */
    public function test_external_phone_qr_scanner_resolves_document_via_https_url()
    {
        $response = $this->loginUser($this->receiverUser)
            ->get(route('qr.external', ['code' => 'NAAP-2026-TRK001']));

        $response->assertRedirect(route('documents.show', $this->document->id));
        $this->assertTrue(session('qr_verified_' . $this->document->id));

        // Routing step scanned_at should now be marked
        $routing = DocumentRouting::where('document_id', $this->document->id)->first();
        $this->assertNotNull($routing->scanned_at);

        // Activity log recorded
        $this->assertDatabaseHas('activity_logs', [
            'document_id' => $this->document->id,
            'action' => 'QR Code Scanned',
        ]);
    }

    /**
     * Test: External phone scanner scanning by numeric ID also resolves securely.
     */
    public function test_external_phone_qr_scanner_resolves_by_id()
    {
        $response = $this->loginUser($this->creatorUser)
            ->get(route('qr.external', ['code' => (string) $this->document->id]));

        $response->assertRedirect(route('documents.show', $this->document->id));
        $this->assertTrue(session('qr_verified_' . $this->document->id));
    }

    /**
     * Test: External scan on confidential document prompts for PIN verification.
     */
    public function test_external_scanner_confidential_document_redirects_to_pin_prompt()
    {
        $this->document->update(['is_confidential' => true]);

        // Receiver scans external QR -> should redirect to PIN prompt, not leak details directly
        $response = $this->loginUser($this->receiverUser)
            ->get(route('qr.external', ['code' => 'NAAP-2026-TRK001']));

        $response->assertRedirect(route('qr.index', ['document_id' => $this->document->id]));
    }

    /**
     * Test: Internal scanner decoding standard HTTPS URL resolves the document properly.
     */
    public function test_internal_scanner_decodes_full_https_url_with_tracking_number()
    {
        $fullQrUrl = 'https://naaprouting.edu/document/qr/NAAP-2026-TRK001';

        $response = $this->loginUser($this->receiverUser)
            ->postJson(route('qr.scan'), [
                'qr_data' => $fullQrUrl,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'document' => [
                'id' => $this->document->id,
                'title' => 'Faculty Tenure Review',
            ]
        ]);
    }

    /**
     * Test: Unauthenticated user scanning QR is redirected to login, preserving intended destination.
     */
    public function test_unauthenticated_external_scan_saves_intended_url_and_redirects_to_login()
    {
        $response = $this->get(route('qr.external', ['code' => 'NAAP-2026-TRK001']));

        $response->assertRedirect(route('home'));
        $this->assertEquals(route('qr.external', ['code' => 'NAAP-2026-TRK001']), session('url.intended'));
    }
}
