<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use Framework\DTO\AbstractDTO;
use Framework\DTO\Attribute\Validate;
use Framework\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

// ── Fixtures ──────────────────────────────────────────────────────────

class ProductDTO extends AbstractDTO
{
    public function __construct(
        #[Validate('required|string|min:2')]
        public readonly string $name,

        #[Validate('required|numeric')]
        public readonly float $price,

        public readonly bool $active = false,

        public readonly int $stock = 0,
    ) {}
}

class DateDTO extends AbstractDTO
{
    public function __construct(
        public readonly \DateTimeImmutable $createdAt,
    ) {}
}

class SimpleDTO extends AbstractDTO
{
    public function __construct(
        public readonly string $title,
        public readonly int    $count,
        public readonly float  $ratio,
        public readonly bool   $flag,
    ) {}
}

// ── Tests ─────────────────────────────────────────────────────────────

class AbstractDTOTest extends TestCase
{
    // ------------------------------------------------------------------
    // Cast automatique
    // ------------------------------------------------------------------

    public function testCastsStringToInt(): void
    {
        $dto = SimpleDTO::fromArray(['title' => 'test', 'count' => '42', 'ratio' => '1.5', 'flag' => '1']);

        $this->assertSame(42, $dto->count);
    }

    public function testCastsStringToFloat(): void
    {
        $dto = SimpleDTO::fromArray(['title' => 'test', 'count' => '1', 'ratio' => '3.14', 'flag' => '0']);

        $this->assertSame(3.14, $dto->ratio);
    }

    public function testCastsTruthy(): void
    {
        foreach (['1', 'true', 'on', 'yes'] as $raw) {
            $dto = SimpleDTO::fromArray(['title' => 'x', 'count' => 0, 'ratio' => 0.0, 'flag' => $raw]);
            $this->assertTrue($dto->flag, "Expected true for '$raw'");
        }
    }

    public function testCastsFalsy(): void
    {
        foreach (['0', 'false', 'off', 'no'] as $raw) {
            $dto = SimpleDTO::fromArray(['title' => 'x', 'count' => 0, 'ratio' => 0.0, 'flag' => $raw]);
            $this->assertFalse($dto->flag, "Expected false for '$raw'");
        }
    }

    public function testCastsStringToString(): void
    {
        $dto = SimpleDTO::fromArray(['title' => 123, 'count' => 0, 'ratio' => 0.0, 'flag' => false]);

        $this->assertSame('123', $dto->title);
    }

    public function testCastsStringToDateTimeImmutable(): void
    {
        $dto = DateDTO::fromArray(['createdAt' => '2024-01-15']);

        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->createdAt);
        $this->assertSame('2024-01-15', $dto->createdAt->format('Y-m-d'));
    }

    // ------------------------------------------------------------------
    // Valeurs par défaut
    // ------------------------------------------------------------------

    public function testUsesDefaultWhenFieldAbsent(): void
    {
        $dto = ProductDTO::fromArray(['name' => 'Stylo', 'price' => '1.99']);

        $this->assertFalse($dto->active);
        $this->assertSame(0, $dto->stock);
    }

    public function testExplicitValueOverridesDefault(): void
    {
        $dto = ProductDTO::fromArray(['name' => 'Stylo', 'price' => '1.99', 'active' => 'true', 'stock' => '10']);

        $this->assertTrue($dto->active);
        $this->assertSame(10, $dto->stock);
    }

    // ------------------------------------------------------------------
    // Validation via #[Validate]
    // ------------------------------------------------------------------

    public function testValidationPassesWithCorrectData(): void
    {
        $dto = ProductDTO::fromArray(['name' => 'Stylo', 'price' => '1.99']);

        $this->assertSame('Stylo', $dto->name);
        $this->assertSame(1.99, $dto->price);
    }

    public function testValidationFailsWhenRequiredFieldMissing(): void
    {
        $this->expectException(ValidationException::class);

        ProductDTO::fromArray(['price' => '1.99']); // name manquant
    }

    public function testValidationFailsWhenRuleViolated(): void
    {
        $this->expectException(ValidationException::class);

        ProductDTO::fromArray(['name' => 'x', 'price' => '1.99']); // name < min:2
    }

    public function testValidationFailsForNonNumericPrice(): void
    {
        $this->expectException(ValidationException::class);

        ProductDTO::fromArray(['name' => 'Stylo', 'price' => 'abc']);
    }

    // ------------------------------------------------------------------
    // fromArray — résultat typé
    // ------------------------------------------------------------------

    public function testFromArrayReturnsCorrectInstance(): void
    {
        $dto = ProductDTO::fromArray(['name' => 'Cahier', 'price' => '2.50']);

        $this->assertInstanceOf(ProductDTO::class, $dto);
        $this->assertSame('Cahier', $dto->name);
        $this->assertSame(2.5, $dto->price);
    }
}
