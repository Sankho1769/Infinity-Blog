<?php
declare(strict_types=1);

namespace InfinityBlog\Mail;

use Throwable;

final class SmtpTransport implements MailTransportInterface
{
    private string $host;
    private int $port;
    private ?string $username;
    private ?string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;
    private int $timeout;

    public function __construct(
        string $host,
        int $port = 587,
        ?string $username = null,
        ?string $password = null,
        string $encryption = 'tls',
        string $fromAddress = 'noreply@infinityblog.test',
        string $fromName = 'Infinity Blog Security',
        int $timeout = 15
    ) {
        $this->host = trim($host);
        $this->port = $port > 0 ? $port : 587;
        $this->username = $username !== null && trim($username) !== '' ? trim($username) : null;
        $this->password = $password !== null && $password !== '' ? $password : null;
        $this->encryption = strtolower(trim($encryption));
        $this->fromAddress = trim($fromAddress);
        $this->fromName = trim($fromName);
        $this->timeout = max(3, min(60, $timeout));
    }

    /**
     * Send email via authentic direct SMTP socket connection.
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
    ): array {
        $cleanTo = self::sanitizeHeader($to);
        if (!filter_var($cleanTo, FILTER_VALIDATE_EMAIL)) {
            return [
                'delivered' => false,
                'status' => 'FAILED',
                'reason' => 'Invalid recipient email address.',
            ];
        }

        $cleanSubject = self::sanitizeHeader($subject);
        $cleanFrom = self::sanitizeHeader($this->fromAddress);
        $cleanFromName = self::sanitizeHeader($this->fromName);

        $socket = null;
        try {
            $isSsl = ($this->encryption === 'ssl' || $this->port === 465);
            $protocol = $isSsl ? 'ssl://' : 'tcp://';
            $remote = $protocol . $this->host . ':' . $this->port;

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);

            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client(
                $remote,
                $errno,
                $errstr,
                (float) $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                return [
                    'delivered' => false,
                    'status' => 'FAILED',
                    'reason' => 'Failed to connect to SMTP host: ' . self::redact($errstr ?: "code {$errno}"),
                ];
            }

            stream_set_timeout($socket, $this->timeout);

            // Read initial greeting banner (220)
            $greeting = $this->readResponse($socket);
            if ($greeting['code'] !== 220) {
                throw new SmtpException("Invalid server greeting: {$greeting['text']}");
            }

            // EHLO
            $clientHost = self::resolveClientHost();
            $ehlo = $this->sendCommand($socket, "EHLO {$clientHost}", [250]);

            // STARTTLS if configured
            $wantsTls = ($this->encryption === 'tls' || $this->encryption === 'starttls' || ($this->port === 587 && $this->encryption !== 'none'));
            if ($wantsTls && !$isSsl) {
                $this->sendCommand($socket, 'STARTTLS', 220);
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                $crypto = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
                if (!$crypto) {
                    throw new SmtpException('TLS cryptographic handshake failed.');
                }
                // Re-issue EHLO over encrypted tunnel
                $this->sendCommand($socket, "EHLO {$clientHost}", [250]);
            }

            // AUTH LOGIN if credentials provided
            if ($this->username !== null && $this->password !== null) {
                $this->sendCommand($socket, 'AUTH LOGIN', 334);
                $this->sendCommand($socket, base64_encode($this->username), 334);
                $this->sendCommand($socket, base64_encode($this->password), 235);
            }

            // MAIL FROM
            $this->sendCommand($socket, "MAIL FROM:<{$cleanFrom}>", [250]);

            // RCPT TO
            $this->sendCommand($socket, "RCPT TO:<{$cleanTo}>", [250, 251]);

            // DATA
            $this->sendCommand($socket, 'DATA', 354);

            // Construct MIME message payload
            $payload = $this->buildPayload($cleanTo, $cleanSubject, $cleanFrom, $cleanFromName, $bodyText, $bodyHtml, $headers);

            // Send payload with dot-stuffing and terminal dot
            fwrite($socket, $payload . "\r\n.\r\n");
            $dataRes = $this->readResponse($socket);
            if ($dataRes['code'] !== 250) {
                throw new SmtpException("Data transmission rejected: {$dataRes['text']}");
            }

            // QUIT
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            $socket = null;

            return [
                'delivered' => true,
                'status' => 'SENT',
            ];
        } catch (Throwable $e) {
            if ($socket !== null && is_resource($socket)) {
                @fclose($socket);
            }

            return [
                'delivered' => false,
                'status' => 'FAILED',
                'reason' => self::redact($e->getMessage()),
            ];
        }
    }

    /**
     * Read an SMTP multi-line response from the stream socket.
     *
     * @param resource $socket
     * @return array{code: int, text: string}
     */
    private function readResponse($socket): array
    {
        $response = '';
        $code = 0;

        while (!feof($socket)) {
            $line = fgets($socket, 2048);
            if ($line === false) {
                break;
            }
            $response .= $line;

            // RFC 5321: A multi-line response has '-' at index 3 (e.g. "250-AUTH"),
            // the final line has a space at index 3 (e.g. "250 OK").
            if (strlen($line) >= 4 && $line[3] === ' ') {
                $code = (int) substr($line, 0, 3);
                break;
            }
            // Some non-standard servers might emit a bare 3-digit code
            if (strlen(trim($line)) === 3 && is_numeric(trim($line))) {
                $code = (int) trim($line);
                break;
            }
        }

        return ['code' => $code, 'text' => trim($response)];
    }

