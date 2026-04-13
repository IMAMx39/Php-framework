<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Framework\Console\Command\MakeControllerCommand;
use Framework\Console\Command\MakeEntityCommand;
use Framework\Console\Command\MakeMigrationCommand;
use Framework\Console\Generator;
use PHPUnit\Framework\TestCase;

class MakeCommandsTest extends TestCase
{
    private string $tmpDir;
    private Generator $generator;

    protected function setUp(): void
    {
        // Répertoire temporaire isolé pour chaque test
        $this->tmpDir    = sys_get_temp_dir() . '/phpfw_make_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->generator = new Generator($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ==================================================================
    // make:entity
    // ==================================================================

    public function testMakeEntityCreatesEntityFile(): void
    {
        $cmd = new MakeEntityCommand($this->generator);
        ob_start();
        $code = $cmd->execute(['Product']);
        ob_end_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->tmpDir . '/app/Entity/Product.php');
    }

    public function testMakeEntityCreatesRepositoryFile(): void
    {
        $cmd = new MakeEntityCommand($this->generator);
        ob_start();
        $cmd->execute(['Product']);
        ob_end_clean();

        $this->assertFileExists($this->tmpDir . '/app/Repository/ProductRepository.php');
    }

    public function testMakeEntityEntityContainsCorrectClass(): void
    {
        $cmd = new MakeEntityCommand($this->generator);
        ob_start();
        $cmd->execute(['Product']);
        ob_end_clean();

        $content = file_get_contents($this->tmpDir . '/app/Entity/Product.php');
        $this->assertStringContainsString('class Product', $content);
        $this->assertStringContainsString("table: 'products'", $content);
        $this->assertStringContainsString('ProductRepository::class', $content);
        $this->assertStringContainsString('#[Entity', $content);
        $this->assertStringContainsString('#[Id]', $content);
    }

    public function testMakeEntityRepositoryContainsCorrectClass(): void
    {
        $cmd = new MakeEntityCommand($this->generator);
        ob_start();
        $cmd->execute(['Product']);
        ob_end_clean();

        $content = file_get_contents($this->tmpDir . '/app/Repository/ProductRepository.php');
        $this->assertStringContainsString('class ProductRepository extends AbstractRepository', $content);
        $this->assertStringContainsString('Product::class', $content);
    }

    public function testMakeEntityTableNameIsPluralSnakeCase(): void
    {
        $cases = [
            'BlogPost'    => 'blog_posts',
            'UserProfile' => 'user_profiles',
            'Category'    => 'categories',
            'Address'     => 'addresses',
        ];

        foreach ($cases as $name => $expectedTable) {
            $tmpDir    = sys_get_temp_dir() . '/phpfw_make_table_' . uniqid();
            mkdir($tmpDir, 0755, true);
            $generator = new Generator($tmpDir);
            $cmd       = new MakeEntityCommand($generator);

            ob_start();
            $cmd->execute([$name]);
            ob_end_clean();

            $content = file_get_contents($tmpDir . '/app/Entity/' . $name . '.php');
            $this->assertStringContainsString("table: '{$expectedTable}'", $content, "Failed for $name");

            $this->removeDir($tmpDir);
        }
    }

