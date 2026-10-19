<?php
declare(strict_types=1);

namespace InfinityBlog\Ai;

use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Summaries\AiProviderConfig;
use InfinityBlog\Summaries\OpenAiResponsesProvider;
use InfinityBlog\Summaries\SummaryProvider;
use InfinityBlog\Summaries\SummaryProviderException;
use PDO;
use Throwable;

final class ArticleAssistantService
{
    private PDO $pdo;
    private PostRepository $posts;
    private ?AiProviderConfig $aiConfig;
    private ?SummaryProvider $provider;

    public function __construct(
        PDO $pdo,
        PostRepository $posts,
        ?AiProviderConfig $aiConfig = null,
        ?SummaryProvider $provider = null
    ) {
        $this->pdo = $pdo;
        $this->posts = $posts;
        $this->aiConfig = $aiConfig;
        $this->provider = $provider;
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
        $post = $this->posts->findPublished($postId);
        if ($post === null) {
            throw new \InvalidArgumentException('Article not found.');
        }

        $question = trim($question);
        if ($question === '' || strlen($question) > 1000) {
            throw new \InvalidArgumentException('Question must be between 1 and 1000 characters.');
        }

        $articleTitle = (string) $post['title'];
        $rawContent = (string) $post['content'];
        $articleBody = trim(strip_tags($rawContent));
        $postSummary = isset($post['summary']) && is_string($post['summary']) ? trim($post['summary']) : '';
        $categoryName = isset($post['category_name']) && is_string($post['category_name']) ? trim($post['category_name']) : '';

        if ($articleBody === '') {
            return [
                'answer' => 'This article does not contain readable text content to analyze.',
                'passage_reference' => null,
                'source' => 'local_context',
            ];
        }

        $answer = '';
        $passageRef = null;
        $source = 'local_context';

        $provider = $this->provider;
        if ($provider === null && $this->aiConfig !== null && $this->aiConfig->apiKey !== '') {
            $provider = new OpenAiResponsesProvider($this->aiConfig);
        }

        if ($provider !== null) {
            try {
                $prompt = "ARTICLE TITLE: {$articleTitle}\n\nARTICLE CONTENT:\n{$articleBody}\n\nREADER QUESTION: {$question}";
                $aiResponse = $provider->summarize($question, $prompt);
                if (trim($aiResponse) !== '') {
                    $answer = trim($aiResponse);
                    $source = 'ai_provider';
                }
            } catch (SummaryProviderException) {
                throw new \RuntimeException('The AI assistant service encountered an upstream provider error. Please try again.');
            } catch (Throwable $e) {
                throw new \RuntimeException('The AI assistant service encountered an error while processing your question: ' . $e->getMessage() . '. Please try again in a moment.');
            }
        }

        if ($answer === '') {
            // Conversational Intent 1: Greeting
            $isGreeting = (bool) preg_match('/^\s*(hi|hello|hey|greetings|good\s+(morning|afternoon|evening)|howdy|sup|hola)\b[!.? ]*$/i', $question);
            if ($isGreeting) {
                $answer = "Greetings, seeker! I am Dr. Strange, your mystical guide to \"{$articleTitle}\". Ask me any question about the concepts, data, or takeaways covered in this article, or request a summary.";
                $passageRef = null;
            }

            // Conversational Intent 2: Overview / "What is the article about?"
            if ($answer === '') {
                $isOverview = (bool) preg_match('/^\s*(what(\'?s|\s+is)?\s+(this|the)\s+article\s+about|about\s+(this|the)\s+article|give\s+me\s+an\s+overview|article\s+overview|what\s+does\s+(this|the)\s+article\s+(talk\s+about|cover|discuss)|what\s+is\s+it\s+about|tell\s+me\s+about\s+(this|the)\s+article|overview|explain\s+(the\s+)?main\s+concepts?)\b/i', $question);
                if ($isOverview) {
                    if ($postSummary !== '') {
                        $answer = "\"{$articleTitle}\" provides an overview of: " . $postSummary;
                        $passageRef = mb_substr($postSummary, 0, 200) . (mb_strlen($postSummary) > 200 ? '...' : '');
                    } else {
                        $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $articleBody)), fn($p) => strlen($p) > 25));
                        $intro = $paragraphs[0] ?? $articleTitle;
                        $categoryPrefix = $categoryName !== '' ? "Published under {$categoryName}, " : '';
                        $answer = "{$categoryPrefix}\"{$articleTitle}\" focuses on: " . $intro;
                        $passageRef = mb_substr($intro, 0, 200) . (mb_strlen($intro) > 200 ? '...' : '');
                    }
                }
            }

            // Conversational Intent 3: Summary / Key Takeaways
            if ($answer === '') {
                $isSummary = (bool) preg_match('/^\s*(summarize(\s+it|\s+this|\s+the\s+article)?|summary(\s+of\s+(this|the)\s+article)?|tl;?dr|give\s+me\s+a\s+summary|what\s+are\s+(the\s+)?key\s+takeaways|key\s+takeaways|main\s+points|brief\s+summary|recap)\b/i', $question);
                if ($isSummary) {
                    if ($postSummary !== '') {
                        $answer = "Here is a summary of \"{$articleTitle}\":\n\n" . $postSummary;
                        $passageRef = mb_substr($postSummary, 0, 200) . (mb_strlen($postSummary) > 200 ? '...' : '');
                    } else {
                        $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $articleBody)), fn($p) => strlen($p) > 25));
                        $summaryParts = array_slice($paragraphs, 0, 2);
                        $combinedSummary = implode(" ", $summaryParts);
                        $answer = "Key takeaways from \"{$articleTitle}\":\n\n" . $combinedSummary;
                        $passageRef = mb_substr($combinedSummary, 0, 200) . (mb_strlen($combinedSummary) > 200 ? '...' : '');
                    }
                }
            }

            // Conversational Intent 4: Specific Factual / Topic Retrieval
            if ($answer === '') {
                $rawBlocks = preg_split('/\n{2,}/', $articleBody) ?: [];
                $paragraphs = [];
                $currentHeading = '';

                foreach ($rawBlocks as $block) {
                    $trimmed = trim($block);
                    if ($trimmed === '') {
                        continue;
                    }
                    if (preg_match('/^#{1,4}\s+([^\r\n]+)/m', $trimmed, $hm)) {
                        $currentHeading = trim($hm[1]);
                        $stripped = trim(preg_replace('/^#{1,4}\s+[^\r\n]+/m', '', $trimmed));
                        if ($stripped === '') {
                            continue;
                        }
                        $trimmed = $stripped;
                    }
                    $combined = ($currentHeading !== '' ? "{$currentHeading}: " : '') . $trimmed;
                    $paragraphs[] = $combined;
                }

                if ($paragraphs === []) {
                    $paragraphs = array_values(array_filter(array_map('trim', explode("\n", $articleBody)), fn($p) => strlen($p) > 20));
                }

                $stopwords = [
                    'a','about','above','after','again','against','all','am','an','and','any','are','aren\'t',
                    'as','at','be','because','been','before','being','below','between','both','but','by',
                    'can','can\'t','cannot','could','couldn\'t','did','didn\'t','do','does','doesn\'t','doing',
                    'don\'t','down','during','each','few','for','from','further','had','hadn\'t','has','hasn\'t',
                    'have','haven\'t','having','he','he\'d','he\'ll','he\'s','her','here','here\'s','hers',
                    'herself','him','himself','his','how','how\'s','i','i\'d','i\'ll','i\'m','i\'ve','if','in',
                    'into','is','isn\'t','it','it\'s','its','itself','let\'s','me','more','most','mustn\'t',
                    'my','myself','no','nor','not','of','off','on','once','only','or','other','ought','our',
                    'ours','ourselves','out','over','own','same','shan\'t','she','she\'d','she\'ll','she\'s',
                    'should','shouldn\'t','so','some','such','than','that','that\'s','the','their','theirs',
                    'them','themselves','then','there','there\'s','these','they','they\'d','they\'ll','they\'re',
                    'they\'ve','this','those','through','to','too','under','until','up','very','was','wasn\'t',
                    'we','we\'d','we\'re','we\'ve','were','weren\'t','what','what\'s','when','when\'s','where',
                    'where\'s','which','while','who','who\'s','whom','why','why\'s','with','won\'t','would',
                    'wouldn\'t','you','you\'d','you\'ll','you\'re','you\'ve','your','yours','yourself','yourselves',
                ];
                $stopMap = array_flip($stopwords);

                $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $keywords = array_values(array_filter($tokens, fn($w) => !isset($stopMap[$w]) && mb_strlen($w) >= 2));

                $bestScore = 0;
                $bestParagraph = '';

                $qClean = trim(preg_replace('/[?!.,;:]+/', '', mb_strtolower($question)));

                foreach ($paragraphs as $p) {
                    $pLower = mb_strtolower($p);
                    $score = 0;

                    if ($qClean !== '' && str_contains($pLower, $qClean)) {
                        $score += 15;
                    }

                    foreach ($keywords as $kw) {
                        $count = substr_count($pLower, $kw);
                        if ($count === 0 && str_ends_with($kw, 's') && strlen($kw) > 3) {
                            $count = substr_count($pLower, rtrim($kw, 's'));
                        }
                        if ($count === 0 && str_ends_with($kw, 'ing') && strlen($kw) > 4) {
                            $count = substr_count($pLower, substr($kw, 0, -3));
                        }
                        if ($count > 0) {
                            $score += min(5, $count) * 2;
                        }
                    }

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestParagraph = $p;
                    }
                }

                if ($bestScore > 0 && $bestParagraph !== '') {
                    $cleanRef = preg_replace('/^#+\s+/', '', $bestParagraph);
                    $passageRef = mb_substr($cleanRef, 0, 220) . (mb_strlen($cleanRef) > 220 ? '...' : '');
                    $answer = "According to the article: \"{$passageRef}\"";
                } else {
                    // Outside scope of article: extract headings or main topics
                    preg_match_all('/^#{1,4}\s+(.+)$/m', $rawContent, $hMatches);
                    $headingList = array_slice(array_map('trim', $hMatches[1] ?? []), 0, 3);

                    if (!empty($headingList)) {
                        $coveredTopics = implode(', ', array_map(fn($h) => '"' . $h . '"', $headingList));
                        $answer = "This question does not appear to be covered in \"{$articleTitle}\". This article focuses on sections such as {$coveredTopics}. Feel free to ask questions about these topics!";
                    } elseif ($categoryName !== '') {
                        $answer = "This question does not appear to be covered in \"{$articleTitle}\". This article focuses on {$categoryName} topics. Feel free to ask about the main themes discussed in the article!";
                    } else {
                        $answer = "This question does not appear to be covered in \"{$articleTitle}\". Feel free to ask about the key concepts or request a summary of the article.";
                    }
                }
            }
        }

        // Store chat history in database
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_assistant_chats (post_id, user_id, session_id, question, answer, passage_reference, created_at) ' .
            'VALUES (:pid, :uid, :sid, :q, :a, :pref, CURRENT_TIMESTAMP)'
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
