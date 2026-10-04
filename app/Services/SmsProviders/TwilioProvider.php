<?php

namespace App\Services\SmsProviders;

use App\Services\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioProvider implements SmsProviderInterface
{
    protected ?string $sid;
    protected ?string $token;
    protected ?string $from;

    public function __construct(?string $sid = null, ?string $token = null, ?string $from = null)
    {
        $this->sid = $sid ?? config('services.sms.twilio.sid');
        $this->token = $token ?? config('services.sms.twilio.token');
        $this->from = $from ?? config('services.sms.twilio.from');
    }

    public function send(string $to, string $message): array
    {
        if (empty($this->sid) || empty($this->token) || empty($this->from)) {
            Log::warning("[Twilio SMS] Twilio credentials missing in config. Falling back to log.");
            return (new LogSmsProvider())->send($to, $message);
        }

        try {
            // Ensure E.164 for Twilio (+63...)
            $e164Number = str_starts_with($to, '+') 
                ? $to 
                : (str_starts_with($to, '09') ? '+63' . substr($to, 1) : '+' . $to);

            $endpoint = "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json";

            $response = Http::withBasicAuth($this->sid, $this->token)
                ->asForm()
                ->post($endpoint, [
                    'From' => $this->from,
                    'To' => $e164Number,
                    'Body' => $message,
                ]);

            $data = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'provider' => 'twilio',
                    'message_id' => $data['sid'] ?? null,
                    'info' => "SMS dispatched via Twilio to {$e164Number}",
                    'raw' => $data,
                ];
            }

            $errorMessage = $data['message'] ?? ("HTTP {$response->status()}: " . $response->body());

            return [
                'success' => false,
                'provider' => 'twilio',
                'message_id' => null,
                'info' => "Twilio error: {$errorMessage}",
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'provider' => 'twilio',
                'message_id' => null,
                'info' => "Twilio connection exception: " . $e->getMessage(),
                'raw' => null,
            ];
        }
    }
}
