<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use JOOservices\LaravelLogging\Contracts\LogStoreInterface;
use JOOservices\LaravelLogging\DTO\ActivityLogData;
use JOOservices\LaravelLogging\Events\ActivityLogStoreFailed;
use Throwable;

final class StoreActivityLogJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [1, 5, 15];

    public function __construct(public readonly ActivityLogData $data)
    {
    }

    public function handle(LogStoreInterface $store): void
    {
        $store->record($this->data);
    }

    public function failed(?Throwable $exception): void
    {
        Event::dispatch(new ActivityLogStoreFailed($this->redactedData(), $exception));
    }

    private function redactedData(): ActivityLogData
    {
        return $this->data->with(
            properties: ['__redacted' => true],
            context: ['__redacted' => true],
            changes: ['__redacted' => true],
        );
    }
}
