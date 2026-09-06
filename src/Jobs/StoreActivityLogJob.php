<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Events\ActivityLogStoreFailed;
use Throwable;

final class StoreActivityLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly ActivityLogData $data)
    {
    }

    public function handle(LogStoreInterface $store): void
    {
        $store->record($this->data);
    }

    public function failed(?Throwable $exception): void
    {
        Event::dispatch(new ActivityLogStoreFailed($this->data, $exception));
    }
}
