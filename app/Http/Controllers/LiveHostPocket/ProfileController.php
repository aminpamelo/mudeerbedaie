<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — profile page for the "You" tab.
 *
 * Read-only summary of the host's own user row plus a minimal avatar upload
 * flow (upload + remove) and a Sign Out action. Deeper profile editing
 * (name/password/appearance) still lives on the main Livewire settings pages
 * outside the Pocket shell; this is intentionally small so the tabbar slot
 * points at something useful.
 */
class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'role' => $user->role_name,
                'avatarUrl' => $user->avatar_url,
            ],
        ]);
    }

    public function uploadAvatar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $validated['avatar']->store('user-avatars', 'public');

        $user->forceFill(['avatar_path' => $path])->save();

        return back()->with('success', 'Profile picture updated.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return back()->with('success', 'Profile picture removed.');
    }
}
