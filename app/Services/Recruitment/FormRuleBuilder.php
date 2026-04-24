<?php

namespace App\Services\Recruitment;

use Illuminate\Validation\Rule;

class FormRuleBuilder
{
    /**
     * Build top-level validation rules keyed by field id.
     *
     * @return array<string, array<int, mixed>>
     */
    public function build(array $schema): array
    {
        $rules = [];
        foreach (($schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                if (in_array($field['type'] ?? null, ['heading', 'paragraph'], true)) {
                    continue;
                }
                $rules[$field['id']] = $this->rulesForField($field);
            }
        }

        return $rules;
    }

    /**
     * Build per-item rules for array-typed fields (checkbox_group children).
     * Returns rules keyed by "<field_id>.*".
     *
     * @return array<string, array<int, mixed>>
     */
    public function buildArrayItemRules(array $schema): array
    {
        $rules = [];
        foreach (($schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                if (($field['type'] ?? null) === 'checkbox_group') {
                    $values = array_column($field['options'] ?? [], 'value');
                    $itemRules = ['string'];
                    if ($values) {
                        $itemRules[] = Rule::in($values);
                    }
                    $rules["{$field['id']}.*"] = $itemRules;
                }
            }
        }

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    private function rulesForField(array $field): array
    {
        $required = (bool) ($field['required'] ?? false);
        $rules = [$required ? 'required' : 'nullable'];

        switch ($field['type']) {
            case 'text':
                $rules[] = 'string';
                $rules[] = 'max:255';
                break;
            case 'textarea':
                $rules[] = 'string';
                $rules[] = 'max:5000';
                break;
            case 'email':
                $rules[] = 'email';
                $rules[] = 'max:255';
                break;
            case 'phone':
                $rules[] = 'string';
                $rules[] = 'max:50';
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'url':
                $rules[] = 'url';
                $rules[] = 'max:1000';
                break;
            case 'select':
            case 'radio':
                $rules[] = 'string';
                $values = array_column($field['options'] ?? [], 'value');
                if ($values) {
                    $rules[] = Rule::in($values);
                }
                break;
            case 'checkbox_group':
                $rules[] = 'array';
                if ($required) {
                    $rules[] = 'min:1';
                }
                break;
            case 'date':
            case 'datetime':
                $rules[] = 'date';
                break;
            case 'file':
                $rules[] = 'file';
                if (! empty($field['accept'])) {
                    $rules[] = 'mimes:'.implode(',', $field['accept']);
                }
                if (! empty($field['max_size_kb'])) {
                    $rules[] = 'max:'.(int) $field['max_size_kb'];
                }
                break;
        }

        return $rules;
    }
}
