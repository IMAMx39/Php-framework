<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Framework\Container\Container;
use Framework\Controller\AbstractController;
use Framework\Http\Request;
use Framework\Http\Response;
use PHPUnit\Framework\TestCase;

// ── Fixtures ──────────────────────────────────────────────────────────

class FakeDep
{
    public string $name = 'fake';
}

class FakeController extends AbstractController
{
    public function __construct(public readonly FakeDep $dep) {}

    public function index(): Response
    {
        return new Response('ok');
    }
}

class NoDepController extends AbstractController
{
    public function index(): Response
    {
        return new Response('no-dep');
    }
}

// ── Tests ─────────────────────────────────────────────────────────────

class ControllerInjectionTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testContainerAutowiresControllerConstructor(): void
    {
        $ctrl = $this->container->make(FakeController::class);

        $this->assertInstanceOf(FakeController::class, $ctrl);
        $this->assertInstanceOf(FakeDep::class, $ctrl->dep);
        $this->assertSame('fake', $ctrl->dep->name);
    }

    public function testControllerWithNoDepInstantiates(): void
    {
        $ctrl = $this->container->make(NoDepController::class);

        $this->assertInstanceOf(NoDepController::class, $ctrl);
    }

    public function testSetContainerStillWorks(): void
    {
        $ctrl = $this->container->make(FakeController::class);
        $ctrl->setContainer($this->container);

        // get() is accessible after setContainer
        $dep = $ctrl->dep;
        $this->assertSame('fake', $dep->name);
    }
}
