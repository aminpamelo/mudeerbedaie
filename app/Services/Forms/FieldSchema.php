<?php

namespace App\Services\Forms;

use App\Models\Form;
use Illuminate\Support\Str;

/**
 * Normalises the raw field array coming from the React builder into a stable,
 * trusted shape before it is persisted on a Form. Guards against unknown
 * field types and missing keys so downstream rendering/validation is safe.
 */
class FieldSchema
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $type = $field['type'] ?? 'short_text';

            if (! in_array($type, Form::FIELD_TYPES, true)) {
                $type = 'short_text';
            }

            $id = $field['id'] ?? null;
            if (! is_string($id) || $id === '') {
                $id = 'fld_'.Str::lower(Str::random(8));
            }

            $options = [];
            foreach (($field['options'] ?? []) as $option) {
                if (is_string($option) || is_numeric($option)) {
                    $options[] = (string) $option;
                }
            }

            $normalized[] = [
                'id' => $id,
                'type' => $type,
                'label' => is_string($field['label'] ?? null) ? $field['label'] : '',
                'help' => is_string($field['help'] ?? null) ? $field['help'] : null,
                'required' => ($field['required'] ?? false) === true,
                'options' => $options,
                'settings' => is_array($field['settings'] ?? null) ? $field['settings'] : [],
            ];
        }

        return $normalized;
    }
}
