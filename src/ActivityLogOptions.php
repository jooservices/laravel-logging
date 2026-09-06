<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging;

final class ActivityLogOptions
{
    /** @var array<int, string>|null */
    public ?array $only = null;

    /** @var array<int, string> */
    public array $except = [];

    public bool $logOnlyDirty = false;

    public bool $submitEmptyLogs = true;

    public string $actionPrefix = 'model';

    public static function make(): self
    {
        return new self();
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function logOnly(array $fields): self
    {
        $this->only = array_values($fields);

        return $this;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function except(array $fields): self
    {
        $this->except = array_values($fields);

        return $this;
    }

    public function logOnlyDirty(bool $enabled = true): self
    {
        $this->logOnlyDirty = $enabled;

        return $this;
    }

    public function dontSubmitEmptyLogs(bool $enabled = true): self
    {
        $this->submitEmptyLogs = ! $enabled;

        return $this;
    }

    public function actionPrefix(string $prefix): self
    {
        $this->actionPrefix = $prefix;

        return $this;
    }
}
