<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Concerns;

use JOOservices\LaravelLogging\ActivityLogOptions;
use JOOservices\LaravelLogging\Facades\ActivityLog;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (self $model): mixed => $model->writeActivityLog('created'));
        static::updated(fn (self $model): mixed => $model->writeActivityLog('updated'));
        static::deleted(fn (self $model): mixed => $model->writeActivityLog('deleted'));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (self $model): mixed => $model->writeActivityLog('restored'));
        }
    }

    protected function activityLogOptions(): ActivityLogOptions
    {
        return ActivityLogOptions::make();
    }

    protected function writeActivityLog(string $event): mixed
    {
        $options = $this->activityLogOptions();
        $before = $event === 'created' ? [] : $this->getOriginal();
        $after = $event === 'deleted' ? [] : $this->getAttributes();
        $changes = $this->activityLogChanges($before, $after, $options);

        if ($changes === [] && ! $options->submitEmptyLogs) {
            return null;
        }

        return ActivityLog::audit()
            ->action($options->actionPrefix.'.'.$event)
            ->on($this)
            ->only($options->only ?? array_keys($after + $before))
            ->except($options->except)
            ->logOnlyDirty($options->logOnlyDirty)
            ->changes($changes)
            ->save();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function activityLogChanges(array $before, array $after, ActivityLogOptions $options): array
    {
        $fields = array_unique([...array_keys($before), ...array_keys($after)]);
        $changes = [];

        foreach ($fields as $field) {
            if ($options->only !== null && ! in_array($field, $options->only, true)) {
                continue;
            }

            if (in_array($field, $options->except, true)) {
                continue;
            }

            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($options->logOnlyDirty && $old === $new) {
                continue;
            }

            $changes[$field] = ['old' => $old, 'new' => $new];
        }

        return $changes;
    }
}
