<?php
declare(strict_types=1);

namespace InfinityBlog\Services;

class AISummarizer {
    /**
     * Generate a concise plain-text summary from canonical title and content.
     */
    public static function generateSummary(string $title, string $content): array {
        $apiKey = getenv('GEMINI_API_KEY') ?: getenv('AI_API_KEY') ?: '';
        $cleanContent = trim(strip_tags($content));
        $cleanTitle = trim(strip_tags($title));

        if (mb_strlen($cleanContent) < 20) {
            return [
                'success' => false,
                'error' => 'Article content is too short to generate a meaningful summary.',
                'code' => 'CONTENT_TOO_SHORT',
            ];
        }

        // Bound input length for safety
        $boundedContent = mb_substr($cleanContent, 0, 4000);

        if ($apiKey) {
            // Call Gemini REST API securely from server side
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);
                $prompt = "You are an editorial assistant. Provide a concise, clear plain-text summary (2 to 3 sentences, maximum 60 words) for the following blog article. Do NOT include Markdown formatting, bullet points, HTML tags, or meta-commentary. Output plain text only.\n\nTitle: {$cleanTitle}\n\nContent:\n{$boundedContent}";

                $payload = json_encode([
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 150,
                        'temperature' => 0.2,
                    ]
                ]);

                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($response && $httpCode === 200) {
                    $json = json_decode($response, true);
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $cleanSummary = self::sanitizeSummary($text);
                    if ($cleanSummary) {
                        return ['success' => true, 'summary' => $cleanSummary];
                    }
                }
                error_log("AI provider response code: {$httpCode}, curl error: {$curlErr}");
            } catch (\Throwable $e) {
                error_log("AI provider exception: " . $e->getMessage());
            }
        }

        // Fallback: high quality extractive plain-text summarizer
        // This ensures the application remains fully functional even in offline / local development
        $sentences = preg_split('/(?<=[.?!])\s+/', $cleanContent, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($sentences)) {
            $selected = array_slice($sentences, 0, 2);
            $summary = implode(' ', $selected);
            if (mb_strlen($summary) > 280) {
                $summary = mb_substr($summary, 0, 277) . '...';
            }
            return [
                'success' => true,
                'summary' => self::sanitizeSummary($summary),
            ];
        }

        return [
            'success' => false,
            'error' => 'Unable to generate summary at this time.',
            'code' => 'AI_UNAVAILABLE',
        ];
    }

    private static function sanitizeSummary(string $raw): string {
        $text = strip_tags($raw);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        if (mb_strlen($text) > 400) {
            $text = mb_substr($text, 0, 397) . '...';
        }
        return $text;
    }
}
