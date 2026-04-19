<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — minimal profile page for the "You" tab.
 *
 * Read-only summary of the host's own user row with a Sign Out action on the
 * page itself. Deeper profile editing lives on the main Livewire Settings
 * pages outside the Pocket shell; this is intentionally small so the tabbar
 * slot finally points at something real.
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
                'role' => $user->role,
            ],
        ]);
    }
}
