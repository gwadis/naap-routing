<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Address;
use App\Services\EmailService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class BrevoTransport extends AbstractTransport
{
    protected ?EmailService $emailService;

    public function __construct(?EmailService $emailService = null, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($dispatcher, $logger);
        $this->emailService = $emailService;
    }

    /**
     * Send the message using the enterprise EmailService (supporting Brevo API, SMTP, or Log).
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $recipients = array_map(fn (Address $address) => $address->getAddress(), $message->getEnvelope()->getRecipients());
        if (empty($recipients)) {
            $recipients = array_map(fn (Address $address) => $address->getAddress(), $email->getTo());
        }

        $subject = $email->getSubject() ?? 'Notification';
        
        $htmlBody = $email->getHtmlBody();
        if (is_resource($htmlBody)) {
            $htmlBody = stream_get_contents($htmlBody);
        }
        if (empty($htmlBody)) {
            $textBody = $email->getTextBody();
            if (is_resource($textBody)) {
                $textBody = stream_get_contents($textBody);
            }
            $htmlBody = !empty($textBody) ? nl2br($textBody) : '';
        }

        $service = $this->emailService ?? new EmailService();

        foreach ($recipients as $recipient) {
            if (!empty($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $service->send($recipient, $subject, $htmlBody);
            }
        }
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
