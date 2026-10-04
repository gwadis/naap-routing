<?php

namespace App\Services;

interface SmsProviderInterface
{
    /**
     * Send an SMS message.
     *
     * @param string $to Normalized recipient phone number
     * @param string $message Text message content
     * @return array [
     *     'success' => bool,
     *     'provider' => string,
     *     'message_id' => ?string,
     *     'info' => string,
     *     'raw' => mixed,
     * ]
     */
    public function send(string $to, string $message): array;
}
