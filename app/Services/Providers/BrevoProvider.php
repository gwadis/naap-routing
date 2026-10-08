<?php

namespace App\Services\Providers;

use App\Services\EmailProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoProvider implements EmailProviderInterface
{
    protected $apiKey;
    protected $fromAddress;
    protected $fromName;

    public function __construct()
    {
        $this->apiKey = config('services.email.api_key') 
            ?: env('BREVO_API_KEY') 
            ?: env('EMAIL_API_KEY');

        $this->fromAddress = config('services.email.from_address') 
            ?: env('EMAIL_FROM_ADDRESS') 
            ?: env('MAIL_FROM_ADDRESS') 
            ?: config('mail.from.address') 
            ?: 'noreply@larable.dev';

        $this->fromName = config('services.email.from_name') 
            ?: env('EMAIL_FROM_NAME') 
            ?: env('MAIL_FROM_NAME') 
            ?: config('mail.from.name') 
            ?: 'NAAP Document Routing';

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('Email API key is missing. Ensure BREVO_API_KEY or EMAIL_API_KEY is configured in your .env file.');
        }

        if (empty($this->fromAddress)) {
            throw new \InvalidArgumentException('Sender email address is missing. Ensure EMAIL_FROM_ADDRESS or MAIL_FROM_ADDRESS is configured in your .env file.');
        }
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $endpoint = 'https://api.brevo.com/v3/smtp/email';
        Log::channel('email')->info("Sending email via Brevo to {$to}. Endpoint: {$endpoint}");
        
        try {
            $sender = [
                'email' => $this->fromAddress,
            ];
            if ($this->fromName) {
                $sender['name'] = $this->fromName;
            }

            $response = Http::timeout(10)
                ->retry(3, 100, null, false)
                ->withHeaders([
                    'api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($endpoint, [
                    'sender' => $sender,
                    'to' => [
                        ['email' => $to]
                    ],
                    'subject' => $subject,
                    'htmlContent' => (str_contains($body, '<br') || str_contains($body, '<p') || str_contains($body, '<div')) ? $body : nl2br($body),
                ]);

            $status = $response->status();
            $responseBody = $response->body();

            Log::channel('email')->info("Brevo provider HTTP Status: " . $status);
            Log::channel('email')->info("Brevo provider Response: " . $responseBody);

            if ($response->successful()) {
                return true;
            }

            // Decode response to extract detailed validation errors/error code/message
            $decoded = json_decode($responseBody, true);
            $errCode = $decoded['code'] ?? 'N/A';
            $errMessage = $decoded['message'] ?? 'N/A';
            $validationErrors = isset($decoded['errors']) ? json_encode($decoded['errors']) : 'N/A';

            $logMessage = "Brevo API Error Details:\n"
                        . "HTTP Status: {$status}\n"
                        . "Error Code: {$errCode}\n"
                        . "Error Message: {$errMessage}\n"
                        . "Validation Errors: {$validationErrors}\n"
                        . "Response Body: {$responseBody}";

            Log::channel('email')->error($logMessage);

            // Throw exception containing full details to be displayed when APP_DEBUG=true
            throw new \RuntimeException($logMessage);
        } catch (\Exception $e) {
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            $exceptionMessage = "Brevo provider exception: " . $e->getMessage();
            Log::channel('email')->error($exceptionMessage . "\nStack trace:\n" . $e->getTraceAsString());
            throw new \RuntimeException($exceptionMessage, 0, $e);
        }
    }
}
