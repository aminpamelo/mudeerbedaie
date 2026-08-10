<?php

namespace App\Http\Controllers\Mindpal;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(SettingsService $settings): Response
    {
        return Inertia::render('Settings', [
            'settings' => [
                'model' => $settings->get('mindpal_model', 'gpt-4o-mini'),
                'max_tokens' => (int) $settings->get('mindpal_max_tokens', 1500),
                'temperature' => (float) $settings->get('mindpal_temperature', 0.3),
                'rate_limit' => (int) $settings->get('mindpal_rate_limit', 50),
                'max_file_size_mb' => (int) $settings->get('mindpal_max_file_size_mb', 50),
                'chunk_size' => (int) $settings->get('mindpal_chunk_size', 500),
                'system_prompt' => $settings->get('mindpal_system_prompt', ''),
            ],
        ]);
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'model' => 'required|string|in:gpt-4o-mini,gpt-4o,gpt-4-turbo',
            'max_tokens' => 'required|integer|min:100|max:4000',
            'temperature' => 'required|numeric|min:0|max:2',
            'rate_limit' => 'required|integer|min:1|max:500',
            'max_file_size_mb' => 'required|integer|min:1|max:100',
            'chunk_size' => 'required|integer|min:100|max:2000',
            'system_prompt' => 'nullable|string|max:5000',
        ]);

        foreach ($data as $key => $value) {
            $settings->set("mindpal_{$key}", $value, is_numeric($value) ? 'number' : 'string', 'mindpal');
        }

        return back()->with('success', 'Settings saved.');
    }
}
