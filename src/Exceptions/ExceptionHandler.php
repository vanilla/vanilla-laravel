<?php

namespace Vanilla\Laravel\Exceptions;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler as BaseExceptionHandler;
use Throwable;
use Vanilla\Laravel\Logging\VanillaLogFormatter;

class ExceptionHandler extends BaseExceptionHandler
{
    public function __construct(Container $container, private VanillaLogFormatter $logFormatter)
    {
        parent::__construct($container);
    }

    /**
     * Implement better exception serialization.
     * @inheritDoc
     */
    protected function convertExceptionToArray(Throwable $e): array
    {
        $result = [
            "message" => $e->getMessage(),
            "exception" => get_class($e),
        ];
        if ($e instanceof ContextException) {
            $result["context"] = $e->getContext();
        }
        if (config("app.debug")) {
            $result["trace"] = $this->logFormatter->stackTraceArray($e->getTrace());
        }
        return $result;
    }

    /**
     * Ensure API requests always return json.
     *
     * @param \Illuminate\Http\Request $request
     * @param Throwable $e
     * @return bool
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return parent::shouldReturnJson($request, $e) || str_starts_with($request->path(), "api/");
    }
}
