<?php
declare(strict_types=1);

namespace InfinityBlog\Sms;

interface SmsProviderInterface
{
    /**
     * Send an SMS message.
     *
     * @param string $to Recipient phone number in E.164 format (e.g. +919876543210).
     * @param string $message Text message content.
     * @return array{delivered: bool, status: string, reason?: string, provider?: string}
     */
    public function send(string $to, string $message): array;
}
