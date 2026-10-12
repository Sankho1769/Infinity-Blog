<?php
declare(strict_types=1);

return [
    'id' => '202610130007_expand_categories',
    'up' => static function (PDO $pdo): void {
        $adminId = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($adminId === false) {
            $adminId = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        }
        if ($adminId === false) {
            // In a fresh test environment where users table has not yet been seeded, skip insertion
            return;
        }
        $adminId = (int) $adminId;

        $categories = [
            [
                'name' => 'Web Development',
                'slug' => 'web-development',
                'description' => 'Modern frontend frameworks, backend architecture, APIs, and responsive design.',
            ],
            [
                'name' => 'Programming & Software Engineering',
                'slug' => 'programming-software-engineering',
                'description' => 'Languages, clean code, design patterns, algorithms, and engineering craft.',
            ],
            [
                'name' => 'Artificial Intelligence & Machine Learning',
                'slug' => 'artificial-intelligence-machine-learning',
                'description' => 'Neural networks, generative models, deep learning, LLMs, and applied AI systems.',
            ],
            [
                'name' => 'Data Science & Analytics',
                'slug' => 'data-science-analytics',
                'description' => 'Data visualization, statistics, big data pipelines, machine learning, and insights.',
            ],
            [
                'name' => 'Cybersecurity & Privacy',
                'slug' => 'cybersecurity-privacy',
                'description' => 'Application security, penetration testing, cryptography, and digital privacy.',
            ],
            [
                'name' => 'Career & Productivity',
                'slug' => 'career-productivity',
                'description' => 'Professional development, developer workflows, remote work, and tech careers.',
            ],
            [
                'name' => 'Education & Learning',
                'slug' => 'education-learning',
                'description' => 'Computer science education, coding tutorials, student guides, and learning paths.',
            ],
            [
                'name' => 'Design & Creativity',
                'slug' => 'design-creativity',
                'description' => 'UI/UX design, design systems, glassmorphism, typography, and creative direction.',
            ],
            [
                'name' => 'Business & Startups',
                'slug' => 'business-startups',
                'description' => 'Technology entrepreneurship, SaaS products, startup strategy, and market growth.',
            ],
            [
                'name' => 'Lifestyle & Wellness',
                'slug' => 'lifestyle-wellness',
                'description' => 'Work-life balance, mental health for developers, ergonomic habits, and well-being.',
            ],
            [
                'name' => 'Science & Innovation',
                'slug' => 'science-innovation',
                'description' => 'Emerging technology, computing history, physics, and breakthrough scientific discoveries.',
            ],
            [
                'name' => 'Travel & Nature',
                'slug' => 'travel-nature',
                'description' => 'Digital nomad life, tech hubs around the world, outdoor adventures, and travel tales.',
            ],
        ];

        $checkStmt = $pdo->prepare('SELECT id FROM categories WHERE name = :name OR slug = :slug LIMIT 1');
        $insertStmt = $pdo->prepare(
            'INSERT INTO categories (name, slug, description, is_active, created_by) ' .
            'VALUES (:name, :slug, :description, 1, :created_by)'
        );

        foreach ($categories as $cat) {
            $checkStmt->execute(['name' => $cat['name'], 'slug' => $cat['slug']]);
            if ($checkStmt->fetchColumn() === false) {
                $insertStmt->execute([
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'description' => $cat['description'],
                    'created_by' => $adminId,
                ]);
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $slugs = [
            'web-development',
            'programming-software-engineering',
            'artificial-intelligence-machine-learning',
            'data-science-analytics',
            'cybersecurity-privacy',
            'career-productivity',
            'education-learning',
            'design-creativity',
            'business-startups',
            'lifestyle-wellness',
            'science-innovation',
            'travel-nature',
        ];
        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        // Only delete if no posts reference them
        $deleteStmt = $pdo->prepare(
            "DELETE FROM categories WHERE slug IN ({$placeholders}) AND id NOT IN (SELECT DISTINCT category_id FROM posts WHERE category_id IS NOT NULL)"
        );
        $deleteStmt->execute($slugs);
    },
];
