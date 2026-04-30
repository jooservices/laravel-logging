<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

final class TestModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
