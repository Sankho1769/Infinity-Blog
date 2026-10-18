<?php
declare(strict_types=1);

namespace InfinityBlog\Mail;

use InfinityBlog\Config\EnvironmentLoader;
use Throwable;

final class MailerService
{
    private ?MailTransportInterface $transport = null;
    private ?string $host;
    private ?int $port;
    private ?string $username;
    private ?string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;
    private string $contactToEmail;

    /**
     * @param array<string, string|false>|null $env
     */
    public function __construct(
        ?MailTransportInterface $transport = null,
        ?string $host = null,
        ?int $port = null,
        ?string $username = null,
        ?string $password = null,
        ?string $encryption = null,
        ?string $fromAddress = null,
        ?string $fromName = null,
        ?string $contactToEmail = null,
        ?array $env = null
    ) {
        $env = self::resolveEnvironment($env);

        $this->host = $host ?? self::envVal($env, 'MAIL_HOST', 'SMTP_HOST');
        $this->port = $port ?? self::envIntVal($env, 'MAIL_PORT', 'SMTP_PORT') ?? 587;
        $this->username = $username ?? self::envVal($env, 'MAIL_USERNAME', 'SMTP_USER');
        $this->password = $password ?? self::envVal($env, 'MAIL_PASSWORD', 'SMTP_PASSWORD');
        $this->encryption = $encryption ?? self::envVal($env, 'MAIL_ENCRYPTION', 'SMTP_ENCRYPTION') ?? 'tls';
        $this->fromAddress = $fromAddress ?? self::envVal($env, 'MAIL_FROM_ADDRESS') ?? 'noreply@infinityblog.test';
        $this->fromName = $fromName ?? self::envVal($env, 'MAIL_FROM_NAME') ?? 'Infinity Blog Security';
        $this->contactToEmail = $contactToEmail ?? self::envVal($env, 'CONTACT_TO_EMAIL') ?? 'banerjeeshubham00@gmail.com';

        $this->transport = $transport;
        if ($this->transport === null) {
            $driver = strtolower(self::envVal($env, 'MAIL_TRANSPORT', 'MAIL_DRIVER') ?? '');
            if ($driver === 'array' || $driver === 'memory') {
                $this->transport = new ArrayMailTransport();
            } elseif ($this->hasSmtpCredentials()) {
                $this->transport = new SmtpTransport(
                    (string) $this->host,
                    (int) $this->port,
                    $this->username,
                    $this->password,
                    $this->encryption,
                    $this->fromAddress,
                    $this->fromName
                );
            }
        }
    }

    /**
     * Resolve environment by merging process getenv with .env file.
     *
     * @param array<string, string|false>|null $explicitEnv
     * @return array<string, string>
     */
    private static function resolveEnvironment(?array $explicitEnv = null): array
    {
        if ($explicitEnv !== null) {
            $normalized = [];
            foreach ($explicitEnv as $k => $v) {
                if (is_string($v)) {
                    $normalized[$k] = $v;
                }
            }
            return $normalized;
        }

        static $cachedEnv = null;
        if ($cachedEnv !== null) {
            return $cachedEnv;
        }

        $base = getenv();
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (is_file($envFile)) {
            $cachedEnv = EnvironmentLoader::loadFile($envFile, $base);
        } else {
            $cachedEnv = [];
            foreach ($base as $k => $v) {
                if (is_string($v)) {
                    $cachedEnv[$k] = $v;
                }
            }
        }

        return $cachedEnv;
    }

