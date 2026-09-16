<?php

namespace App\Services\Providers;

use App\Services\EmailProviderInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SmtpProvider implements EmailProviderInterface
{
    /**
     * Send email using Laravel's configured Mail / SMTP driver.
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     */
    public function send(string $to, string $subject, string $body): bool
    {
        try {
            $fromAddress = config('mail.from.address') ?: config('services.email.from_address');
            $fromName = config('mail.from.name') ?: config('services.email.from_name', config('app.name'));

            Mail::html(nl2br(e($body)), function ($message) use ($to, $subject, $fromAddress, $fromName) {
                $message->to($to)
                        ->subject($subject);

                if ($fromAddress) {
                    $message->from($fromAddress, $fromName);
                }
            });

            Log::channel('email')->info("Email successfully sent via SMTP to {$to}");
            return true;
        } catch (\Throwable $e) {
            Log::channel('email')->error("Failed to send email via SMTP to {$to}: " . $e->getMessage());
            return false;
        }
    }
}
