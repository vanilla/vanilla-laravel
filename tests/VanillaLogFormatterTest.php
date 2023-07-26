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
     * PUTTING THIS UTILITY EARLY SO IT HAS A STABLE STACKTRACE.
     *
     * Assert that a certain log line comes out of a callable.
     *
     * @param string $expectedLogLine
     * @param callable $callable
     * @return void
     */
    private function assertLogLine(string $expectedLogLine, callable $callable): void
    {
        call_user_func($callable);
        $logRecords = $this->testLogs->getRecords();
        $lastLog = end($logRecords);
        $this->assertSame(trim($expectedLogLine), trim($lastLog->formatted));
    }

    /**
     * Test that things format as expected.
     */
    public function testFormatAsExcepted(): void
    {
        $this->assertLogLine(
            '$json:{"message":"hello world","level":200,"level_name":"INFO","channel":"my-logger","datetime":"2022-01-01T00:00:00+00:00","_schema":"v2","stacktrace":"/tests/VanillaLogFormatterTest.php (64)\n/unknown (0)\n/tests/VanillaLogFormatterTest.php (51)\n/tests/VanillaLogFormatterTest.php (62)"}',
            fn() => $this->logger->info("hello world"),
        );

        $this->assertLogLine(
            '$json:{"message":"hello world","level":200,"level_name":"INFO","channel":"my-logger","datetime":"2022-01-01T00:00:00+00:00","_schema":"v2","extra-data":{"foo":"bar"},"stacktrace":"/tests/VanillaLogFormatterTest.php (69)\n/unknown (0)\n/tests/VanillaLogFormatterTest.php (51)\n/tests/VanillaLogFormatterTest.php (67)"}',
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
            '$json:{"message":"hello world","level":200,"level_name":"INFO","channel":"my-logger","datetime":"2022-01-01T00:00:00+00:00","_schema":"v2","error":{"class":"Vanilla\\\\Laravel\\\\Exceptions\\\\ContextException","message":"Bam!","code":500,"file":"/tests/VanillaLogFormatterTest.php:73","previous":{"class":"Exception","message":"Parent Exception","code":543,"file":"/tests/VanillaLogFormatterTest.php:77","stacktrace":""},"contextFoo":"contextBar","stacktrace":""},"stacktrace":"/tests/VanillaLogFormatterTest.php (81)\n/unknown (0)\n/tests/VanillaLogFormatterTest.php (51)\n/tests/VanillaLogFormatterTest.php (79)"}',
            fn() => $this->logger->info("hello world", ["error" => $excpection]),
        );
    }
}
