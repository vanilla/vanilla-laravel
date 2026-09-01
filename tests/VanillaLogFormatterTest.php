<?php

namespace VanillaTests\Laravel;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Vanilla\Laravel\Exceptions\ContextException;
use Vanilla\Laravel\Logging\VanillaLogFormatter;

/**
 * Tests for the log formatter.
 */
class VanillaLogFormatterTest extends TestCase
{
    /** @var TestHandler */
    private $testLogs;

    /** @var Logger */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $testLogs = new TestHandler();
        $logFormatter = new VanillaLogFormatter();
        $basePath = realpath(__DIR__ . "/../");
        $logFormatter->setApplicationBasePath($basePath);
        $testLogs->setFormatter($logFormatter);
        $logger = new Logger("my-logger");
        $logger->pushHandler($testLogs);

        $time = new \DateTimeImmutable("2022-01-01");
        $logFormatter->mockedTime = $time;

        $this->logger = $logger;
        $this->testLogs = $testLogs;
    }

    /**
     * Assert that a certain log line comes out of a callable.
     *
     * @param array<string, mixed> $expectedPayload
     * @param callable $callable
     * @return void
     */
    private function assertLogLine(array $expectedPayload, callable $callable): void
    {
        call_user_func($callable);
        $logRecords = $this->testLogs->getRecords();
        $lastLog = end($logRecords);
        $payload = json_decode(substr(trim($lastLog->formatted), 6), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey("stacktrace", $payload);
        unset($payload["stacktrace"]);

        if (isset($payload["error"]["stacktrace"])) {
            unset($payload["error"]["stacktrace"]);
        }
        if (isset($payload["error"]["previous"]["stacktrace"])) {
            unset($payload["error"]["previous"]["stacktrace"]);
        }
        if (isset($payload["error"]["file"])) {
            $this->assertStringEndsWith("VanillaLogFormatterTest.php", explode(":", $payload["error"]["file"])[0]);
            unset($payload["error"]["file"]);
        }
        if (isset($payload["error"]["previous"]["file"])) {
            $this->assertStringEndsWith(
                "VanillaLogFormatterTest.php",
                explode(":", $payload["error"]["previous"]["file"])[0],
            );
            unset($payload["error"]["previous"]["file"]);
        }

        $this->assertSame($expectedPayload, $payload);
    }

    /**
     * Test that things format as expected.
     */
    public function testFormatAsExcepted(): void
    {
        $this->assertLogLine(
            [
                "message" => "hello world",
                "level" => 200,
                "level_name" => "INFO",
                "channel" => "my-logger",
                "datetime" => "2022-01-01T00:00:00+00:00",
                "_schema" => "v2",
            ],
            fn() => $this->logger->info("hello world"),
        );

        $this->assertLogLine(
            [
                "message" => "hello world",
                "level" => 200,
                "level_name" => "INFO",
                "channel" => "my-logger",
                "datetime" => "2022-01-01T00:00:00+00:00",
                "_schema" => "v2",
                "extra-data" => ["foo" => "bar"],
            ],
            fn() => $this->logger->info("hello world", ["extra-data" => ["foo" => "bar"]]),
        );

        // Now test exception serialization
        $excpection = new ContextException(
            "Bam!",
            500,
            ["contextFoo" => "contextBar"],
            new \Exception("Parent Exception", 543),
        );
        $this->assertLogLine(
            [
                "message" => "hello world",
                "level" => 200,
                "level_name" => "INFO",
                "channel" => "my-logger",
                "datetime" => "2022-01-01T00:00:00+00:00",
                "_schema" => "v2",
                "error" => [
                    "class" => ContextException::class,
                    "message" => "Bam!",
                    "code" => 500,
                    "previous" => [
                        "class" => "Exception",
                        "message" => "Parent Exception",
                        "code" => 543,
                    ],
                    "contextFoo" => "contextBar",
                ],
            ],
            fn() => $this->logger->info("hello world", ["error" => $excpection]),
        );
    }
}