    /**
     * @param array<string, string> $env
     */
    private static function envVal(array $env, string ...$keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($env[$k]) && trim($env[$k]) !== '') {
                return trim($env[$k]);
            }
            $proc = getenv($k);
            if (is_string($proc) && trim($proc) !== '') {
                return trim($proc);
            }
        }
        return null;
    }

    /**
     * @param array<string, string> $env
     */
    private static function envIntVal(array $env, string ...$keys): ?int
    {
        $val = self::envVal($env, ...$keys);
        if ($val !== null && is_numeric($val)) {
            return (int) $val;
        }
        return null;
    }

    public function hasSmtpCredentials(): bool
    {
        return $this->host !== null
            && $this->port !== null
            && $this->username !== null
            && $this->password !== null;
    }

    /**
     * True if either a custom transport was provided or valid SMTP settings exist.
     */
    public function isConfigured(): bool
    {
        return $this->transport !== null || $this->hasSmtpCredentials();
    }

    public function getTransport(): ?MailTransportInterface
    {
        return $this->transport;
    }

    public function setTransport(MailTransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    public function getContactToEmail(): string
    {
        return $this->contactToEmail;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function getFromName(): string
    {
        return $this->fromName;
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
        if (!$this->isConfigured()) {
            return [
                'delivered' => false,
                'status' => 'BLOCKED',
                'reason' => 'SMTP mail gateway is not configured.',
            ];
        }

        $cleanTo = SmtpTransport::sanitizeHeader($to);
        if (!filter_var($cleanTo, FILTER_VALIDATE_EMAIL)) {
            return [
                'delivered' => false,
                'status' => 'FAILED',
                'reason' => 'Invalid destination email address.',
            ];
        }

        try {
            if ($this->transport !== null) {
                return $this->transport->send($cleanTo, $subject, $bodyText, $bodyHtml, $headers);
            }

            return [
                'delivered' => false,
                'status' => 'BLOCKED',
                'reason' => 'No active mail transport initialized.',
            ];
        } catch (Throwable $e) {
            return [
                'delivered' => false,
                'status' => 'FAILED',
                'reason' => SmtpTransport::redact($e->getMessage()),
            ];
        }
    }

    /**
     * Send two-step verification OTP code to user.
     *
     * @return array{delivered: bool, status: string, reason?: string}
     */
    public function sendOtp(string $to, string $displayName, string $otp): array
    {
        $subject = 'Your Infinity Blog Verification Code: ' . $otp;

        $name = trim($displayName) !== '' ? trim($displayName) : 'User';

        $bodyText = "Hello {$name},\n\n" .
            "Your single-use verification code for logging into Infinity Blog is:\n\n" .
            "  {$otp}\n\n" .
            "This code is valid for 10 minutes. If you did not make this request, please change your password immediately.\n\n" .
            "— Infinity Blog Security\n" .
            "Automated system notification. Please do not reply directly to this address.";

        $htmlEscapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $htmlEscapedOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $bodyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Infinity Blog Verification Code</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b;">
  <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="text-align: center; margin-bottom: 24px;">
      <span style="display: inline-block; padding: 6px 14px; border-radius: 9999px; background: rgba(99, 102, 241, 0.12); color: #4f46e5; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
        Infinity Blog Security
      </span>
      <h2 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 14px 0 6px 0;">Sign-In Verification Code</h2>
      <p style="font-size: 14px; color: #64748b; margin: 0;">Hello {$htmlEscapedName}, use the code below to complete your sign-in.</p>
    </div>

    <div style="text-align: center; background: #f1f5f9; border-radius: 12px; padding: 22px 16px; margin-bottom: 24px; border: 1px dashed #cbd5e1;">
      <div style="font-size: 34px; font-weight: 800; letter-spacing: 0.35em; color: #4338ca; font-family: monospace;">
        {$htmlEscapedOtp}
      </div>
      <div style="font-size: 12px; color: #64748b; margin-top: 8px;">Valid for 10 minutes (single use)</div>
    </div>

    <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 10px; padding: 12px 16px; margin-bottom: 24px; font-size: 13px; color: #92400e; line-height: 1.5;">
      <strong>Security Notice:</strong> If you did not initiate this login request, your account credentials may be compromised. Please sign in immediately and change your password.
    </div>

    <div style="border-top: 1px solid #e2e8f0; padding-top: 18px; text-align: center; font-size: 12px; color: #94a3b8;">
      This is an automated security transmission from Infinity Blog. Please do not reply directly to this message.
    </div>
  </div>
</body>
</html>
HTML;

        return $this->send($to, $subject, $bodyText, $bodyHtml);
    }

    /**
     * Send account registration email verification OTP code to new user.
     *
     * @return array{delivered: bool, status: string, reason?: string}
     */
    public function sendRegistrationOtp(string $to, string $displayName, string $otp): array
    {
        $subject = 'Verify your Infinity Blog account';

        $name = trim($displayName) !== '' ? trim($displayName) : 'New Member';

        $bodyText = "Hello {$name},\n\n" .
            "Thank you for joining Infinity Blog!\n\n" .
            "To verify your email address and activate your account, please enter the following 6-digit verification code:\n\n" .
            "  {$otp}\n\n" .
            "This verification code will expire in 10 minutes (single use).\n" .
            "Security Notice: Never share this code with anyone. Infinity Blog staff will never ask for your code.\n\n" .
            "If you did not create an account on Infinity Blog, please disregard this email.\n\n" .
            "— The Infinity Blog Team\n" .
            "Automated system notification. Please do not reply directly to this address.";

        $htmlEscapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $htmlEscapedOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $bodyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify your Infinity Blog account</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b;">
  <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="text-align: center; margin-bottom: 24px;">
      <span style="display: inline-block; padding: 6px 14px; border-radius: 9999px; background: rgba(99, 102, 241, 0.12); color: #4f46e5; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
        Account Verification
      </span>
      <h2 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 14px 0 6px 0;">Welcome to Infinity Blog!</h2>
      <p style="font-size: 14px; color: #64748b; margin: 0;">Hello {$htmlEscapedName}, please verify your email address to activate your account.</p>
    </div>

    <div style="text-align: center; background: #f1f5f9; border-radius: 12px; padding: 24px 16px; margin-bottom: 24px; border: 1px dashed #cbd5e1;">
      <div style="font-size: 36px; font-weight: 800; letter-spacing: 0.35em; color: #4338ca; font-family: monospace;">
        {$htmlEscapedOtp}
      </div>
      <div style="font-size: 12px; color: #64748b; margin-top: 10px;">Expires in 10 minutes &bull; Single-use code</div>
    </div>

    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 16px; margin-bottom: 24px; font-size: 13px; color: #166534; line-height: 1.5;">
      <strong>Security Notice:</strong> Never share this verification code with anyone. Infinity Blog staff will never ask for your code.
    </div>

    <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 20px;">
      If you did not request this registration, you can safely ignore this email. No account will be created without this verification code.
    </p>

    <div style="border-top: 1px solid #e2e8f0; padding-top: 18px; text-align: center; font-size: 12px; color: #94a3b8;">
      &mdash; The Infinity Blog Team &bull; Automated notification, please do not reply.
    </div>
  </div>
</body>
</html>
HTML;

        return $this->send($to, $subject, $bodyText, $bodyHtml);
    }

    /**
     * Dispatch contact form submission notification to administrator.
     *
     * @param array{
     *     ticket_id?: int|string|null,
     *     name?: string,
     *     email?: string,
     *     subject?: string,
     *     message?: string,
     *     timestamp?: string
     * } $data
     * @return array{delivered: bool, status: string, reason?: string}
     */
    public function sendContactNotification(array $data): array
    {
        $ticketId = $data['ticket_id'] ?? rand(1000, 9999);
        $name = SmtpTransport::sanitizeHeader(trim((string) ($data['name'] ?? 'Visitor')));
        $email = SmtpTransport::sanitizeHeader(trim((string) ($data['email'] ?? '')));
        $subject = SmtpTransport::sanitizeHeader(trim((string) ($data['subject'] ?? 'New Inquiry')));
        $message = trim((string) ($data['message'] ?? ''));
        $timestamp = trim((string) ($data['timestamp'] ?? gmdate('Y-m-d H:i:s')));

        $emailSubject = "[Infinity Blog Contact] #{$ticketId}: {$subject}";

        $headers = [];
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $replyToName = $name !== '' ? $name : 'Visitor';
            $headers['Reply-To'] = "{$replyToName} <{$email}>";
        }

        $bodyText = "New contact inquiry submitted on Infinity Blog\n" .
            "===================================================\n\n" .
            "Ticket Reference : #{$ticketId}\n" .
            "Submission Time  : {$timestamp} (UTC)\n" .
            "Sender Name      : {$name}\n" .
            "Sender Email     : {$email}\n" .
            "Topic / Subject  : {$subject}\n\n" .
            "Message:\n" .
            "---------------------------------------------------\n" .
            "{$message}\n" .
            "---------------------------------------------------\n\n" .
            "Reply directly to this email to respond to {$name} ({$email}).\n";

        $htmlName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $htmlEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $htmlSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $htmlMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $htmlTimestamp = htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8');
        $htmlTicketId = htmlspecialchars((string) $ticketId, ENT_QUOTES, 'UTF-8');

        $bodyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Contact Message #{$htmlTicketId}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b;">
  <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px;">
      <div>
        <span style="display: inline-block; padding: 4px 10px; border-radius: 9999px; background: rgba(99, 102, 241, 0.12); color: #4f46e5; font-size: 11px; font-weight: 700; text-transform: uppercase;">
          Contact Inquiry
        </span>
        <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 8px 0 0 0;">Ticket #{$htmlTicketId}</h2>
      </div>
      <div style="font-size: 12px; color: #64748b; text-align: right;">
        {$htmlTimestamp} UTC
      </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;">
      <tr>
        <td style="padding: 6px 0; color: #64748b; width: 120px; font-weight: 600;">From:</td>
        <td style="padding: 6px 0; color: #0f172a; font-weight: 700;">{$htmlName} &lt;<a href="mailto:{$htmlEmail}" style="color: #4f46e5; text-decoration: none;">{$htmlEmail}</a>&gt;</td>
      </tr>
      <tr>
        <td style="padding: 6px 0; color: #64748b; font-weight: 600;">Subject:</td>
        <td style="padding: 6px 0; color: #0f172a; font-weight: 600;">{$htmlSubject}</td>
      </tr>
    </table>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px;">
      <h3 style="font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; margin: 0 0 10px 0; letter-spacing: 0.05em;">Message Body</h3>
      <div style="font-size: 14px; line-height: 1.6; color: #0f172a; white-space: pre-wrap;">{$htmlMessage}</div>
    </div>

    <div style="background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #0369a1;">
      💡 <strong>Quick Reply:</strong> You can hit <em>Reply</em> in your email client to send your response directly to <strong>{$htmlEmail}</strong>.
    </div>

    <div style="border-top: 1px solid #e2e8f0; padding-top: 16px; margin-top: 24px; text-align: center; font-size: 12px; color: #94a3b8;">
      Infinity Blog CMS &bull; Automated notification dispatch
    </div>
  </div>
</body>
</html>
HTML;

        return $this->send($this->contactToEmail, $emailSubject, $bodyText, $bodyHtml, $headers);
    }
}
