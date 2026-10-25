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
    public ?string $instagramUrl;
    public ?string $facebookUrl;
    public ?string $githubUrl;
    public ?string $whatsappNumber;
    public bool $whatsappPublic;
    public ?string $twitterUrl;
    public ?string $linkedinUrl;
    public ?string $phone;
    public ?string $mobileNumberCiphertext;
    public ?string $mobileNumberHash;
    public ?string $mobileNumberVerifiedAt;
    public ?string $avatarPath;
    public ?string $bannerPath;
    public string $profileVisibility;
    public bool $showInRecommendations;
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
        ?string $createdAt = null,
        ?string $mobileNumberCiphertext = null,
        ?string $mobileNumberHash = null,
        ?string $mobileNumberVerifiedAt = null,
        ?string $instagramUrl = null,
        ?string $facebookUrl = null,
        ?string $githubUrl = null,
        ?string $whatsappNumber = null,
        bool $whatsappPublic = false,
        ?string $twitterUrl = null,
        ?string $linkedinUrl = null,
        bool $showInRecommendations = true
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
        $this->mobileNumberCiphertext = $mobileNumberCiphertext;
        $this->mobileNumberHash = $mobileNumberHash;
        $this->mobileNumberVerifiedAt = $mobileNumberVerifiedAt;
        $this->avatarPath = $avatarPath;
        $this->bannerPath = $bannerPath;
        $this->profileVisibility = $profileVisibility;
        $this->showInRecommendations = $showInRecommendations;
        $this->createdAt = $createdAt;
        $this->instagramUrl = $instagramUrl;
        $this->facebookUrl = $facebookUrl;
        $this->githubUrl = $githubUrl;
        $this->whatsappNumber = $whatsappNumber;
        $this->whatsappPublic = $whatsappPublic;
        $this->twitterUrl = $twitterUrl;
        $this->linkedinUrl = $linkedinUrl;
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
        $whatsappLink = null;
        if ($this->whatsappPublic && !empty($this->whatsappNumber)) {
            $digits = preg_replace('/\D+/', '', $this->whatsappNumber);
            if (!empty($digits)) {
                $whatsappLink = 'https://wa.me/' . $digits;
            }
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'handle' => \InfinityBlog\Support\UserIdentityHelper::formatHandle($this->username, $this->displayName),
            'display_name' => $this->displayName,
            'role' => $this->role,
            'bio' => $this->bio,
            'location' => $this->location,
            'interests' => $this->interests,
            'website' => $this->website,
            'instagram_url' => $this->instagramUrl,
            'facebook_url' => $this->facebookUrl,
            'github_url' => $this->githubUrl,
            'whatsapp_number' => $this->whatsappPublic ? $this->whatsappNumber : null,
            'whatsapp_public' => $this->whatsappPublic,
            'whatsapp_link' => $whatsappLink,
            'twitter_url' => $this->twitterUrl,
            'x_url' => $this->twitterUrl,
            'linkedin_url' => $this->linkedinUrl,
            'avatar_path' => $this->avatarPath,
            'banner_path' => $this->bannerPath,
            'avatar_url' => ArticleImageHelper::resolveAvatarUrl($this->avatarPath),
            'banner_url' => ArticleImageHelper::resolveBannerUrl($this->bannerPath),
            'show_in_recommendations' => $this->showInRecommendations,
        ];
    }

    /** @return array<string, mixed> */
    public function toPrivateArray(): array
    {
        $mobileService = new \InfinityBlog\Security\MobileNumberService();
        $decryptedMobile = $this->mobileNumberCiphertext !== null
            ? $mobileService->decrypt($this->mobileNumberCiphertext)
            : ($this->phone !== null && str_starts_with($this->phone, '+') ? $this->phone : null);
        $maskedMobile = $decryptedMobile !== null ? $mobileService->mask($decryptedMobile) : null;

        $whatsappLink = null;
        if (!empty($this->whatsappNumber)) {
            $digits = preg_replace('/\D+/', '', $this->whatsappNumber);
            if (!empty($digits)) {
                $whatsappLink = 'https://wa.me/' . $digits;
            }
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'handle' => \InfinityBlog\Support\UserIdentityHelper::formatHandle($this->username, $this->displayName),
            'email' => $this->email,
            'display_name' => $this->displayName,
            'role' => $this->role,
            'status' => $this->status,
            'bio' => $this->bio,
            'location' => $this->location,
            'interests' => $this->interests,
            'website' => $this->website,
            'instagram_url' => $this->instagramUrl,
            'facebook_url' => $this->facebookUrl,
            'github_url' => $this->githubUrl,
            'whatsapp_number' => $this->whatsappNumber,
            'whatsapp_public' => $this->whatsappPublic,
            'whatsapp_link' => $whatsappLink,
            'twitter_url' => $this->twitterUrl,
            'x_url' => $this->twitterUrl,
            'linkedin_url' => $this->linkedinUrl,
            'phone' => $decryptedMobile ?? $this->phone,
            'mobile_number' => $decryptedMobile,
            'mobile_number_masked' => $maskedMobile,
            'mobile_number_verified_at' => $this->mobileNumberVerifiedAt,
            'avatar_path' => $this->avatarPath,
            'banner_path' => $this->bannerPath,
            'avatar_url' => ArticleImageHelper::resolveAvatarUrl($this->avatarPath),
            'banner_url' => ArticleImageHelper::resolveBannerUrl($this->bannerPath),
            'profile_visibility' => $this->profileVisibility,
            'show_in_recommendations' => $this->showInRecommendations,
            'created_at' => $this->createdAt,
        ];
    }
}
