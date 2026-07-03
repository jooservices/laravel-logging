<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use Illuminate\Contracts\Auth\Authenticatable;
use JOOservices\LaravelLogging\Support\LogIdentity;
use JOOservices\LaravelLogging\Tests\Stubs\TestModel;
use JOOservices\LaravelLogging\Tests\TestCase;

final class LogIdentityTest extends TestCase
{
    public function test_actor_and_subject_null_targets(): void
    {
        $this->assertSame(['type' => null, 'id' => null], LogIdentity::actor(null));
        $this->assertSame(['type' => null, 'id' => null], LogIdentity::subject(null));
    }

    public function test_actor_resolves_authenticatable_identifier(): void
    {
        $user = new class implements Authenticatable
        {
            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 55;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return 'secret';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }
        };

        $this->assertSame([
            'type' => $user::class,
            'id' => '55',
        ], LogIdentity::actor($user));
    }

    public function test_subject_resolves_model_key(): void
    {
        $model = new TestModel(['id' => 88]);

        $this->assertSame([
            'type' => TestModel::class,
            'id' => '88',
        ], LogIdentity::subject($model));
    }
}
