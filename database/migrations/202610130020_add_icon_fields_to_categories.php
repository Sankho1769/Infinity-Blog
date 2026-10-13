<?php
declare(strict_types=1);

/**
 * Migration 202610130020: Add icon_name and custom_logo_path to categories table.
 */
return [
    'id' => '202610130020_add_icon_fields_to_categories',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $catCols = $pdo->query('PRAGMA table_info(categories)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($catCols, 'name');
            if (!in_array('icon_name', $colNames, true)) {
                $pdo->exec('ALTER TABLE categories ADD COLUMN icon_name VARCHAR(64) NULL');
            }
            if (!in_array('custom_logo_path', $colNames, true)) {
                $pdo->exec('ALTER TABLE categories ADD COLUMN custom_logo_path VARCHAR(255) NULL');
            }
        } else {
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'categories'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if (!in_array('icon_name', $existing, true)) {
                $pdo->exec('ALTER TABLE categories ADD COLUMN icon_name VARCHAR(64) NULL AFTER description');
            }
            if (!in_array('custom_logo_path', $existing, true)) {
                $pdo->exec('ALTER TABLE categories ADD COLUMN custom_logo_path VARCHAR(255) NULL AFTER icon_name');
            }
        }

        // Seed distinct icons for the existing 12 categories and standard categories
        $iconMap = [
            'web-development' => 'globe-code',
            'programming-software-engineering' => 'terminal-code',
            'artificial-intelligence-machine-learning' => 'ai-brain',
            'data-science-analytics' => 'bar-chart',
            'cybersecurity-privacy' => 'shield-lock',
            'career-productivity' => 'briefcase-check',
            'education-learning' => 'graduation-cap',
            'design-creativity' => 'palette',
            'business-startups' => 'rocket',
            'lifestyle-wellness' => 'heart-sun',
            'science-innovation' => 'atom',
            'travel-nature' => 'compass',
            'tech-insights' => 'cpu',
            'math' => 'math-pi',
        ];

        $update = $pdo->prepare('UPDATE categories SET icon_name = :icon WHERE slug = :slug AND (icon_name IS NULL OR icon_name = "")');
        foreach ($iconMap as $slug => $icon) {
            $update->execute([
                ':icon' => $icon,
                ':slug' => $slug,
            ]);
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'sqlite') {
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'categories'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if (in_array('custom_logo_path', $existing, true)) {
                $pdo->exec('ALTER TABLE categories DROP COLUMN custom_logo_path');
            }
            if (in_array('icon_name', $existing, true)) {
                $pdo->exec('ALTER TABLE categories DROP COLUMN icon_name');
            }
        }
    },
];
