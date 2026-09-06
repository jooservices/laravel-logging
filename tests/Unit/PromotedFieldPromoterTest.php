<?php

declare(strict_types=1);

namespace JOOservices\LaravelLogging\Tests\Unit;

use JOOservices\LaravelLogging\Support\PromotedFieldPromoter;
use JOOservices\LaravelLogging\Tests\TestCase;

final class PromotedFieldPromoterTest extends TestCase
{
    public function test_promoted_field_promoter_copies_nested_values(): void
    {
        $this->app['config']->set('laravel-logging.promoted_fields', [
            'site_id' => 'properties.site_id',
            'batch_id' => 'context.batch_id',
        ]);

        $document = PromotedFieldPromoter::apply([
            'properties' => ['site_id' => 10],
            'context' => ['batch_id' => 'b-1'],
        ]);

        $this->assertSame(10, $document['site_id']);
        $this->assertSame('b-1', $document['batch_id']);
    }

    public function test_promoted_field_promoter_skips_missing_paths(): void
    {
        $this->app['config']->set('laravel-logging.promoted_fields', [
            'site_id' => 'properties.site_id',
        ]);

        $document = PromotedFieldPromoter::apply(['properties' => []]);

        $this->assertArrayNotHasKey('site_id', $document);
    }

    public function test_promoted_field_index_definitions_include_occurred_at_compound(): void
    {
        $this->app['config']->set('laravel-logging.promoted_fields', [
            'site_id' => 'properties.site_id',
        ]);

        $indexes = PromotedFieldPromoter::indexDefinitions();

        $this->assertCount(2, $indexes);
        $this->assertSame(['site_id' => 1], $indexes[0]['keys']);
        $this->assertSame(['site_id' => 1, 'occurred_at' => -1], $indexes[1]['keys']);
    }

    public function test_promoted_field_promoter_skips_invalid_mapping_entries(): void
    {
        $this->app['config']->set('laravel-logging.promoted_fields', [
            '' => 'properties.site_id',
            'valid' => '',
            123 => 'properties.site_id',
        ]);

        $document = PromotedFieldPromoter::apply([
            'properties' => ['site_id' => 99],
        ]);

        $this->assertArrayNotHasKey('valid', $document);
        $this->assertArrayNotHasKey(123, $document);
        $this->assertArrayNotHasKey('site_id', $document);
    }

    public function test_promoted_field_index_definitions_skip_invalid_field_names(): void
    {
        $this->app['config']->set('laravel-logging.promoted_fields', [
            '' => 'properties.site_id',
            'site_id' => 'properties.site_id',
        ]);

        $indexes = PromotedFieldPromoter::indexDefinitions();

        $this->assertCount(2, $indexes);
        $this->assertSame(['site_id' => 1], $indexes[0]['keys']);
    }

    public function test_promoted_field_promoter_skips_reserved_target_fields(): void
    {
        $this->app['config']->set('laravel-logging.promoted_fields', [
            'action' => 'context.should_not_overwrite',
            'batch_id' => 'context.batch',
            '' => 'context.ignored',
        ]);

        $document = PromotedFieldPromoter::apply([
            'action' => 'keep-me',
            'context' => [
                'should_not_overwrite' => 'nope',
                'batch' => 'b-1',
            ],
        ]);

        $this->assertSame('keep-me', $document['action']);
        $this->assertSame('b-1', $document['batch_id']);
    }
}
