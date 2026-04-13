<?php

declare(strict_types=1);

namespace Tests\Unit\Logger;

use Framework\Logger\Handler\FileHandler;
use Framework\Logger\Handler\HandlerInterface;
use Framework\Logger\Handler\NullHandler;
use Framework\Logger\LogLevel;
use Framework\Logger\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    // ------------------------------------------------------------------
    // Méthodes de commodité
    // ------------------------------------------------------------------

    public function testAllLevelMethodsCallLog(): void
    {
        $levels   = LogLevel::SEVERITY;
        $received = [];

        $handler = $this->makeHandler(function (array $r) use (&$received) {
            $received[] = $r['level'];
        });

        $logger = new Logger('test');
        $logger->addHandler($handler);

        $logger->debug('d');
        $logger->info('i');
        $logger->notice('n');
        $logger->warning('w');
        $logger->error('e');
        $logger->critical('c');
        $logger->alert('a');
        $logger->emergency('em');

        $this->assertSame(array_keys($levels), $received);
    }

    public function testInvalidLevelThrows(): void
    {
        $logger = new Logger('test');
        $logger->addHandler(new NullHandler());

        $this->expectException(\InvalidArgumentException::class);

        $logger->log('super_level', 'message');
    }

    // ------------------------------------------------------------------
    // Channel
    // ------------------------------------------------------------------

    public function testChannelIsIncludedInRecord(): void
    {
        $received = [];
        $logger   = new Logger('payments');
        $logger->addHandler($this->makeHandler(function (array $r) use (&$received) {
            $received = $r;
        }));

        $logger->info('paid');

        $this->assertSame('payments', $received['channel']);
    }

    // ------------------------------------------------------------------
    // Contexte et interpolation
    // ------------------------------------------------------------------

    public function testContextIsPassedToHandler(): void
    {
        $received = [];
        $logger   = new Logger('test');
        $logger->addHandler($this->makeHandler(function (array $r) use (&$received) {
            $received = $r;
        }));

        $logger->info('User logged in', ['user_id' => 42, 'ip' => '127.0.0.1']);

        $this->assertSame(['user_id' => 42, 'ip' => '127.0.0.1'], $received['context']);
    }

    // ------------------------------------------------------------------
    // Sérialisation des exceptions
    // ------------------------------------------------------------------

    public function testExceptionInContextIsSerialized(): void
    {
        $received = [];
        $logger   = new Logger('test');
        $logger->addHandler($this->makeHandler(function (array $r) use (&$received) {
            $received = $r;
        }));

        $e = new \RuntimeException('something broke');
        $logger->error('Error occurred', ['exception' => $e]);

        $this->assertIsArray($received['context']['exception']);
        $this->assertSame(\RuntimeException::class, $received['context']['exception']['class']);
        $this->assertSame('something broke', $received['context']['exception']['message']);
        $this->assertStringContainsString('LoggerTest.php', $received['context']['exception']['file']);
    }

    // ------------------------------------------------------------------
    // Record contient une DateTimeImmutable
    // ------------------------------------------------------------------

    public function testRecordContainsDatetime(): void
    {
        $received = [];
        $logger   = new Logger('test');
        $logger->addHandler($this->makeHandler(function (array $r) use (&$received) {
            $received = $r;
        }));

        $logger->info('msg');

        $this->assertInstanceOf(\DateTimeImmutable::class, $received['datetime']);
    }

    // ------------------------------------------------------------------
    // Plusieurs handlers
    // ------------------------------------------------------------------

    public function testMultipleHandlersAllReceiveRecord(): void
    {
        $count  = 0;
        $logger = new Logger('test');

        for ($i = 0; $i < 3; $i++) {
            $logger->addHandler($this->makeHandler(function () use (&$count) {
                $count++;
            }));
        }

        $logger->info('msg');

        $this->assertSame(3, $count);
    }

    // ------------------------------------------------------------------
    // LogLevel::isHandling
    // ------------------------------------------------------------------

    public function testLogLevelIsHandling(): void
    {
        $this->assertTrue(LogLevel::isHandling(LogLevel::ERROR,   LogLevel::WARNING));
        $this->assertTrue(LogLevel::isHandling(LogLevel::WARNING,  LogLevel::WARNING));
        $this->assertFalse(LogLevel::isHandling(LogLevel::DEBUG,   LogLevel::WARNING));
        $this->assertFalse(LogLevel::isHandling(LogLevel::INFO,    LogLevel::ERROR));
        $this->assertTrue(LogLevel::isHandling(LogLevel::EMERGENCY, LogLevel::DEBUG));
    }

    // ------------------------------------------------------------------
    // FileHandler
    // ------------------------------------------------------------------

    public function testFileHandlerWritesToFile(): void
    {
        $path   = sys_get_temp_dir() . '/phpfw_test_' . uniqid() . '.log';
        $logger = new Logger('test');
        $logger->addHandler(new FileHandler($path, LogLevel::DEBUG));

        $logger->warning('disk almost full', ['used' => '95%']);

        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('disk almost full', $content);
        $this->assertStringContainsString('"used":"95%"', $content);

        unlink($path);
    }

    public function testFileHandlerRespectsMinLevel(): void
    {
        $path   = sys_get_temp_dir() . '/phpfw_test_' . uniqid() . '.log';
        $logger = new Logger('test');
        $logger->addHandler(new FileHandler($path, LogLevel::ERROR));

        $logger->debug('ignored');
        $logger->info('ignored too');
        $logger->error('this one gets written');

        $content = file_get_contents($path);
        $this->assertStringNotContainsString('ignored', $content);
        $this->assertStringContainsString('this one gets written', $content);

        unlink($path);
    }

    public function testFileHandlerAppendsMultipleLines(): void
    {
        $path   = sys_get_temp_dir() . '/phpfw_test_' . uniqid() . '.log';
        $logger = new Logger('test');
        $logger->addHandler(new FileHandler($path));

        $logger->info('first');
        $logger->info('second');
        $logger->info('third');

        $lines = array_filter(explode(PHP_EOL, file_get_contents($path)));
        $this->assertCount(3, $lines);

        unlink($path);
    }

    // ------------------------------------------------------------------
    // NullHandler
    // ------------------------------------------------------------------

    public function testNullHandlerAcceptsAllLevels(): void
    {
        $handler = new NullHandler();

        foreach (array_keys(LogLevel::SEVERITY) as $level) {
            $this->assertTrue($handler->isHandling($level));
        }
    }

    // ------------------------------------------------------------------
    // LineFormatter
    // ------------------------------------------------------------------

    public function testLineFormatterInterpolatesPlaceholders(): void
    {
        $path   = sys_get_temp_dir() . '/phpfw_test_' . uniqid() . '.log';
        $logger = new Logger('test');
        $logger->addHandler(new FileHandler($path));

        $logger->info('User {id} logged in from {ip}', ['id' => 42, 'ip' => '127.0.0.1']);

        $content = file_get_contents($path);
        $this->assertStringContainsString('User 42 logged in from 127.0.0.1', $content);

        unlink($path);
    }

    public function testLineFormatterFormat(): void
    {
        $path   = sys_get_temp_dir() . '/phpfw_test_' . uniqid() . '.log';
        $logger = new Logger('app');
        $logger->addHandler(new FileHandler($path));

        $logger->error('test message');

        $content = trim(file_get_contents($path));
        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] app\.ERROR: test message$/',
            $content,
        );

        unlink($path);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function makeHandler(callable $callback): HandlerInterface
    {
        return new class ($callback) implements HandlerInterface {
            public function __construct(private readonly \Closure $callback) {}

            public function handle(array $record): void
            {
                ($this->callback)($record);
            }

            public function isHandling(string $level): bool
            {
                return true;
            }
        };
    }
}
