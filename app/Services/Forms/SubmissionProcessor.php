<?php

namespace App\Services\Forms;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Turns a Form's stored field schema into validation rules, validates an
 * incoming public submission against them, persists any uploaded files, and
 * returns a normalised answer map keyed by field id ready to store as JSON.
 */
class SubmissionProcessor
{
    /**
     * Validate the request against the form schema and return the normalised
     * answer map keyed by field id.
     *
     * @return array<string, mixed>
     */
    public function process(Form $form, Request $request): array
    {
        $rules = [];
        $attributes = [];

        foreach ($form->answerableFields() as $field) {
            $key = 'answers.'.$field['id'];
            $rules[$key] = $this->rulesForField($field);
            $attributes[$key] = $field['label'] ?? 'Field';
        }

        $validated = Validator::make(
            $request->all(),
            $rules,
            [],
            $attributes,
        )->validate();

        $answers = $validated['answers'] ?? [];

        $data = [];

        foreach ($form->answerableFields() as $field) {
            $id = $field['id'];
            $value = $answers[$id] ?? null;

            if (($field['type'] ?? null) === 'file') {
                $data[$id] = $this->storeFile($form, $request, $id);

                continue;
            }

            if ($value !== null) {
                $data[$id] = $value;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function rulesForField(array $field): array
    {
        $required = ($field['required'] ?? false) === true;
        $rules = [$required ? 'required' : 'nullable'];

        $options = array_values(array_filter(
            $field['options'] ?? [],
            fn ($o): bool => $o !== null && $o !== '',
        ));

        switch ($field['type'] ?? 'short_text') {
            case 'long_text':
            case 'short_text':
                $rules[] = 'string';
                $rules[] = 'max:5000';
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'email':
                $rules[] = 'email';
                $rules[] = 'max:255';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'phone':
                $rules[] = 'string';
                $rules[] = 'max:30';
                break;
            case 'rating':
                $max = (int) ($field['settings']['max'] ?? 5);
                $rules[] = 'integer';
                $rules[] = 'min:1';
                $rules[] = 'max:'.max(1, $max);
                break;
            case 'radio':
            case 'dropdown':
                $rules[] = 'string';
                if ($options !== []) {
                    $rules[] = Rule::in($options);
                }
                break;
            case 'checkbox':
                $rules[] = 'array';
                break;
            case 'file':
                $accept = $field['settings']['accept'] ?? null;
                $maxKb = (int) ($field['settings']['max_kb'] ?? 5120);
                $rules[] = 'file';
                $rules[] = 'max:'.max(1, $maxKb);
                if (is_string($accept) && $accept !== '') {
                    $rules[] = 'mimes:'.$accept;
                }
                break;
            default:
                $rules[] = 'string';
                $rules[] = 'max:5000';
        }

        return $rules;
    }

    /**
     * Persist an uploaded file for a file field and return its descriptor.
     *
     * @return array{path: string, name: string, size: int, mime: string|null}|null
     */
    private function storeFile(Form $form, Request $request, string $fieldId): ?array
    {
        $file = $request->file('answers.'.$fieldId);

        if (! $file instanceof UploadedFile) {
            return null;
        }

        $path = $file->store('forms/'.$form->uuid.'/submissions', 'public');

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }
}
