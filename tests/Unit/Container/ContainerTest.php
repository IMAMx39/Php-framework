<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Framework\Container\Container;
use Framework\Container\ContainerException;
use PHPUnit\Framework\TestCase;

// ── Fixtures ──────────────────────────────────────────────────────────

class SimpleService
{
    public string $value = 'simple';
}

class DependentService
{
    public function __construct(public readonly SimpleService $simple) {}
}

class ServiceWithDefault
{
    public function __construct(public readonly string $name = 'default') {}
}

interface CounterInterface {}

class CounterImpl implements CounterInterface
{
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }
}

// ── Tests ─────────────────────────────────────────────────────────────

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        CounterImpl::$instances = 0;
    }

    // ------------------------------------------------------------------
    // make() — autowiring
    // ------------------------------------------------------------------

    public function testMakeInstantiatesConcreteClass(): void
    {
        $svc = $this->container->make(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $svc);
        $this->assertSame('simple', $svc->value);
    }

    public function testMakeResolvesNestedDependencies(): void
    {
        $svc = $this->container->make(DependentService::class);

        $this->assertInstanceOf(DependentService::class, $svc);
        $this->assertInstanceOf(SimpleService::class, $svc->simple);
    }

    public function testMakeUsesDefaultParameterWhenNoTypeHint(): void
    {
        $svc = $this->container->make(ServiceWithDefault::class);

        $this->assertSame('default', $svc->name);
    }

    // ------------------------------------------------------------------
    // bind()
    // ------------------------------------------------------------------

    public function testBindWithClosure(): void
    {
        $this->container->bind(SimpleService::class, fn () => new SimpleService());

        $svc = $this->container->make(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $svc);
    }

    public function testBindCreatesNewInstanceEachCall(): void
    {
        $this->container->bind(CounterInterface::class, fn () => new CounterImpl());

        $this->container->make(CounterInterface::class);
        $this->container->make(CounterInterface::class);

        $this->assertSame(2, CounterImpl::$instances);
    }

    public function testBindInterfaceToConcreteClass(): void
    {
        $this->container->bind(CounterInterface::class, CounterImpl::class);

        $svc = $this->container->make(CounterInterface::class);

        $this->assertInstanceOf(CounterImpl::class, $svc);
    }

    // ------------------------------------------------------------------
    // singleton()
    // ------------------------------------------------------------------

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton(CounterInterface::class, fn () => new CounterImpl());

        $a = $this->container->make(CounterInterface::class);
        $b = $this->container->make(CounterInterface::class);

        $this->assertSame($a, $b);
        $this->assertSame(1, CounterImpl::$instances);
    }

    // ------------------------------------------------------------------
    // instance()
    // ------------------------------------------------------------------

    public function testInstanceReturnsSameObject(): void
    {
        $svc = new SimpleService();
        $svc->value = 'injected';

        $this->container->instance(SimpleService::class, $svc);

        $resolved = $this->container->make(SimpleService::class);

        $this->assertSame($svc, $resolved);
        $this->assertSame('injected', $resolved->value);
    }

    // ------------------------------------------------------------------
    // has()
    // ------------------------------------------------------------------

    public function testHasReturnsTrueForConcreteClass(): void
    {
        $this->assertTrue($this->container->has(SimpleService::class));
    }

    public function testHasReturnsTrueForBinding(): void
    {
        $this->container->bind('foo', fn () => null);

        $this->assertTrue($this->container->has('foo'));
    }

    public function testHasReturnsFalseForUnknownKey(): void
    {
        $this->assertFalse($this->container->has('NonExistentClass\Xyz'));
    }

    // ------------------------------------------------------------------
    // Erreurs
    // ------------------------------------------------------------------

    public function testMakeThrowsForNonInstantiableClass(): void
    {
        $this->expectException(ContainerException::class);

        $this->container->make(CounterInterface::class); // interface sans binding
    }

    // ------------------------------------------------------------------
    // get() — alias de make()
    // ------------------------------------------------------------------

    public function testGetDelegatesToMake(): void
    {
        $svc = $this->container->get(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $svc);
    }
}
