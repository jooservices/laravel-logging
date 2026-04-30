<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Models;

use Illuminate\Database\Eloquent\Model;
use JOOservices\LaravelLogging\Concerns\LogsActivity;

abstract class ActivityLoggableModel extends Model
{
    use LogsActivity;
}
