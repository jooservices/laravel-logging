<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Adapters;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use JOOservices\LaravelLogging\Contracts\SecurityLogAdapterInterface;

final class SecurityLogAdapter extends BaseLogAdapter implements SecurityLogAdapterInterface
{
    protected string $type = 'security';

    protected string $adapter = 'security';

    protected ?string $level = 'notice';

    public function loginSucceeded(Authenticatable | Model $actor): static
    {
        return $this->level('info')->action('login.succeeded')->by($actor);
    }

    public function loginFailed(string $identifier): static
    {
        return $this->level('warning')->action('login.failed')->properties(['identifier' => $identifier]);
    }

    public function logout(Authenticatable | Model | null $actor = null): static
    {
        return $this->level('info')->action('logout')->by($actor);
    }

    public function passwordChanged(Authenticatable | Model $actor): static
    {
        return $this->action('password.changed')->by($actor);
    }

    public function twoFactorEnabled(Authenticatable | Model $actor): static
    {
        return $this->action('2fa.enabled')->by($actor);
    }

    public function twoFactorDisabled(Authenticatable | Model $actor): static
    {
        return $this->action('2fa.disabled')->by($actor);
    }

    public function apiKeyCreated(Authenticatable | Model $actor, Model | string | null $apiKey = null): static
    {
        return $this->action('api_key.created')->by($actor)->on($apiKey);
    }

    public function apiKeyDeleted(Authenticatable | Model $actor, Model | string | null $apiKey = null): static
    {
        return $this->action('api_key.deleted')->by($actor)->on($apiKey);
    }

    public function permissionChanged(Authenticatable | Model $actor, Model | string | null $subject = null): static
    {
        return $this->action('permission.changed')->by($actor)->on($subject);
    }

    public function suspicious(string $reason): static
    {
        return $this->level('warning')->action('suspicious.request')->properties(['reason' => $reason]);
    }
}
