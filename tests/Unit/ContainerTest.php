<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Tests\Unit;

use IhumbakWooBulkEdit\Container;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_set_and_get_returns_instance(): void
    {
        $this->container->set('service', static fn () => new stdClass());

        $result = $this->container->get('service');

        self::assertInstanceOf(stdClass::class, $result);
    }

    public function test_get_returns_same_instance_singleton(): void
    {
        $this->container->set('service', static fn () => new stdClass());

        $first = $this->container->get('service');
        $second = $this->container->get('service');

        self::assertSame($first, $second);
    }

    public function test_has_returns_false_for_unregistered(): void
    {
        self::assertFalse($this->container->has('unknown'));
    }

    public function test_has_returns_true_for_registered(): void
    {
        $this->container->set('service', static fn () => new stdClass());

        self::assertTrue($this->container->has('service'));
    }

    public function test_get_throws_for_unknown_service(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown/');

        $this->container->get('Unknown');
    }

    public function test_re_registration_clears_cached_instance(): void
    {
        $this->container->set('service', static fn () => (object) ['v' => 1]);
        $first = $this->container->get('service');

        $this->container->set('service', static fn () => (object) ['v' => 2]);
        $second = $this->container->get('service');

        self::assertNotSame($first, $second);
        self::assertSame(2, $second->v);
    }

    public function test_factory_receives_container(): void
    {
        $received = null;

        $this->container->set('service', static function (Container $c) use (&$received) {
            $received = $c;
            return new stdClass();
        });

        $this->container->get('service');

        self::assertSame($this->container, $received);
    }
}
