<?php

namespace App\DTOs;

class SiigoInvoicePayload
{
    /**
     * @param  array<int,array<string,mixed>>  $items
     * @param  array<int,array<string,mixed>>  $payments
     */
    public function __construct(
        public readonly int $documentId,
        public readonly string $date,
        public readonly array $customer,
        public readonly int $seller,
        public readonly array $items,
        public readonly array $payments,
        public readonly ?string $observations = null,
        public readonly bool $sendToDian = false,
        public readonly bool $sendMail = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'document' => ['id' => $this->documentId],
            'date' => $this->date,
            'customer' => $this->customer,
            'seller' => $this->seller,
            'items' => $this->items,
            'payments' => $this->payments,
            'observations' => $this->observations ?? '',
            'stamp' => ['send' => $this->sendToDian],
            'mail' => ['send' => $this->sendMail],
        ];
    }
}
