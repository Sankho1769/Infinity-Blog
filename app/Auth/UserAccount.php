<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

final class UserAccount
{
    public int $id;
    public string $username;
    public string $displayName;
    public string $role;
    public string $status;
    private string $passwordHash;

    public function __construct(
        int $id,
        string $username,
        string $displayName,
        string $role,
        string $status,
        string $passwordHash
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->displayName = $displayName;
        $this->role = $role;
        $this->status = $status;
        $this->passwordHash = $passwordHash;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /** @return array{id: int, username: string, display_name: string, role: string} */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->displayName,
            'role' => $this->role,
        ];
    }
}
