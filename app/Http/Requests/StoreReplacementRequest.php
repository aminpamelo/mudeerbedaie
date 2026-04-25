<?php

namespace App\Http\Requests;

use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = LiveScheduleAssignment::find($this->input('live_schedule_assignment_id'));

        return $assignment !== null
            && $assignment->live_host_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'live_schedule_assignment_id' => ['required', 'integer', 'exists:live_schedule_assignments,id'],
            'scope' => ['required', Rule::in([SessionReplacementRequest::SCOPE_ONE_DATE, SessionReplacementRequest::SCOPE_PERMANENT])],
            'target_date' => [
                'nullable',
                'required_if:scope,one_date',
                'date',
                'after_or_equal:today',
            ],
            'reason_category' => ['required', Rule::in(SessionReplacementRequest::REASON_CATEGORIES)],
            'reason_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'scope.required' => 'Sila pilih skop penggantian.',
            'target_date.required_if' => 'Sila pilih tarikh untuk penggantian sekali sahaja.',
            'target_date.after_or_equal' => 'Tarikh tidak boleh berada pada masa lampau.',
            'reason_category.required' => 'Sila pilih sebab permohonan.',
            'reason_note.max' => 'Catatan tidak boleh melebihi 500 aksara.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $assignment = LiveScheduleAssignment::find($this->input('live_schedule_assignment_id'));

            if ($this->input('scope') === SessionReplacementRequest::SCOPE_ONE_DATE) {
                $target = Carbon::parse($this->input('target_date'));
                if ($assignment && (int) $target->dayOfWeek !== (int) $assignment->day_of_week) {
                    $v->errors()->add('target_date', 'Tarikh yang dipilih tidak sepadan dengan hari slot ini.');

                    return;
                }
            }

            $duplicate = SessionReplacementRequest::query()
                ->where('live_schedule_assignment_id', $this->input('live_schedule_assignment_id'))
                ->where('status', SessionReplacementRequest::STATUS_PENDING)
                ->when(
                    $this->input('scope') === SessionReplacementRequest::SCOPE_ONE_DATE,
                    fn ($q) => $q->whereDate('target_date', $this->input('target_date')),
                    fn ($q) => $q->where('scope', SessionReplacementRequest::SCOPE_PERMANENT)
                )
                ->exists();

            if ($duplicate) {
                $v->errors()->add('live_schedule_assignment_id', 'Sudah ada permohonan tertunda untuk slot ini.');
            }
        });
    }
}
