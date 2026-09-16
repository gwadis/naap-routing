<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Document;
use App\Models\DocumentRouting;

class QRLookupTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $document;

    protected function setUp(): void
    {
        parent::setUp();

        $office = Office::create(['name' => 'HR Office', 'department' => 'HR']);
        $this->user = User::create([
            'name' => 'HR User',
            'username' => 'hr_user_test',
            'email' => 'hr_test@naap.edu',
            'password' => bcrypt('password'),
            'role' => 'USER',
            'office_id' => $office->id,
            'status' => 'Active',
            'email_verified_at' => now(),
        ]);

        $this->document = Document::create([
            'title' => 'Sample Routing Doc',
            'description' => 'Test document',
            'type' => 'PDF',
            'priority' => 'Normal',
            'sla' => 'Standard',
            'origin_office_id' => $office->id,
            'current_office_id' => $office->id,
            'destination_office_id' => $office->id,
            'receiver_user_id' => $this->user->id,
            'uploaded_by' => $this->user->id,
            'file_path' => 'documents/test.pdf',
            'qr_code' => 'qr_codes/1.png',
            'status' => 'Pending',
        ]);

        DocumentRouting::create([
            'document_id' => $this->document->id,
            'from_office_id' => $office->id,
            'to_office_id' => $office->id,
            'receiver_user_id' => $this->user->id,
            'sender_user_id' => $this->user->id,
            'status' => 'Pending',
            'sort_order' => 1,
            'sla_hours' => 24,
            'sla_due_at' => now()->addHours(24),
            'sla_status' => 'on_time',
        ]);
    }

    public function test_qr_scan_finds_document_with_various_url_formats()
    {
        $urls = [
            "http://naaprouting_system.test/documents/" . $this->document->id,
            "https://naaprouting_system.test/track/" . $this->document->id,
            "http://naaprouting_system.test/Documents/" . $this->document->id,
            "http://naaprouting_system.test/Track/" . $this->document->id,
            "http://naaprouting_system.test/storage/qr_codes/" . $this->document->id . ".png",
            "qr_codes/" . $this->document->id . ".png",
            (string) $this->document->id
        ];

        foreach ($urls as $qrData) {
            $response = $this->withSession([
                'authenticated' => true,
                'user_id' => $this->user->id,
                'user_role' => 'USER',
                'user_name' => $this->user->name
            ])->post(route('qr.scan'), [
                'qr_data' => $qrData
            ]);

            $response->assertStatus(200);
            $response->assertJson([
                'success' => true,
                'redirect_url' => route('track.detail', $this->document->id),
                'document' => [
                    'id' => $this->document->id,
                    'title' => $this->document->title,
                ]
            ]);
        }
    }

    public function test_qr_scan_returns_404_for_invalid_qr_code()
    {
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->user->id,
            'user_role' => 'USER',
            'user_name' => $this->user->name
        ])->post(route('qr.scan'), [
            'qr_data' => 'invalid-qr-code-here'
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => true
        ]);
    }
}
