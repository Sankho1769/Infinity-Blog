<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

use InfinityBlog\Summaries\AiProviderConfig;
use InfinityBlog\Summaries\OpenAiResponsesProvider;
use InfinityBlog\Summaries\SummaryProvider;
use InfinityBlog\Summaries\SummaryProviderException;
use Throwable;

final class StudioAiService
{
    private ?AiProviderConfig $aiConfig;
    private ?SummaryProvider $provider;

    public function __construct(?AiProviderConfig $aiConfig = null, ?SummaryProvider $provider = null)
    {
        $this->aiConfig = $aiConfig;
        $this->provider = $provider;
    }

    /**
     * Process an AI writing action on the provided text.
     *
     * @param string $action One of the 8 supported actions
     * @param string $text Selected or context text
     * @param array<string, mixed> $options E.g. ['tone' => 'formal', 'title' => 'Article title']
     * @return array{action: string, original: string, result: string, provider: string}
     */
    public function assist(string $action, string $text, array $options = []): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Please provide text or write some content to analyze.');
        }

        $validActions = [
            'improve_writing',
            'fix_grammar',
            'change_tone',
            'make_shorter',
            'make_longer',
            'simplify',
            'suggest_headline',
            'continue_writing',
        ];

        if (!in_array($action, $validActions, true)) {
            throw new \InvalidArgumentException('Unsupported AI writing action.');
        }

        // Try upstream provider if available
        $provider = $this->provider;
        if ($provider === null && $this->aiConfig !== null && $this->aiConfig->apiKey !== '') {
            $provider = new OpenAiResponsesProvider($this->aiConfig);
        }

        $providerError = null;
        if ($provider !== null) {
            try {
                $prompt = $this->buildPrompt($action, $text, $options);
                $response = $provider->summarize("AI Writing Assistant: {$action}", $prompt);
                $cleanResult = trim($response);
                if ($cleanResult !== '') {
                    return [
                        'action' => $action,
                        'original' => $text,
                        'result' => $cleanResult,
                        'provider' => 'ai_provider',
                        'provider_name' => 'OpenAI',
                        'is_fallback' => false,
                    ];
                }
            } catch (Throwable $e) {
                // Provider failure recorded honestly
                $providerError = $e->getMessage() ?: get_class($e);
            }
        }

        // Contextual rule-based transformation fallback
        $result = $this->executeFallback($action, $text, $options);

        return [
            'action' => $action,
            'original' => $text,
            'result' => $result,
            'provider' => 'local_editorial_engine',
            'provider_name' => 'Local Editorial Engine',
            'is_fallback' => true,
            'provider_error' => $providerError,
        ];
    }

    private function buildPrompt(string $action, string $text, array $options): string
    {
        $tone = (string) ($options['tone'] ?? 'engaging and polished');
        $title = (string) ($options['title'] ?? 'Article');

        return match ($action) {
            'improve_writing' => "You are an expert editorial writing coach. Enhance the clarity, cadence, and expressive flow of the following passage from the article \"{$title}\". Preserve the author's core ideas, nuance, and intent, but elevate the prose to be engaging and vivid:\n\n{$text}\n\nReturn ONLY the improved text.",
            'fix_grammar' => "You are a meticulous copy editor. Correct all spelling, grammar, punctuation, and typographical issues in this passage from \"{$title}\". Maintain the author's original voice:\n\n{$text}\n\nReturn ONLY the corrected text.",
            'change_tone' => "Rewrite the following passage in a {$tone} tone. Keep the core meaning intact while adapting vocabulary and sentence structure appropriately:\n\n{$text}\n\nReturn ONLY the rewritten text.",
            'make_shorter' => "Make the following text more concise, direct, and punchy. Eliminate filler, redundancy, and fluff while retaining all crucial arguments and facts:\n\n{$text}\n\nReturn ONLY the condensed text.",
            'make_longer' => "Expand the following passage by deepening the explanation, illustrating key points with vivid context or examples, and strengthening transitions. Maintain a cohesive, natural voice:\n\n{$text}\n\nReturn ONLY the expanded text.",
            'simplify' => "Rewrite the following passage using simple, accessible language (plain English at an 8th-grade reading level). Break complex sentences into clear thoughts:\n\n{$text}\n\nReturn ONLY the simplified text.",
            'suggest_headline' => "Based on the following content from \"{$title}\", generate 5 compelling, magnetic, and distinct headlines or titles (ranging from thought-provoking to actionable):\n\n{$text}\n\nReturn each headline on a new line numbered 1 to 5.",
            'continue_writing' => "Read the following article draft up to this point, then seamlessly write the next 2-3 paragraphs in the exact same voice, style, and momentum:\n\n{$text}\n\nReturn ONLY the continuation.",
            default => $text,
        };
    }

    private function executeFallback(string $action, string $text, array $options): string
    {
        $tone = (string) ($options['tone'] ?? 'engaging and polished');

        switch ($action) {
            case 'improve_writing':
                // Clean up spacing, refine passive or repetitive constructs
                $enhanced = preg_replace('/\s+/', ' ', $text);
                $enhanced = preg_replace('/\b(in order to)\b/i', 'to', (string) $enhanced);
                $enhanced = preg_replace('/\b(due to the fact that)\b/i', 'because', (string) $enhanced);
                $enhanced = preg_replace('/\b(at this point in time)\b/i', 'currently', (string) $enhanced);
                $enhanced = preg_replace('/\b(a lot of)\b/i', 'numerous', (string) $enhanced);
                $enhanced = preg_replace('/\b(very unique)\b/i', 'distinct', (string) $enhanced);
                $enhanced = preg_replace('/\b(utilize)\b/i', 'use', (string) $enhanced);
                return trim((string) $enhanced);

            case 'fix_grammar':
                $fixed = preg_replace('/\s+([.,;:!?])/', '$1', $text);
                $fixed = preg_replace('/([.,;:!?])(?=[^\s\d])/', '$1 ', (string) $fixed);
                $fixed = preg_replace('/\b(teh)\b/i', 'the', (string) $fixed);
                $fixed = preg_replace('/\b(recieve)\b/i', 'receive', (string) $fixed);
                $fixed = preg_replace('/\b(seperate)\b/i', 'separate', (string) $fixed);
                $fixed = preg_replace('/\b(dont)\b/i', "don't", (string) $fixed);
                $fixed = preg_replace('/\b(cant)\b/i', "can't", (string) $fixed);
                $fixed = preg_replace('/\b(its a)\b/i', "it's a", (string) $fixed);
                return ucfirst(trim((string) $fixed));

            case 'change_tone':
                if (stripos($tone, 'formal') !== false) {
                    $res = preg_replace('/\b(gonna|wanna)\b/i', 'intending to', $text);
                    $res = preg_replace('/\b(stuff|things)\b/i', 'elements', (string) $res);
                    return "In evaluating this perspective, " . lcfirst(trim((string) $res));
                }
                if (stripos($tone, 'casual') !== false || stripos($tone, 'conversational') !== false) {
                    return "Here's the real story: " . trim($text);
                }
                return trim($text) . " Ultimately, this shapes how we perceive the narrative moving forward.";

            case 'make_shorter':
                // Extract first 1-2 sentences or shorten long clauses
                $sentences = preg_split('/(?<=[.?!])\s+/', $text);
                if (is_array($sentences) && count($sentences) > 2) {
                    return trim(implode(' ', array_slice($sentences, 0, 2)));
                }
                return trim($text);

            case 'make_longer':
                return trim($text) . "\n\nFurthermore, when we examine the broader implications, this dynamic reveals how small foundational choices compound over time. By observing how creators and thinkers approach this challenge, we uncover actionable strategies that bridge theory with everyday practice.";

            case 'simplify':
                $simple = preg_replace('/\b(consequently|furthermore|moreover)\b/i', 'also', $text);
                $simple = preg_replace('/\b(subsequently)\b/i', 'then', (string) $simple);
                $simple = preg_replace('/\b(demonstrates)\b/i', 'shows', (string) $simple);
                $simple = preg_replace('/\b(facilitate)\b/i', 'help', (string) $simple);
                return trim((string) $simple);

            case 'suggest_headline':
                $clean = trim(preg_replace('/[#*_`]/', '', $text));
                $firstWords = implode(' ', array_slice(explode(' ', $clean), 0, 6));
                return "1. The Future of {$firstWords}: A Modern Guide\n" .
                       "2. Why {$firstWords} Matters More Than Ever\n" .
                       "3. Mastering {$firstWords}: Key Insights & Practical Strategies\n" .
                       "4. The Unseen Power Behind {$firstWords}\n" .
                       "5. How to Rethink {$firstWords} in Today's World";

            case 'continue_writing':
                return "Building on this foundation, the next question becomes how to sustain momentum in practice. Rather than treating this as a one-time effort, the most resilient approaches integrate deliberate checkpoints and feedback loops. In doing so, we create space for continuous refinement and deeper creative resonance.";

            default:
                return $text;
        }
    }
}
