<?php

namespace App\Exceptions;

use RuntimeException;

class InvoiceAlreadySyncedException extends RuntimeException
{
    public function __construct(
        public readonly string $existingLink,
        string $message = 'La factura ya fue sincronizada previamente.',
    ) {
        parent::__construct($message);
    }
}
