<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    // Accessors and Mutators
    public function getValueAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }

        return match ($this->type) {
            'boolean' => (bool) $value,
            'number' => is_numeric($value) ? (float) $value : $value,
            'json' => json_decode($value, true),
            'encrypted' => $this->decryptValue($value),
            'file' => $value ? Storage::url($value) : null,
            default => $value,
        };
    }

    public function setValueAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['value'] = null;

            return;
        }

        $this->attributes['value'] = match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'number' => (string) $value,
            'json' => json_encode($value),
            'encrypted' => $this->encryptValue($value),
            default => (string) $value,
        };
    }

    // Get the raw value without casting (useful for form inputs)
    public function getRawValue()
    {
        return $this->attributes['value'] ?? null;
    }

    // Get the display value (for showing in UI)
    public function getDisplayValue()
    {
        if ($this->type === 'encrypted' && ! empty($this->attributes['value'])) {
            return str_repeat('*', 12); // Show masked value
        }

        if ($this->type === 'boolean') {
            return $this->value ? 'Yes' : 'No';
        }

        if ($this->type === 'file' && $this->value) {
            return basename($this->value);
        }

        return $this->value;
    }

    // Encryption helpers
    private function encryptValue($value)
    {
        try {
            return Crypt::encryptString($value);
        } catch (\Exception $e) {
            return $value; // Fallback to plain text if encryption fails
        }
    }

    private function decryptValue($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value; // Return as-is if decryption fails
        }
    }

    // Scopes
    public function scopeGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    // Static helper methods
    public static function getValue($key, $default = null)
    {
        $setting = static::byKey($key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function setValue($key, $value, $type = 'string', $group = 'general')
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
            ]
        );
    }

    public static function getGroup($group)
    {
        return static::group($group)->get()->keyBy('key');
    }

    // File handling helpers
    public function getFileUrl()
    {
        if ($this->type === 'file' && ! empty($this->attributes['value'])) {
            return Storage::url($this->attributes['value']);
        }

        return null;
    }

    public function getFilePath()
    {
        if ($this->type === 'file') {
            return $this->attributes['value'];
        }

        return null;
    }

    // Delete old file when updating file type settings
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($setting) {
            if ($setting->type === 'file' && $setting->isDirty('value')) {
                $oldFile = $setting->getOriginal('value');
                if ($oldFile && Storage::exists($oldFile)) {
                    Storage::delete($oldFile);
                }
            }
        });

        static::deleting(function ($setting) {
            if ($setting->type === 'file' && $setting->value) {
                $filePath = $setting->getFilePath();
                if ($filePath && Storage::exists($filePath)) {
                    Storage::delete($filePath);
                }
            }
        });
    }
}
