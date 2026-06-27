<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class SiigoCustomerService
{
    public function __construct(private readonly SiigoAuthService $auth)
    {
    }

    /**
     * Busca un cliente por número de identificación.
     * Devuelve los datos del primer resultado o null si no existe.
     */
    public function findByIdentification(string $identification): ?array
    {
        $response = $this->safeRequest(
            fn () => $this->client()->get(
                rtrim((string) config('siigo.api_base_url'), '/').'/v1/customers',
                ['identification' => $identification],
            ),
            "No se pudo consultar el cliente {$identification} en Siigo.",
        );

        $body = $response->json() ?? [];
        $results = $body['results'] ?? $body;

        if (is_array($results) && count($results) > 0 && isset($results[0]['identification'])) {
            return $results[0];
        }

        return null;
    }

    /**
     * Construye un objeto customer completo (para crear inline desde POST /v1/invoices)
     * usando los datos del contacto Zoho y los defaults geográficos del config.
     */
    public function buildCustomerPayloadFromZoho(
        array $zohoContact,
        string $identification,
        string $idType
    ): array {
        $personType = $this->resolvePersonType($zohoContact, $idType);
        $names = $this->resolveName($zohoContact, $personType);
        $defaults = (array) config('siigo.customer_defaults');

        $billingAddress = $zohoContact['billing_address'] ?? [];
        $address = $this->cleanString($billingAddress['address'] ?? null)
            ?? $this->cleanString($defaults['address'] ?? 'N/A');

        $payload = [
            'person_type' => $personType,
            'id_type' => $idType,
            'identification' => $identification,
            'branch_office' => 0,
            'name' => $names,
            'address' => [
                'address' => $address,
                'city' => [
                    'country_code' => (string) ($defaults['country_code'] ?? 'CO'),
                    'state_code' => (string) ($defaults['state_code'] ?? '11'),
                    'city_code' => (string) ($defaults['city_code'] ?? '11001'),
                ],
            ],
        ];

        $phone = $this->resolvePhone($zohoContact);
        if ($phone !== null) {
            $payload['phones'] = [$phone];
        }

        $contact = $this->resolveContactPerson($zohoContact, $names);
        if ($contact !== null) {
            $payload['contacts'] = [$contact];
        }

        return $payload;
    }

    private function resolvePersonType(array $contact, string $idType): string
    {
        $contactType = strtolower((string) ($contact['contact_type'] ?? ''));
        if ($contactType === 'business' || $idType === '31') {
            return 'Company';
        }

        return 'Person';
    }

    /**
     * Siigo espera `name` como string para Company y array [nombre, apellido] para Person.
     */
    private function resolveName(array $contact, string $personType): array|string
    {
        $companyName = $this->cleanString($contact['company_name'] ?? null);
        $contactName = $this->cleanString($contact['contact_name'] ?? null);
        $firstName = $this->cleanString($contact['first_name'] ?? null);
        $lastName = $this->cleanString($contact['last_name'] ?? null);

        if ($personType === 'Company') {
            return $companyName ?? $contactName ?? 'Sin nombre';
        }

        if ($firstName !== null || $lastName !== null) {
            return [$firstName ?? '-', $lastName ?? '-'];
        }

        $parts = preg_split('/\s+/', (string) ($contactName ?? $companyName ?? 'Cliente Zoho'), 2);
        $first = $parts[0] ?? 'Cliente';
        $last = $parts[1] ?? 'Zoho';

        return [$first, $last];
    }

    private function resolvePhone(array $contact): ?array
    {
        $candidates = [
            $contact['mobile'] ?? null,
            $contact['phone'] ?? null,
            $contact['billing_address']['phone'] ?? null,
        ];

        foreach ($candidates as $raw) {
            $number = preg_replace('/\D+/', '', (string) ($raw ?? ''));
            if (is_string($number) && strlen($number) >= 7) {
                return ['number' => substr($number, 0, 10)];
            }
        }

        return null;
    }

    private function resolveContactPerson(array $contact, array|string $name): ?array
    {
        $email = $this->cleanString($contact['email'] ?? null);
        if ($email === null) {
            return null;
        }

        if (is_array($name)) {
            return [
                'first_name' => $name[0] ?? 'Cliente',
                'last_name' => $name[1] ?? '-',
                'email' => $email,
            ];
        }

        return [
            'first_name' => $name,
            'email' => $email,
        ];
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->auth->getToken(),
            'Partner-Id' => (string) config('siigo.partner_id', 'integration-hub'),
            'Content-Type' => 'application/json',
        ])->timeout((int) config('siigo.timeout', 30));
    }

    /**
     * @param  callable():Response  $callable
     */
    private function safeRequest(callable $callable, string $message): Response
    {
        try {
            $response = $callable();
        } catch (Throwable $e) {
            throw new ExternalApiException($message.' '.$e->getMessage(), 'siigo', null, [], $e);
        }

        if ($response->failed() && $response->status() !== 404) {
            throw new ExternalApiException(
                $message,
                'siigo',
                $response->status(),
                is_array($response->json()) ? $response->json() : ['raw' => $response->body()],
            );
        }

        return $response;
    }
}
