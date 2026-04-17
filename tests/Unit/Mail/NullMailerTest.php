<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use Framework\Mail\Message;
use Framework\Mail\NullMailer;
use PHPUnit\Framework\TestCase;

class NullMailerTest extends TestCase
{
    private NullMailer $mailer;

    protected function setUp(): void
    {
        $this->mailer = new NullMailer();
    }

    private function validMessage(string $to = 'user@example.com'): Message
    {
        return (new Message())
            ->from('sender@example.com')
            ->to($to)
            ->subject('Test')
            ->text('Hello');
    }

    public function testSendStoresMessage(): void
    {
        $this->mailer->send($this->validMessage());

        $this->assertCount(1, $this->mailer->getSent());
    }

    public function testGetLastSent(): void
    {
        $m1 = $this->validMessage('a@b.com');
        $m2 = $this->validMessage('c@d.com');

        $this->mailer->send($m1);
        $this->mailer->send($m2);

        $this->assertSame($m2, $this->mailer->getLastSent());
    }

    public function testGetLastSentReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->mailer->getLastSent());
    }

    public function testReset(): void
    {
        $this->mailer->send($this->validMessage());
        $this->mailer->reset();

        $this->assertEmpty($this->mailer->getSent());
        $this->assertNull($this->mailer->getLastSent());
    }

    public function testMultipleSends(): void
    {
        $this->mailer->send($this->validMessage('a@b.com'));
        $this->mailer->send($this->validMessage('c@d.com'));
        $this->mailer->send($this->validMessage('e@f.com'));

        $this->assertCount(3, $this->mailer->getSent());
    }

    public function testSendValidatesMessage(): void
    {
        $incomplete = new Message();  // pas de from, to, subject, body

        $this->expectException(\LogicException::class);
        $this->mailer->send($incomplete);
    }
}
