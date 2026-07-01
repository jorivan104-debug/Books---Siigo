<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Illuminate\Http\Client\Response;

class SiigoCatalogService
{
    public function __construct(private readonly SiigoHttpClient $http)
    {
    }

    /**
     * @return array<string, array<int, array{id: int|string, label: string}>>
     */
    public function fetchAll(): array
    {
        return [
            'document_types' => $this->fetchList(
                fn () => $this->http->get('v1/document-types', ['type' => 'FV']),
                fn ($row) => isset($row['id'], $row['name']) ? ['id' => $row['id'], 'label' => (string) $row['name']] : null,
            ),
            'sellers' => $this->fetchList(
                fn () => $this->http->get('v1/users', ['page' => 1, 'page_size' => 50]),
                fn ($row) => isset($row['id']) ? [
                    'id' => $row['id'],
                    'label' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
                ] : null,
            ),
            'payment_types' => $this->fetchList(
                fn () => $this->http->get('v1/payment-types', ['document_type' => 'FV']),
                fn ($row) => isset($row['id'], $row['name']) ? ['id' => $row['id'], 'label' => (string) $row['name']] : null,
            ),
            'taxes' => $this->fetchList(
                fn () => $this->http->get('v1/taxes'),
                fn ($row) => isset($row['id'], $row['name']) ? [
                    'id' => $row['id'],
                    'label' => (string) $row['name'].' ('.($row['percentage'] ?? '?').'%)',
                ] : null,
            ),
        ];
    }

    /**
     * @param  callable(): Response  $fetch
     * @param  callable(array<string, mixed>): ?array{id: int|string, label: string}  $map
     * @return array<int, array{id: int|string, label: string}>
     */
    private function fetchList(callable $fetch, callable $map): array
    {
        try {
            $response = $fetch();
        } catch (ExternalApiException $e) {
            return [['id' => '-', 'label' => 'Error: '.$e->getMessage()]];
        }

        if ($response->failed()) {
            return [['id' => '-', 'label' => 'HTTP '.$response->status()]];
        }

        $results = $response->json('results') ?? $response->json() ?? [];
        if (! is_array($results)) {
            return [];
        }

        $rows = isset($results[0]) || empty($results) ? $results : [$results];
        $items = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mapped = $map($row);
            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        return $items;
    }
}
