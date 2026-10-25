<?php
declare(strict_types=1);

/**
 * Migration: 202610250033_ensure_category_chat_rooms
 * Description: Link existing chat rooms to categories, provision unique persistent chat rooms
 *              for all categories in the database, and enforce UNIQUE (category_id).
 */
return [
    'id' => '202610250033_ensure_category_chat_rooms',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // 1. Link pre-existing rooms by slug or name if they have NULL category_id
        try {
            $catStmt = $pdo->query('SELECT id, name, slug FROM categories');
            if ($catStmt) {
                $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($categories as $cat) {
                    $cId = (int) $cat['id'];
                    $cSlug = (string) $cat['slug'];
                    $cName = (string) $cat['name'];

                    // Check if an existing room matches this category by slug or name
                    $findRoom = $pdo->prepare(
                        'SELECT id FROM chat_rooms WHERE category_id IS NULL AND (slug = :slug OR LOWER(name) = LOWER(:name)) LIMIT 1'
                    );
                    $findRoom->execute([':slug' => $cSlug, ':name' => $cName]);
                    $matchId = $findRoom->fetchColumn();

                    if ($matchId) {
                        $upd = $pdo->prepare('UPDATE chat_rooms SET category_id = :cid, name = :name WHERE id = :id');
                        $upd->execute([':cid' => $cId, ':name' => $cName, ':id' => $matchId]);
                    }
                }
            }
        } catch (\Throwable) {
        }

        // 2. Provision persistent chat rooms for any categories that do not have one yet
        try {
            $catStmt = $pdo->query('SELECT id, name, slug, description, created_by FROM categories WHERE is_active = 1 ORDER BY id ASC');
            if ($catStmt) {
                $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

                // Fetch first admin ID as fallback creator
                $adminId = 1;
                try {
                    $aStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
                    $foundAdmin = $aStmt ? $aStmt->fetchColumn() : null;
                    if ($foundAdmin) {
                        $adminId = (int) $foundAdmin;
                    }
                } catch (\Throwable) {}

                foreach ($categories as $cat) {
                    $cId = (int) $cat['id'];
                    $cName = (string) $cat['name'];
                    $cSlug = (string) $cat['slug'];
                    $cDesc = !empty($cat['description'])
                        ? (string) $cat['description']
                        : "Community discussions, resources, and live chat for {$cName}.";
                    $cCreator = !empty($cat['created_by']) ? (int) $cat['created_by'] : $adminId;

                    // Verify room for category does not already exist
                    $checkStmt = $pdo->prepare('SELECT id FROM chat_rooms WHERE category_id = :cid LIMIT 1');
                    $checkStmt->execute([':cid' => $cId]);
                    if ($checkStmt->fetchColumn()) {
                        continue;
                    }

                    // Ensure unique slug
                    $roomSlug = $cSlug;
                    $slugCheck = $pdo->prepare('SELECT id FROM chat_rooms WHERE slug = :slug LIMIT 1');
                    $slugCheck->execute([':slug' => $roomSlug]);
                    if ($slugCheck->fetchColumn()) {
                        $roomSlug = 'category-' . $cSlug;
                    }

                    $insertStmt = $pdo->prepare(
                        'INSERT INTO chat_rooms (slug, name, description, category_id, created_by, is_active, created_at) ' .
                        'VALUES (:slug, :name, :desc, :cid, :uid, 1, CURRENT_TIMESTAMP)'
                    );
                    $insertStmt->execute([
                        ':slug' => $roomSlug,
                        ':name' => $cName,
                        ':desc' => $cDesc,
                        ':cid' => $cId,
                        ':uid' => $cCreator,
                    ]);
                }
            }
        } catch (\Throwable) {
        }

        // 3. Add UNIQUE constraint on category_id
        if ($driver === 'mysql') {
            $indexes = [];
            $idxStmt = $pdo->query("SHOW INDEX FROM chat_rooms");
            if ($idxStmt) {
                while ($idx = $idxStmt->fetch(PDO::FETCH_ASSOC)) {
                    $indexes[] = $idx['Key_name'];
                }
            }

            if (!in_array('uq_chat_room_category_id', $indexes, true)) {
                $pdo->exec("ALTER TABLE chat_rooms ADD UNIQUE KEY uq_chat_room_category_id (category_id)");
            }
        } elseif ($driver === 'sqlite') {
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_chat_room_category_id ON chat_rooms (category_id)");
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            try {
                $pdo->exec("ALTER TABLE chat_rooms DROP INDEX uq_chat_room_category_id");
            } catch (\Throwable) {}
        } elseif ($driver === 'sqlite') {
            try {
                $pdo->exec("DROP INDEX IF EXISTS uq_chat_room_category_id");
            } catch (\Throwable) {}
        }
    }
];
