<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

interface SecurityLogAdapterInterface extends LogAdapterInterface
{
    public function loginSucceeded(Authenticatable|Model $actor): static;

    public function loginFailed(string $identifier): static;

    public function logout(Authenticatable|Model|null $actor = null): static;

    public function passwordChanged(Authenticatable|Model $actor): static;

    public function twoFactorEnabled(Authenticatable|Model $actor): static;

    public function twoFactorDisabled(Authenticatable|Model $actor): static;

    public function apiKeyCreated(Authenticatable|Model $actor, Model|string|null $apiKey = null): static;

    public function apiKeyDeleted(Authenticatable|Model $actor, Model|string|null $apiKey = null): static;

    public function permissionChanged(Authenticatable|Model $actor, Model|string|null $subject = null): static;

    public function suspicious(string $reason): static;
}
