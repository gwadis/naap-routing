<?php

namespace Tests\Feature;

use App\Mail\SecurityMail;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountLockoutEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_lockout_email_excludes_ip_address_and_retains_required_details()
    {
        Mail::fake();

        $office = Office::firstOrCreate(
            ['name' => 'HR Office'],
            ['department' => 'Administration']
        );

        $user = User::create([
            'name' => 'Shane Muesco',
            'username' => 'test_shanemuesco',
            'email' => 'shane_lockout_test@naap.org',
            'password' => Hash::make('CorrectPassword123!'),
            'failed_login_attempts' => 4,
            'office_id' => $office->id,
            'role' => 'STAFF',
        ]);

        $ip = '198.51.100.42';

        // 5th failed login attempt triggers lockout
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/login', [
            'username' => 'test_shanemuesco',
            'password' => 'WrongPassword!',
        ]);

        $user->refresh();
        $this->assertNotNull($user->locked_until);

        Mail::assertQueued(SecurityMail::class, function (SecurityMail $mail) use ($user, $ip) {
            $rendered = $mail->render();

            // 1. IP Address must NOT be displayed, included, or exposed anywhere
            $hasIpHeader = stripos($rendered, 'IP Address') !== false;
            $hasActualIp = stripos($rendered, $ip) !== false;

            // 2. Retains required information
            $hasName = stripos($rendered, 'Shane Muesco') !== false;
            $hasLockoutMsg = stripos($rendered, 'temporarily locked for 15 minutes due to 5 consecutive failed login attempts') !== false;
            $hasAttemptDetails = stripos($rendered, 'Attempt details:') !== false;
            $hasTimestamp = stripos($rendered, 'Timestamp:') !== false;
            $hasAdminNotice = stripos($rendered, 'please contact your administrator immediately') !== false;

            return !$hasIpHeader 
                && !$hasActualIp 
                && $hasName 
                && $hasLockoutMsg 
                && $hasAttemptDetails 
                && $hasTimestamp 
                && $hasAdminNotice;
        });
    }

    public function test_account_locked_email_template_rendering_directly()
    {
        $timestamp = '2026-10-08 12:13:22';
        $body = view('emails.account_locked', compact('timestamp'))->render();

        $this->assertStringNotContainsString('IP Address', $body);
        $this->assertStringNotContainsString('127.0.0.1', $body);
        $this->assertStringContainsString('Your NAAP Routing account has been temporarily locked for 15 minutes due to 5 consecutive failed login attempts.', $body);
        $this->assertStringContainsString('Attempt details:', $body);
        $this->assertStringContainsString('Timestamp:', $body);
        $this->assertStringContainsString($timestamp, $body);
        $this->assertStringContainsString('If this was not you, please contact your administrator immediately.', $body);
    }
}
