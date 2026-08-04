<?php

namespace App\Http\Requests\Fighter;

use App\Support\FighterOrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The one-click status change from the orders table. Per-order ownership and
 * the "has fulfilment taken this over?" guard are enforced in the controller.
 */
class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isFighter() || $user->isAdmin());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(FighterOrderStatus::EDITABLE)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'You can only set an order to pending, processing, completed or cancelled.',
        ];
    }
}
