<?php
declare(strict_types=1);

namespace InfinityBlog\Sms;

use Throwable;

final class TwilioSmsProvider implements SmsProviderInterface
{
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;

    public function __construct(string $accountSid, string $authToken, string $fromNumber)
    {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->fromNumber = $fromNumber;
    }

    public function send(string $to, string $message): array
    {
        if (trim($this->accountSid) === '' || trim($this->authToken) === '' || trim($this->fromNumber) === '') {
            return [
                'delivered' => false,
                'status' => 'BLOCKED',
                'reason' => 'Twilio credentials or sender phone number are missing.',
                'provider' => 'twilio',
            ];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($this->accountSid) . '/Messages.json';
        $postData = http_build_query([
            'To' => $to,
            'From' => $this->fromNumber,
            'Body' => $message,
        ]);

        $options = [
            'http' => [
                'header' => [
                    'Authorization: Basic ' . base64_encode($this->accountSid . ':' . $this->authToken),
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                'method' => 'POST',
                'content' => $postData,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ];

        try {
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return [
                    'delivered' => false,
                    'status' => 'FAILED',
                    'reason' => 'Network request to Twilio API failed.',
                    'provider' => 'twilio',
                ];
            }

            $decoded = json_decode($response, true);
            $statusCode = 0;
            if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $matches)) {
                $statusCode = (int) $matches[1];
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'delivered' => true,
                    'status' => 'SENT',
                    'provider' => 'twilio',
                ];
            }

            $errMessage = is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : 'Twilio returned HTTP ' . $statusCode;
            return [
                'delivered' => false,
                'status' => 'FAILED',
                'reason' => $errMessage,
                'provider' => 'twilio',
            ];
        } catch (Throwable $e) {
            return [
                'delivered' => false,
                'status' => 'FAILED',
                'reason' => 'Exception during Twilio dispatch: ' . $e->getMessage(),
                'provider' => 'twilio',
            ];
        }
    }
}
