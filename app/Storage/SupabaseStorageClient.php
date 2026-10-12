<?php
declare(strict_types=1);

namespace InfinityBlog\Storage;

use Throwable;

final class SupabaseStorageClient implements StorageClientInterface
{
    private SupabaseStorageConfig $config;
    private int $timeoutSeconds;

    public function __construct(SupabaseStorageConfig $config, int $timeoutSeconds = 30)
    {
        $this->config = $config;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function upload(string $bucket, string $path, string $binaryData, string $mimeType, bool $upsert = true): string
    {
        if (!$this->config->isConfigured()) {
            throw new StorageException('Supabase Storage is not configured.', 'NOT_CONFIGURED');
        }

        $cleanPath = ltrim($path, '/');
        $endpoint = $this->config->supabaseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . self::encodePath($cleanPath);

        $headers = [
            'Authorization: Bearer ' . $this->config->secretKey,
            'apikey: ' . $this->config->secretKey,
            'Content-Type: ' . $mimeType,
        ];
        if ($upsert) {
            $headers[] = 'x-upsert: true';
        }

        $response = $this->request('POST', $endpoint, $headers, $binaryData);

        if ($response['status'] >= 200 && $response['status'] < 300) {
            return $cleanPath;
        }

        $errorMessage = self::extractErrorMessage($response['body'], 'Failed to upload object to storage.');
        throw new StorageException($errorMessage, 'UPLOAD_FAILED', $response['status']);
    }

    public function delete(string $bucket, string $path): bool
    {
        if (!$this->config->isConfigured()) {
            throw new StorageException('Supabase Storage is not configured.', 'NOT_CONFIGURED');
        }

        $cleanPath = ltrim($path, '/');
        $endpoint = $this->config->supabaseUrl . '/storage/v1/object/' . rawurlencode($bucket);

        $headers = [
            'Authorization: Bearer ' . $this->config->secretKey,
            'apikey: ' . $this->config->secretKey,
            'Content-Type: application/json',
        ];

        $payload = json_encode(['prefixes' => [$cleanPath]], JSON_UNESCAPED_SLASHES);
        $response = $this->request('DELETE', $endpoint, $headers, $payload !== false ? $payload : '{}');

        // If batch delete returns 200, or single delete returns 200/404, consider successful
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return true;
        }

        // Try single-path delete as fallback
        $singleEndpoint = $endpoint . '/' . self::encodePath($cleanPath);
        $singleResponse = $this->request('DELETE', $singleEndpoint, [
            'Authorization: Bearer ' . $this->config->secretKey,
            'apikey: ' . $this->config->secretKey,
        ]);

        return ($singleResponse['status'] >= 200 && $singleResponse['status'] < 300) || $singleResponse['status'] === 404;
    }

    public function getPublicUrl(string $bucket, string $path): string
    {
        $cleanPath = ltrim($path, '/');
        return $this->config->supabaseUrl . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . self::encodePath($cleanPath);
    }

    public function createSignedUrl(string $bucket, string $path, int $expiresInSeconds = 3600): string
    {
        if (!$this->config->isConfigured()) {
            throw new StorageException('Supabase Storage is not configured.', 'NOT_CONFIGURED');
        }

        $cleanPath = ltrim($path, '/');
        $endpoint = $this->config->supabaseUrl . '/storage/v1/object/sign/' . rawurlencode($bucket) . '/' . self::encodePath($cleanPath);

        $headers = [
            'Authorization: Bearer ' . $this->config->secretKey,
            'apikey: ' . $this->config->secretKey,
            'Content-Type: application/json',
        ];

        $payload = json_encode(['expiresIn' => max(60, min(86400, $expiresInSeconds))], JSON_UNESCAPED_SLASHES);
        $response = $this->request('POST', $endpoint, $headers, $payload !== false ? $payload : '{}');

        if ($response['status'] >= 200 && $response['status'] < 300) {
            $data = json_decode($response['body'], true);
            if (is_array($data) && !empty($data['signedURL'])) {
                $signedUrl = (string) $data['signedURL'];
                if (str_starts_with($signedUrl, 'http://') || str_starts_with($signedUrl, 'https://')) {
                    return $signedUrl;
                }
                return $this->config->supabaseUrl . (str_starts_with($signedUrl, '/') ? '' : '/') . $signedUrl;
            }
        }

        $errorMessage = self::extractErrorMessage($response['body'], 'Failed to create signed URL.');
        throw new StorageException($errorMessage, 'SIGN_FAILED', $response['status']);
    }

    public function download(string $bucket, string $path): ?string
    {
        if (!$this->config->isConfigured()) {
            throw new StorageException('Supabase Storage is not configured.', 'NOT_CONFIGURED');
        }

        $cleanPath = ltrim($path, '/');
        $endpoint = $this->config->supabaseUrl . '/storage/v1/object/authenticated/' . rawurlencode($bucket) . '/' . self::encodePath($cleanPath);

        $headers = [
            'Authorization: Bearer ' . $this->config->secretKey,
            'apikey: ' . $this->config->secretKey,
        ];

        $response = $this->request('GET', $endpoint, $headers);

        if ($response['status'] === 404) {
            return null;
        }

        if ($response['status'] >= 200 && $response['status'] < 300) {
            return $response['body'];
        }

        $errorMessage = self::extractErrorMessage($response['body'], 'Failed to download object from storage.');
        throw new StorageException($errorMessage, 'DOWNLOAD_FAILED', $response['status']);
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($responseBody === false) {
                throw new StorageException('Connection to Supabase Storage failed: ' . ($curlError !== '' ? $curlError : 'network timeout'), 'NETWORK_ERROR');
            }

            return ['status' => $statusCode, 'body' => (string) $responseBody];
        }

        // Stream context fallback
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
            ],
        ];
        if ($body !== null) {
            $options['http']['content'] = $body;
        }

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        $statusCode = 500;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('#^HTTP/\S+\s+([0-9]{3})#i', $headerLine, $m)) {
                    $statusCode = (int) $m[1];
                    break;
                }
            }
        }

        if ($result === false && $statusCode === 500) {
            throw new StorageException('Connection to Supabase Storage failed.', 'NETWORK_ERROR');
        }

        return ['status' => $statusCode, 'body' => (string) $result];
    }

    private static function encodePath(string $path): string
    {
        $segments = explode('/', $path);
        return implode('/', array_map('rawurlencode', $segments));
    }

    private static function extractErrorMessage(string $responseBody, string $default): string
    {
        $data = json_decode($responseBody, true);
        if (is_array($data)) {
            if (!empty($data['message']) && is_string($data['message'])) {
                return $data['message'];
            }
            if (!empty($data['error']) && is_string($data['error'])) {
                return $data['error'];
            }
        }
        return $default;
    }
}
