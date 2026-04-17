<?php

declare(strict_types=1);

namespace Tests\Unit\Serializer;

use Framework\ORM\Attribute\Column;
use Framework\ORM\Attribute\Entity;
use Framework\Serializer\Serializer;
use Framework\Serializer\SerializeGroup;
use PHPUnit\Framework\TestCase;

// ── Fixtures ─────────────────────────────────────────────────────────

#[Entity(table: 'products')]
class Product
{
    #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string')]
    private string $name;

    #[Column(name: 'unit_price', type: 'float')]
    private float $price;

    #[Column(type: 'boolean')]
    private bool $active = true;

    public function __construct(string $name, float $price)
    {
        $this->name  = $name;
        $this->price = $price;
    }

    public function setId(int $id): void { $this->id = $id; }
    public function getId(): ?int        { return $this->id; }
    public function getName(): string    { return $this->name; }
    public function getPrice(): float    { return $this->price; }
    public function isActive(): bool     { return $this->active; }
}

class UserWithGroups
{
    #[Column(type: 'integer')]
    private int $id;

    #[Column(type: 'string')]
    #[SerializeGroup('public', 'admin')]
    private string $name;

    #[Column(type: 'string')]
    #[SerializeGroup('admin')]
    private string $email;

    #[Column(type: 'string')]
    #[SerializeGroup('internal')]
    private string $passwordHash;

    public function __construct(int $id, string $name, string $email, string $passwordHash)
    {
        $this->id           = $id;
        $this->name         = $name;
        $this->email        = $email;
        $this->passwordHash = $passwordHash;
    }

    public function getId(): int           { return $this->id; }
    public function getName(): string      { return $this->name; }
    public function getEmail(): string     { return $this->email; }
    public function getPasswordHash(): string { return $this->passwordHash; }
}

class WithToArray
{
    public function toArray(): array
    {
        return ['custom' => true, 'value' => 42];
    }
}

class SimplePublic
{
    public string $name  = 'test';
    public int    $count = 3;
}

// ── Tests ─────────────────────────────────────────────────────────────

class SerializerTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new Serializer();
    }

    // ------------------------------------------------------------------
    // Scalaires
    // ------------------------------------------------------------------

    public function testNormalizeScalar(): void
    {
        $this->assertSame(42, $this->serializer->normalize(42));
        $this->assertSame('hello', $this->serializer->normalize('hello'));
        $this->assertNull($this->serializer->normalize(null));
        $this->assertTrue($this->serializer->normalize(true));
    }

    // ------------------------------------------------------------------
    // Objet avec #[Column]
    // ------------------------------------------------------------------

    public function testNormalizeEntityWithColumnAttributes(): void
    {
        $product = new Product('Laptop', 999.99);
        $product->setId(1);

        $result = $this->serializer->normalize($product);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
        $this->assertSame('Laptop', $result['name']);
        $this->assertSame(999.99, $result['price']);
        $this->assertTrue($result['active']);
    }

    public function testSnakeCaseKeys(): void
    {
        $product = new Product('Test', 10.0);
        $result  = $this->serializer->normalize($product);

        // La propriété $price → clé 'price' (pas de getter, snake_case identique)
        $this->assertArrayHasKey('price', $result);
    }

    // ------------------------------------------------------------------
    // toArray()
    // ------------------------------------------------------------------

    public function testNormalizeUsesToArrayWhenAvailable(): void
    {
        $obj    = new WithToArray();
        $result = $this->serializer->normalize($obj);

        $this->assertSame(['custom' => true, 'value' => 42], $result);
    }

    // ------------------------------------------------------------------
    // Objet public sans #[Column]
    // ------------------------------------------------------------------

    public function testNormalizePublicProperties(): void
    {
        $obj    = new SimplePublic();
        $result = $this->serializer->normalize($obj);

        $this->assertSame('test', $result['name']);
        $this->assertSame(3, $result['count']);
    }

    // ------------------------------------------------------------------
    // Collection
    // ------------------------------------------------------------------

    public function testNormalizeCollection(): void
    {
        $products = [
            new Product('A', 10.0),
            new Product('B', 20.0),
        ];

        $result = $this->serializer->normalizeCollection($products);

        $this->assertCount(2, $result);
        $this->assertSame('A', $result[0]['name']);
        $this->assertSame('B', $result[1]['name']);
    }

    public function testNormalizeArray(): void
    {
        $result = $this->serializer->normalize([1, 'two', null]);

        $this->assertSame([1, 'two', null], $result);
    }

    // ------------------------------------------------------------------
    // Groupes
    // ------------------------------------------------------------------

    public function testNoGroupsIncludesAll(): void
    {
        $user   = new UserWithGroups(1, 'Alice', 'alice@x.com', 'hash');
        $result = $this->serializer->normalize($user, []);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('password_hash', $result);
    }

    public function testPublicGroupExcludesAdminAndInternal(): void
    {
        $user   = new UserWithGroups(1, 'Alice', 'alice@x.com', 'hash');
        $result = $this->serializer->normalize($user, ['public']);

        $this->assertArrayHasKey('id', $result);      // pas de groupe → toujours inclus
        $this->assertArrayHasKey('name', $result);    // groupe 'public' → inclus
        $this->assertArrayNotHasKey('email', $result);         // groupe 'admin' uniquement
        $this->assertArrayNotHasKey('password_hash', $result); // groupe 'internal' uniquement
    }

    public function testAdminGroupIncludesEmailButNotInternal(): void
    {
        $user   = new UserWithGroups(1, 'Alice', 'alice@x.com', 'hash');
        $result = $this->serializer->normalize($user, ['admin']);

        $this->assertArrayHasKey('email', $result);
        $this->assertArrayNotHasKey('password_hash', $result);
    }

    // ------------------------------------------------------------------
    // toJson
    // ------------------------------------------------------------------

    public function testToJson(): void
    {
        $product = new Product('Widget', 5.0);
        $product->setId(7);

        $json   = $this->serializer->toJson($product);
        $parsed = json_decode($json, true);

        $this->assertIsArray($parsed);
        $this->assertSame(7, $parsed['id']);
        $this->assertSame('Widget', $parsed['name']);
    }

    public function testToJsonCollection(): void
    {
        $json   = $this->serializer->toJson([new Product('A', 1.0), new Product('B', 2.0)]);
        $parsed = json_decode($json, true);

        $this->assertCount(2, $parsed);
    }

    // ------------------------------------------------------------------
    // denormalize
    // ------------------------------------------------------------------

    public function testDenormalize(): void
    {
        $data   = ['name' => 'Gadget', 'price' => 12.5, 'active' => true];
        $object = $this->serializer->denormalize($data, Product::class);

        $this->assertInstanceOf(Product::class, $object);
        $this->assertSame('Gadget', $object->getName());
        $this->assertSame(12.5, $object->getPrice());
    }
}
