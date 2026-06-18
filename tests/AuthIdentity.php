<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Tests;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A non-Eloquent Authenticatable used to prove the subject type was widened
 * beyond Model.
 */
class AuthIdentity implements Authenticatable
{
    public bool $verified = false;

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return 1;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
