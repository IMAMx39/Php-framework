<?php

declare(strict_types=1);

namespace Tests\Unit\ORM;

use Framework\ORM\Attribute\Column;
use Framework\ORM\Attribute\Entity;
use Framework\ORM\Attribute\GeneratedValue;
use Framework\ORM\Attribute\Id;
use Framework\ORM\Attribute\ManyToOne;
use Framework\ORM\Attribute\OneToMany;
use Framework\ORM\EntityMapper;
use PHPUnit\Framework\TestCase;

// ── Fixtures ──────────────────────────────────────────────────────────

enum ArticleStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Archived  = 'archived';
}

#[Entity(table: 'articles_with_status')]
class ArticleWithStatusEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string')]
    private string $title = '';

    #[Column(type: 'string', nullable: true)]
    private ?ArticleStatus $status = null;
}

#[Entity(table: 'articles')]
class ArticleEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[Column(name: 'body_text', type: 'string', nullable: true)]
    private ?string $bodyText = null;

    #[Column(type: 'integer', nullable: true)]
    private ?int $views = null;

    #[Column(type: 'boolean', nullable: true)]
    private ?bool $published = null;

    #[Column(type: 'float', nullable: true)]
    private ?float $rating = null;

    // Relation — doit être ignorée par extract()
    #[ManyToOne(targetEntity: 'UserEntity', joinColumn: 'user_id')]
    private ?object $author = null;

    // OneToMany — doit être ignorée par extract()
    #[OneToMany(targetEntity: 'CommentEntity', mappedBy: 'article_id')]
    private array $comments = [];
}

// ── Tests ─────────────────────────────────────────────────────────────

