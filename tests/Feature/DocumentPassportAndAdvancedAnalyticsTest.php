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
use Illuminate\Support\Facades\Notification;

class DocumentPassportAndAdvancedAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $staffReceiver;
    protected $unauthorizedStaff;
    protected $originOffice;
    protected $middleOffice;
    protected $targetOffice;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();

        $this->originOffice = Office::create([
            'name'       => 'Office of the President',
            'department' => 'OP',
        ]);
        $this->middleOffice = Office::create([
            'name'       => 'Human Resources',
            'department' => 'HR',
        ]);
        $this->targetOffice = Office::create([
            'name'       => 'Academic Affairs',
            'department' => 'VPAA',
        ]);

        $this->admin = User::create([
            'name'                  => 'System Administrator',
            'username'              => 'sysadmin',
            'email'                 => 'admin@naap.org',
            'password'              => bcrypt('Secret123!'),
            'role'                  => 'Administrator',
            'office_id'             => $this->originOffice->id,
            'is_active'             => true,
            'needs_password_change' => false,
        ]);

        $this->staffReceiver = User::create([
            'name'                  => 'Dean of Academics',
            'username'              => 'dean_acad',
            'email'                 => 'dean@naap.org',
            'password'              => bcrypt('Secret123!'),
            'role'                  => 'Staff',
            'office_id'             => $this->targetOffice->id,
            'signature'             => 'signatures/dean.png',
            'is_active'             => true,
            'needs_password_change' => false,
        ]);

        $this->unauthorizedStaff = User::create([
            'name'                  => 'Unrelated Staff',
            'username'              => 'unrelated',
            'email'                 => 'unrelated@naap.org',
            'password'              => bcrypt('Secret123!'),
            'role'                  => 'Staff',
            'office_id'             => $this->middleOffice->id,
            'is_active'             => true,
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
     * Test 1: NAAP Document Passport Identity, Journey & Access Control.
     */
    public function test_document_passport_displays_real_identity_and_journey(): void
    {
        $doc = Document::create([
            'title'                 => 'Aviation Safety Audit Checklist 2026',
            'description'           => 'Mandatory inspection checklist for hangar facilities',
            'tracking_number'       => 'TRK-20260930-SAFE01',
            'category'              => 'Safety Compliance',
            'origin_office_id'      => $this->originOffice->id,
            'current_office_id'     => $this->targetOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'receiver_user_id'      => $this->staffReceiver->id,
            'uploaded_by'           => $this->admin->id,
            'sla'                   => 'Complex Transaction (7 Working Days)',
            'priority'              => 'Normal',
            'status'                => 'Received',
            'due_date'              => now()->addDays(7),
            'created_at'            => now()->subHours(10),
            'received_at'           => now()->subHours(2),
        ]);

        // Add routing step
        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->originOffice->id,
            'to_office_id'     => $this->targetOffice->id,
            'sender_user_id'   => $this->admin->id,
            'receiver_user_id' => $this->staffReceiver->id,
            'status'           => 'Received',
            'sort_order'       => 1,
            'pending_at'       => now()->subHours(10),
            'received_at'      => now()->subHours(2),
            'notes'            => 'Immediate hangar review required',
        ]);

        // Admin can view Document Passport
        $adminPassport = $this->actingAsAdmin()->get(route('documents.passport', $doc->id));
        $adminPassport->assertStatus(200);
        $adminPassport->assertSee('Aviation Safety Audit Checklist 2026');
        $adminPassport->assertSee('TRK-20260930-SAFE01');
        $adminPassport->assertSee('Office of the President');
        $adminPassport->assertSee('Academic Affairs');
        $adminPassport->assertSee('Dean of Academics');
        $adminPassport->assertSee('Document Registered & Uploaded');
        $adminPassport->assertSee('Physical Document Received');

        // Legitimate staff recipient can view Document Passport
        $staffPassport = $this->actingAsStaff($this->staffReceiver)->get(route('documents.passport', $doc->id));
        $staffPassport->assertStatus(200);
        $staffPassport->assertSee('TRK-20260930-SAFE01');

        // Unauthorized staff cannot view Document Passport
        $unauthPassport = $this->actingAsStaff($this->unauthorizedStaff)->get(route('documents.passport', $doc->id));
        $unauthPassport->assertStatus(403);
    }

    /**
     * Test 2: NAAP Document Completion Record (Internal Certificate of Traceability) on terminal completion.
     */
    public function test_document_passport_completion_record_on_completed_document(): void
    {
        $doc = Document::create([
            'title'                 => 'Flight Instructor Certification Approval',
            'description'           => 'Endorsement of senior flight instructor qualifications',
            'tracking_number'       => 'TRK-20260930-CERT88',
            'category'              => 'Accreditation',
            'origin_office_id'      => $this->originOffice->id,
            'current_office_id'     => $this->targetOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'receiver_user_id'      => $this->staffReceiver->id,
            'uploaded_by'           => $this->admin->id,
            'sla'                   => 'Simple Transaction (3 Working Days)',
            'priority'              => 'High',
            'status'                => 'Completed',
            'due_date'              => now()->addDays(3),
            'created_at'            => now()->subDays(2),
            'received_at'           => now()->subDay(),
            'processed_at'          => now()->subHours(4),
            'completed_at'          => now()->subHour(),
        ]);

        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->originOffice->id,
            'to_office_id'     => $this->targetOffice->id,
            'sender_user_id'   => $this->admin->id,
            'receiver_user_id' => $this->staffReceiver->id,
            'status'           => 'Completed',
            'sort_order'       => 1,
            'pending_at'       => now()->subDays(2),
            'received_at'      => now()->subDay(),
            'released_at'      => now()->subHour(),
            'signed_by'        => 'Dean of Academics',
            'notes'            => 'All flight instructor endorsements verified and approved',
        ]);

        $passportResp = $this->actingAsAdmin()->get(route('documents.passport', $doc->id));
        $passportResp->assertStatus(200);

        // Assert Completion Record Certificate
        $passportResp->assertSee('NAAP Document Completion Record');
        $passportResp->assertSee('NAAP-PASSPORT-TRK-20260930-CERT88');
        $passportResp->assertSee('Verified Internal Traceability Certificate');
        $passportResp->assertSee('Dean of Academics');
        $passportResp->assertSee('Academic Affairs');
    }

    /**
     * Test 3: Route Deviations & Exceptions Detection.
     */
    public function test_route_deviations_and_reversal_detection(): void
    {
        $doc = Document::create([
            'title'                 => 'Avionics Lab Budget Request',
            'description'           => 'Budget allocation for upgraded flight simulator avionics',
            'tracking_number'       => 'TRK-20260930-AVIO77',
            'category'              => 'Finance',
            'origin_office_id'      => $this->originOffice->id,
            'current_office_id'     => $this->middleOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'receiver_user_id'      => $this->unauthorizedStaff->id,
            'uploaded_by'           => $this->admin->id,
            'status'                => 'Reverted',
            'created_at'            => now()->subDays(3),
        ]);

        // Hop 1: Normal
        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->originOffice->id,
            'to_office_id'     => $this->middleOffice->id,
            'status'           => 'Completed',
            'sort_order'       => 1,
            'released_at'      => now()->subDays(2),
        ]);

        // Hop 2: Reverted back for revision
        DocumentRouting::create([
            'document_id'      => $doc->id,
            'from_office_id'   => $this->middleOffice->id,
            'to_office_id'     => $this->middleOffice->id,
            'status'           => 'Reverted',
            'sort_order'       => 2,
            'released_at'      => now()->subDay(),
            'notes'            => 'Itemized cost table missing supplier quotation',
        ]);

        $passportResp = $this->actingAsAdmin()->get(route('documents.passport', $doc->id));
        $passportResp->assertStatus(200);
        $passportResp->assertSee('Route Exceptions');
        $passportResp->assertSee('Revision Reversal');
        $passportResp->assertSee('Itemized cost table missing supplier quotation');

        // Dashboard analytics must reflect Route Exceptions
        $dashResp = $this->actingAsAdmin()->get(route('dashboard'));
        $dashResp->assertStatus(200);
        $dashResp->assertSee('Route Exceptions');
        $dashResp->assertSee('Avionics Lab Budget Request');
    }

    /**
     * Test 4: Office Bottleneck Intelligence in Dashboard.
     */
    public function test_office_bottleneck_intelligence_and_drilldown(): void
    {
        // Create 2 pending documents in targetOffice and 1 overdue
        Document::create([
            'title'             => 'Pending Document 1',
            'tracking_number'   => 'TRK-PENDING-01',
            'origin_office_id'  => $this->originOffice->id,
            'current_office_id' => $this->targetOffice->id,
            'uploaded_by'       => $this->admin->id,
            'status'            => 'Pending',
            'due_date'          => now()->subDays(2), // Overdue
        ]);

        Document::create([
            'title'             => 'Pending Document 2',
            'tracking_number'   => 'TRK-PENDING-02',
            'origin_office_id'  => $this->originOffice->id,
            'current_office_id' => $this->targetOffice->id,
            'uploaded_by'       => $this->admin->id,
            'status'            => 'Pending',
            'due_date'          => now()->addDays(3),
        ]);

        $dashResp = $this->actingAsAdmin()->get(route('dashboard'));
        $dashResp->assertStatus(200);
        $dashResp->assertSee('Office Bottleneck');
        $dashResp->assertSee('Academic Affairs');

        // Drill-down filter to target office in documents module
        $drillResp = $this->actingAsAdmin()->get(route('documents.index', ['office_id' => $this->targetOffice->id]));
        $drillResp->assertStatus(200);
        $drillResp->assertSee('Pending Document 1');
        $drillResp->assertSee('Pending Document 2');
    }
}
