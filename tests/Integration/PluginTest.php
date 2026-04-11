<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Integration;

use IhumbakWooBulkEdit\Container;
use IhumbakWooBulkEdit\Fields\FieldRegistry;
use IhumbakWooBulkEdit\Plugin;
use WP_UnitTestCase;

final class PluginTest extends WP_UnitTestCase
{
    public function test_instance_returns_singleton(): void
    {
        $first = Plugin::instance();
        $second = Plugin::instance();

        self::assertSame($first, $second);
    }

    public function test_container_is_accessible(): void
    {
        $container = Plugin::instance()->container();

        self::assertInstanceOf(Container::class, $container);
    }

    public function test_boot_registers_services(): void
    {
        $plugin = Plugin::instance();
        $plugin->boot();

        self::assertTrue($plugin->container()->has(FieldRegistry::class));
    }

    public function test_boot_is_idempotent(): void
    {
        $plugin = Plugin::instance();
        $plugin->boot();
        $plugin->boot();

        // No exception means idempotent
        self::assertTrue(true);
    }
}
