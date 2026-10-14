<?php
declare(strict_types=1);

namespace InfinityBlog\Sms;

final class ArraySmsProvider implements SmsProviderInterface
{
    /** @var list<array{to: string, message: string, timestamp: string}> */
    private array $sentMessages = [];

    /**
     * @return array{delivered: bool, status: string, provider: string}
     */
    public function send(string $to, string $message): array
    {
        $this->sentMessages[] = [
            'to' => $to,
            'message' => $message,
            'timestamp' => gmdate('Y-m-d H:i:s'),
        ];

        return [
            'delivered' => true,
            'status' => 'SENT',
            'provider' => 'array',
        ];
    }

    /** @return list<array{to: string, message: string, timestamp: string}> */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /** @return array{to: string, message: string, timestamp: string}|null */
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
