<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use JsonException;

final class Request
{
    public string $method;
    public string $path;
    public string $sourceAddress;

    /** @var array<string, string> */
    private array $headers;

    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed>|null */
    private ?array $json = null;
    private bool $jsonRead = false;

    /** @param array<string, string> $headers */
    private function __construct(string $method, string $path, string $sourceAddress, array $headers, array $query)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->sourceAddress = filter_var($sourceAddress, FILTER_VALIDATE_IP) ? $sourceAddress : 'unknown';
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->query = $query;
    }

    public static function fromGlobals(string $baseUrl): self
    {
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!is_string($requestPath)) {
            $requestPath = '/';
        }
        $basePath = parse_url($baseUrl, PHP_URL_PATH);
        if (is_string($basePath) && $basePath !== '' && $basePath !== '/' && str_starts_with($requestPath, $basePath)) {
            $requestPath = substr($requestPath, strlen($basePath));
        } else {
            $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
            if ($scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '.' && str_starts_with($requestPath, $scriptDir)) {
                $requestPath = substr($requestPath, strlen($scriptDir));
            } else {
                $parentDir = rtrim(str_replace('\\', '/', dirname($scriptDir)), '/');
                if ($parentDir !== '' && $parentDir !== '/' && $parentDir !== '.' && str_starts_with($requestPath, $parentDir)) {
                    $requestPath = substr($requestPath, strlen($parentDir));
                }
            }
        }
        $apiPosition = strpos($requestPath, '/api/v1');
        if ($apiPosition !== false) {
            $requestPath = substr($requestPath, $apiPosition + strlen('/api/v1'));
        }
        if ($requestPath === '') {
            $requestPath = '/';
        }

        $headers = [];
        if (function_exists('getallheaders')) {
            $providedHeaders = getallheaders();
            if (is_array($providedHeaders)) {
                foreach ($providedHeaders as $name => $value) {
                    if (is_string($name) && is_string($value)) {
                        $headers[$name] = $value;
                    }
                }
            }
        }
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_') && is_string($value)) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim($requestPath, '/'),
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            $headers,
            is_array($_GET ?? null) ? $_GET : []
        );
    }

    /** @param array<string, string> $headers
     *  @param array<string, mixed> $json
     */
    public static function fromValues(
        string $method,
        string $path,
        string $sourceAddress,
        array $headers = [],
        array $json = [],
        array $query = []
    ): self {
        $request = new self($method, $path, $sourceAddress, $headers, $query);
        $request->json = $json;
        $request->jsonRead = true;

        return $request;
    }

    public function query(string $name): mixed
    {
        return $this->query[$name] ?? null;
    }

    /** @return array<string, mixed> */
    public function queryParameters(): array
    {
        return $this->query;
    }

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;

        return is_string($value) ? trim($value) : null;
    }

    /** @return array<string, mixed> */
    public function jsonObject(int $maxBytes = 16384): array
    {
        if ($maxBytes < 1 || $maxBytes > 1048576) {
            throw new \InvalidArgumentException('The request size limit is invalid.');
        }
        if ($this->jsonRead) {
            return $this->json ?? [];
        }
        $this->jsonRead = true;

        if ($this->json !== null) {
            return $this->json;
        }

        $contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
        if ($contentLength !== false && $contentLength > $maxBytes) {
            throw new RequestException(413, 'REQUEST_TOO_LARGE', 'The request is too large.');
        }

        $contentType = strtolower(trim(explode(';', $this->header('Content-Type') ?? '')[0]));
        if ($contentType !== 'application/json') {
            throw new RequestException(415, 'UNSUPPORTED_MEDIA_TYPE', 'A JSON request is required.');
        }

        $body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
        if (!is_string($body) || strlen($body) > $maxBytes) {
            throw new RequestException(413, 'REQUEST_TOO_LARGE', 'The request is too large.');
        }
        if (trim($body) === '') {
            $this->json = [];
            return $this->json;
        }
        if (substr(ltrim($body), 0, 1) !== '{') {
            throw new RequestException(400, 'BAD_REQUEST', 'The request body is invalid.');
        }

        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RequestException(400, 'BAD_REQUEST', 'The request body is invalid.');
        }
        if (!is_array($decoded)) {
            throw new RequestException(400, 'BAD_REQUEST', 'The request body is invalid.');
        }

        $this->json = $decoded;

        return $this->json;
    }

    public static function detectBasePath(string $baseUrl = ''): string
    {
        $basePath = parse_url($baseUrl, PHP_URL_PATH);
        if (is_string($basePath) && $basePath !== '' && $basePath !== '/') {
            return rtrim($basePath, '/');
        }
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        return ($scriptDir === '' || $scriptDir === '/' || $scriptDir === '.') ? '' : $scriptDir;
    }

    public function isJson(): bool
    {
        $contentType = strtolower(trim(explode(';', $this->header('Content-Type') ?? '')[0]));
        return $contentType === 'application/json';
    }

    public function postParameter(string $name): mixed
    {
        return $_POST[$name] ?? null;
    }

    /** @return array<string, mixed> */
    public function postParameters(): array
    {
        return is_array($_POST ?? null) ? $_POST : [];
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        if ($this->jsonRead && $this->json !== null) {
            return $this->json;
        }
        if ($this->isJson()) {
            try {
                return $this->jsonObject();
            } catch (RequestException) {
                return [];
            }
        }
        return $this->postParameters();
    }

    /** @return array<string, mixed> */
    public function json(int $maxBytes = 16384): array
    {
        return $this->input();
    }
}
