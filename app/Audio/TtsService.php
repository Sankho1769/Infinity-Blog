<?php
declare(strict_types=1);

namespace InfinityBlog\Audio;

use InfinityBlog\Posts\PostNotFoundException;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Storage\MediaService;
use InfinityBlog\Storage\StorageClientInterface;
use InfinityBlog\Storage\SupabaseStorageConfig;
use InfinityBlog\Summaries\AiProviderConfig;
use Throwable;

final class TtsService
{
    private PostRepository $posts;
    private ?TtsProvider $provider;
    private ?AiProviderConfig $aiConfig;
    private ?SupabaseStorageConfig $storageConfig;
    private ?MediaService $media;
    private ?StorageClientInterface $storageClient;

    public function __construct(
        PostRepository $posts,
        ?TtsProvider $provider = null,
        ?AiProviderConfig $aiConfig = null,
        ?SupabaseStorageConfig $storageConfig = null,
        ?MediaService $media = null,
        ?StorageClientInterface $storageClient = null
    ) {
        $this->posts = $posts;
        $this->provider = $provider;
        $this->aiConfig = $aiConfig;
        $this->storageConfig = $storageConfig;
        $this->media = $media;
        $this->storageClient = $storageClient;
    }

    /**
     * Synthesize and store persistent article audio in Supabase audio bucket.
     * Prevents race conditions and duplicate jobs by verifying source hash.
     * Cleans up superseded or previous audio assets safely.
     *
     * @return array{audio_path: string, audio_status: string, audio_duration: int}|null
     */
    public function generateArticleAudio(int $postId, int $authorId, bool $isSummary = false, string $voice = 'alloy'): ?array
    {
        $post = $this->posts->findForAuthor($postId, $authorId);
        if ($post === null) {
            $post = $this->posts->findForAdmin($postId);
        }
        if ($post === null) {
            return null;
        }

        $sourceHash = PostRepository::sourceHash($post['title'], $post['content']);

        // Check if provider and storage are configured
        if ($this->provider === null || $this->storageConfig === null || !$this->storageConfig->isConfigured() || $this->storageClient === null) {
            $this->posts->markAudioFailureIfCurrent($postId, $authorId, $authorId, $sourceHash, 'unconfigured');
            return null;
        }

        $title = (string) ($post['title'] ?? '');
        $content = (string) ($post['content'] ?? '');
        $summary = !empty($post['summary']) ? (string) $post['summary'] : null;

        $targetText = ($isSummary && $summary !== null) ? $summary : $content;
        $cleanText = TextCleaner::cleanForSpeech($title, $targetText);

        if (trim($cleanText) === '') {
            $this->posts->markAudioFailureIfCurrent($postId, $authorId, $authorId, $sourceHash, 'failed');
            return null;
        }

        // Set status to generating
        $this->posts->setAudioStatus($postId, 'generating');

        // Synthesize audio
        try {
            $audioBinary = $this->provider->synthesize($cleanText, $voice, 'mp3');
        } catch (Throwable) {
            $this->posts->markAudioFailureIfCurrent($postId, $authorId, $authorId, $sourceHash, 'failed');
            return null;
        }

        if (strlen($audioBinary) < 100) {
            $this->posts->markAudioFailureIfCurrent($postId, $authorId, $authorId, $sourceHash, 'failed');
            return null;
        }

        $bucket = $this->storageConfig->bucketAudio ?? SupabaseStorageConfig::DEFAULT_BUCKET_AUDIO;
        $filename = ($isSummary ? 'summary_' : 'full_') . 'p' . $postId . '_' . substr($sourceHash, 0, 10) . '_' . time() . '.mp3';
        $storagePath = 'audio/' . $filename;

        // Upload to Supabase bucket
        try {
            $this->storageClient->upload($bucket, $storagePath, $audioBinary, 'audio/mpeg', true);
        } catch (Throwable) {
            $this->posts->markAudioFailureIfCurrent($postId, $authorId, $authorId, $sourceHash, 'failed');
            return null;
        }

        $wordCount = str_word_count($cleanText);
        $durationSeconds = max(5, (int) ceil(($wordCount / 140) * 60));

        // Save metadata if article source hash has not changed
        $saved = $this->posts->saveAudioIfCurrent($postId, $authorId, $authorId, $sourceHash, $storagePath, $durationSeconds, $isSummary);

        if ($saved === null) {
            // Source text changed while audio was generating: delete uploaded audio so no orphan remains
            try {
                $this->storageClient->delete($bucket, $storagePath);
            } catch (Throwable) {
            }
            return null;
        }

        // If a previous audio file was replaced, delete old file from storage
        if (!empty($saved['previous_audio_path']) && $saved['previous_audio_path'] !== $storagePath) {
            try {
                $this->storageClient->delete($bucket, $saved['previous_audio_path']);
            } catch (Throwable) {
            }
        }

        return $saved;
    }

    /**
     * Delete stored audio asset and clear metadata.
     */
    public function deleteArticleAudio(int $postId, int $authorId): bool
    {
        $post = $this->posts->findForAuthor($postId, $authorId);
        if ($post === null) {
            return false;
        }

        $audioPath = $post['audio_path'] ?? null;
        if (!empty($audioPath) && $this->storageConfig !== null && $this->storageClient !== null) {
            $bucket = $this->storageConfig->bucketAudio ?? SupabaseStorageConfig::DEFAULT_BUCKET_AUDIO;
            try {
                $this->storageClient->delete($bucket, $audioPath);
            } catch (Throwable) {
            }
        }

        $this->posts->setAudioStatus($postId, 'idle');
        return true;
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
        $durationSecondsEst = max(10, (int) ceil(($wordCount / 140) * 60));

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
                if (!empty($post['audio_duration'])) {
                    $durationSecondsEst = (int) $post['audio_duration'];
                }
            } catch (Throwable) {
                $audioUrl = null;
            }
        }

        if (!$hasServerAudio) {
            if ($this->provider === null || $this->aiConfig === null) {
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
