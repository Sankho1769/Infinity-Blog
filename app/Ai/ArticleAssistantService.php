<?php
declare(strict_types=1);

namespace InfinityBlog\Ai;

use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Summaries\AiProviderConfig;
use InfinityBlog\Summaries\OpenAiResponsesProvider;
use PDO;
use Throwable;

final class ArticleAssistantService
{
    private PDO $pdo;
    private PostRepository $posts;
    private ?AiProviderConfig $aiConfig;

    public function __construct(PDO $pdo, PostRepository $posts, ?AiProviderConfig $aiConfig = null)
    {
        $this->pdo = $pdo;
        $this->posts = $posts;
        $this->aiConfig = $aiConfig;
    }

    /**
     * Ask a question about a specific published article.
     * @return array{
     *     answer: string,
     *     passage_reference: ?string,
     *     source: string
     * }
     */
    public function ask(int $postId, ?int $userId, string $sessionId, string $question): array
    {
        $post = $this->posts->findPublic($postId);
        if ($post === null) {
            throw new \InvalidArgumentException('Article not found.');
        }

        $question = trim($question);
        if ($question === '' || strlen($question) > 1000) {
            throw new \InvalidArgumentException('Question must be between 1 and 1000 characters.');
        }

        $articleTitle = (string) $post['title'];
        $articleBody = strip_tags((string) $post['content']);

        $answer = '';
        $passageRef = null;
        $source = 'local_context';

        if ($this->aiConfig !== null && $this->aiConfig->apiKey !== '') {
            try {
                $provider = new OpenAiResponsesProvider($this->aiConfig);
                $systemInstruction = "You are the Infinity Blog Reading Assistant. Answer the reader's question strictly using the provided article content. " .
                    "If the article does not contain enough information to answer, state clearly: 'The article does not contain sufficient information to answer this question.' " .
                    "Keep your answer concise (2-4 sentences) and quote a relevant passage where appropriate.";

                $prompt = "ARTICLE TITLE: {$articleTitle}\n\nARTICLE CONTENT:\n{$articleBody}\n\nREADER QUESTION: {$question}";
                $aiResponse = $provider->summarize($question, $prompt); // Using provider client
                if (trim($aiResponse) !== '') {
                    $answer = trim($aiResponse);
                    $source = 'ai_provider';
                }
            } catch (Throwable) {
                // Fall back to intelligent contextual extractor
            }
        }

        if ($answer === '') {
            // Intelligent local passage analysis
            $paragraphs = array_filter(array_map('trim', explode("\n", $articleBody)), fn($p) => strlen($p) > 20);
            $keywords = array_filter(
                preg_split('/\W+/u', mb_strtolower($question)),
                fn($w) => strlen($w) > 3 && !in_array($w, ['what', 'when', 'where', 'which', 'who', 'how', 'does', 'this', 'that', 'with', 'from', 'about'], true)
            );

            $bestScore = 0;
            $bestParagraph = '';

            foreach ($paragraphs as $p) {
                $pLower = mb_strtolower($p);
                $score = 0;
                foreach ($keywords as $kw) {
                    if (str_contains($pLower, $kw)) {
                        $score += 1;
                    }
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestParagraph = $p;
                }
            }

            if ($bestScore > 0 && $bestParagraph !== '') {
                $passageRef = mb_substr($bestParagraph, 0, 200) . (mb_strlen($bestParagraph) > 200 ? '...' : '');
                $answer = "Based on the article: \"{$passageRef}\"";
            } else {
                $answer = "The article does not contain specific information directly addressing your query.";
            }
        }

        // Store chat history in database
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_assistant_chats (post_id, user_id, session_id, question, answer, passage_reference, created_at) ' .
            'VALUES (:pid, :uid, :sid, :q, :a, :pref, NOW())'
        );
        $stmt->execute([
            ':pid' => $postId,
            ':uid' => $userId,
            ':sid' => $sessionId,
            ':q' => $question,
            ':a' => $answer,
            ':pref' => $passageRef,
        ]);

        return [
            'answer' => $answer,
            'passage_reference' => $passageRef,
            'source' => $source,
        ];
    }

    /**
     * Get chat history for a session on a post.
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $postId, string $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, question, answer, passage_reference, created_at ' .
            'FROM article_assistant_chats WHERE post_id = :pid AND session_id = :sid ORDER BY created_at ASC LIMIT 30'
        );
        $stmt->execute([':pid' => $postId, ':sid' => $sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
