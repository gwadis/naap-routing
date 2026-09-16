<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Otp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Use log provider to prevent external HTTP calls during test unless mocked
        Config::set('services.email.provider', 'log');
        Config::set('services.email.from_address', 'test@naap.org');
        Config::set('services.email.from_name', 'NAAP Test');
    }

    public function test_user_can_authenticate_with_correct_credentials_and_triggers_otp()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@naap.org',
            'password' => 'Password123!',
            'role' => 'Staff',
            'needs_password_change' => false
        ]);

        $response = $this->post(route('login.submit'), [
            'username' => 'test@naap.org',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('login.otp.verify'));
        
        $this->assertDatabaseHas('otps', [
            'user_id' => $user->id,
            'is_used' => false,
            'attempts' => 0,
        ]);

        $otp = Otp::where('user_id', $user->id)->first();
        $this->assertNotNull($otp);
        $this->assertEquals($user->id, session('temp_otp_user_id'));
    }

    public function test_login_fails_with_invalid_credentials_and_no_otp_generated()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@naap.org',
            'password' => 'Password123!',
            'role' => 'Staff',
            'needs_password_change' => false
        ]);

        $response = $this->post(route('login.submit'), [
            'username' => 'test@naap.org',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('otps', [
            'user_id' => $user->id,
        ]);
        $this->assertNull(session('temp_otp_user_id'));
    }

    public function test_user_can_verify_correct_otp_and_authenticates()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@naap.org',
            'password' => 'Password123!',
            'role' => 'Staff',
            'needs_password_change' => false
        ]);

        $otpCode = '123456';
        $otp = Otp::create([
            'user_id' => $user->id,
            'otp' => Hash::make($otpCode),
            'expires_at' => now()->addMinutes(5),
            'is_used' => false,
            'attempts' => 0,
        ]);

        $this->withSession([
            'temp_otp_user_id' => $user->id,
            'temp_otp_login_time' => now()
        ]);

        $response = $this->post(route('login.otp.verify.submit'), [
            'otp' => $otpCode,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue(auth()->check() || session('authenticated') === true);

        $otp->refresh();
        $this->assertTrue($otp->is_used);
        $this->assertNull(session('temp_otp_user_id'));
    }

    public function test_expired_otp_is_rejected()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@naap.org',
            'password' => 'Password123!',
            'role' => 'Staff',
            'needs_password_change' => false
        ]);

        $otpCode = '123456';
        $otp = Otp::create([
            'user_id' => $user->id,
            'otp' => Hash::make($otpCode),
            'expires_at' => now()->subMinutes(1),
            'is_used' => false,
            'attempts' => 0,
        ]);

        $this->withSession([
            'temp_otp_user_id' => $user->id,
            'temp_otp_login_time' => now()
        ]);

        $response = $this->post(route('login.otp.verify.submit'), [
            'otp' => $otpCode,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'OTP expired');
        $this->assertFalse(auth()->check());
    }

    public function test_used_otp_cannot_be_reused()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@naap.org',
            'password' => 'Password123!',
            'role' => 'Staff',
            'needs_password_change' => false
        ]);

        $otpCode = '123456';
        $otp = Otp::create([
            'user_id' => $user->id,
            'otp' => Hash::make($otpCode),
            'expires_at' => now()->addMinutes(5),
            'is_used' => true,
            'attempts' => 0,
        ]);

        $this->withSession([
            'temp_otp_user_id' => $user->id,
            'temp_otp_login_time' => now()
        ]);

        $response = $this->post(route('login.otp.verify.submit'), [
            'otp' => $otpCode,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'OTP already used');
        $this->assertFalse(auth()->check());
    }

    public function test_too_many_failed_attempts_invalidates_otp()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@naap.org',
            'password' => 'Password123!',
            'role' => 'Staff',
            'needs_password_change' => false
        ]);

        $otpCode = '123456';
        $otp = Otp::create([
            'user_id' => $user->id,
            'otp' => Hash::make($otpCode),
            'expires_at' => now()->addMinutes(5),
            'is_used' => false,
            'attempts' => 4,
        ]);

        $this->withSession([
            'temp_otp_user_id' => $user->id,
            'temp_otp_login_time' => now()
        ]);

        // 5th attempt (invalid code)
        $response = $this->post(route('login.otp.verify.submit'), [
            'otp' => '000000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Too many attempts');
        
        $otp->refresh();
        $this->assertTrue($otp->is_used);
        $this->assertEquals(5, $otp->attempts);
    }

    public function test_resend_otp_rate_limiting()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@naap.org',
            'password' => 'Password123!',
            'role' => 'Staff',
            'needs_password_change' => false
        ]);

        // Last OTP created just now
        $otp = Otp::create([
            'user_id' => $user->id,
            'otp' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'is_used' => false,
            'attempts' => 0,
        ]);

        $this->withSession([
            'temp_otp_user_id' => $user->id,
            'temp_otp_login_time' => now()
        ]);

        // Attempt resend immediately
        $response = $this->post(route('login.otp.resend'));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Too many resend requests');

        // Travel 61 seconds forward
        $this->travel(61)->seconds();

        $response = $this->post(route('login.otp.resend'));
        $response->assertRedirect();
        $response->assertSessionHas('success', 'A new verification code has been sent to your email.');

        // Old OTP should be deleted, new one should exist
        $this->assertEquals(1, Otp::where('user_id', $user->id)->count());
        $newOtp = Otp::where('user_id', $user->id)->first();
        $this->assertNotEquals($otp->id, $newOtp->id);
    }
}
