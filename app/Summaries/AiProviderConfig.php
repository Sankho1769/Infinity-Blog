<?php
declare(strict_types=1);

namespace InfinityBlog\Summaries;

use InfinityBlog\Config\EnvironmentLoader;
use InvalidArgumentException;

final class AiProviderConfig
{
    public string $provider;
    public string $model;
    public string $apiKey;
    public int $timeoutSeconds;
    public int $maxInputChars;
    public int $maxOutputChars;
    public int $userRateLimit;

    private function __construct(
        string $provider,
        string $model,
        string $apiKey,
        int $timeoutSeconds,
        int $maxInputChars,
        int $maxOutputChars,
        int $userRateLimit
    ) {
        $this->provider = $provider;
        $this->model = $model;
        $this->apiKey = $apiKey;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->maxInputChars = $maxInputChars;
        $this->maxOutputChars = $maxOutputChars;
        $this->userRateLimit = $userRateLimit;
    }

    /** @param array<string, string|false> $environment */
    public static function fromArray(array $environment): ?self
    {
        $provider = self::value($environment, 'AI_PROVIDER');
        $model = self::value($environment, 'AI_MODEL');
        $apiKey = self::value($environment, 'AI_API_KEY');
        if ($provider === '' && $model === '' && $apiKey === '') {
            return null;
        }
        if ($provider !== 'openai') {
            throw new InvalidArgumentException('AI_PROVIDER must select a supported provider.');
        }
        if ($model === '' || strlen($model) > 100 || preg_match('/^[A-Za-z0-9._-]+$/', $model) !== 1) {
            throw new InvalidArgumentException('AI_MODEL is invalid.');
        }
        if ($apiKey === '' || strlen($apiKey) > 2048 || preg_match('/[\r\n\x00-\x1F\x7F]/', $apiKey) === 1) {
            throw new InvalidArgumentException('AI_API_KEY is invalid.');
        }

        return new self(
            'openai',
            $model,
            $apiKey,
            self::integer($environment, 'AI_TIMEOUT_SECONDS', 20, 2, 60),
            self::integer($environment, 'AI_MAX_INPUT_CHARS', 12000, 100, 50000),
            self::integer($environment, 'AI_MAX_OUTPUT_CHARS', 1000, 50, 4000),
            self::integer($environment, 'AI_SUMMARY_RATE_LIMIT', 10, 1, 1000)
        );
    }

    public static function fromEnvironment(): ?self
    {
        $root = dirname(__DIR__, 2);
        return self::fromArray(EnvironmentLoader::loadFile($root . '/.env', getenv()));
    }

    public function maxOutputTokens(): int
    {
        return min(2048, max(128, (int) ceil($this->maxOutputChars / 2)));
    }

    /** @param array<string, string|false> $environment */
    private static function value(array $environment, string $name): string
    {
        $value = $environment[$name] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    /** @param array<string, string|false> $environment */
    private static function integer(array $environment, string $name, int $default, int $minimum, int $maximum): int
    {
        $value = self::value($environment, $name);
        if ($value === '') {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => $maximum]]);
        if ($parsed === false) {
            throw new InvalidArgumentException($name . ' is invalid.');
        }
        return (int) $parsed;
    }
}
