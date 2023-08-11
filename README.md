# vanilla/laravel

A set of common laravel utilities and configurations used in Vanilla laravel services.

## Installation

Require this package with composer using the following command:

```sh
composer require vanilla/laravel
```

This package makes use of Laravels package auto-discovery mechanism so it should automatically be applied.

## Additional Logging Context

This is automatically configured if the package is installed through composer.

Every made during a web request will have the following additional data applied.

```json
{
    // ... Basic log data here.
    "tags": ["webRequest"],
    "request": {
        "hostname": "www.your-service.com",
        "method": "GET",
        "path": "/some/path/here?queryParams",
        "protocol": "https",
        "url": "https://www.your-service.com/some/path/here?queryParams",
        "clientIP": "0.0.0.0"
    }
}
```

## Improved JSON Log Formatter

To apply this, update your desired log channel in `config/logging.php` file to use the new log formatter.

**`config/logging.php`**

```php
return [
    // ...Other configs here
    "channels" => [
        // ...Other configs here
        "single" => [
            "driver" => "single",
            "formatter" => VanillaLogFormatter::class,
            "path" => storage_path("logs/laravel.log"),
            "level" => env("LOG_LEVEL", "debug"),
        ],
        "syslog" => [
            "driver" => "syslog",
            "formatter" => VanillaLogFormatter::class,
            "level" => env("LOG_LEVEL", "debug"),
        ],
        // ...Other configs here
    ],
    // ...Other configs here
];
```

The improved log formatter provides the following behaviours:

-   Outputs logs as serialized JSON prepended with `$json:` in the standard vanilla `v2` logging schema.
-   `Vanilla\Laravel\Exception\ContextException` instances will now have their context serialized.
-   Exception stack traces have improved serialization.
    -   Frames do not include the base path of the repo.
    -   Certain common frames are always excludes (like ones in the log formatter and exception handler).
    -   Vendor stack frames are collapsed.
    -   Only files and line numbers are included.
-   All logs get a minimal stacktrace.

## Improved Exception Handling

To apply this have your exception handler in `Exceptions\Handler.php` extend from `Vanilla\Laravel\Exceptions\ExceptionHandler` instead of `Illuminate\Foundation\Exceptions\Handler`.

It has the following improved behaviours

-   API responses will always be serialized as JSON.
-   Thrown `ContextExceptions` will serialize their context.
-   If the `app.debug` config is enabled then stack traces will be returned in the JSON output.

## Context Exceptions

Oftentimes when you throw an exception you might have some useful structured data that you want captured for logging or an HTTP response.

For these case throw or extend `Vanilla\Laravel\Exceptions\ContextException`. It adds an optional array of `context`.

Want to add additional context to a caught `ContextException`? Use `ContextException::withContext($newContext)`.

## Configuration Validation

This package registers a new command `artisan config:validate` that will automatically run before `config:cache`. It will look through your configuration files for validation specification under a `ValidateConfigCommand::KEY` key and validate the rest of that configuration file according to those rules.

### Example

**orch.php**
```php

return [
    ///
    /// Config validate here!!!
    /// 
    ValidateConfigCommand::KEY => [
        "type" => ["in:orchestration,local"],
        "base_url" => ["required", "url"],
        "search_service_base_url" => ["required", "url"],
        "hostname" => ["string"],
        "token" => ["string", "required"],
        "network" => ["string", "required"],
        "zone" => ["string", "required"],
    ],

    /*
    |--------------------------------------------------------------------------
    | Orchestration
    |--------------------------------------------------------------------------
    |
    | These fields are used for accessing orchestration and fetching site info.
    */
    "type" => env("ORCH_TYPE", $isOrchestration ? "orchestration" : "local"),

    // Base URL for orchestration.
    "base_url" => env("ORCH_BASE_URL"),

    // Optional. Primarily used in localhost to force `Host` header with a base_url of an internalIP for the VPN.
    "hostname" => env("ORCH_HOSTNAME"),

    // Access token used to call orchestration.
    "token" => env("ORCH_TOKEN"),

    // Which orchestration network to use.
    "network" => env("ORCH_NETWORK"),

    // Which orchestration zone to use.
    "zone" => env("ORCH_ZONE"),

    // Base url to use when calling elasticsearch.
    "search_service_base_url" => env("ORCH_SEARCH_SERVICE_BASE_URL"),
];
```