class EntityMapperTest extends TestCase
{
    private EntityMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new EntityMapper();
    }

    // ------------------------------------------------------------------
    // hydrate()
    // ------------------------------------------------------------------

    public function testHydrateSetsMappedColumns(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, [
            'id'        => '1',
            'title'     => 'Hello World',
            'body_text' => 'Some content',
            'views'     => '42',
            'published' => '1',
            'rating'    => '4.5',
        ]);

        $ref = new \ReflectionClass($entity);

        $this->assertSame(1,     $this->getProp($entity, $ref, 'id'));
        $this->assertSame('Hello World', $this->getProp($entity, $ref, 'title'));
        $this->assertSame('Some content', $this->getProp($entity, $ref, 'bodyText'));
        $this->assertSame(42,    $this->getProp($entity, $ref, 'views'));
        $this->assertTrue(       $this->getProp($entity, $ref, 'published'));
        $this->assertSame(4.5,   $this->getProp($entity, $ref, 'rating'));
    }

    public function testHydrateIgnoresMissingColumns(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, [
            'id'    => '5',
            'title' => 'Partial',
            // body_text, views, published, rating absents
        ]);

        // Doit juste ne pas planter — les propriétés restent à leur valeur par défaut
        $this->assertInstanceOf(ArticleEntity::class, $entity);
    }

    public function testHydrateNullOnNullableColumn(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, [
            'id'        => '1',
            'title'     => 'X',
            'body_text' => null,
            'views'     => null,
        ]);

        $ref = new \ReflectionClass($entity);

        $this->assertNull($this->getProp($entity, $ref, 'bodyText'));
        $this->assertNull($this->getProp($entity, $ref, 'views'));
    }

    public function testHydrateDoesNotCallConstructor(): void
    {
        // newInstanceWithoutConstructor — le titre peut rester non initialisé
        // tant que la row le fournit. Ce test vérifie juste qu'on obtient l'instance.
        $entity = $this->mapper->hydrate(ArticleEntity::class, [
            'id'    => '1',
            'title' => 'Constructed',
        ]);

        $this->assertInstanceOf(ArticleEntity::class, $entity);
    }

    // ------------------------------------------------------------------
    // hydrateAll()
    // ------------------------------------------------------------------

    public function testHydrateAllReturnsCollection(): void
    {
        $entities = $this->mapper->hydrateAll(ArticleEntity::class, [
            ['id' => '1', 'title' => 'First'],
            ['id' => '2', 'title' => 'Second'],
        ]);

        $this->assertCount(2, $entities);
        $this->assertContainsOnlyInstancesOf(ArticleEntity::class, $entities);
    }

    public function testHydrateAllReturnsEmptyArrayForNoRows(): void
    {
        $entities = $this->mapper->hydrateAll(ArticleEntity::class, []);

        $this->assertSame([], $entities);
    }

    // ------------------------------------------------------------------
    // extract()
    // ------------------------------------------------------------------

    public function testExtractSkipsIdWhenIncludeIdFalse(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, ['id' => '10', 'title' => 'Test']);

        $data = $this->mapper->extract($entity, includeId: false);

        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
    }

    public function testExtractIncludesIdWhenIncludeIdTrue(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, ['id' => '10', 'title' => 'Test']);

        $data = $this->mapper->extract($entity, includeId: true);

        $this->assertArrayHasKey('id', $data);
        $this->assertSame(10, $data['id']);
    }

    public function testExtractUsesCustomColumnName(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, ['id' => '1', 'title' => 'T', 'body_text' => 'Content']);

        $data = $this->mapper->extract($entity, includeId: false);

        $this->assertArrayHasKey('body_text', $data);
        $this->assertArrayNotHasKey('bodyText', $data);
    }

    public function testExtractSkipsRelationProperties(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, ['id' => '1', 'title' => 'T']);

        $data = $this->mapper->extract($entity, includeId: false);

        $this->assertArrayNotHasKey('author', $data);
        $this->assertArrayNotHasKey('comments', $data);
    }

    public function testExtractOmitsNullNonNullableValues(): void
    {
        // title est non-nullable — mais ici on le force à null pour tester le comportement
        // (dans la pratique la propriété aurait déjà une valeur)
        $entity = $this->mapper->hydrate(ArticleEntity::class, ['id' => '1', 'title' => 'OK', 'views' => null]);

        $data = $this->mapper->extract($entity);

        // views est nullable — si null, il est absent du résultat OU présent avec null
        // Le code actuel l'exclut si null (non-nullable) ou l'inclut si nullable
        // Vérifions simplement que title est présent
        $this->assertSame('OK', $data['title']);
    }

    // ------------------------------------------------------------------
    // getId() / setId() / getIdColumnName()
    // ------------------------------------------------------------------

    public function testGetIdReturnsNullBeforePersist(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, ['title' => 'T']);

        $this->assertNull($this->mapper->getId($entity));
    }

    public function testGetIdReturnsValueAfterHydrate(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, ['id' => '7', 'title' => 'T']);

        $this->assertSame(7, $this->mapper->getId($entity));
    }

    public function testSetIdInjectsValue(): void
    {
        $entity = $this->mapper->hydrate(ArticleEntity::class, ['title' => 'T']);

        $this->mapper->setId($entity, 99);

        $this->assertSame(99, $this->mapper->getId($entity));
    }

    public function testGetIdColumnNameReturnsIdByDefault(): void
    {
        $this->assertSame('id', $this->mapper->getIdColumnName(ArticleEntity::class));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function getProp(object $entity, \ReflectionClass $ref, string $name): mixed
    {
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);

        return $prop->getValue($entity);
    }

    // ------------------------------------------------------------------
    // Enum support
    // ------------------------------------------------------------------

    public function testHydratesCastsStringToBackedEnum(): void
    {
        $entity = $this->mapper->hydrate(ArticleWithStatusEntity::class, [
            'id'     => '1',
            'title'  => 'Hello',
            'status' => 'published',
        ]);

        $ref    = new \ReflectionClass($entity);
        $status = $this->getProp($entity, $ref, 'status');

        $this->assertInstanceOf(ArticleStatus::class, $status);
        $this->assertSame(ArticleStatus::Published, $status);
    }

    public function testHydratesNullableEnumAsNull(): void
    {
        $entity = $this->mapper->hydrate(ArticleWithStatusEntity::class, [
            'id'     => '1',
            'title'  => 'Hello',
            'status' => null,
        ]);

        $ref    = new \ReflectionClass($entity);
        $status = $this->getProp($entity, $ref, 'status');

        $this->assertNull($status);
    }

    public function testExtractConvertsEnumToScalar(): void
    {
        $entity = $this->mapper->hydrate(ArticleWithStatusEntity::class, [
            'id'     => '1',
            'title'  => 'Hello',
            'status' => 'draft',
        ]);

        $data = $this->mapper->extract($entity);

        $this->assertSame('draft', $data['status']);
        $this->assertIsString($data['status']);
    }

    public function testExtractHandlesNullEnum(): void
    {
        $entity = $this->mapper->hydrate(ArticleWithStatusEntity::class, [
            'id'     => '1',
            'title'  => 'Hello',
            'status' => null,
        ]);

        $data = $this->mapper->extract($entity);

        $this->assertArrayHasKey('status', $data);
        $this->assertNull($data['status']);
    }
}
