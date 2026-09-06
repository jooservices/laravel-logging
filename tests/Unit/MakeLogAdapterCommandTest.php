<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Tests\TestCase;

final class MakeLogAdapterCommandTest extends TestCase
{
    public function test_command_writes_adapter_stub_with_forced_overwrite(): void
    {
        $path = $this->app->basePath('app/Logging/Adapters/GeneratedOpsAdapter.php');
        @unlink($path);

        $this->artisan('make:log-adapter', [
            'name' => 'GeneratedOpsAdapter',
            '--type' => 'ops',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('GeneratedOpsAdapter', $contents);
        @unlink($path);
    }

    public function test_command_uses_published_stub_and_falls_back_for_invalid_type(): void
    {
        $stubDir = $this->app->basePath('stubs');
        if (! is_dir($stubDir)) {
            mkdir($stubDir, 0777, true);
        }

        $published = $stubDir . '/log-adapter.stub';
        file_put_contents(
            $published,
            "<?php\nnamespace {{ namespace }};\nclass {{ class }} { protected string \$type = '{{ type }}'; protected string \$adapter = '{{ adapter }}'; }\n",
        );

        $path = $this->app->basePath('app/Logging/Adapters/PublishedStubAdapter.php');
        @unlink($path);

        $this->artisan('make:log-adapter', [
            'name' => 'PublishedStubAdapter',
            '--type' => 'bad type!',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString("\$type = 'ops'", $contents);

        @unlink($path);
        @unlink($published);
    }
}
