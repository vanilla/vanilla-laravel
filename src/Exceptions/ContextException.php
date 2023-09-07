<?php

namespace Vanilla\Laravel\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Exception that can provide some context for logs.
 */
class ContextException extends \Garden\Utils\ContextException implements HttpExceptionInterface
{
    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        return $this->getHttpStatusCode();
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return [];
    }
}
