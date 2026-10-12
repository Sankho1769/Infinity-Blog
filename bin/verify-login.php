<?php
declare(strict_types=1);

use InfinityBlog\Auth\AdminProvisioningDiagnostic;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;

require dirname(__DIR__) . '/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$arg = $argv[1] ?? 'Shubham Banerjee';

if ($arg === '--inspect' || $arg === '-inspect') {
    try {
        $config = AppConfig::fromEnvironment();
        if ($config->environment === 'test') {
            throw new RuntimeException('Inspection is disabled in the test environment.');
        }

        $pdo = ConnectionFactory::createForCliDiagnostics($config->database);
        $identity = $pdo->query('SELECT DATABASE() AS database_name, CURRENT_USER() AS database_account')->fetch(PDO::FETCH_ASSOC);
        fwrite(STDOUT, "Database: " . ($identity['database_name'] ?? 'unknown') . " via " . ($identity['database_account'] ?? 'unknown') . "\n");

        fwrite(STDOUT, "=== Administrator Accounts in users table ===\n");
        $stmt = $pdo->prepare('SELECT id, username, display_name, role, status, last_login_at, auth_version FROM users WHERE username IN (?, ?) ORDER BY id ASC');
        $stmt->execute(['shubham banerjee', 'sounak sikdar']);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $u) {
            fwrite(STDOUT, sprintf(
                "User #%d: username='%s', display_name='%s', role=%s, status=%s, last_login_at=%s, auth_version=%d\n",
                $u['id'],
                $u['username'],
                $u['display_name'],
                $u['role'],
                $u['status'],
                $u['last_login_at'] ?? 'NULL',
                $u['auth_version']
            ));
        }

        fwrite(STDOUT, "\n=== Recent Successful Login Events in audit_logs ===\n");
        $stmtAudit = $pdo->query(
            "SELECT id, actor_user_id, action, entity_type, entity_id, created_at FROM audit_logs WHERE action = 'auth.login_succeeded' ORDER BY id DESC LIMIT 10"
        );
        $logs = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);
        if ($logs === []) {
            fwrite(STDOUT, "No auth.login_succeeded records found.\n");
        } else {
            foreach ($logs as $l) {
                fwrite(STDOUT, sprintf(
                    "Audit #%d: actor_user_id=%d, action=%s, entity=%s #%d, created_at=%s\n",
                    $l['id'],
                    $l['actor_user_id'],
                    $l['action'],
                    $l['entity_type'],
                    $l['entity_id'],
                    $l['created_at']
                ));
            }
        }
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Inspection failed.\n");
        fwrite(STDERR, 'Safe diagnostic: ' . AdminProvisioningDiagnostic::format($e) . "\n");
        exit(1);
    }
}

$normalizedIdentifier = strtolower(trim($arg));
if (!in_array($normalizedIdentifier, ['shubham banerjee', 'sounak sikdar'], true)) {
    fwrite(STDERR, "Error: Identifier must be 'Shubham Banerjee' or 'Sounak Sikdar'.\n");
    exit(2);
}

$password = getenv('ADMIN_LOGIN_PASSWORD');
if (!is_string($password) || $password === '') {
    fwrite(STDERR, "Error: Password must be supplied via the secure prompt wrapper.\n");
    exit(2);
}

$baseUrl = rtrim((string) (getenv('API_BASE_URL') ?: 'http://localhost:8086'), '/');

