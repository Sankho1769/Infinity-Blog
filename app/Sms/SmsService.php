<?php
declare(strict_types=1);

namespace InfinityBlog\Sms;

final class SmsService
{
    private ?SmsProviderInterface $provider;

    public function __construct(?SmsProviderInterface $provider = null)
    {
        $this->provider = $provider ?? self::resolveProviderFromEnvironment();
    }

    private static function resolveProviderFromEnvironment(): ?SmsProviderInterface
    {
        $providerType = strtolower(trim((string) (getenv('SMS_PROVIDER') ?: '')));

        if ($providerType === 'array' || $providerType === 'mock') {
            return new ArraySmsProvider();
        }

        $sid = (string) (getenv('SMS_ACCOUNT_SID') ?: getenv('TWILIO_ACCOUNT_SID') ?: getenv('SMS_API_KEY') ?: '');
        $token = (string) (getenv('SMS_AUTH_TOKEN') ?: getenv('TWILIO_AUTH_TOKEN') ?: getenv('SMS_API_SECRET') ?: '');
        $from = (string) (getenv('SMS_FROM_NUMBER') ?: getenv('TWILIO_FROM_NUMBER') ?: '');

        $hasPlaceholders = str_contains($sid, 'replace-with') || str_contains($token, 'replace-with') || str_contains($from, 'replace-with');

        if ($providerType === 'twilio' || (!empty($sid) && !$hasPlaceholders)) {
            if (empty($sid) || empty($token) || empty($from) || $hasPlaceholders) {
                return null; // Unconfigured
            }
            return new TwilioSmsProvider($sid, $token, $from);
        }

        return null;
    }

    /**
     * Dispatch registration OTP SMS.
     *
     * @return array{delivered: bool, status: string, reason?: string, provider?: string}
     */
    public function sendRegistrationOtp(string $to, string $otp): array
    {
        if ($this->provider === null) {
            return [
                'delivered' => false,
                'status' => 'BLOCKED',
                'reason' => 'SMS service is unconfigured. Set SMS_ACCOUNT_SID, SMS_AUTH_TOKEN, and SMS_FROM_NUMBER in .env (or SMS_PROVIDER=array for testing).',
                'provider' => 'none',
            ];
        }

        $message = "Your Infinity Blog registration verification code is: {$otp}. Valid for 10 minutes. Do not share this code.";
        return $this->provider->send($to, $message);
    }

    public function provider(): ?SmsProviderInterface
    {
        return $this->provider;
    }
}
