<?php
declare(strict_types=1);

namespace InfinityBlog\Audio;

use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Posts\PostNotFoundException;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Storage\MediaService;
use InfinityBlog\Storage\SupabaseStorageConfig;
use InfinityBlog\Summaries\AiProviderConfig;
use Throwable;

final class TtsService
{
    private PostRepository $posts;
    private ?AiProviderConfig $aiConfig;
    private ?SupabaseStorageConfig $storageConfig;
    private ?MediaService $media;

    public function __construct(
        PostRepository $posts,
        ?AiProviderConfig $aiConfig = null,
        ?SupabaseStorageConfig $storageConfig = null,
        ?MediaService $media = null
    ) {
        $this->posts = $posts;
        $this->aiConfig = $aiConfig;
        $this->storageConfig = $storageConfig;
        $this->media = $media;
    }

    /**
     * Get speech data for reader UI (clean text for browser TTS or stored audio URL).
     *
     * @return array{
     *     has_server_audio: bool,
     *     audio_url: ?string,
     *     audio_status: string,
     *     full_text: string,
     *     summary_text: ?string,
     *     word_count: int,
     *     duration_seconds_est: int
     * }
     */
    public function getArticleSpeechData(array $post): array
    {
        $title = (string) ($post['title'] ?? '');
        $content = (string) ($post['content'] ?? '');
        $summary = !empty($post['summary']) ? (string) $post['summary'] : null;

        $cleanFullText = TextCleaner::cleanForSpeech($title, $content);
        $cleanSummaryText = $summary !== null ? TextCleaner::cleanForSpeech($title, $summary) : null;

        $wordCount = str_word_count($cleanFullText);
        $durationSecondsEst = max(10, (int) ceil(($wordCount / 140) * 60)); // ~140 words per minute speaking rate

        $audioUrl = null;
        $hasServerAudio = false;
        $audioStatus = (string) ($post['audio_status'] ?? 'idle');

        if (!empty($post['audio_path']) && $this->storageConfig !== null && $this->storageConfig->isConfigured()) {
            try {
                $bucket = $this->storageConfig->bucketAudio ?? SupabaseStorageConfig::DEFAULT_BUCKET_AUDIO;
                $audioUrl = $this->storageConfig->supabaseUrl . '/storage/v1/object/public/' .
                    $bucket . '/' . ltrim((string) $post['audio_path'], '/');
                $hasServerAudio = true;
                $audioStatus = 'ready';
            } catch (Throwable) {
                $audioUrl = null;
            }
        }

        if (!$hasServerAudio) {
            if ($this->aiConfig === null) {
                $audioStatus = 'unconfigured';
            }
        }

        return [
            'has_server_audio' => $hasServerAudio,
            'audio_url' => $audioUrl,
            'audio_status' => $audioStatus,
            'full_text' => $cleanFullText,
            'summary_text' => $cleanSummaryText,
            'word_count' => $wordCount,
            'duration_seconds_est' => $durationSecondsEst,
        ];
    }
}
