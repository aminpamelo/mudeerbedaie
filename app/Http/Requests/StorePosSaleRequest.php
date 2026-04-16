<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePosSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sales_source_id' => ['required', 'exists:sales_sources,id'],
            'customer_id' => ['nullable', 'exists:users,id'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cod'],
            'payment_reference' => ['nullable', 'required_if:payment_method,bank_transfer', 'string', 'max:255'],
            'payment_status' => ['required', 'in:paid,pending'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'upsell_class_session_id' => ['nullable', 'integer', 'exists:class_sessions,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.itemable_type' => ['required', 'in:product,package,course'],
            'items.*.itemable_id' => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.class_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sales_source_id.required' => 'Please select a sales source.',
            'sales_source_id.exists' => 'The selected sales source is invalid.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'payment_reference.required_if' => 'Payment reference is required for bank transfer.',
            'customer_name.required_without' => 'Customer name is required for walk-in customers.',
            'customer_phone.required_without' => 'Customer phone is required for walk-in customers.',
        ];
    }
}
