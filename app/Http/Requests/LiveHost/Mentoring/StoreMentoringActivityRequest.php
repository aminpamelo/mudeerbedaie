<?php

namespace App\Http\Requests\LiveHost\Mentoring;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreMentoringActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && in_array($user->role, ['admin', 'admin_livehost', 'livehost_assistant'], true);
    }

    /**
     * Normalise the React ISO-8601 `occurred_at` to the app timezone so it is
     * stored consistently (the value is written through Eloquent, but we keep
     * the same convention as the other mentoring datetime inputs).
     */
    protected function prepareForValidation(): void
    {
        $occurredAt = $this->input('occurred_at');

        if ($occurredAt === null || $occurredAt === '') {
            $this->merge(['occurred_at' => now()->format('Y-m-d H:i:s')]);

            return;
        }

        try {
            $this->merge([
                'occurred_at' => Carbon::parse($occurredAt)
                    ->setTimezone(config('app.timezone'))
                    ->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Leave as-is so the 'date' rule reports a clean error.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:coaching,meeting,training,check_in,other'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['required', 'date'],
            'mentee_id' => ['nullable', 'integer', 'exists:live_host_mentees,id'],
        ];
    }
}
