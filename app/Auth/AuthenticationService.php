<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

final class AuthenticationService
{
    // Random-input bcrypt fixture; it has no corresponding account or known password.
    // Keep its algorithm/cost aligned if the application's password-hash policy changes.
    private const DUMMY_PASSWORD_HASH = '$2y$10$nX0docNa.gv5OTbajqP4yOUcDHbTBVMx7nWjufFhQzYm9Q2QZ2Km6';

    private UserRepository $users;
    private LoginAttemptLimiter $limiter;

    public function __construct(UserRepository $users, LoginAttemptLimiter $limiter)
    {
        $this->users = $users;
        $this->limiter = $limiter;
    }

    public function login(string $identifier, string $password, string $sourceAddress): LoginResult
    {
        $identifier = UserRepository::normalizeIdentifier($identifier);
        $sourceAddress = trim($sourceAddress);
        if ($sourceAddress === '') {
            $sourceAddress = 'unknown';
        }

        if ($this->limiter->isLimited($identifier, $sourceAddress)) {
            return new LoginResult(LoginResult::LIMITED);
        }

        $account = $this->users->findByIdentifier($identifier);
        $passwordValid = $account !== null
            ? $account->verifyPassword($password)
            : password_verify($password, self::DUMMY_PASSWORD_HASH);

        if ($account === null || !$passwordValid || !$account->isActive()) {
            $this->limiter->recordFailure($identifier, $sourceAddress);
            return new LoginResult(LoginResult::FAILURE);
        }

        $this->users->recordSuccessfulLogin($account->id);
        $this->limiter->recordSuccess($identifier);

        return new LoginResult(LoginResult::SUCCESS, $account);
    }
}