    public function testMakeEntityReturnsErrorCodeWithNoArgument(): void
    {
        $cmd = new MakeEntityCommand($this->generator);
        ob_start();
        $code = $cmd->execute([]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function testMakeEntitySkipsExistingFile(): void
    {
        $cmd = new MakeEntityCommand($this->generator);

        ob_start();
        $cmd->execute(['Product']);  // première fois
        $code = $cmd->execute(['Product']);  // deuxième fois — skip
        ob_end_clean();

        // Le code doit rester 0 (succès avec warning) — pas d'erreur fatale
        $this->assertSame(0, $code);
    }

    // ==================================================================
    // make:migration
    // ==================================================================

    public function testMakeMigrationCreatesFile(): void
    {
        $cmd = new MakeMigrationCommand($this->generator);
        ob_start();
        $code = $cmd->execute(['CreateProductsTable']);
        ob_end_clean();

        $this->assertSame(0, $code);

        $files = glob($this->tmpDir . '/migrations/Version*.php');
        $this->assertCount(1, $files);
    }

    public function testMakeMigrationFileContainsCorrectClass(): void
    {
        $cmd = new MakeMigrationCommand($this->generator);
        ob_start();
        $cmd->execute(['CreateProductsTable']);
        ob_end_clean();

        $files   = glob($this->tmpDir . '/migrations/Version*.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('extends AbstractMigration', $content);
        $this->assertStringContainsString('CreateProductsTable', $content);
        $this->assertStringContainsString('public function up(): void', $content);
        $this->assertStringContainsString('public function down(): void', $content);
    }

    public function testMakeMigrationClassNameContainsTimestamp(): void
    {
        $cmd = new MakeMigrationCommand($this->generator);
        ob_start();
        $cmd->execute(['AddPriceToProducts']);
        ob_end_clean();

        $files = glob($this->tmpDir . '/migrations/Version*.php');
        $this->assertMatchesRegularExpression('/Version\d{14}AddPriceToProducts\.php$/', $files[0]);
    }

    public function testMakeMigrationNormalizesDescription(): void
    {
        $cmd = new MakeMigrationCommand($this->generator);
        ob_start();
        $cmd->execute(['create users table']);  // espaces → PascalCase
        ob_end_clean();

        $files = glob($this->tmpDir . '/migrations/Version*.php');
        $this->assertStringContainsString('CreateUsersTable', $files[0]);
    }

    public function testMakeMigrationReturnsErrorWithNoArgument(): void
    {
        $cmd = new MakeMigrationCommand($this->generator);
        ob_start();
        $code = $cmd->execute([]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    // ==================================================================
    // make:controller
    // ==================================================================

    public function testMakeControllerCreatesFile(): void
    {
        $cmd = new MakeControllerCommand($this->generator);
        ob_start();
        $code = $cmd->execute(['Home']);
        ob_end_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->tmpDir . '/app/Controller/HomeController.php');
    }

    public function testMakeControllerFileContainsCorrectClass(): void
    {
        $cmd = new MakeControllerCommand($this->generator);
        ob_start();
        $cmd->execute(['Product']);
        ob_end_clean();

        $content = file_get_contents($this->tmpDir . '/app/Controller/ProductController.php');
        $this->assertStringContainsString('class ProductController extends AbstractController', $content);
        $this->assertStringContainsString('#[Route', $content);
        $this->assertStringContainsString('/product', $content);
        $this->assertStringContainsString('product.index', $content);
        $this->assertStringContainsString('public function index(', $content);
    }

    public function testMakeControllerNormalizesControllerSuffix(): void
    {
        // "HomeController" et "Home" doivent produire le même résultat
        $cmd = new MakeControllerCommand($this->generator);
        ob_start();
        $cmd->execute(['HomeController']);
        ob_end_clean();

        $this->assertFileExists($this->tmpDir . '/app/Controller/HomeController.php');

        $content = file_get_contents($this->tmpDir . '/app/Controller/HomeController.php');
        // Ne doit pas contenir "HomeControllerController"
        $this->assertStringNotContainsString('HomeControllerController', $content);
    }

    public function testMakeControllerReturnsErrorWithNoArgument(): void
    {
        $cmd = new MakeControllerCommand($this->generator);
        ob_start();
        $code = $cmd->execute([]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function testMakeControllerSkipsExistingFile(): void
    {
        $cmd = new MakeControllerCommand($this->generator);
        ob_start();
        $cmd->execute(['Home']);
        $code = $cmd->execute(['Home']);
        ob_end_clean();

        $this->assertSame(1, $code); // deuxième appel → skip → retourne 1
    }

    // ==================================================================
    // Generator
    // ==================================================================

    public function testGeneratorWritesFile(): void
    {
        $written = $this->generator->write('test/file.txt', 'hello');

        $this->assertTrue($written);
        $this->assertFileExists($this->tmpDir . '/test/file.txt');
        $this->assertSame('hello', file_get_contents($this->tmpDir . '/test/file.txt'));
    }

    public function testGeneratorReturnsFalseWhenFileExists(): void
    {
        $this->generator->write('test/file.txt', 'first');
        $written = $this->generator->write('test/file.txt', 'second');

        $this->assertFalse($written);
        $this->assertSame('first', file_get_contents($this->tmpDir . '/test/file.txt'));
    }

    public function testGeneratorCreatesIntermediateDirectories(): void
    {
        $this->generator->write('a/b/c/file.txt', 'deep');

        $this->assertDirectoryExists($this->tmpDir . '/a/b/c');
        $this->assertFileExists($this->tmpDir . '/a/b/c/file.txt');
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
