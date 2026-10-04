<?php

namespace App\Services\SmsProviders;

use App\Services\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AndroidGatewayProvider implements SmsProviderInterface
{
    protected ?string $url;
    protected ?string $token;

    public function __construct(?string $url = null, ?string $token = null)
    {
        $this->url = $url ?? config('services.sms.android_gateway.url', env('ANDROID_GATEWAY_URL'));
        $this->token = $token ?? config('services.sms.android_gateway.token', env('ANDROID_GATEWAY_TOKEN'));
    }

    public function send(string $to, string $message): array
    {
        if (empty($this->url)) {
            Log::warning("[Android SMS Gateway] ANDROID_GATEWAY_URL is not set in .env. Falling back to log.");
            return (new LogSmsProvider())->send($to, $message);
        }

        $endpoint = rtrim($this->url, '/');
        // If the URL is just an IP:port (e.g. http://192.168.1.15:8080), append /send
        if (!preg_match('/\/(send|sms|api|messages)/i', $endpoint)) {
            $endpoint .= '/send';
        }

        try {
            $headers = ['Accept' => 'application/json'];
            if (!empty($this->token)) {
                $headers['Authorization'] = 'Bearer ' . $this->token;
                $headers['X-Token'] = $this->token;
            }

            Log::channel('single')->info("[Android SMS Gateway] Forwarding SMS to {$endpoint} for recipient {$to}");

            // Standard payload supported by most Android SMS Gateway apps
            $payload = [
                'to' => $to,
                'phone' => $to,
                'number' => $to,
                'message' => $message,
                'text' => $message,
            ];

            $response = Http::timeout(8)
                ->withHeaders($headers)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json() ?? ['raw' => $response->body()];
                $messageId = $data['id'] ?? $data['message_id'] ?? ('AND-' . uniqid());

                Log::channel('single')->info("[Android SMS Gateway] Successfully sent SMS via Android Phone to {$to}");

                return [
                    'success' => true,
                    'provider' => 'android_gateway',
                    'message_id' => (string) $messageId,
                    'info' => "SMS dispatched through local Android Phone Gateway for {$to}",
                    'raw' => $data,
                ];
            }

            // Retry with form-encoded if JSON returned 400/415
            if ($response->status() === 415 || $response->status() === 400) {
                $formResponse = Http::timeout(8)
                    ->withHeaders($headers)
                    ->asForm()
                    ->post($endpoint, $payload);

                if ($formResponse->successful()) {
                    return [
                        'success' => true,
                        'provider' => 'android_gateway',
                        'message_id' => 'AND-' . uniqid(),
                        'info' => "SMS dispatched through local Android Phone Gateway for {$to}",
                        'raw' => $formResponse->json() ?? $formResponse->body(),
                    ];
                }
            }

            $errorMsg = "HTTP {$response->status()}: " . $response->body();
            Log::channel('single')->error("[Android SMS Gateway] Response error: {$errorMsg}");

            return [
                'success' => false,
                'provider' => 'android_gateway',
                'message_id' => null,
                'info' => "Android Gateway error: {$errorMsg}",
                'raw' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::channel('single')->error("[Android SMS Gateway] Connection failed to {$endpoint}: " . $e->getMessage());

            return [
                'success' => false,
                'provider' => 'android_gateway',
                'message_id' => null,
                'info' => "Could not connect to Android Phone Gateway at {$endpoint}. Please check that your phone app server is running on your Wi-Fi network.",
                'raw' => null,
            ];
        }
    }
}
