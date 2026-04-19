<?php

namespace App\Http\Requests\LiveHost;

use App\Models\User;
use App\Rules\NoCircularUpline;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLiveHostCommissionProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $host = $this->route('host');
        $hostId = $host instanceof User ? $host->id : (int) $host;

        return [
            'base_salary_myr' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'per_live_rate_myr' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'upline_user_id' => ['nullable', 'integer', 'exists:users,id', new NoCircularUpline($hostId)],
            'override_rate_l1_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'override_rate_l2_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
