<?php
declare(strict_types=1);

namespace InfinityBlog\Audio;

use Throwable;

final class OpenAiTtsProvider implements TtsProvider
{
    private string $apiKey;
    private string $model;
    private int $timeoutSeconds;

    public function __construct(string $apiKey, string $model = 'tts-1', int $timeoutSeconds = 30)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeoutSeconds = max(5, min(120, $timeoutSeconds));
    }

    /**
     * Synthesize audio from text using OpenAI's Audio Speech API.
     *
     * @throws TtsProviderException
     */
    public function synthesize(string $text, string $voice = 'alloy', string $format = 'mp3'): string
    {
        $cleanText = trim($text);
        if ($cleanText === '') {
            throw new TtsProviderException('Cannot synthesize empty text.');
        }

        // OpenAI audio/speech API has a 4096-character limit per request
        if (mb_strlen($cleanText) > 4000) {
            $cleanText = mb_substr($cleanText, 0, 4000) . '...';
        }

        $supportedVoices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
        $selectedVoice = in_array(strtolower($voice), $supportedVoices, true) ? strtolower($voice) : 'alloy';

        $payload = [
            'model' => $this->model,
            'input' => $cleanText,
            'voice' => $selectedVoice,
            'response_format' => $format,
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $ch = curl_init('https://api.openai.com/v1/audio/speech');
        if ($ch === false) {
            throw new TtsProviderException('Failed to initialize cURL for speech synthesis.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: audio/mpeg',
            ],
        ]);

        try {
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || $curlError !== '') {
                throw new TtsProviderException('TTS network error: ' . ($curlError ?: 'Unknown transport failure'));
            }

            if ($statusCode !== 200) {
                throw new TtsProviderException('OpenAI TTS API rejected request with HTTP ' . $statusCode);
            }

            if (!is_string($response) || strlen($response) < 100) {
                throw new TtsProviderException('OpenAI TTS API returned invalid or empty audio stream.');
            }

            return $response;
        } catch (Throwable $exception) {
            if ($exception instanceof TtsProviderException) {
                throw $exception;
            }
            throw new TtsProviderException('TTS generation failed.', 0, $exception);
        }
    }
}
