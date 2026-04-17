<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use Framework\Mail\Address;
use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase
{
    public function testFormatWithName(): void
    {
        $a = new Address('john@example.com', 'John Doe');
        $this->assertSame('"John Doe" <john@example.com>', $a->format());
    }

    public function testFormatWithoutName(): void
    {
        $a = new Address('john@example.com');
        $this->assertSame('john@example.com', $a->format());
    }

    public function testToStringCallsFormat(): void
    {
        $a = new Address('john@example.com', 'John');
        $this->assertSame($a->format(), (string) $a);
    }

    public function testNameWithQuotesIsEscaped(): void
    {
        $a = new Address('a@b.com', 'O\'Brien');
        $this->assertStringContainsString('\\', $a->format());
    }
}
