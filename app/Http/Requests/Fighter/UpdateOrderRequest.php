<?php

namespace App\Http\Requests\Fighter;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Only a fighter (or an admin peeking at the portal) may reach here; the
     * controller enforces per-order ownership on top of this.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isFighter() || $user->isAdmin());
    }

    /**
     * The segment is never accepted from the client — the controller keeps the
     * order on the fighter's own segment.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'customer_postcode' => ['nullable', 'string', 'max:10'],
            'customer_city' => ['nullable', 'string', 'max:100'],
            'customer_state' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cod'],
            'payment_reference' => ['nullable', 'required_if:payment_method,bank_transfer', 'string', 'max:255'],
            'payment_status' => ['required', 'in:paid,pending'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'remove_receipt_attachment' => ['nullable', 'boolean'],
            // Optional: item-less (seeded/legacy) orders can be edited without
            // touching line items — the controller preserves their totals.
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer', 'exists:product_order_items,id'],
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
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'payment_reference.required_if' => 'Payment reference is required for bank transfer.',
            'customer_name.required' => 'Customer name is required.',
            'customer_phone.required' => 'Customer phone is required.',
        ];
    }
}
