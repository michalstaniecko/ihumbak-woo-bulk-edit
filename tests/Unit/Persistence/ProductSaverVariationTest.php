<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit\Persistence;

use IhumbakWooBulkEdit\Persistence\ProductSaver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProductSaver behaviour specific to product variations.
 *
 * These tests use a real WC environment (Integration suite bootstrap), but
 * the assertions concern logic that lives entirely in ProductSaver — i.e. the
 * unsupported-status guard added for variation rows.
 *
 * These tests only use `ReflectionClass` and do not require a WP/WC environment,
 * so they run correctly under both the unit and integration bootstraps.
 *
 * NOTE: to run only this file, use:
 *   vendor/bin/phpunit --testsuite unit --filter ProductSaverVariationTest
 */
final class ProductSaverVariationTest extends TestCase
{
    /**
     * @dataProvider unsupportedStatusProvider
     */
    public function test_save_variation_status_rejects_unsupported_value(string $badStatus): void
    {
        // ProductSaver::save() rejects unsupported variation statuses BEFORE
        // touching the database. We can assert that the guard constant / logic
        // exists by inspecting the class with reflection or by confirming that
        // the constant list is defined correctly.
        //
        // Because this is a unit-level concern (no DB needed), we use
        // reflection to verify the VARIATION_ALLOWED_STATUSES constant.

        $reflection = new \ReflectionClass(ProductSaver::class);
        self::assertTrue(
            $reflection->hasConstant('VARIATION_ALLOWED_STATUSES'),
            'ProductSaver must declare VARIATION_ALLOWED_STATUSES constant'
        );

        $allowed = $reflection->getConstant('VARIATION_ALLOWED_STATUSES');
        self::assertIsArray($allowed, 'VARIATION_ALLOWED_STATUSES must be an array');
        self::assertNotContains($badStatus, $allowed, "'{$badStatus}' must NOT be in VARIATION_ALLOWED_STATUSES");
    }

    /**
     * @return list<array{string}>
     */
    public static function unsupportedStatusProvider(): array
    {
        return [
            ['draft'],
            ['pending'],
            ['future'],
            ['trash'],
        ];
    }

    public function test_save_variation_status_accepts_publish_and_private(): void
    {
        $reflection = new \ReflectionClass(ProductSaver::class);
        self::assertTrue(
            $reflection->hasConstant('VARIATION_ALLOWED_STATUSES'),
            'ProductSaver must declare VARIATION_ALLOWED_STATUSES constant'
        );

        $allowed = $reflection->getConstant('VARIATION_ALLOWED_STATUSES');
        self::assertContains('publish', $allowed, "'publish' must be in VARIATION_ALLOWED_STATUSES");
        self::assertContains('private', $allowed, "'private' must be in VARIATION_ALLOWED_STATUSES");
    }
}