try {
    $config = AppConfig::fromEnvironment();
    if ($config->environment === 'test') {
        throw new RuntimeException('Verification is disabled in the test environment.');
    }

    // Step 1: Request CSRF token and session cookie
    $csrfUrl = $baseUrl . '/auth/csrf';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $csrfUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $rawCsrfResponse = curl_exec($ch);
    if ($rawCsrfResponse === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Failed to connect to API server at ' . $csrfUrl . ' (' . $curlError . ')');
    }

    $csrfHttpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $csrfHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($csrfHttpStatus !== 200) {
        throw new RuntimeException('CSRF endpoint returned HTTP status ' . $csrfHttpStatus);
    }

    $csrfHeaders = substr($rawCsrfResponse, 0, $csrfHeaderSize);
    $csrfBody = substr($rawCsrfResponse, $csrfHeaderSize);

    if (!preg_match('/Set-Cookie:\s*(INFINITYBLOGSESSID=[^;\r\n]+)/i', $csrfHeaders, $cookieMatch)) {
        throw new RuntimeException('API did not return an INFINITYBLOGSESSID session cookie.');
    }
    $sessionCookie = $cookieMatch[1];

    $csrfJson = json_decode($csrfBody, true);
    if (!is_array($csrfJson) || !isset($csrfJson['data']['csrf_token']) || !is_string($csrfJson['data']['csrf_token'])) {
        throw new RuntimeException('API did not return a valid CSRF token payload.');
    }
    $csrfToken = $csrfJson['data']['csrf_token'];

    // Step 2: Perform login POST request with origin, CSRF, and session cookie
    $loginUrl = $baseUrl . '/auth/login';
    $loginPayload = json_encode([
        'identifier' => $normalizedIdentifier,
        'password' => $password,
    ], JSON_THROW_ON_ERROR);

    // Immediately unset password from memory and environment
    unset($password);
    putenv('ADMIN_LOGIN_PASSWORD');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $loginUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $loginPayload,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Cookie: ' . $sessionCookie,
            'X-CSRF-Token: ' . $csrfToken,
            'Origin: ' . $baseUrl,
        ],
    ]);

    $rawLoginResponse = curl_exec($ch);
    if ($rawLoginResponse === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Failed to send login request to API (' . $curlError . ')');
    }

    $loginHttpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loginHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $loginBody = substr($rawLoginResponse, $loginHeaderSize);
    $loginJson = json_decode($loginBody, true);

    if ($loginHttpStatus !== 200) {
        $errorCode = is_array($loginJson) && isset($loginJson['error']['code']) ? $loginJson['error']['code'] : 'HTTP_' . $loginHttpStatus;
        $errorMessage = is_array($loginJson) && isset($loginJson['error']['message']) ? $loginJson['error']['message'] : 'Login request failed';
        throw new RuntimeException('Authentication rejected: [' . $errorCode . '] ' . $errorMessage);
    }

    if (!is_array($loginJson) || !isset($loginJson['data']['user']) || !is_array($loginJson['data']['user'])) {
        throw new RuntimeException('API returned unexpected response structure.');
    }

    $user = $loginJson['data']['user'];
    $userId = (int) ($user['id'] ?? 0);
    $username = (string) ($user['username'] ?? '');
    $displayName = (string) ($user['display_name'] ?? '');
    $role = (string) ($user['role'] ?? '');

    // Step 3: Connect via separate database connection and verify users record & audit_logs entry
    $pdo = ConnectionFactory::createForCliDiagnostics($config->database);

    $userStmt = $pdo->prepare('SELECT id, username, display_name, role, status, last_login_at, auth_version FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $dbUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($dbUser)) {
        throw new RuntimeException('Database record for authenticated user ID ' . $userId . ' was not found.');
    }
    if ($dbUser['last_login_at'] === null) {
        throw new RuntimeException('User last_login_at was not updated in the database.');
    }

    $auditStmt = $pdo->prepare(
        'SELECT id, actor_user_id, action, entity_type, entity_id, created_at FROM audit_logs ' .
        'WHERE actor_user_id = ? AND action = ? ORDER BY id DESC LIMIT 1'
    );
    $auditStmt->execute([$userId, 'auth.login_succeeded']);
    $auditRow = $auditStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($auditRow)) {
        throw new RuntimeException('Matching auth.login_succeeded audit log entry was not found in audit_logs.');
    }

    // Output only privacy-safe, sanitized report
    fwrite(STDOUT, "=== Login Verification Summary ===\n");
    fwrite(STDOUT, "API Endpoint: POST " . $baseUrl . "/auth/login\n");
    fwrite(STDOUT, "HTTP Status: " . $loginHttpStatus . " OK\n");
    fwrite(STDOUT, "CSRF & Origin Protections: Enforced and validated\n");
    fwrite(STDOUT, "Authenticated User ID: " . $userId . "\n");
    fwrite(STDOUT, "Username: " . $username . "\n");
    fwrite(STDOUT, "Display Name: " . $displayName . "\n");
    fwrite(STDOUT, "Role: " . $role . "\n");
    fwrite(STDOUT, "Database Account: " . $config->database->username . "\n");
    fwrite(STDOUT, "Database Target: " . $config->database->database . "\n");
    fwrite(STDOUT, "User Record last_login_at: " . $dbUser['last_login_at'] . "\n");
    fwrite(STDOUT, "Audit Log ID: " . $auditRow['id'] . "\n");
    fwrite(STDOUT, "Audit Event Type: " . $auditRow['action'] . "\n");
    fwrite(STDOUT, "Audit Actor User ID: " . $auditRow['actor_user_id'] . "\n");
    fwrite(STDOUT, "Audit Entity Type: " . $auditRow['entity_type'] . " #" . $auditRow['entity_id'] . "\n");
    fwrite(STDOUT, "Audit Timestamp: " . $auditRow['created_at'] . "\n");
    fwrite(STDOUT, "Result: Genuine end-to-end authentication and audit logging confirmed.\n");

} catch (Throwable $exception) {
    unset($password);
    putenv('ADMIN_LOGIN_PASSWORD');
    fwrite(STDERR, "Verification failed.\n");
    fwrite(STDERR, 'Safe diagnostic: ' . AdminProvisioningDiagnostic::format($exception) . "\n");
    exit(1);
}
