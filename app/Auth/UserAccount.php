<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

use InfinityBlog\Posts\ArticleImageHelper;

final class UserAccount
{
    public int $id;
    public string $username;
    public ?string $email;
    public string $displayName;
    public string $role;
    public string $status;
    public int $authVersion;
    public ?string $bio;
    public ?string $location;
    public ?string $interests;
    public ?string $website;
    public ?string $phone;
    public ?string $avatarPath;
    public ?string $bannerPath;
    public string $profileVisibility;
    public ?string $createdAt;
    private string $passwordHash;

    public function __construct(
        int $id,
        string $username,
        string $displayName,
        string $role,
        string $status,
        string $passwordHash,
        int $authVersion,
        ?string $email = null,
        ?string $bio = null,
        ?string $location = null,
        ?string $interests = null,
        ?string $website = null,
        ?string $phone = null,
        ?string $avatarPath = null,
        ?string $bannerPath = null,
        string $profileVisibility = 'public',
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->displayName = $displayName;
        $this->role = $role;
        $this->status = $status;
        $this->passwordHash = $passwordHash;
        $this->authVersion = $authVersion;
        $this->email = $email;
        $this->bio = $bio;
        $this->location = $location;
        $this->interests = $interests;
        $this->website = $website;
        $this->phone = $phone;
        $this->avatarPath = $avatarPath;
        $this->bannerPath = $bannerPath;
        $this->profileVisibility = $profileVisibility;
        $this->createdAt = $createdAt;
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

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->displayName,
            'role' => $this->role,
            'bio' => $this->bio,
            'location' => $this->location,
            'interests' => $this->interests,
            'website' => $this->website,
            'avatar_path' => $this->avatarPath,
            'banner_path' => $this->bannerPath,
            'avatar_url' => ArticleImageHelper::resolveAvatarUrl($this->avatarPath),
            'banner_url' => ArticleImageHelper::resolveBannerUrl($this->bannerPath),
        ];
    }

    /** @return array<string, mixed> */
    public function toPrivateArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'role' => $this->role,
            'status' => $this->status,
            'bio' => $this->bio,
            'location' => $this->location,
            'interests' => $this->interests,
            'website' => $this->website,
            'phone' => $this->phone,
            'avatar_path' => $this->avatarPath,
            'banner_path' => $this->bannerPath,
            'avatar_url' => ArticleImageHelper::resolveAvatarUrl($this->avatarPath),
            'banner_url' => ArticleImageHelper::resolveBannerUrl($this->bannerPath),
            'profile_visibility' => $this->profileVisibility,
            'created_at' => $this->createdAt,
        ];
    }
}
