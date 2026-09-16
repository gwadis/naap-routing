<?php

namespace App\Services;

interface EmailProviderInterface
{
    /**
     * Send email.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body HTML or Text email body
     * @return bool
     */
    public function send(string $to, string $subject, string $body): bool;
}
