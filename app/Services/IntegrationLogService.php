<?php

namespace App\Services;

use App\Enums\IntegrationType;
use App\Enums\SyncStatus;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntegrationLogService
{
    public function start(
        IntegrationType $integration,
        string $operation,
        ?string $organizationId,
        ?string $externalId,
        array $requestPayload
    ): ?IntegrationLog {
        try {
            return IntegrationLog::create([
                'integration' => $integration->value,
                'operation' => $operation,
                'status' => SyncStatus::Pending->value,
                'organization_id' => $organizationId,
                'external_id' => $externalId,
                'request_payload' => $requestPayload,
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo crear integration_log (sync continúa sin persistir).', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function success(
        ?IntegrationLog $log,
        array $responsePayload,
        ?string $message = null,
        ?string $siigoInvoiceId = null
    ): ?IntegrationLog {
        return $this->safeUpdate($log, [
            'status' => SyncStatus::Success->value,
            'response_payload' => $responsePayload,
            'message' => $message,
            'siigo_invoice_id' => $siigoInvoiceId,
        ]);
    }

    public function skipped(
        ?IntegrationLog $log,
        string $message,
        array $responsePayload = []
    ): ?IntegrationLog {
        return $this->safeUpdate($log, [
            'status' => SyncStatus::Skipped->value,
            'message' => $message,
            'response_payload' => $responsePayload,
        ]);
    }

    public function failed(
        ?IntegrationLog $log,
        string $message,
        array $errorDetails = [],
        array $responsePayload = []
    ): ?IntegrationLog {
        return $this->safeUpdate($log, [
            'status' => SyncStatus::Failed->value,
            'message' => $message,
            'error_details' => $errorDetails,
            'response_payload' => $responsePayload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function safeUpdate(?IntegrationLog $log, array $attributes): ?IntegrationLog
    {
        if ($log === null) {
            return null;
        }

        try {
            $log->update($attributes);
        } catch (Throwable $e) {
            Log::warning('No se pudo actualizar integration_log.', [
                'error' => $e->getMessage(),
                'log_id' => $log->id,
            ]);
        }

        return $log;
    }
}
