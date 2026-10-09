<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

use PDO;
use Throwable;

final class AdminProvisioner
{
    /** @var array<string, string> */
    private const ADMINS = [
        'shubham banerjee' => 'Shubham Banerjee',
        'sounak sikdar' => 'Sounak Sikdar',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function provision(string $shubhamPassword, string $sounakPassword): void
    {
        self::validatePassword($shubhamPassword);
        self::validatePassword($sounakPassword);
        if (hash_equals($shubhamPassword, $sounakPassword)) {
            throw new AdminProvisioningException('Each administrator must have a distinct password.');
        }

        $this->begin();
        try {
            $lookup = $this->pdo->prepare('SELECT username FROM users WHERE username IN (:shubham, :sounak)');
            $lookup->execute([
                'shubham' => 'shubham banerjee',
                'sounak' => 'sounak sikdar',
            ]);
            if ($lookup->fetchColumn() !== false) {
                throw new AdminProvisioningException('One or more initial administrator accounts already exist.');
            }

            $insert = $this->pdo->prepare(
                "INSERT INTO users (username, display_name, password_hash, role, status) " .
                "VALUES (:username, :display_name, :password_hash, 'admin', 'active')"
            );
            foreach (self::ADMINS as $username => $displayName) {
                $password = $username === 'shubham banerjee' ? $shubhamPassword : $sounakPassword;
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if (!is_string($hash)) {
                    throw new AdminProvisioningException('Administrator credentials could not be stored.');
                }
                $insert->execute([
                    'username' => $username,
                    'display_name' => $displayName,
                    'password_hash' => $hash,
                ]);
            }

            $this->pdo->commit();
        } catch (AdminProvisioningException $exception) {
            $this->rollback();
            throw $exception;
        } catch (Throwable) {
            $this->rollback();
            throw new AdminProvisioningException('Administrator provisioning failed.');
        }
    }

    public function rotatePassword(string $identifier, string $newPassword): void
    {
        $username = UserRepository::normalizeIdentifier($identifier);
        if (!array_key_exists($username, self::ADMINS)) {
            throw new AdminProvisioningException('Only the two configured administrator credentials can be rotated here.');
        }
        self::validatePassword($newPassword);

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new AdminProvisioningException('Administrator credentials could not be stored.');
        }

        $this->begin();
        try {
            $lookup = $this->pdo->prepare("SELECT id FROM users WHERE username = :username AND role = 'admin' LIMIT 1");
            $lookup->execute(['username' => $username]);
            $id = filter_var($lookup->fetchColumn(), FILTER_VALIDATE_INT);
            if ($id === false || $id < 1
                || !(new UserRepository($this->pdo))->replacePasswordHashAndRevokeSessions((int) $id, $hash)) {
                throw new AdminProvisioningException('The configured administrator account does not exist.');
            }
            $this->pdo->commit();
        } catch (AdminProvisioningException $exception) {
            $this->rollback();
            throw $exception;
        } catch (Throwable) {
            $this->rollback();
            throw new AdminProvisioningException('Administrator password rotation failed.');
        }
    }

    private static function validatePassword(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 72) {
            throw new AdminProvisioningException('Passwords must be between 12 and 72 bytes.');
        }
    }

    private function begin(): void
    {
        try {
            if ($this->pdo->inTransaction() || !$this->pdo->beginTransaction()) {
                throw new AdminProvisioningException('Administrator provisioning could not begin.');
            }
        } catch (AdminProvisioningException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AdminProvisioningException('Administrator provisioning could not begin.');
        }
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable) {
                throw new AdminProvisioningException('Administrator operation could not be rolled back.');
            }
        }
    }
}
