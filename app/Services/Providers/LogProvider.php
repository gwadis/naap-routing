<?php

namespace App\Services\Providers;

use App\Services\EmailProviderInterface;
use Illuminate\Support\Facades\Log;

class LogProvider implements EmailProviderInterface
{
    public function send(string $to, string $subject, string $body): bool
    {
        Log::channel('email')->info("Sending Email (LogProvider):\nTo: {$to}\nSubject: {$subject}\nBody:\n{$body}");
        return true;
    }
}
