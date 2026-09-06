<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging;

use JOOservices\LaravelLogging\Support\AuditableAttributes;

final class ActivityLogOptions
{
    /** @var array<int, string>|null */
    public ?array $only = null;

    /** @var array<int, string> */
    public array $except = [];

    public bool $logOnlyDirty = true;

    public bool $submitEmptyLogs = false;

    public string $actionPrefix = 'model';

    public static function make(): self
    {
        $options = new self();
        $options->except = AuditableAttributes::defaultExcept();

        return $options;
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
        $this->except = array_values(array_unique([...AuditableAttributes::defaultExcept(), ...$fields]));

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
