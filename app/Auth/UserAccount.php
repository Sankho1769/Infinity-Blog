<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

final class UserAccount
{
    public int $id;
    public string $username;
    public ?string $email;
    public string $displayName;
    public string $role;
    public string $status;
    public int $authVersion;
    private string $passwordHash;

    public function __construct(
        int $id,
        string $username,
        string $displayName,
        string $role,
        string $status,
        string $passwordHash,
        int $authVersion,
        ?string $email = null
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->displayName = $displayName;
        $this->role = $role;
        $this->status = $status;
        $this->passwordHash = $passwordHash;
        $this->authVersion = $authVersion;
        $this->email = $email;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isUser(): bool
    {
        return $this->role === 'user' || $this->role === 'author';
    }

    public function isReader(): bool
    {
        return $this->role === 'reader' || $this->role === 'user';
    }

    public function isAuthor(): bool
    {
        return $this->role === 'user' || $this->role === 'author';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /** @return array{id: int, username: string, email: ?string, display_name: string, role: string} */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'role' => $this->role,
        ];
    }
}
