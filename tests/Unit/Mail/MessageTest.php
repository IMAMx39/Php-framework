<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use Framework\Mail\Message;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    private function validMessage(): Message
    {
        return (new Message())
            ->from('sender@example.com', 'Sender')
            ->to('recipient@example.com', 'Recipient')
            ->subject('Hello')
            ->text('Hello world');
    }

    // ------------------------------------------------------------------
    // Construction fluide
    // ------------------------------------------------------------------

    public function testFluentBuild(): void
    {
        $msg = $this->validMessage();

        $this->assertSame('sender@example.com', $msg->getFrom()->email);
        $this->assertSame('Sender', $msg->getFrom()->name);
        $this->assertCount(1, $msg->getTo());
        $this->assertSame('Hello', $msg->getSubject());
        $this->assertSame('Hello world', $msg->getText());
    }

    public function testMultipleRecipients(): void
    {
        $msg = (new Message())
            ->from('a@b.com')
            ->to('c@d.com')
            ->to('e@f.com')
            ->cc('g@h.com')
            ->bcc('i@j.com')
            ->subject('Test')
            ->text('body');

        $this->assertCount(2, $msg->getTo());
        $this->assertCount(1, $msg->getCc());
        $this->assertCount(1, $msg->getBcc());
    }

    public function testHtmlBody(): void
    {
        $msg = $this->validMessage()->html('<p>Hello</p>');

        $this->assertSame('<p>Hello</p>', $msg->getHtml());
    }

    // ------------------------------------------------------------------
    // validate()
    // ------------------------------------------------------------------

    public function testValidMessagePassesValidation(): void
    {
        $this->validMessage()->validate();
        $this->assertTrue(true);
    }

    public function testMissingFromThrows(): void
    {
        $msg = (new Message())->to('a@b.com')->subject('s')->text('t');

        $this->expectException(\LogicException::class);
        $msg->validate();
    }

    public function testMissingToThrows(): void
    {
        $msg = (new Message())->from('a@b.com')->subject('s')->text('t');

        $this->expectException(\LogicException::class);
        $msg->validate();
    }

    public function testMissingSubjectThrows(): void
    {
        $msg = (new Message())->from('a@b.com')->to('c@d.com')->text('t');

        $this->expectException(\LogicException::class);
        $msg->validate();
    }

    public function testMissingBodyThrows(): void
    {
        $msg = (new Message())->from('a@b.com')->to('c@d.com')->subject('s');

        $this->expectException(\LogicException::class);
        $msg->validate();
    }

    public function testHtmlAloneIsValidBody(): void
    {
        $msg = (new Message())->from('a@b.com')->to('c@d.com')->subject('s')->html('<p>ok</p>');
        $msg->validate();

        $this->assertTrue(true);
    }
}
