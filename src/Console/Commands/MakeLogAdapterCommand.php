<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:log-adapter')]
final class MakeLogAdapterCommand extends GeneratorCommand
{
    protected $name = 'make:log-adapter';

    protected $description = 'Create a custom activity log adapter class';

    protected $type = 'Log adapter';

    protected function getStub(): string
    {
        return dirname(__DIR__, 3) . '/stubs/log-adapter.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Logging\Adapters';
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the adapter class'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['type', null, InputOption::VALUE_OPTIONAL, 'Default log type for the adapter', 'ops'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);
        $type = (string) $this->option('type');
        $class = class_basename($name);
        $adapterKey = Str::of($class)
            ->replaceEnd('Adapter', '')
            ->snake()
            ->toString();

        return str_replace(
            ['{{ type }}', '{{ adapter }}'],
            [$type !== '' ? $type : 'ops', $adapterKey !== '' ? $adapterKey : 'custom'],
            $stub,
        );
    }
}
