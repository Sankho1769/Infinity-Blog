<?php
declare(strict_types=1);

namespace InfinityBlog\Mail;

use Throwable;

final class MailerService
{
    private ?string $host;
    private ?int $port;
    private ?string $username;
    private ?string $password;
    private string $fromAddress;
    private string $fromName;

    public function __construct(
        ?string $host = null,
        ?int $port = null,
        ?string $username = null,
        ?string $password = null,
        ?string $fromAddress = null,
        ?string $fromName = null
    ) {
        $this->host = $host ?? self::envString('MAIL_HOST', 'SMTP_HOST');
        $this->port = $port ?? self::envInt('MAIL_PORT', 'SMTP_PORT');
        $this->username = $username ?? self::envString('MAIL_USERNAME', 'SMTP_USER');
        $this->password = $password ?? self::envString('MAIL_PASSWORD', 'SMTP_PASSWORD');
        $this->fromAddress = $fromAddress ?? self::envString('MAIL_FROM_ADDRESS') ?? 'noreply@infinityblog.test';
        $this->fromName = $fromName ?? self::envString('MAIL_FROM_NAME') ?? 'Infinity Blog Security';
    }

    private static function envString(string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $val = getenv($key);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }
        return null;
    }

    private static function envInt(string ...$keys): ?int
    {
        foreach ($keys as $key) {
            $val = getenv($key);
            if (is_string($val) && is_numeric($val)) {
                return (int) $val;
            }
        }
        return null;
    }

    public function isConfigured(): bool
    {
        return $this->host !== null && $this->port !== null && $this->username !== null && $this->password !== null;
    }

    /**
     * Mask an email address for privacy-safe display.
     * e.g., "shubham.banerjee@example.com" -> "s***e@example.com"
     */
    public static function maskEmail(?string $email): string
    {
        if ($email === null || trim($email) === '') {
            return '••••••';
        }
        $parts = explode('@', trim($email), 2);
        if (count($parts) !== 2) {
            return '••••••';
        }
        $user = $parts[0];
        $domain = $parts[1];

        $len = strlen($user);
        if ($len <= 2) {
            $maskedUser = substr($user, 0, 1) . '***';
        } else {
            $maskedUser = substr($user, 0, 1) . '***' . substr($user, -1);
        }

        return $maskedUser . '@' . $domain;
    }

    /**
     * Send email or record BLOCKED status if unconfigured.
     *
     * @return array{delivered: bool, status: string, reason?: string}
     */
    public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'delivered' => false,
                'status' => 'BLOCKED',
                'reason' => 'SMTP mail gateway is not configured.',
            ];
        }

        try {
            $headers = [
                'From' => "{$this->fromName} <{$this->fromAddress}>",
                'Reply-To' => $this->fromAddress,
                'X-Mailer' => 'InfinityBlog-Mailer/1.0',
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/plain; charset=UTF-8',
            ];

            $headerLines = [];
            foreach ($headers as $k => $v) {
                $headerLines[] = "$k: $v";
            }

            $success = @mail($to, $subject, $bodyText, implode("\r\n", $headerLines));
            if ($success) {
                return ['delivered' => true, 'status' => 'SENT'];
            }

            return [
                'delivered' => false,
                'status' => 'FAILED',
                'reason' => 'Local mail transport failed.',
            ];
        } catch (Throwable) {
            return [
                'delivered' => false,
                'status' => 'FAILED',
                'reason' => 'Exception during mail dispatch.',
            ];
        }
    }
}
