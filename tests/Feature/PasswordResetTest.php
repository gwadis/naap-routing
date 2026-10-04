<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Mail\SecurityMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_login_page_contains_working_forgot_password_link(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee(route('password.request'), false);
        $response->assertSee('Forgot Password?');
    }

    public function test_forgot_password_page_renders_successfully(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSee('Forgot Password');
        $response->assertSee(route('password.email'), false);
    }

    public function test_authenticated_user_is_redirected_to_dashboard_from_forgot_password(): void
    {
        $user = User::create([
            'name' => 'Authenticated User',
            'username' => 'authuser',
            'email' => 'authuser@naap.org',
            'password' => 'ValidPassword123!',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        $response = $this->withSession(['user_id' => $user->id])
            ->actingAs($user)
            ->get(route('password.request'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_can_request_password_reset_with_valid_email(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'email' => 'jane@naap.org',
            'password' => 'OldPassword123!',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'jane@naap.org',
        ]);

        $response->assertSessionHas('status');
        $response->assertRedirect();

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function ($notification) use ($user) {
                return !empty($notification->token);
            }
        );

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'jane@naap.org',
        ]);
    }

    public function test_user_can_request_password_reset_with_valid_username(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'John Officer',
            'username' => 'johnofficer',
            'email' => 'officer@naap.org',
            'password' => 'OldPassword123!',
            'role' => User::ROLE_OFFICE_HEAD,
            'needs_password_change' => false,
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'johnofficer',
        ]);

        $response->assertSessionHas('status');
        $response->assertRedirect();

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function ($notification) {
                return !empty($notification->token);
            }
        );

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'officer@naap.org',
        ]);
    }

    public function test_account_enumeration_is_prevented_for_nonexistent_account(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'nonexistent@naap.org',
        ]);

        $response->assertSessionHas('status');
        $response->assertSessionMissing('error');
        $response->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_reset_password_page_loads_with_valid_token(): void
    {
        $user = User::create([
            'name' => 'Reset Target',
            'username' => 'resettarget',
            'email' => 'target@naap.org',
            'password' => 'OldPassword123!',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'target@naap.org',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Set New Password');
        $response->assertSee('target@naap.org');
        $response->assertSee($token);
    }

    public function test_reset_password_page_rejects_invalid_token(): void
    {
        $user = User::create([
            'name' => 'Reset Target',
            'username' => 'resettarget',
            'email' => 'target@naap.org',
            'password' => 'OldPassword123!',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        $response = $this->get(route('password.reset', [
            'token' => 'invalid-token-string',
            'email' => 'target@naap.org',
        ]));

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors(['email']);
    }

    public function test_user_can_reset_password_and_login_with_new_password(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Robert Johnson',
            'username' => 'robertj',
            'email' => 'robert@naap.org',
            'password' => 'OldStrongPassword123!',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        $token = Password::broker()->createToken($user);

        $newPassword = 'NewSecretPassword2026!#';

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'robert@naap.org',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        // Token must be invalidated and removed from database
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'robert@naap.org',
        ]);

        // Refresh user and verify password updated
        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check('OldStrongPassword123!', $user->password));

        // Verify security confirmation email was queued/sent
        Mail::assertQueued(SecurityMail::class, function ($mail) {
            return $mail->hasTo('robert@naap.org');
        });

        // Verify login fails with old password
        $oldLoginResponse = $this->post(route('login.submit'), [
            'username' => 'robert@naap.org',
            'password' => 'OldStrongPassword123!',
        ]);
        $oldLoginResponse->assertSessionHas('error');

        // Verify login succeeds with new password
        $newLoginResponse = $this->post(route('login.submit'), [
            'username' => 'robert@naap.org',
            'password' => $newPassword,
        ]);
        $this->assertTrue(
            $newLoginResponse->isRedirect(route('login.otp.verify')) ||
            $newLoginResponse->isRedirect(route('dashboard'))
        );
    }

    public function test_reset_password_enforces_password_complexity_rules(): void
    {
        $user = User::create([
            'name' => 'Complexity User',
            'username' => 'complexityuser',
            'email' => 'complex@naap.org',
            'password' => 'OldStrongPassword123!',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        $token = Password::broker()->createToken($user);

        // Test 1: Too short (< 12 characters)
        $response1 = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'Short1!',
                'password_confirmation' => 'Short1!',
            ]);
        $response1->assertSessionHasErrors(['password']);

        // Test 2: Missing special character
        $response2 = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NoSpecialCharacter123',
                'password_confirmation' => 'NoSpecialCharacter123',
            ]);
        $response2->assertSessionHasErrors(['password']);

        // Test 3: Password confirmation mismatch
        $response3 = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'ValidPassword123!',
                'password_confirmation' => 'DifferentPassword123!',
            ]);
        $response3->assertSessionHasErrors(['password']);
    }

    public function test_used_token_cannot_be_reused(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Single Use User',
            'username' => 'singleuse',
            'email' => 'single@naap.org',
            'password' => 'OldPassword123!',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        $token = Password::broker()->createToken($user);
        $firstNewPassword = 'FirstNewPassword123!';

        // First reset succeeds
        $response1 = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'single@naap.org',
            'password' => $firstNewPassword,
            'password_confirmation' => $firstNewPassword,
        ]);
        $response1->assertRedirect(route('login'));

        // Attempting to reuse the exact same token must fail
        $secondNewPassword = 'SecondNewPassword123!';
        $response2 = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'single@naap.org',
            'password' => $secondNewPassword,
            'password_confirmation' => $secondNewPassword,
        ]);

        $response2->assertSessionHasErrors(['email']);

        // Password should still be firstNewPassword
        $user->refresh();
        $this->assertTrue(Hash::check($firstNewPassword, $user->password));
        $this->assertFalse(Hash::check($secondNewPassword, $user->password));
    }

    /**
     * Requirement 15 Comprehensive Suite:
     * 1. User A has a designated email.
     * 2. User B has a different designated email.
     * 3. Submit User A's email to Forgot Password.
     * 4. Verify the notification recipient is User A's email.
     * 5. Verify the notification is NOT sent to User B.
     * 6. Verify it is NOT sent to the admin email.
     * 7. Verify it is NOT sent to MAIL_FROM_ADDRESS unless that happens to legitimately be the user's registered email.
     * 8. Verify the reset token belongs to User A.
     * 9. Verify User A can reset their password.
     * 10. Verify User B cannot use User A's reset token.
     */
    public function test_password_reset_recipient_isolation_and_token_security(): void
    {
        Notification::fake();
        Mail::fake();

        // 1. User A has a designated email
        $userA = User::create([
            'name' => 'Alice User',
            'username' => 'alice_user',
            'email' => 'alice@naap.org',
            'password' => 'AliceOriginalPass123!',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        // 2. User B has a different designated email
        $userB = User::create([
            'name' => 'Bob User',
            'username' => 'bob_user',
            'email' => 'bob@naap.org',
            'password' => 'BobOriginalPass123!',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        // Admin user
        $admin = User::create([
            'name' => 'System Admin',
            'username' => 'sysadmin',
            'email' => 'admin@naap.org',
            'password' => 'AdminOriginalPass123!',
            'role' => User::ROLE_ADMIN,
            'needs_password_change' => false,
        ]);

        $mailFromAddress = config('mail.from.address');

        // 3. Submit User A's email to Forgot Password
        $response = $this->post(route('password.email'), [
            'email' => 'alice@naap.org',
        ]);

        $response->assertSessionHas('status');
        $response->assertRedirect();

        // 4. Verify notification recipient is User A's email
        $capturedToken = null;
        Notification::assertSentTo(
            $userA,
            ResetPasswordNotification::class,
            function ($notification, $channels, $notifiable) use ($userA, &$capturedToken) {
                $capturedToken = $notification->token;
                // Verify model mail routing resolves to Alice's email
                $this->assertEquals('alice@naap.org', $notifiable->routeNotificationForMail($notification));
                $this->assertEquals('alice@naap.org', $notifiable->getEmailForPasswordReset());
                $this->assertEquals('alice@naap.org', $notifiable->email);

                // Verify the mail representation has Alice's email in the URL
                $mail = $notification->toMail($notifiable);
                $this->assertStringContainsString('email=alice%40naap.org', $mail->viewData['actionUrl']);
                return !empty($notification->token);
            }
        );

        // 5. Verify the notification is NOT sent to User B
        Notification::assertNotSentTo($userB, ResetPasswordNotification::class);

        // 6. Verify it is NOT sent to the admin email
        Notification::assertNotSentTo($admin, ResetPasswordNotification::class);

        // 7. Verify it is NOT sent to MAIL_FROM_ADDRESS
        if ($mailFromAddress !== 'alice@naap.org') {
            $fromUsers = User::where('email', $mailFromAddress)->get();
            foreach ($fromUsers as $fromUser) {
                Notification::assertNotSentTo($fromUser, ResetPasswordNotification::class);
            }
        }

        // 8. Verify the reset token belongs to User A in database
        $this->assertNotNull($capturedToken);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'alice@naap.org',
        ]);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'bob@naap.org',
        ]);
        $this->assertTrue(Password::broker()->tokenExists($userA, $capturedToken));
        $this->assertFalse(Password::broker()->tokenExists($userB, $capturedToken));

        // 10. Verify User B cannot use User A's reset token
        $responseBob = $this->post(route('password.update'), [
            'token' => $capturedToken,
            'email' => 'bob@naap.org',
            'password' => 'BobNewPassword123!@#',
            'password_confirmation' => 'BobNewPassword123!@#',
        ]);
        $responseBob->assertSessionHasErrors(['email']);
        $userB->refresh();
        $this->assertTrue(Hash::check('BobOriginalPass123!', $userB->password));

        // 9. Verify User A can reset their password
        $responseAlice = $this->post(route('password.update'), [
            'token' => $capturedToken,
            'email' => 'alice@naap.org',
            'password' => 'AliceNewPassword123!@#',
            'password_confirmation' => 'AliceNewPassword123!@#',
        ]);
        $responseAlice->assertRedirect(route('login'));
        $userA->refresh();
        $this->assertTrue(Hash::check('AliceNewPassword123!@#', $userA->password));
        $this->assertFalse(Hash::check('AliceOriginalPass123!', $userA->password));

        // Token must be consumed
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'alice@naap.org',
        ]);
    }

    public function test_user_with_empty_or_invalid_email_does_not_dispatch_notification(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'No Email User',
            'username' => 'noemailuser',
            'email' => 'invalid-email-format',
            'password' => 'Password123!@#',
            'role' => User::ROLE_STAFF,
            'needs_password_change' => false,
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'noemailuser',
        ]);

        // Generic response returned for security, no notification dispatched
        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_brevo_transport_bridge_dispatches_email_correctly(): void
    {
        $emailServiceMock = \Mockery::mock(\App\Services\EmailService::class);
        $emailServiceMock->shouldReceive('send')
            ->once()
            ->with('recipient@example.com', 'Test Subject', \Mockery::on(function ($body) {
                return str_contains($body, 'Hello World');
            }))
            ->andReturn(true);

        $transport = new \App\Mail\Transport\BrevoTransport($emailServiceMock);

        $symfonyEmail = (new \Symfony\Component\Mime\Email())
            ->from('noreply@larable.dev')
            ->to('recipient@example.com')
            ->subject('Test Subject')
            ->html('<p>Hello World</p>');

        $envelope = new \Symfony\Component\Mailer\Envelope(
            new \Symfony\Component\Mime\Address('noreply@larable.dev'),
            [new \Symfony\Component\Mime\Address('recipient@example.com')]
        );

        $sentMessage = new \Symfony\Component\Mailer\SentMessage($symfonyEmail, $envelope);

        $reflection = new \ReflectionClass($transport);
        $doSendMethod = $reflection->getMethod('doSend');
        $doSendMethod->setAccessible(true);
        $doSendMethod->invoke($transport, $sentMessage);

        $this->assertEquals('brevo', (string) $transport);
    }
}

