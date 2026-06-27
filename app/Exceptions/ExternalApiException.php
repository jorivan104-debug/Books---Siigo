<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ExternalApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?int $statusCode = null,
        public readonly array $responseBody = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function context(): array
    {
        return [
            'provider' => $this->provider,
            'status' => $this->statusCode,
            'body' => $this->responseBody,
        ];
    }
}
