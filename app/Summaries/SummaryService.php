<?php
declare(strict_types=1);

namespace InfinityBlog\Summaries;

use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Posts\PostNotFoundException;
use InfinityBlog\Posts\PostRepository;
use Throwable;

final class SummaryService
{
    private PostRepository $posts;
    private SummaryRateLimiter $rateLimiter;
    private ?AiProviderConfig $config;
    private ?SummaryProvider $provider;

    public function __construct(
        PostRepository $posts,
        SummaryRateLimiter $rateLimiter,
        ?AiProviderConfig $config,
        ?SummaryProvider $provider
    ) {
        $this->posts = $posts;
        $this->rateLimiter = $rateLimiter;
        $this->config = $config;
        $this->provider = $provider;
    }

    /** @return array{summary: string, summary_status: string, summary_generated_at: string} */
    public function generate(int $postId, UserAccount $actor, string $sourceAddress): array
    {
        if ($actor->role === 'author') {
            $ownerId = $actor->id;
            $post = $this->posts->findForAuthor($postId, $actor->id);
        } elseif ($actor->role === 'admin') {
            $ownerId = null;
            $post = $this->posts->findForAdmin($postId);
        } else {
            throw new PostNotFoundException();
        }
        if ($post === null) {
            throw new PostNotFoundException();
        }

        $input = $post['title'] . "\n" . $post['content'];
        $maxInputChars = $this->config?->maxInputChars ?? 12000;
        if (self::characterCount($input) > $maxInputChars) {
            throw new SummaryInputTooLargeException();
        }

        $sourceHash = PostRepository::sourceHash($post['title'], $post['content']);
        $this->rateLimiter->consume($actor->id, $postId, $sourceAddress);
        if ($this->config === null || $this->provider === null) {
            $this->posts->markSummaryFailureIfCurrent($postId, $ownerId, $actor->id, $sourceHash);
            throw new SummaryUnavailableException();
        }
        try {
            $summary = self::validateOutput(
                $this->provider->summarize($post['title'], $post['content']),
                $this->config->maxOutputChars
            );
        } catch (Throwable) {
            $this->posts->markSummaryFailureIfCurrent($postId, $ownerId, $actor->id, $sourceHash);
            throw new SummaryUnavailableException();
        }

        $saved = $this->posts->saveSummaryIfCurrent($postId, $ownerId, $actor->id, $sourceHash, $summary);
        if ($saved === null) {
            throw new SummarySourceChangedException();
        }
        return $saved;
    }

    private static function validateOutput(string $output, int $maxCharacters): string
    {
        $output = str_replace(["\r\n", "\r"], "\n", trim($output));
        if ($output === '' || preg_match('//u', $output) !== 1
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $output) === 1
            || preg_match('/<\s*\/?\s*[A-Za-z!][^>]*>/', $output) === 1
            || self::characterCount($output) > $maxCharacters) {
            throw new SummaryOutputInvalidException();
        }
        return $output;
    }

    private static function characterCount(string $value): int
    {
        $count = preg_match_all('/./us', $value);
        return $count === false ? PHP_INT_MAX : $count;
    }
}
