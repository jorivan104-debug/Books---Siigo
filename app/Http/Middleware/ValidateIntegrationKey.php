<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateIntegrationKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = config('integration.header', 'X-INTEGRATION-KEY');
        $expected = (string) config('integration.api_key', '');
        $received = (string) $request->header($headerName, '');

        if ($expected === '' || ! hash_equals($expected, $received)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No autorizado: header X-INTEGRATION-KEY inválido o ausente.',
                'details' => new \stdClass(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
