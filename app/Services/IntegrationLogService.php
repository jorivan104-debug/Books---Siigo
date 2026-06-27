<?php

namespace App\Services;

use App\Enums\IntegrationType;
use App\Enums\SyncStatus;
use App\Models\IntegrationLog;

class IntegrationLogService
{
    public function start(
        IntegrationType $integration,
        string $operation,
        ?string $organizationId,
        ?string $externalId,
        array $requestPayload
    ): IntegrationLog {
        return IntegrationLog::create([
            'integration' => $integration->value,
            'operation' => $operation,
            'status' => SyncStatus::Pending->value,
            'organization_id' => $organizationId,
            'external_id' => $externalId,
            'request_payload' => $requestPayload,
        ]);
    }

    public function success(
        IntegrationLog $log,
        array $responsePayload,
        ?string $message = null,
        ?string $siigoInvoiceId = null
    ): IntegrationLog {
        $log->update([
            'status' => SyncStatus::Success->value,
            'response_payload' => $responsePayload,
            'message' => $message,
            'siigo_invoice_id' => $siigoInvoiceId,
        ]);

        return $log;
    }

    public function skipped(
        IntegrationLog $log,
        string $message,
        array $responsePayload = []
    ): IntegrationLog {
        $log->update([
            'status' => SyncStatus::Skipped->value,
            'message' => $message,
            'response_payload' => $responsePayload,
        ]);

        return $log;
    }

    public function failed(
        IntegrationLog $log,
        string $message,
        array $errorDetails = [],
        array $responsePayload = []
    ): IntegrationLog {
        $log->update([
            'status' => SyncStatus::Failed->value,
            'message' => $message,
            'error_details' => $errorDetails,
            'response_payload' => $responsePayload,
        ]);

        return $log;
    }
}
