<?php

namespace Vanilla\Laravel\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use Throwable;
use Vanilla\Laravel\Exceptions\ContextException;

/**
 * Custom JSON based formats for logs.
 */
class VanillaLogFormatter extends JsonFormatter
{
    private string $applicationBasePath = "";
    public \DateTimeInterface|null $mockedTime = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->ignoreEmptyContextAndExtra = true;
        $this->includeStacktraces = true;
        $this->appendNewline = false;
    }

    /**
     * Set the application base path to strip from debug stack traces.
     *
     * @param string $basePath
     *
     * @return void
     */
    public function setApplicationBasePath(string $basePath): void
    {
        $this->applicationBasePath = $basePath;
    }

    /**
     * Overridden to add exception context.
     *
     * @inheritDoc
     */
    protected function normalizeException(Throwable $e, int $depth = 0): array
    {
        $result = parent::normalizeException($e, $depth);
        if ($e instanceof ContextException) {
            $result = array_merge($result, $e->getContext());
        }
        unset($result["trace"]);
        $result["stacktrace"] = self::stackTraceString($e->getTrace());
        if (isset($result["file"])) {
            $result["file"] = self::substringLeftTrim($result["file"], $this->applicationBasePath);
        }

        return $result;
    }

    /**
     * Flatten the extra and context into to the top level log message.
     * Also applies a stack trace to logs.
     *
     * @param array|LogRecord $record
     *
     * This may be `LogRecord` in Laravel 10 and an array in Laravel 9, LogRecord supports ArrayAccess.
     * @psalm-suppress PossiblyInvalidArgument
     *
     * @return string
     */
    public function format($record): string
    {
        $result = parent::format($record);

        return "\$json:{$result}\n";
    }

    protected function normalizeRecord(LogRecord $record): array
    {
        $record = parent::normalizeRecord($record);
        $record["_schema"] = "v2";

        // Flatten the extra and context
        if (isset($record["context"]) && is_array($record["context"])) {
            foreach ($record["context"] as $key => $val) {
                $record[$key] = $val;
            }
            unset($record["context"]);
        }

        if (isset($record["extra"]) && is_array($record["extra"])) {
            foreach ($record["extra"] as $key => $val) {
                $record[$key] = $val;
            }
            unset($record["extra"]);
        }

        $exception = new \Exception();
        $trace = $exception->getTrace();
        $record["stacktrace"] = $this->stackTraceString($trace, null, 6);

        if ($this->mockedTime) {
            $record["datetime"] = $this->mockedTime->format(\DateTimeImmutable::ATOM);
        }

        return $record;
    }

    /**
     * Abbreviate and format a stack trace to give enough information to help debugging without taking up too much space in logs.
     *
     * @param array $trace The trace as returned by `debug_backtrace()` or `Throwable::getTrace()`.
     * @param int|null $limit The number of lines to return.
     * @param int $offset The part of the trace to start at.
     * @return array The stacktrace array.
     */
    public function stackTraceArray(array $trace, int $limit = null, int $offset = 0): array
    {
        $trace = array_slice($trace, $offset, $limit);
        $r = [];
        $countVendorFrames = 0;
        $currentVendorPrefix = null;

        foreach ($trace as $item) {
            $rawFilePath = $item["file"] ?? "/unknown";
            $file = self::substringLeftTrim($rawFilePath, $this->applicationBasePath);

            if (str_starts_with($file, "/vendor/bin") || str_starts_with($file, "/vendor/phpunit")) {
                // Ignore phpunit traces.
                continue;
            }

            // Skip vendor frames.
            if (str_starts_with($file, "/vendor")) {
                $pieces = explode("/", $file);
                $newVendorPrefix = implode("/", [...array_slice($pieces, 0, 3), "**/*"]);
                if ($newVendorPrefix === $currentVendorPrefix) {
                    $countVendorFrames++;
                } else {
                    // Record the existing vendor frames if we have em.
                    if ($countVendorFrames > 0) {
                        $r[] = "{$currentVendorPrefix} ({$countVendorFrames} frames)";
                    }

                    // Start tracking the new vendor frames.
                    $countVendorFrames = 1;
                    $currentVendorPrefix = $newVendorPrefix;
                }
                continue;
            }

            if ($countVendorFrames > 0) {
                // Record the vendor frames.
                $r[] = "{$currentVendorPrefix} ({$countVendorFrames} frames)";

                // Clear them
                $countVendorFrames = 0;
                $currentVendorPrefix = null;
            }

            $line = $item["line"] ?? 0;

            $r[] = "$file ($line)";
        }

        // Clear the end.
        if ($countVendorFrames > 0) {
            // Record the vendor frames.
            $r[] = "{$currentVendorPrefix} ({$countVendorFrames} frames)";
        }

        return $r;
    }

    /**
     * Abbreviate and format a stack trace to give enough information to help debugging without taking up too much space in logs.
     *
     * @param array $trace The trace as returned by `debug_backtrace()` or `Throwable::getTrace()`.
     * @param int|null $limit The number of lines to return.
     * @param int $offset The part of the trace to start at.
     * @return string Returns a string with filenames and line numbers.
     */
    public function stackTraceString(array $trace, int $limit = null, int $offset = 0): string
    {
        $result = $this->stackTraceArray($trace, $limit, $offset);

        return implode("\n", $result);
    }

    /**
     * Remove a substring from the beginning of the string.
     *
     * @param string $str The string to search.
     * @param string $trim The substring to trim off the search string.
     * @param bool $caseInsensitive Whether or not to do a case insensitive comparison.
     * @return string Returns the trimmed string.
     */
    private static function substringLeftTrim(string $str, string $trim, bool $caseInsensitive = false): string
    {
        if (strlen($str) < strlen($trim)) {
            return $str;
        } elseif (substr_compare($str, $trim, 0, strlen($trim), $caseInsensitive) === 0) {
            return substr($str, strlen($trim));
        } else {
            return $str;
        }
    }
}
