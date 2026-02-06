<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'custom_field_id',
        'value',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function getTypedValueAttribute()
    {
        $field = $this->customField;

        if (! $field) {
            return $this->value;
        }

        return match ($field->type) {
            'number' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'date' => $this->value ? \Carbon\Carbon::parse($this->value) : null,
            'multiselect' => json_decode($this->value, true) ?? [],
            default => $this->value,
        };
    }
}
