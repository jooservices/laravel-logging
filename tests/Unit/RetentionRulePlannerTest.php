<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use Carbon\CarbonImmutable;
use JOOservices\LaravelLogging\Support\RetentionRulePlanner;
use JOOservices\LaravelLogging\Tests\TestCase;

final class RetentionRulePlannerTest extends TestCase
{
    public function test_retention_rule_planner_builds_cutoffs(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00', 'UTC'));

        $this->app['config']->set('laravel-logging.retention.rules', [
            [
                'adapter' => 'system',
                'level' => 'debug',
                'action_prefix' => 'http.',
                'retention_days' => 14,
            ],
            [
                'retention_days' => 0,
            ],
        ]);

        $rules = RetentionRulePlanner::rules();

        $this->assertCount(1, $rules);
        $this->assertSame('system', $rules[0]['adapter']);
        $this->assertSame('debug', $rules[0]['level']);
        $this->assertSame('http.', $rules[0]['action_prefix']);
        $this->assertTrue($rules[0]['cutoff']->equalTo(CarbonImmutable::parse('2026-05-18 12:00:00', 'UTC')));

        CarbonImmutable::setTestNow();
    }

    public function test_retention_rule_planner_accepts_minimal_rule_and_skips_invalid_entries(): void
    {
        $this->app['config']->set('laravel-logging.retention.rules', [
            'not-an-array',
            [
                'retention_days' => 30,
            ],
            [
                'adapter' => '',
                'level' => '',
                'action_prefix' => '',
                'retention_days' => 7,
            ],
        ]);

        $rules = RetentionRulePlanner::rules();

        $this->assertCount(2, $rules);
        $this->assertNull($rules[0]['adapter']);
        $this->assertNull($rules[1]['adapter']);
        $this->assertNull($rules[1]['level']);
        $this->assertNull($rules[1]['action_prefix']);
    }
}
