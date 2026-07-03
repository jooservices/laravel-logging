<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;

final class ActivityLogAggregator
{
    /** @param Builder<Model> $builder */
    public function __construct(private readonly Builder $builder) {}

    /**
     * @return array<string, int>
     */
    public function countByAction(): array
    {
        return $this->countByField('action');
    }

    /**
     * @return array<string, int>
     */
    public function countByLevel(): array
    {
        return $this->countByField('level');
    }

    /**
     * @return array<string, int>
     */
    private function countByField(string $field): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($this->builder->get([$field]) as $record) {
            if (! $record instanceof ActivityLogRecord) {
                continue;
            }

            $key = (string) ($record->getAttribute($field) ?? '');

            if ($key === '') {
                $key = '__empty__';
            }

            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }
}
