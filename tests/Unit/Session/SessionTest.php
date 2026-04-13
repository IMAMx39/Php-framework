<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use Framework\Session\Session;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session();
        $this->session->start();
        $this->session->flush();
    }

    // ------------------------------------------------------------------
    // set / get / has / remove
    // ------------------------------------------------------------------

    public function testSetAndGet(): void
    {
        $this->session->set('foo', 'bar');

        $this->assertSame('bar', $this->session->get('foo'));
    }

    public function testGetReturnsDefaultWhenKeyAbsent(): void
    {
        $this->assertNull($this->session->get('missing'));
        $this->assertSame('default', $this->session->get('missing', 'default'));
    }

    public function testHasReturnsTrueWhenKeyExists(): void
    {
        $this->session->set('key', 'value');

        $this->assertTrue($this->session->has('key'));
    }

    public function testHasReturnsFalseWhenKeyAbsent(): void
    {
        $this->assertFalse($this->session->has('nonexistent'));
    }

    public function testRemoveDeletesKey(): void
    {
        $this->session->set('key', 'value');
        $this->session->remove('key');

        $this->assertFalse($this->session->has('key'));
    }

    public function testRemoveNonExistentKeyDoesNotThrow(): void
    {
        $this->session->remove('ghost');

        $this->assertFalse($this->session->has('ghost'));
    }

    // ------------------------------------------------------------------
    // Valeurs diverses
    // ------------------------------------------------------------------

    public function testSetStoresInteger(): void
    {
        $this->session->set('count', 42);

        $this->assertSame(42, $this->session->get('count'));
    }

    public function testSetStoresArray(): void
    {
        $this->session->set('data', ['a' => 1, 'b' => 2]);

        $this->assertSame(['a' => 1, 'b' => 2], $this->session->get('data'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->session->set('x', 'first');
        $this->session->set('x', 'second');

        $this->assertSame('second', $this->session->get('x'));
    }

    // ------------------------------------------------------------------
    // flush()
    // ------------------------------------------------------------------

    public function testFlushEmptiesSession(): void
    {
        $this->session->set('a', 1);
        $this->session->set('b', 2);
        $this->session->flush();

        $this->assertFalse($this->session->has('a'));
        $this->assertFalse($this->session->has('b'));
    }

    // ------------------------------------------------------------------
    // Flash messages
    // ------------------------------------------------------------------

    public function testFlashIsConsumedOnRead(): void
    {
        $this->session->flash('success', 'Sauvegardé !');

        $this->assertSame('Sauvegardé !', $this->session->getFlash('success'));
        $this->assertNull($this->session->getFlash('success')); // consommé
    }

    public function testGetFlashReturnsDefaultWhenAbsent(): void
    {
        $this->assertNull($this->session->getFlash('missing'));
        $this->assertSame('fallback', $this->session->getFlash('missing', 'fallback'));
    }

    public function testHasFlashReturnsTrueBeforeRead(): void
    {
        $this->session->flash('info', 'message');

        $this->assertTrue($this->session->hasFlash('info'));
    }

    public function testHasFlashReturnsFalseAfterRead(): void
    {
        $this->session->flash('info', 'message');
        $this->session->getFlash('info');

        $this->assertFalse($this->session->hasFlash('info'));
    }

    public function testMultipleFlashMessages(): void
    {
        $this->session->flash('success', 'OK');
        $this->session->flash('error', 'KO');

        $this->assertSame('OK', $this->session->getFlash('success'));
        $this->assertSame('KO', $this->session->getFlash('error'));
    }

    public function testPullFlashesReturnsAllAndClearsThemAll(): void
    {
        $this->session->flash('a', 'alpha');
        $this->session->flash('b', 'beta');

        $flashes = $this->session->pullFlashes();

        $this->assertSame(['a' => 'alpha', 'b' => 'beta'], $flashes);
        $this->assertFalse($this->session->hasFlash('a'));
        $this->assertFalse($this->session->hasFlash('b'));
    }

    public function testPullFlashesReturnsEmptyArrayWhenNone(): void
    {
        $this->assertSame([], $this->session->pullFlashes());
    }

    // ------------------------------------------------------------------
    // getId()
    // ------------------------------------------------------------------

    public function testGetIdReturnsNonEmptyString(): void
    {
        $this->assertNotEmpty($this->session->getId());
    }
}
