<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Support\AuditableAttributes;
use JOOservices\LaravelLogging\Tests\TestCase;

final class AuditableAttributesTest extends TestCase
{
    public function test_filter_honors_only_except_and_skips_empty_keys(): void
    {
        $filtered = AuditableAttributes::filter(
            attributes: ['' => 'skip', 'name' => 'x', 'secret' => 'y', 'visible' => 'z'],
            only: ['visible', 'secret'],
            except: ['secret'],
            hidden: ['hidden_field'],
        );

        $this->assertSame(['visible' => 'z'], $filtered);
    }

    public function test_filter_skips_hidden_attributes_unless_allowlisted(): void
    {
        $hiddenSkipped = AuditableAttributes::filter(
            attributes: ['name' => 'x', 'secret_note' => 'y'],
            only: null,
            except: [],
            hidden: ['secret_note'],
        );

        $this->assertSame(['name' => 'x'], $hiddenSkipped);
    }
}
