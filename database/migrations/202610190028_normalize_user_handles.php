<?php
declare(strict_types=1);

return [
    'id' => '202610190028_normalize_user_handles',
    'up' => static function (PDO $pdo): void {
        $stmt = $pdo->query('SELECT id, username, display_name, email FROM users ORDER BY id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $takenHandles = [];
        foreach ($rows as $r) {
            $u = (string) $r['username'];
            if (!str_contains($u, '@') && !str_contains($u, ' ') && preg_match('/^[a-zA-Z0-9._]{2,30}$/', $u) === 1) {
                $takenHandles[strtolower($u)] = (int) $r['id'];
            }
        }

        $updateStmt = $pdo->prepare('UPDATE users SET username = :username WHERE id = :id');

        foreach ($rows as $r) {
            $userId = (int) $r['id'];
            $username = (string) $r['username'];
            $displayName = (string) ($r['display_name'] ?? '');
            $email = (string) ($r['email'] ?? '');

            $isClean = !str_contains($username, '@')
                && !str_contains($username, ' ')
                && preg_match('/^[a-zA-Z0-9._]{2,30}$/', $username) === 1;

            if ($isClean) {
                continue;
            }

            $source = trim($displayName);
            if ($source === '' && $email !== '') {
                $parts = explode('@', $email, 2);
                $source = $parts[0] ?? '';
            }
            if ($source === '') {
                $source = 'user_' . $userId;
            }

            $base = \InfinityBlog\Support\UserIdentityHelper::slugifyHandle($source);
            if (strlen($base) < 2) {
                $base = 'user_' . $userId;
            }
            if (strlen($base) > 26) {
                $base = substr($base, 0, 26);
            }

            $candidate = $base;
            $counter = 2;
            while (isset($takenHandles[$candidate]) && $takenHandles[$candidate] !== $userId) {
                $suffix = '_' . $counter;
                $candidate = substr($base, 0, 30 - strlen($suffix)) . $suffix;
                $counter++;
            }

            $takenHandles[$candidate] = $userId;
            $updateStmt->execute([
                'username' => $candidate,
                'id' => $userId,
            ]);
        }
    },
    'down' => static function (PDO $pdo): void {
        // Safe no-op: user handles do not need to revert to email addresses
    },
];
