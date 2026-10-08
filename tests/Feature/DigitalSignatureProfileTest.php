<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Department;

class DigitalSignatureProfileTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $dept;
    protected $office;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dept = Department::create(['name' => 'Administration', 'status' => 'active']);
        $this->office = Office::create(['name' => 'Office of the President', 'department' => 'Administration']);

        $this->user = User::create([
            'name' => 'Gladys Marmol',
            'username' => 'gladys_test',
            'email' => 'gladys_test@naap.org',
            'password' => bcrypt('Password123!'),
            'role' => 'ADMIN',
            'department_id' => $this->dept->id,
            'office_id' => $this->office->id,
            'position' => 'Administrator',
            'signature' => null,
            'needs_password_change' => false,
        ]);
    }

    public function test_profile_displays_empty_state_when_user_has_no_signature()
    {
        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->user->id,
            'user_role' => $this->user->role,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ])->get(route('profile'));

        $response->assertStatus(200);
        $response->assertSee('Current Active Signature', false);
        $response->assertSee('No digital signature uploaded', false);
        $response->assertSee('Upload a signature to use it during electronic document routing and approvals.', false);
        $response->assertSee('Add Signature', false);
        $response->assertSee('Not Configured', false);
        $response->assertDontSee('Signature Configured', false);
        $response->assertDontSee('Saved signature on file.', false);
    }

    public function test_user_can_upload_valid_signature_image_file()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('my_signature.png', 400, 150);

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->user->id,
            'user_role' => $this->user->role,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ])->post(route('profile.signature'), [
            'sig_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertNotNull($this->user->signature);
        $this->assertTrue(Storage::disk('public')->exists($this->user->signature));
        $this->assertTrue($this->user->hasValidSignature());
    }

    public function test_user_can_save_drawn_canvas_signature()
    {
        Storage::fake('public');

        // Valid 1x1 transparent PNG data URI
        $base64Png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->user->id,
            'user_role' => $this->user->role,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ])->post(route('profile.signature'), [
            'signature_data' => $base64Png,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertNotNull($this->user->signature);
        $this->assertTrue(Storage::disk('public')->exists($this->user->signature));
        $this->assertTrue($this->user->hasValidSignature());
    }

    public function test_profile_displays_saved_signature_preview_when_configured()
    {
        Storage::fake('public');

        $fakeImagePath = 'signatures/sig_test12345.png';
        Storage::disk('public')->put($fakeImagePath, 'fake-image-binary-data');

        $this->user->update(['signature' => $fakeImagePath]);

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->user->id,
            'user_role' => $this->user->role,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ])->get(route('profile'));

        $response->assertStatus(200);
        $response->assertSee('Current Active Signature', false);
        $response->assertSee('Signature Configured', false);
        $response->assertSee('Saved signature on file. Applied automatically when completing workflow actions.', false);
        $response->assertSee('Replace Signature', false);
        $response->assertSee($this->user->signature_url, false);
        $response->assertDontSee('No digital signature uploaded', false);
    }

    public function test_profile_reverts_to_clean_empty_state_if_signature_file_missing_on_disk()
    {
        Storage::fake('public');

        // Signature path set in DB but not on disk
        $this->user->update(['signature' => 'signatures/deleted_sig.png']);

        $this->assertFalse($this->user->hasValidSignature());

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->user->id,
            'user_role' => $this->user->role,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ])->get(route('profile'));

        $response->assertStatus(200);
        $response->assertSee('No digital signature uploaded', false);
        $response->assertSee('Add Signature', false);
        $response->assertSee('Not Configured', false);
        $response->assertDontSee('Signature Configured', false);
    }

    public function test_rejects_non_image_or_invalid_executable_upload()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('malicious.php', 10, 'text/x-php');

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->user->id,
            'user_role' => $this->user->role,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ])->post(route('profile.signature'), [
            'sig_file' => $file,
        ]);

        $response->assertSessionHasErrors(['sig_file']);

        $this->user->refresh();
        $this->assertNull($this->user->signature);
    }
}
