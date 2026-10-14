<?php
declare(strict_types=1);

namespace InfinityBlog\Mail;

final class ArrayMailTransport implements MailTransportInterface
{
    /** @var list<array{to: string, subject: string, bodyText: string, bodyHtml: ?string, headers: array<string, string>}> */
    private array $sentMessages = [];

    /**
     * @param array<string, string> $headers
     * @return array{delivered: bool, status: string, reason?: string}
     */
    public function send(
        string $to,
        string $subject,
        string $bodyText,
        ?string $bodyHtml = null,
        array $headers = []
    ): array {
        $this->sentMessages[] = [
            'to' => $to,
            'subject' => $subject,
            'bodyText' => $bodyText,
            'bodyHtml' => $bodyHtml,
            'headers' => $headers,
        ];

        return ['delivered' => true, 'status' => 'SENT'];
    }

    /** @return list<array{to: string, subject: string, bodyText: string, bodyHtml: ?string, headers: array<string, string>}> */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /** @return array{to: string, subject: string, bodyText: string, bodyHtml: ?string, headers: array<string, string>}|null */
    public function lastMessage(): ?array
    {
        return $this->sentMessages !== [] ? $this->sentMessages[count($this->sentMessages) - 1] : null;
    }

    public function count(): int
    {
        return count($this->sentMessages);
    }

    public function clear(): void
    {
        $this->sentMessages = [];
    }
}
