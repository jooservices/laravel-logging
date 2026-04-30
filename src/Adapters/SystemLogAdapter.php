<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Adapters;

use JOOservices\LaravelLogging\Contracts\SystemLogAdapterInterface;
use Throwable;

final class SystemLogAdapter extends BaseLogAdapter implements SystemLogAdapterInterface
{
    protected string $type = 'system';

    protected string $adapter = 'system';

    protected ?string $level = 'info';

    public function commandStarted(string $command): static
    {
        return $this->action('command.started')->context(['command' => $command])->bySystem();
    }

    public function commandCompleted(string $command): static
    {
        return $this->action('command.completed')->context(['command' => $command])->bySystem();
    }

    public function commandFailed(string $command, Throwable $exception): static
    {
        return $this->level('error')->action('command.failed')->context(['command' => $command, ...$this->exceptionContext($exception)])->bySystem();
    }

    public function jobStarted(string $job): static
    {
        return $this->action('job.started')->context(['job' => $job])->bySystem();
    }

    public function jobCompleted(string $job): static
    {
        return $this->action('job.completed')->context(['job' => $job])->bySystem();
    }

    public function jobFailed(string $job, Throwable $exception): static
    {
        return $this->level('error')->action('job.failed')->context(['job' => $job, ...$this->exceptionContext($exception)])->bySystem();
    }

    public function schedulerStarted(): static
    {
        return $this->action('scheduler.started')->bySystem();
    }

    public function schedulerCompleted(): static
    {
        return $this->action('scheduler.completed')->bySystem();
    }

    public function schedulerFailed(Throwable $exception): static
    {
        return $this->level('error')->action('scheduler.failed')->context($this->exceptionContext($exception))->bySystem();
    }

    public function exception(Throwable $exception): static
    {
        return $this->level('error')->action('exception.captured')->context($this->exceptionContext($exception));
    }
}
