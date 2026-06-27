<?php

namespace App\Support;

class ZohoCustomFieldHelper
{
    /**
     * Devuelve el valor de un custom field para una entidad de Zoho.
     *
     * Zoho expone los custom fields de varias formas:
     *  - como propiedad directa con prefijo `cf_` (cf_xxx en el root del objeto)
     *  - dentro de `custom_field_hash` con la misma clave
     *  - dentro del array `custom_fields[]` (cada item con api_name/placeholder y value/value_formatted)
     *
     * Este helper revisa las tres ubicaciones y devuelve null si no hay valor.
     */
    public static function getValue(array $entity, string $apiName): ?string
    {
        if (array_key_exists($apiName, $entity)) {
            return self::normalize($entity[$apiName]);
        }

        $hash = $entity['custom_field_hash'] ?? null;
        if (is_array($hash) && array_key_exists($apiName, $hash)) {
            return self::normalize($hash[$apiName]);
        }

        $list = $entity['custom_fields'] ?? null;
        if (is_array($list)) {
            foreach ($list as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $candidates = [
                    $field['api_name'] ?? null,
                    $field['placeholder'] ?? null,
                ];
                if (in_array($apiName, $candidates, true)) {
                    return self::normalize($field['value'] ?? $field['value_formatted'] ?? null);
                }
            }
        }

        return null;
    }

    private static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }
}
