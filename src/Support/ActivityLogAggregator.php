<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use MongoDB\Laravel\Query\Builder as MongoQueryBuilder;
use Traversable;

final class ActivityLogAggregator
{
    /** @param Builder<Model> $builder */
    public function __construct(private readonly Builder $builder)
    {
    }

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
    public function countByField(string $field): array
    {
        $base = $this->builder->getQuery();

        if ($base instanceof MongoQueryBuilder) {
            return $this->countByFieldWithGroup($base, $field);
        }

        return $this->countByFieldInMemory($field);
    }

    /**
     * @return array<string, int>
     */
    private function countByFieldWithGroup(MongoQueryBuilder $base, string $field): array
    {
        $clone = clone $base;
        $clone->orders = null;
        $clone->offset = null;
        $clone->limit = null;
        $clone->columns = [];
        $clone->aggregate = null;
        $clone->groups = null;

        $find = $clone->toMql()['find'] ?? [[], []];
        /** @var array<string, mixed> $match */
        $match = is_array($find[0] ?? null) ? $find[0] : [];

        $pipeline = [];

        if ($match !== []) {
            $pipeline[] = ['$match' => $match];
        }

        $pipeline[] = [
            '$group' => [
                '_id' => ['$ifNull' => ['$' . $field, '']],
                'count' => ['$sum' => 1],
            ],
        ];

        $cursor = $base->raw(static fn($collection) => $collection->aggregate($pipeline, [
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ]));

        $rows = $cursor instanceof Traversable ? iterator_to_array($cursor) : (array) $cursor;
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rawKey = $row['_id'] ?? '';
            $key = is_scalar($rawKey) ? (string) $rawKey : '';

            if ($key === '') {
                $key = '__empty__';
            }

            $rawCount = $row['count'] ?? 0;
            $counts[$key] = is_numeric($rawCount) ? (int) $rawCount : 0;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function countByFieldInMemory(string $field): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($this->builder->get([$field]) as $record) {
            if (! $record instanceof ActivityLogRecord) {
                continue;
            }

            $attribute = $record->getAttribute($field);
            $key = is_scalar($attribute) ? (string) $attribute : '';

            if ($key === '') {
                $key = '__empty__';
            }

            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }
}
