<?php

namespace App\Services\SmsProviders;

use App\Services\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsProviderInterface
{
    /**
     * Local testing log driver.
     * Records to system log without faking cellular transmission.
     */
    public function send(string $to, string $message): array
    {
        $logMessage = "[NAAP SMS LOG DRIVER] Recipient: {$to} | Message: \"{$message}\"";
        
        Log::channel('single')->info($logMessage);

        return [
            'success' => false,
            'provider' => 'log',
            'status' => 'SMS_UNAVAILABLE',
            'message_id' => null,
            'info' => "SMS fallback unavailable — logged locally for development. No live SMS provider configured.",
            'raw' => [
                'recipient' => $to,
                'message' => $message,
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }
}
