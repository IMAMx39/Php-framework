<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use Framework\Event\Event;
use Framework\Event\EventDispatcher;
use Framework\Event\ExceptionEvent;
use Framework\Event\KernelEvents;
use Framework\Event\RequestEvent;
use Framework\Event\ResponseEvent;
use Framework\Http\Request;
use Framework\Http\Response;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    // ------------------------------------------------------------------
    // on() / emit() — base
    // ------------------------------------------------------------------

    public function testListenerIsCalledOnEmit(): void
    {
        $called = false;

        $this->dispatcher->on('test.event', function (Event $e) use (&$called) {
            $called = true;
        });

        $this->dispatcher->emit('test.event', new Event());

        $this->assertTrue($called);
    }

    public function testMultipleListenersAreAllCalled(): void
    {
        $log = [];

        $this->dispatcher->on('test.event', function () use (&$log) { $log[] = 'A'; });
        $this->dispatcher->on('test.event', function () use (&$log) { $log[] = 'B'; });
        $this->dispatcher->on('test.event', function () use (&$log) { $log[] = 'C'; });

        $this->dispatcher->emit('test.event', new Event());

        $this->assertSame(['A', 'B', 'C'], $log);
    }

    public function testNoListenersDoesNotThrow(): void
    {
        $event = $this->dispatcher->emit('event.without.listeners', new Event());

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testEmitReturnsTheEvent(): void
    {
        $original = new Event();
        $returned = $this->dispatcher->emit('test', $original);

        $this->assertSame($original, $returned);
    }

    // ------------------------------------------------------------------
    // Priorité
    // ------------------------------------------------------------------

    public function testHigherPriorityListenerCalledFirst(): void
    {
        $log = [];

        $this->dispatcher->on('test', function () use (&$log) { $log[] = 'low';    }, priority: 0);
        $this->dispatcher->on('test', function () use (&$log) { $log[] = 'high';   }, priority: 10);
        $this->dispatcher->on('test', function () use (&$log) { $log[] = 'medium'; }, priority: 5);

        $this->dispatcher->emit('test', new Event());

        $this->assertSame(['high', 'medium', 'low'], $log);
    }

    public function testNegativePriorityCalledLast(): void
    {
        $log = [];

        $this->dispatcher->on('test', function () use (&$log) { $log[] = 'normal'; }, priority: 0);
        $this->dispatcher->on('test', function () use (&$log) { $log[] = 'last';   }, priority: -10);

        $this->dispatcher->emit('test', new Event());

        $this->assertSame(['normal', 'last'], $log);
    }

    // ------------------------------------------------------------------
    // stopPropagation()
    // ------------------------------------------------------------------

    public function testStopPropagationPreventsSubsequentListeners(): void
    {
        $log = [];

        $this->dispatcher->on('test', function (Event $e) use (&$log) {
            $log[] = 'first';
            $e->stopPropagation();
        });
        $this->dispatcher->on('test', function () use (&$log) {
            $log[] = 'second'; // ne doit pas être appelé
        });

        $this->dispatcher->emit('test', new Event());

        $this->assertSame(['first'], $log);
    }

    // ------------------------------------------------------------------
    // off()
    // ------------------------------------------------------------------

    public function testOffRemovesListener(): void
    {
        $called = false;
        $listener = function () use (&$called) { $called = true; };

        $this->dispatcher->on('test', $listener);
        $this->dispatcher->off('test', $listener);

        $this->dispatcher->emit('test', new Event());

        $this->assertFalse($called);
    }

    public function testOffOnNonExistentEventDoesNotThrow(): void
    {
        $this->dispatcher->off('no.such.event', fn () => null);

        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------
    // hasListeners() / getListeners() / getEventNames()
    // ------------------------------------------------------------------

    public function testHasListenersReturnsTrueAfterOn(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('test'));

        $this->dispatcher->on('test', fn () => null);

        $this->assertTrue($this->dispatcher->hasListeners('test'));
    }

    public function testHasListenersReturnsFalseAfterOff(): void
    {
        $listener = fn () => null;
        $this->dispatcher->on('test', $listener);
        $this->dispatcher->off('test', $listener);

        $this->assertFalse($this->dispatcher->hasListeners('test'));
    }

    public function testGetListenersReturnsEmptyArrayForUnknownEvent(): void
    {
        $this->assertSame([], $this->dispatcher->getListeners('unknown'));
    }

    public function testGetEventNamesReturnsRegisteredEvents(): void
    {
        $this->dispatcher->on('event.one', fn () => null);
        $this->dispatcher->on('event.two', fn () => null);

        $names = $this->dispatcher->getEventNames();

        $this->assertContains('event.one', $names);
        $this->assertContains('event.two', $names);
    }

    // ------------------------------------------------------------------
    // RequestEvent
    // ------------------------------------------------------------------

    public function testRequestEventCanSetResponse(): void
    {
        $this->dispatcher->on(KernelEvents::REQUEST, function (RequestEvent $e) {
            $e->setResponse(new Response('short-circuit', 503));
        });

        /** @var RequestEvent $event */
        $event = $this->dispatcher->emit(KernelEvents::REQUEST, new RequestEvent($this->makeRequest()));

        $this->assertTrue($event->hasResponse());
        $this->assertSame(503, $event->getResponse()->getStatusCode());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testRequestEventWithoutResponseHasNoResponse(): void
    {
        $event = new RequestEvent($this->makeRequest());

        $this->assertFalse($event->hasResponse());
        $this->assertNull($event->getResponse());
    }

    // ------------------------------------------------------------------
    // ResponseEvent
    // ------------------------------------------------------------------

    public function testResponseEventCanReplaceResponse(): void
    {
        $this->dispatcher->on(KernelEvents::RESPONSE, function (ResponseEvent $e) {
            $e->getResponse()->setHeader('X-Custom', 'yes');
        });

        $response = new Response('OK', 200);
        /** @var ResponseEvent $event */
        $event = $this->dispatcher->emit(
            KernelEvents::RESPONSE,
            new ResponseEvent($this->makeRequest(), $response),
        );

        $this->assertSame(['X-Custom' => 'yes'], $event->getResponse()->getHeaders());
    }

    // ------------------------------------------------------------------
    // ExceptionEvent
    // ------------------------------------------------------------------

    public function testExceptionEventCanProvideResponse(): void
    {
        $this->dispatcher->on(KernelEvents::EXCEPTION, function (ExceptionEvent $e) {
            $e->setResponse(new Response('handled', 500));
        });

        $exception = new \RuntimeException('boom');
        /** @var ExceptionEvent $event */
        $event = $this->dispatcher->emit(
            KernelEvents::EXCEPTION,
            new ExceptionEvent($this->makeRequest(), $exception),
        );

        $this->assertTrue($event->hasResponse());
        $this->assertSame('handled', $event->getResponse()->getContent());
        $this->assertSame($exception, $event->getThrowable());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeRequest(string $method = 'GET', string $uri = '/'): Request
    {
        return new Request([], [], ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri], [], null);
    }
}
