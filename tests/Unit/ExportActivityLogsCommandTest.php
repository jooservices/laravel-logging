<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Tests\TestCase;

final class ExportActivityLogsCommandTest extends TestCase
{
    public function test_command_rejects_invalid_json_chunk_and_date_options(): void
    {
        $this->artisan('activity-log:export', ['--json' => true])
            ->assertExitCode(2);

        $this->artisan('activity-log:export', ['--chunk' => '0'])
            ->assertExitCode(2);

        $this->artisan('activity-log:export', ['--from' => 'not-a-date'])
            ->assertExitCode(2);
    }
}
