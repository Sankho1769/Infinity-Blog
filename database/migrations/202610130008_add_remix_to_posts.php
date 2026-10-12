<?php
declare(strict_types=1);

return [
    'id' => '202610130008_add_remix_to_posts',
    'up' => static function (PDO $pdo): void {
        // Add remixed_from_post_id column, foreign key, and index to posts table
        $pdo->exec(
            'ALTER TABLE posts ' .
            'ADD COLUMN remixed_from_post_id BIGINT UNSIGNED NULL AFTER category_id, ' .
            'ADD INDEX idx_posts_remixed_from (remixed_from_post_id), ' .
            'ADD CONSTRAINT fk_posts_remixed_from FOREIGN KEY (remixed_from_post_id) REFERENCES posts (id) ON DELETE SET NULL ON UPDATE CASCADE'
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec(
            'ALTER TABLE posts ' .
            'DROP FOREIGN KEY fk_posts_remixed_from, ' .
            'DROP INDEX idx_posts_remixed_from, ' .
            'DROP COLUMN remixed_from_post_id'
        );
    },
];
