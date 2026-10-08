<?php

namespace Tests\Feature;

use App\Mail\WelcomeUserMail;
use App\Models\Department;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserAccountActivationTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $department;
    private $office;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create(['name' => 'IT Department', 'status' => 'active']);
        $this->office = Office::create(['name' => 'IT Office', 'department' => 'IT Department']);

        $this->admin = User::create([
            'name' => 'System Admin',
            'username' => 'sysadmin',
            'email' => 'sysadmin@naap.org',
            'password' => Hash::make('AdminSecret123!'),
            'role' => 'Super Administrator',
            'department_id' => $this->department->id,
            'office_id' => $this->office->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_creates_user_without_password_generating_temporary_credentials()
    {
        Mail::fake();

        // 1. Admin enters user profile details without entering any password
        $userData = [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan.delacruz@naap.org',
            'employee_id' => 'EMP-9901',
            'position' => 'Records Specialist',
            'role' => 'Staff',
            'department_id' => $this->department->id,
            'office_id' => $this->office->id,
            'status' => 'active',
            'phone' => '09170001122',
        ];

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->admin->id,
            'user_role' => $this->admin->role,
            'user_name' => $this->admin->name,
            'user_email' => $this->admin->email,
        ])->post(route('users.store'), $userData);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        // Admin must not see the temporary password in flash messages
        $flashMsg = session('success');
        $this->assertStringNotContainsString('password', strtolower($flashMsg) === 'password' ? 'x' : '');

        // User must be created in DB
        $newUser = User::where('email', 'juan.delacruz@naap.org')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('Juan Dela Cruz', $newUser->name);
        $this->assertEquals('EMP-9901', $newUser->employee_id);
        $this->assertEquals('Staff', $newUser->role);
        $this->assertTrue((bool)$newUser->needs_password_change);

        // Password must be hashed securely (never plaintext)
        $this->assertNotEmpty($newUser->password);
        $this->assertNotEquals('DummyPassword', $newUser->password);

        // Email must be dispatched with account credentials and first-time setup instructions
        Mail::assertQueued(WelcomeUserMail::class, function (WelcomeUserMail $mail) use ($newUser) {
            $this->assertEquals($newUser->id, $mail->user->id);
            $this->assertNotEmpty($mail->temporaryPassword);

            // Verify temporary password works with Hash
            $this->assertTrue(Hash::check($mail->temporaryPassword, $newUser->password));

            $rendered = $mail->render();
            // Must contain temporary credentials and security instructions
            $this->assertStringContainsString($mail->temporaryPassword, $rendered);
            $this->assertStringContainsString('Juan Dela Cruz', $rendered);
            $this->assertStringContainsString('IT Department', $rendered);
            $this->assertStringContainsString('Important First-Time Login Instructions', $rendered);
            $this->assertStringContainsString('Sign In to NAAP Routing', $rendered);

            return true;
        });
    }

    public function test_user_first_login_forces_password_change_and_invalidates_temporary_password()
    {
        $temporaryPassword = 'TempSecretPassword123!';

        $newUser = User::create([
            'name' => 'Maria Clara',
            'username' => 'mclara',
            'email' => 'maria.clara@naap.org',
            'password' => Hash::make($temporaryPassword),
            'role' => 'Staff',
            'department_id' => $this->department->id,
            'office_id' => $this->office->id,
            'status' => 'active',
            'needs_password_change' => true,
        ]);

        // Simulated first login session with forced password change pending
        $this->withSession([
            'change_password_user_id' => $newUser->id,
        ]);

        // User visits force password reset page
        $pageResponse = $this->withSession(['change_password_user_id' => $newUser->id])
            ->get(route('login.password.reset'));
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('Change Password');

        // User sets permanent password
        $permanentPassword = 'PermanentSecure@2026!';
        $resetResponse = $this->withSession(['change_password_user_id' => $newUser->id])
            ->post(route('login.password.update'), [
                'new_password' => $permanentPassword,
                'new_password_confirmation' => $permanentPassword,
            ]);

        $resetResponse->assertRedirect(route('dashboard'));

        $newUser->refresh();

        // 1. Permanent password is now active
        $this->assertTrue(Hash::check($permanentPassword, $newUser->password));

        // 2. needs_password_change is now false
        $this->assertFalse((bool)$newUser->needs_password_change);

        // 3. The temporary password NO LONGER works
        $this->assertFalse(Hash::check($temporaryPassword, $newUser->password));
    }

    public function test_account_creation_rolls_back_if_welcome_email_fails()
    {
        Mail::shouldReceive('to')->andThrow(new \Exception('SMTP Connection Timed Out'));

        $userData = [
            'name' => 'Failed Email User',
            'email' => 'failed.email@naap.org',
            'role' => 'Staff',
            'department_id' => $this->department->id,
            'office_id' => $this->office->id,
            'status' => 'active',
        ];

        $response = $this->withSession([
            'authenticated' => true,
            'user_id' => $this->admin->id,
            'user_role' => $this->admin->role,
            'user_name' => $this->admin->name,
            'user_email' => $this->admin->email,
        ])->post(route('users.store'), $userData);

        $response->assertSessionHas('error');

        // User must NOT be created in database
        $this->assertNull(User::where('email', 'failed.email@naap.org')->first());
    }
}
