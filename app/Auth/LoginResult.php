<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

final class LoginResult
{
    public const SUCCESS = 'success';
    public const FAILURE = 'failure';
    public const LIMITED = 'limited';

    public string $status;
    public ?UserAccount $account;

    public function __construct(string $status, ?UserAccount $account = null)
    {
        $this->status = $status;
        $this->account = $account;
    }
}
