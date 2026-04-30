<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Contracts;

use Throwable;

interface SystemLogAdapterInterface extends LogAdapterInterface
{
    public function commandStarted(string $command): static;

    public function commandCompleted(string $command): static;

    public function commandFailed(string $command, Throwable $exception): static;

    public function jobStarted(string $job): static;

    public function jobCompleted(string $job): static;

    public function jobFailed(string $job, Throwable $exception): static;

    public function schedulerStarted(): static;

    public function schedulerCompleted(): static;

    public function schedulerFailed(Throwable $exception): static;

    public function exception(Throwable $exception): static;
}