    /**
     * Send an SMTP command line and assert the expected response code.
     *
     * @param resource $socket
     * @param int|list<int> $expectedCodes
     * @return array{code: int, text: string}
     */
    private function sendCommand($socket, string $command, int|array $expectedCodes): array
    {
        fwrite($socket, $command . "\r\n");
        $res = $this->readResponse($socket);

        $allowed = is_array($expectedCodes) ? $expectedCodes : [$expectedCodes];
        if (!in_array($res['code'], $allowed, true)) {
            // Mask commands that might be sensitive
            $cmdName = strtok($command, ' ') ?: 'COMMAND';
            throw new SmtpException("SMTP error during {$cmdName}: [{$res['code']}] {$res['text']}");
        }

        return $res;
    }

    /**
     * Build RFC 2822 / MIME compliant email body with optional HTML alternative.
     *
     * @param array<string, string> $customHeaders
     */
    private function buildPayload(
        string $to,
        string $subject,
        string $fromAddress,
        string $fromName,
        string $bodyText,
        ?string $bodyHtml,
        array $customHeaders
    ): string {
        $boundary = '=_Part_' . bin2hex(random_bytes(16));
        $date = gmdate('D, d M Y H:i:s +0000');
        $messageId = '<' . bin2hex(random_bytes(16)) . '@' . ($this->host ?: 'infinityblog.test') . '>';

        $headers = [
            'Date' => $date,
            'From' => "{$fromName} <{$fromAddress}>",
            'To' => "<{$to}>",
            'Subject' => $subject,
            'Message-ID' => $messageId,
            'X-Mailer' => 'InfinityBlog-Mailer/2.0',
            'MIME-Version' => '1.0',
        ];

        foreach ($customHeaders as $k => $v) {
            $cleanKey = self::sanitizeHeader((string) $k);
            $cleanVal = self::sanitizeHeader((string) $v);
            if ($cleanKey !== '' && $cleanVal !== '') {
                $headers[$cleanKey] = $cleanVal;
            }
        }

        $hasHtml = ($bodyHtml !== null && trim($bodyHtml) !== '');
        if ($hasHtml) {
            $headers['Content-Type'] = "multipart/alternative; boundary=\"{$boundary}\"";
        } else {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8; format=flowed';
            $headers['Content-Transfer-Encoding'] = '8bit';
        }

        $raw = '';
        foreach ($headers as $name => $val) {
            $raw .= "{$name}: {$val}\r\n";
        }
        $raw .= "\r\n";

        if ($hasHtml) {
            $raw .= "--{$boundary}\r\n";
            $raw .= "Content-Type: text/plain; charset=UTF-8; format=flowed\r\n";
            $raw .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $raw .= $bodyText . "\r\n\r\n";

            $raw .= "--{$boundary}\r\n";
            $raw .= "Content-Type: text/html; charset=UTF-8\r\n";
            $raw .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $raw .= $bodyHtml . "\r\n\r\n";

            $raw .= "--{$boundary}--\r\n";
        } else {
            $raw .= $bodyText . "\r\n";
        }

        // Normalize newlines to CRLF
        $raw = str_replace(["\r\n", "\r", "\n"], "\r\n", $raw);

        // Dot-stuffing per RFC 5321 §4.5.2
        return preg_replace('/^\./m', '..', $raw) ?? $raw;
    }

    /**
     * Prevent SMTP header injection by stripping CR, LF, and null bytes.
     */
    public static function sanitizeHeader(string $val): string
    {
        return trim((string) preg_replace('/[\r\n\0]/', '', $val));
    }

    /**
     * Redact passwords and tokens from error messages.
     */
    public static function redact(string $text): string
    {
        $safe = preg_replace('/(?:password|secret|pass|token|auth)[=:\s]+[^\s,;&]+/i', '[REDACTED]', $text);
        return (string) preg_replace('/[a-zA-Z0-9+\/]{24,}={0,2}/', '[REDACTED]', (string) $safe);
    }

    private static function resolveClientHost(): string
    {
        $hn = gethostname();
        if (is_string($hn) && trim($hn) !== '' && preg_match('/^[a-zA-Z0-9.\-]+$/', $hn) === 1) {
            return trim($hn);
        }
        return 'localhost';
    }
}
