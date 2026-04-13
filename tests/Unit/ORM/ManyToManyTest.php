<?php

declare(strict_types=1);

namespace Tests\Unit\ORM;

use App\Entity\Post;
use App\Entity\Tag;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use Framework\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration de la relation ManyToMany Post ↔ Tag.
 * Utilise une base SQLite en mémoire pour l'isolation.
 */
class ManyToManyTest extends TestCase
{
    private Connection $db;
    private PostRepository $postRepo;
    private TagRepository  $tagRepo;

    protected function setUp(): void
    {
        $_ENV['DATABASE_URL'] = 'sqlite::memory:';
        $this->db       = new Connection();
        $this->postRepo = new PostRepository($this->db);
        $this->tagRepo  = new TagRepository($this->db);

        $this->db->query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE)');
        $this->db->query('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255) NOT NULL, content TEXT, user_id INTEGER, created_at VARCHAR(255))');
        $this->db->query('CREATE TABLE comments (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT NOT NULL, post_id INTEGER NOT NULL, user_id INTEGER, created_at VARCHAR(255))');
        $this->db->query('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50) NOT NULL UNIQUE, color VARCHAR(7))');
        $this->db->query('CREATE TABLE post_tags (post_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY (post_id, tag_id))');
    }

    // ------------------------------------------------------------------
    // Création de base
    // ------------------------------------------------------------------

    public function testTagCanBeSavedAndRetrieved(): void
    {
        $tag = new Tag('PHP', '#777BB4');
        $this->tagRepo->save($tag);

        $this->assertNotNull($tag->getId());

        $found = $this->tagRepo->find($tag->getId());

        $this->assertInstanceOf(Tag::class, $found);
        $this->assertSame('PHP', $found->getName());
        $this->assertSame('#777BB4', $found->getColor());
    }

    public function testTagFindByName(): void
    {
        $this->tagRepo->save(new Tag('ORM'));
        $this->tagRepo->save(new Tag('PHP'));

        $found = $this->tagRepo->findByName('ORM');

        $this->assertNotNull($found);
        $this->assertSame('ORM', $found->getName());
    }

    public function testFindByNameReturnsNullForUnknownTag(): void
    {
        $this->assertNull($this->tagRepo->findByName('nonexistent'));
    }

    // ------------------------------------------------------------------
    // attach() — ajouter une relation
    // ------------------------------------------------------------------

    public function testAttachAddsTagToPost(): void
    {
        [$post, $php, ] = $this->createFixtures();

        $this->postRepo->addTag($post, $php);

        $loaded = $this->postRepo->find($post->getId(), relations: ['tags']);

        $this->assertCount(1, $loaded->getTags());
        $this->assertSame('PHP', $loaded->getTags()[0]->getName());
    }

    public function testAttachMultipleTags(): void
    {
        [$post, $php, $orm] = $this->createFixtures();

        $this->postRepo->addTag($post, $php);
        $this->postRepo->addTag($post, $orm);

        $loaded = $this->postRepo->find($post->getId(), relations: ['tags']);

        $this->assertCount(2, $loaded->getTags());
    }

    public function testAttachIsIdempotent(): void
    {
        [$post, $php, ] = $this->createFixtures();

        $this->postRepo->addTag($post, $php);
        $this->postRepo->addTag($post, $php); // doublon

        $loaded = $this->postRepo->find($post->getId(), relations: ['tags']);

        $this->assertCount(1, $loaded->getTags());
    }

    // ------------------------------------------------------------------
    // detach() — retirer une relation
    // ------------------------------------------------------------------

    public function testDetachRemovesTag(): void
    {
        [$post, $php, $orm] = $this->createFixtures();

        $this->postRepo->addTag($post, $php);
        $this->postRepo->addTag($post, $orm);
        $this->postRepo->removeTag($post, $php);

        $loaded = $this->postRepo->find($post->getId(), relations: ['tags']);

        $this->assertCount(1, $loaded->getTags());
        $this->assertSame('ORM', $loaded->getTags()[0]->getName());
    }

    public function testDetachNonExistentRelationDoesNotThrow(): void
    {
        [$post, $php, ] = $this->createFixtures();

        // Pas de tag attaché — ne doit pas planter
        $this->postRepo->removeTag($post, $php);

        $loaded = $this->postRepo->find($post->getId(), relations: ['tags']);

        $this->assertCount(0, $loaded->getTags());
    }

    // ------------------------------------------------------------------
    // sync() — remplacer toute la collection
    // ------------------------------------------------------------------

    public function testSyncReplacesExistingTags(): void
    {
        [$post, $php, $orm] = $this->createFixtures();
        $mysql = new Tag('MySQL', '#00758F');
        $this->tagRepo->save($mysql);

        $this->postRepo->addTag($post, $php);
        $this->postRepo->syncTags($post, [$orm, $mysql]);

        $loaded = $this->postRepo->find($post->getId(), relations: ['tags']);
        $names  = array_map(fn (Tag $t) => $t->getName(), $loaded->getTags());
        sort($names);

        $this->assertSame(['MySQL', 'ORM'], $names);
    }

    public function testSyncWithEmptyCollectionRemovesAllTags(): void
    {
        [$post, $php, $orm] = $this->createFixtures();

        $this->postRepo->addTag($post, $php);
        $this->postRepo->addTag($post, $orm);
        $this->postRepo->syncTags($post, []);

        $loaded = $this->postRepo->find($post->getId(), relations: ['tags']);

        $this->assertCount(0, $loaded->getTags());
    }

    public function testSyncKeepsUnchangedTags(): void
    {
        [$post, $php, $orm] = $this->createFixtures();

        $this->postRepo->addTag($post, $php);
        $this->postRepo->addTag($post, $orm);

        // On resync avec les mêmes — rien ne change
        $this->postRepo->syncTags($post, [$php, $orm]);

        $loaded = $this->postRepo->find($post->getId(), relations: ['tags']);

        $this->assertCount(2, $loaded->getTags());
    }

    // ------------------------------------------------------------------
    // orderBy sur ManyToMany
    // ------------------------------------------------------------------

    public function testTagsAreReturnedInAlphabeticalOrder(): void
    {
        [$post, , $orm] = $this->createFixtures();
        $ajax = new Tag('Ajax', '#F0DB4F');
        $this->tagRepo->save($ajax);

        $this->postRepo->addTag($post, $orm);
        $this->postRepo->addTag($post, $ajax);

        $loaded = $this->postRepo->find($post->getId(), relations: ['tags']);
        $names  = array_map(fn (Tag $t) => $t->getName(), $loaded->getTags());

        $this->assertSame(['Ajax', 'ORM'], $names);
    }

    // ------------------------------------------------------------------
    // findWithAll()
    // ------------------------------------------------------------------

    public function testFindWithAllIncludesTags(): void
    {
        [$post, $php, $orm] = $this->createFixtures();

        $this->postRepo->addTag($post, $php);
        $this->postRepo->addTag($post, $orm);

        $loaded = $this->postRepo->findWithAll($post->getId());

        $this->assertNotNull($loaded);
        $this->assertCount(2, $loaded->getTags());
    }

    // ------------------------------------------------------------------
    // Plusieurs posts — isolation
    // ------------------------------------------------------------------

    public function testTagsAreIsolatedPerPost(): void
    {
        [$post1, $php, $orm] = $this->createFixtures();
        $post2 = new Post('Post 2');
        $this->postRepo->save($post2);

        $this->postRepo->addTag($post1, $php);
        $this->postRepo->addTag($post2, $orm);

        $loaded1 = $this->postRepo->find($post1->getId(), relations: ['tags']);
        $loaded2 = $this->postRepo->find($post2->getId(), relations: ['tags']);

        $this->assertCount(1, $loaded1->getTags());
        $this->assertSame('PHP', $loaded1->getTags()[0]->getName());

        $this->assertCount(1, $loaded2->getTags());
        $this->assertSame('ORM', $loaded2->getTags()[0]->getName());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return array{Post, Tag, Tag}
     */
    private function createFixtures(): array
    {
        $post = new Post('Test Post', 'Content');
        $this->postRepo->save($post);

        $php = new Tag('PHP', '#777BB4');
        $this->tagRepo->save($php);

        $orm = new Tag('ORM', '#4CAF50');
        $this->tagRepo->save($orm);

        return [$post, $php, $orm];
    }
}
