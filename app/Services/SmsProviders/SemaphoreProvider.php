<?php

namespace App\Services\SmsProviders;

use App\Services\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemaphoreProvider implements SmsProviderInterface
{
    protected ?string $apiKey;
    protected ?string $senderName;
    protected string $endpoint = 'https://api.semaphore.co/api/v4/messages';

    public function __construct(?string $apiKey = null, ?string $senderName = null)
    {
        $this->apiKey = $apiKey ?? config('services.sms.semaphore.api_key');
        $this->senderName = $senderName ?? config('services.sms.semaphore.sender_name');
    }

    public function send(string $to, string $message): array
    {
        if (empty($this->apiKey)) {
            Log::warning("[Semaphore SMS] SEMAPHORE_API_KEY is not configured in .env. Falling back to log.");
            return (new LogSmsProvider())->send($to, $message);
        }

        try {
            $payload = [
                'apikey' => $this->apiKey,
                'number' => $to,
                'message' => $message,
            ];

            if (!empty($this->senderName) && strtolower($this->senderName) !== 'naap') {
                $payload['sendername'] = $this->senderName;
            }

            Log::channel('single')->info("[Semaphore SMS] Dispatching SMS to {$to} via Semaphore API");

            $response = Http::timeout(15)
                ->asForm()
                ->post($this->endpoint, $payload);

            $data = $response->json();

            if ($response->successful()) {
                // Semaphore returns an array of message objects on success
                $messageId = null;
                if (is_array($data) && isset($data[0]['message_id'])) {
                    $messageId = (string) $data[0]['message_id'];
                } elseif (is_array($data) && isset($data['message_id'])) {
                    $messageId = (string) $data['message_id'];
                }

                Log::channel('single')->info("[Semaphore SMS] SMS successfully queued/sent to {$to}. Message ID: {$messageId}");

                return [
                    'success' => true,
                    'provider' => 'semaphore',
                    'message_id' => $messageId ?? ('SEM-' . uniqid()),
                    'info' => "SMS delivered to Semaphore gateway for {$to}",
                    'raw' => $data,
                ];
            }

            $responseBody = $response->body();
            if ($response->status() === 403 && str_contains($responseBody, 'not yet been approved')) {
                $errorMessage = "Your Semaphore account ('school') was successfully connected! However, Semaphore currently has it marked as 'Pending' approval with 0 credit balance. Please check your Semaphore dashboard at https://semaphore.co (or check your email for their activation link) to complete activation.";
            } elseif (is_array($data) && isset($data['error'])) {
                $errorMessage = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
            } else {
                $errorMessage = "HTTP {$response->status()}: " . $responseBody;
            }

            Log::channel('single')->error("[Semaphore SMS] Failed sending to {$to}: {$errorMessage}");

            return [
                'success' => false,
                'provider' => 'semaphore',
                'message_id' => null,
                'info' => "Semaphore API error: {$errorMessage}",
                'raw' => $data ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::channel('single')->error("[Semaphore SMS] Exception while sending to {$to}: " . $e->getMessage());

            return [
                'success' => false,
                'provider' => 'semaphore',
                'message_id' => null,
                'info' => "Semaphore connection failed: " . $e->getMessage(),
                'raw' => null,
            ];
        }
    }
}
