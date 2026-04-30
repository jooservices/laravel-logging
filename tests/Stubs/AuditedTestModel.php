<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Stubs;

use JOOservices\LaravelLogging\ActivityLogOptions;
use JOOservices\LaravelLogging\Concerns\LogsActivity;
use MongoDB\Laravel\Eloquent\Model;

final class AuditedTestModel extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $connection = 'mongodb';

    protected $table = 'audited_test_models';

    public $timestamps = false;

    public ActivityLogOptions $options;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->options = ActivityLogOptions::make();
    }

    public function logForTest(string $event): mixed
    {
        return $this->writeActivityLog($event);
    }

    protected function activityLogOptions(): ActivityLogOptions
    {
        return $this->options;
    }
}
