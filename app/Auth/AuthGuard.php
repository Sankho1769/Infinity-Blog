<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

final class AuthGuard
{
    private SessionManager $sessions;
    private UserRepository $users;

    public function __construct(SessionManager $sessions, UserRepository $users)
    {
        $this->sessions = $sessions;
        $this->users = $users;
    }

    public function currentAccount(): ?UserAccount
    {
        $id = $this->sessions->currentUserId();
        if ($id === null) {
            return null;
        }
        if ($this->sessions->isExpired()) {
            $this->sessions->clearAuthentication();
            return null;
        }

        $account = $this->users->findById($id);
        $sessionVersion = $this->sessions->currentAuthVersion();
        if ($account === null || !$account->isActive()
            || $sessionVersion === null || $sessionVersion !== $account->authVersion) {
            $this->sessions->clearAuthentication();
            return null;
        }

        $this->sessions->touch();

        return $account;
    }

    /** @param list<string> $allowedRoles */
    public function permits(array $allowedRoles): bool
    {
        $account = $this->currentAccount();

        return $account !== null && in_array($account->role, $allowedRoles, true);
    }

    public function isAdmin(): bool
    {
        return $this->permits(['admin']);
    }

    public function isUser(): bool
    {
        return $this->permits(['user', 'author']);
    }

    public function isAuthor(): bool
    {
        return $this->permits(['user', 'author']);
    }

    public function isReader(): bool
    {
        return $this->permits(['user', 'reader', 'author']);
    }

    public function isAuthorOrAdmin(): bool
    {
        return $this->permits(['user', 'author', 'admin']);
    }
}
