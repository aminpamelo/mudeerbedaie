<?php

namespace App\Http\Requests\Forms;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'form_category_id' => ['nullable', 'integer', Rule::exists('form_categories', 'id')],
            'status' => ['required', Rule::in([Form::STATUS_DRAFT, Form::STATUS_PUBLISHED, Form::STATUS_CLOSED])],
            'fields' => ['array'],
            'fields.*.id' => ['nullable', 'string', 'max:40'],
            'fields.*.type' => ['required', Rule::in(Form::FIELD_TYPES)],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.help' => ['nullable', 'string', 'max:500'],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['array'],
            'fields.*.settings' => ['array'],
            'settings' => ['nullable', 'array'],
            'settings.confirmation_message' => ['nullable', 'string', 'max:1000'],
            'settings.allow_multiple' => ['boolean'],
            'settings.collect_email' => ['boolean'],
        ];
    }
}
