<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use Illuminate\Contracts\Support\Arrayable;
use JOOservices\LaravelLogging\Adapters\AuditLogAdapter;
use JOOservices\LaravelLogging\Contracts\ActivityLogPayloadLimiterInterface;
use JOOservices\LaravelLogging\Contracts\LogContextResolverInterface;
use JOOservices\LaravelLogging\Contracts\LogSanitizerInterface;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\Tests\Stubs\TestBackedString;
use JOOservices\LaravelLogging\Tests\Stubs\TestModel;
use JOOservices\LaravelLogging\Tests\TestCase;
use JsonSerializable;

final class AuditLogAdapterTest extends TestCase
{
    private function adapter(): AuditLogAdapter
    {
        return new AuditLogAdapter(
            $this->app->make(LogStoreInterface::class),
            $this->app->make(LogSanitizerInterface::class),
            $this->app->make(ActivityLogPayloadLimiterInterface::class),
            $this->app->make(LogContextResolverInterface::class),
        );
    }

    public function test_created_updated_deleted_and_restored_build_expected_changes(): void
    {
        $model = new TestModel([
            'id' => 9,
            'name' => 'alpha',
            'password' => 'secret',
            'remember_token' => 'tok',
        ]);
        $model->syncOriginal();
        $model->setAttribute('name', 'beta');

        $created = $this->adapter()->created($model)->toData();
        $this->assertSame('created', $created->action);
        $this->assertArrayHasKey('after', $created->changes);
        $this->assertIsArray($created->changes['after']);
        $this->assertArrayNotHasKey('password', $created->changes['after']);

        $updated = $this->adapter()->updated($model)->toData();
        $this->assertSame('updated', $updated->action);
        $this->assertArrayHasKey('name', $updated->changes);

        $deleted = $this->adapter()->deleted($model)->toData();
        $this->assertSame('deleted', $deleted->action);
        $this->assertArrayHasKey('before', $deleted->changes);

        $restored = $this->adapter()->restored($model)->toData();
        $this->assertSame('restored', $restored->action);
        $this->assertArrayHasKey('after', $restored->changes);
    }

    public function test_changes_accepts_arrayable_and_json_serializable_payloads(): void
    {
        $arrayable = new class implements Arrayable {
            public function toArray(): array
            {
                return ['flag' => true];
            }
        };
        $json = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'scalar';
            }
        };

        $fromArrayable = $this->adapter()->action('arrayable')->changes($arrayable)->toData();
        $this->assertSame(['flag' => true], $fromArrayable->changes);

        $fromJson = $this->adapter()->action('json')->changes($json)->toData();
        $this->assertSame(['value' => 'scalar'], $fromJson->changes);
    }

    public function test_fluent_helpers_accept_backed_enums_and_guest_actor(): void
    {
        $data = $this->adapter()
            ->type(TestBackedString::Audit)
            ->level(TestBackedString::Error)
            ->action(TestBackedString::Boom)
            ->message('hello')
            ->source(TestBackedString::Cli)
            ->byGuest()
            ->toData();

        $this->assertSame('audit', $data->type);
        $this->assertSame('error', $data->level);
        $this->assertSame('boom', $data->action);
        $this->assertSame('hello', $data->message);
        $this->assertSame('cli', $data->source);
        $this->assertSame('guest', $data->actorType);
    }
}
