<?php

namespace App\Http\Requests;

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'admin_livehost'], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'replacement_host_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'replacement_host_id.required' => 'Sila pilih pengganti.',
            'replacement_host_id.exists' => 'Pengganti yang dipilih tidak sah.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $req = $this->route('replacementRequest');
            if (! $req instanceof SessionReplacementRequest) {
                return;
            }

            $candidateId = (int) $this->input('replacement_host_id');

            if ($candidateId === (int) $req->original_host_id) {
                $v->errors()->add('replacement_host_id', 'Pengganti tidak boleh sama dengan pemohon.');

                return;
            }

            $busy = LiveScheduleAssignment::query()
                ->where('day_of_week', $req->assignment->day_of_week)
                ->where('time_slot_id', $req->assignment->time_slot_id)
                ->where('status', '!=', 'cancelled')
                ->where('live_host_id', $candidateId)
                ->exists();

            if ($busy) {
                $v->errors()->add('replacement_host_id', 'Pengganti sudah ada slot bertindih pada masa ini.');
            }
        });
    }
}
