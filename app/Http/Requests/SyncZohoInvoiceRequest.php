<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SyncZohoInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'max:50'],
            'invoice_id' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'organization_id es obligatorio.',
            'invoice_id.required' => 'invoice_id es obligatorio.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(new JsonResponse([
            'success' => false,
            'message' => 'Payload inválido.',
            'details' => [
                'errors' => $validator->errors()->toArray(),
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
