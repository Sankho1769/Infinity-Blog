<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

use PDO;

final class StudioDataService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get studio data for a given user and post/draft.
     *
     * @return array{
     *     subtitle: string,
     *     research_notes: string,
     *     quick_notes: array<int, array{id: string, text: string, done: bool}>,
     *     target_words: int,
     *     settings: array<string, mixed>
     * }
     */
    public function getStudioData(int $userId, string $draftKey, ?int $postId = null): array
    {
        try {
            if ($postId !== null) {
                $stmt = $this->pdo->prepare(
                    'SELECT subtitle, research_notes, quick_notes, target_words, settings ' .
                    'FROM post_studio_data ' .
                    'WHERE user_id = :user_id AND (post_id = :post_id OR draft_key = :draft_key) ' .
                    'ORDER BY updated_at DESC LIMIT 1'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'post_id' => $postId,
                    'draft_key' => $draftKey,
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    'SELECT subtitle, research_notes, quick_notes, target_words, settings ' .
                    'FROM post_studio_data ' .
                    'WHERE user_id = :user_id AND draft_key = :draft_key ' .
                    'ORDER BY updated_at DESC LIMIT 1'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'draft_key' => $draftKey,
                ]);
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $row = false;
        }

        $defaultQuickNotes = [
            ['id' => 'qn-1', 'text' => 'Add statistic about screen time', 'done' => true],
            ['id' => 'qn-2', 'text' => 'Include personal experience', 'done' => false],
            ['id' => 'qn-3', 'text' => 'Find a relevant quote', 'done' => false],
            ['id' => 'qn-4', 'text' => 'Add image suggestions', 'done' => false],
        ];

        $defaultSettings = [
            'theme' => 'lavender',
            'font' => 'serif',
            'textSize' => 18,
            'editorWidth' => 'comfortable',
            'lineSpacing' => 'relaxed',
            'focusDefault' => false,
        ];

        if (!$row) {
            return [
                'subtitle' => '',
                'research_notes' => '',
                'quick_notes' => $defaultQuickNotes,
                'target_words' => 2000,
                'settings' => $defaultSettings,
            ];
        }

        $quickNotes = [];
        if (!empty($row['quick_notes'])) {
            $decoded = json_decode((string) $row['quick_notes'], true);
            if (is_array($decoded)) {
                $quickNotes = $decoded;
            }
        }
        if (empty($quickNotes)) {
            $quickNotes = $defaultQuickNotes;
        }

        $settings = $defaultSettings;
        if (!empty($row['settings'])) {
            $decodedSettings = json_decode((string) $row['settings'], true);
            if (is_array($decodedSettings)) {
                $settings = array_merge($defaultSettings, $decodedSettings);
            }
        }

        return [
            'subtitle' => (string) ($row['subtitle'] ?? ''),
            'research_notes' => (string) ($row['research_notes'] ?? ''),
            'quick_notes' => $quickNotes,
            'target_words' => (int) ($row['target_words'] ?? 2000),
            'settings' => $settings,
        ];
    }

    /**
     * Upsert studio data for a given user and post/draft.
     *
     * @param array<int, array{id: string, text: string, done: bool}>|null $quickNotes
     * @param array<string, mixed>|null $settings
     */
    public function saveStudioData(
        int $userId,
        string $draftKey,
        ?int $postId,
        ?string $subtitle,
        ?string $researchNotes,
        ?array $quickNotes,
        ?int $targetWords,
        ?array $settings
    ): void {
        $encodedQuickNotes = $quickNotes !== null ? json_encode($quickNotes, JSON_UNESCAPED_UNICODE) : null;
        $encodedSettings = $settings !== null ? json_encode($settings, JSON_UNESCAPED_UNICODE) : null;
        $words = $targetWords !== null && $targetWords > 0 ? $targetWords : 2000;

        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO post_studio_data (user_id, post_id, draft_key, subtitle, research_notes, quick_notes, target_words, settings, updated_at) ' .
                    'VALUES (:user_id, :post_id, :draft_key, :subtitle, :research_notes, :quick_notes, :target_words, :settings, CURRENT_TIMESTAMP) ' .
                    'ON CONFLICT(user_id, draft_key) DO UPDATE SET ' .
                    'post_id = COALESCE(excluded.post_id, post_studio_data.post_id), ' .
                    'subtitle = COALESCE(excluded.subtitle, post_studio_data.subtitle), ' .
                    'research_notes = COALESCE(excluded.research_notes, post_studio_data.research_notes), ' .
                    'quick_notes = COALESCE(excluded.quick_notes, post_studio_data.quick_notes), ' .
                    'target_words = COALESCE(excluded.target_words, post_studio_data.target_words), ' .
                    'settings = COALESCE(excluded.settings, post_studio_data.settings), ' .
                    'updated_at = CURRENT_TIMESTAMP'
                );
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO post_studio_data (user_id, post_id, draft_key, subtitle, research_notes, quick_notes, target_words, settings) ' .
                    'VALUES (:user_id, :post_id, :draft_key, :subtitle, :research_notes, :quick_notes, :target_words, :settings) ' .
                    'ON DUPLICATE KEY UPDATE ' .
                    'post_id = COALESCE(VALUES(post_id), post_id), ' .
                    'subtitle = COALESCE(VALUES(subtitle), subtitle), ' .
                    'research_notes = COALESCE(VALUES(research_notes), research_notes), ' .
                    'quick_notes = COALESCE(VALUES(quick_notes), quick_notes), ' .
                    'target_words = COALESCE(VALUES(target_words), target_words), ' .
                    'settings = COALESCE(VALUES(settings), settings), ' .
                    'updated_at = CURRENT_TIMESTAMP'
                );
            }

            $stmt->execute([
                'user_id' => $userId,
                'post_id' => $postId,
                'draft_key' => $draftKey,
                'subtitle' => $subtitle,
                'research_notes' => $researchNotes,
                'quick_notes' => $encodedQuickNotes,
                'target_words' => $words,
                'settings' => $encodedSettings,
            ]);
        } catch (\Throwable) {}
    }
}
