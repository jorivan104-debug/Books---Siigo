<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncZohoInvoiceRequest;
use App\Services\ZohoToSiigoInvoiceSyncService;
use Illuminate\Http\JsonResponse;

class ZohoInvoiceSyncController extends Controller
{
    public function __construct(private readonly ZohoToSiigoInvoiceSyncService $sync)
    {
    }

    public function sync(SyncZohoInvoiceRequest $request): JsonResponse
    {
        $result = $this->sync->sync(
            organizationId: (string) $request->input('organization_id'),
            invoiceId: (string) $request->input('invoice_id'),
        );

        $status = $result['success'] === true ? 200 : 422;

        return response()->json($result, $status);
    }
}
