<?php
declare(strict_types=1);

namespace InfinityBlog\Mail;

interface MailTransportInterface
{
    /**
     * Send email via the underlying transport.
     *
     * @param array<string, string> $headers
     * @return array{delivered: bool, status: string, reason?: string}
     */
    public function send(
        string $to,
        string $subject,
        string $bodyText,
        ?string $bodyHtml = null,
        array $headers = []
    ): array;
}
