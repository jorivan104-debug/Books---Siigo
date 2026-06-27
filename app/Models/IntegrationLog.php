<?php

namespace App\Models;

use App\Enums\IntegrationType;
use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $integration
 * @property string $operation
 * @property string $status
 * @property string|null $organization_id
 * @property string|null $external_id
 * @property string|null $siigo_invoice_id
 * @property array|null $request_payload
 * @property array|null $response_payload
 * @property string|null $message
 * @property array|null $error_details
 */
class IntegrationLog extends Model
{
    protected $table = 'integration_logs';

    protected $fillable = [
        'integration',
        'operation',
        'status',
        'organization_id',
        'external_id',
        'siigo_invoice_id',
        'request_payload',
        'response_payload',
        'message',
        'error_details',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'error_details' => 'array',
    ];

    public function scopeIntegration(Builder $query, IntegrationType $integration): Builder
    {
        return $query->where('integration', $integration->value);
    }

    public function scopeStatus(Builder $query, SyncStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
