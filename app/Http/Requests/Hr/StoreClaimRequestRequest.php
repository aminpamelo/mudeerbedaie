<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreClaimRequestRequest extends FormRequest
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
        $claimType = null;
        if ($this->input('claim_type_id')) {
            $claimType = \App\Models\ClaimType::find($this->input('claim_type_id'));
        }

        $rules = [
            'claim_type_id' => ['required', 'exists:claim_types,id'],
            'claim_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];

        if ($claimType && $claimType->is_mileage_type) {
            $rules['vehicle_rate_id'] = ['required', 'exists:claim_type_vehicle_rates,id'];
            $rules['distance_km'] = ['required', 'numeric', 'min:0.01'];
            $rules['origin'] = ['required', 'string', 'max:255'];
            $rules['destination'] = ['required', 'string', 'max:255'];
            $rules['trip_purpose'] = ['required', 'string', 'max:255'];
        } else {
            $rules['amount'] = ['required', 'numeric', 'min:0.01'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'claim_type_id.exists' => 'The selected claim type does not exist.',
            'amount.min' => 'The claim amount must be at least RM 0.01.',
            'receipt.max' => 'The receipt file must not exceed 5MB.',
            'receipt.mimes' => 'The receipt must be a PDF, JPG, JPEG, or PNG file.',
            'vehicle_rate_id.required' => 'Please select a vehicle type.',
            'vehicle_rate_id.exists' => 'The selected vehicle type does not exist.',
            'distance_km.required' => 'Please enter the distance traveled.',
            'distance_km.min' => 'The distance must be at least 0.01 km.',
            'origin.required' => 'Please enter the starting location.',
            'destination.required' => 'Please enter the destination.',
            'trip_purpose.required' => 'Please enter the trip purpose.',
        ];
    }
}
