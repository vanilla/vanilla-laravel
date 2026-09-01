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

    /** @var VanillaLogFormatter */
    private $logFormatter;

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
        $this->logFormatter = $logFormatter;
    }

    /**
     * Test stack trace abbreviation and formatting rules directly.
     */
    public function testStackTraceArray(): void
    {
        $trace = [
            ["file" => $this->basePath() . "/src/Foo.php", "line" => 10],
            ["file" => $this->basePath() . "/vendor/monolog/monolog/src/Monolog/Logger.php", "line" => 200],
            [
                "file" => $this->basePath() . "/vendor/monolog/monolog/src/Monolog/Handler/AbstractHandler.php",
                "line" => 50,
            ],
            ["line" => 0],
            ["file" => $this->basePath() . "/vendor/phpunit/phpunit/src/Framework/TestCase.php", "line" => 100],
            ["file" => $this->basePath() . "/vendor/bin/phpunit", "line" => 122],
        ];

        $this->assertSame(
            ["/src/Foo.php (10)", "/vendor/monolog/**/* (2 frames)", "/unknown (0)"],
            $this->logFormatter->stackTraceArray($trace),
        );
        $this->assertSame(
            "/src/Foo.php (10)\n/vendor/monolog/**/* (2 frames)\n/unknown (0)",
            $this->logFormatter->stackTraceString($trace),
        );
        $this->assertSame(["/unknown (0)"], $this->logFormatter->stackTraceArray([["line" => 0]]));
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

        $exception = new ContextException(
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
            fn() => $this->logger->info("hello world", ["error" => $exception]),
            [
                "error" => "",
                "previous" => "",
            ],
        );
    }

    private function expectedLogStackTraceLinePattern(): string
    {
        return "#^(/tests/VanillaLogFormatterTest\\.php \\(\\d+\\)|/unknown \\(0\\))$#";
    }

    /**
     * Assert formatted log payload and stack trace content.
     *
     * @param array<string, mixed> $expectedPayload
     * @param array<string, string> $expectedNestedStackTraces
     */
    private function assertLogLine(
        array $expectedPayload,
        callable $callable,
        array $expectedNestedStackTraces = [],
    ): void {
        call_user_func($callable);
        $logRecords = $this->testLogs->getRecords();
        $lastLog = end($logRecords);
        $payload = json_decode(substr(trim($lastLog->formatted), 6), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey("stacktrace", $payload);
        $this->assertFormattedLogStackTrace($payload["stacktrace"]);
        unset($payload["stacktrace"]);

        if (isset($payload["error"]["stacktrace"])) {
            $this->assertSame(
                $expectedNestedStackTraces["error"] ?? null,
                $payload["error"]["stacktrace"],
                "Unexpected nested error stacktrace.",
            );
            unset($payload["error"]["stacktrace"]);
        }
        if (isset($payload["error"]["previous"]["stacktrace"])) {
            $this->assertSame(
                $expectedNestedStackTraces["previous"] ?? null,
                $payload["error"]["previous"]["stacktrace"],
                "Unexpected nested previous stacktrace.",
            );
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

    private function assertFormattedLogStackTrace(string $stacktrace): void
    {
        $lines = $stacktrace === "" ? [] : explode("\n", $stacktrace);
        $this->assertNotEmpty($lines, "Expected a formatted log stack trace.");

        $pattern = $this->expectedLogStackTraceLinePattern();
        foreach ($lines as $index => $line) {
            $this->assertMatchesRegularExpression($pattern, $line, "Unexpected stack trace line at index {$index}.");
        }

        $this->assertContains("/unknown (0)", $lines);
        $this->assertTrue(
            (bool) array_filter(
                $lines,
                static fn(string $line): bool => str_starts_with($line, "/tests/VanillaLogFormatterTest.php"),
            ),
            "Expected at least one test file frame in the log stack trace.",
        );
    }

    private function basePath(): string
    {
        return realpath(__DIR__ . "/../");
    }
}
