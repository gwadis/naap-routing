<?php

namespace App\Services;

use App\Services\Providers\BrevoProvider;
use App\Services\Providers\LogProvider;
use App\Services\Providers\SmtpProvider;

class EmailService
{
    /**
     * The configured email provider instance.
     *
     * @var EmailProviderInterface
     */
    protected $provider;

    public function __construct()
    {
        $providerName = config('services.email.provider');

        if (empty($providerName)) {
            $providerName = config('mail.default', 'log');
        }

        switch (strtolower($providerName)) {
            case 'smtp':
            case 'mail':
                $this->provider = new SmtpProvider();
                break;
            case 'brevo':
                $this->provider = new BrevoProvider();
                break;
            case 'log':
                $this->provider = new LogProvider();
                break;
            default:
                if (config('mail.default') === 'smtp') {
                    $this->provider = new SmtpProvider();
                } else {
                    $this->provider = new LogProvider();
                }
                break;
        }
    }

    /**
     * Send email via the configured provider.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email HTML/text body
     * @return bool
     */
    public function send(string $to, string $subject, string $body): bool
    {
        return $this->provider->send($to, $subject, $body);
    }
}
