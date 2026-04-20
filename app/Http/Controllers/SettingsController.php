<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('Settings/Index', [
            'user' => $user,
            'settings' => [
                'theme' => session('theme', 'light'),
                'notifications' => [
                    'email' => true,
                    'browser' => true,
                ],
                'privacy' => [
                    'profile_visible' => true,
                    'activity_visible' => false,
                ],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['sometimes', 'in:light,dark,system'],
            'notifications.email' => ['sometimes', 'boolean'],
            'notifications.browser' => ['sometimes', 'boolean'],
            'privacy.profile_visible' => ['sometimes', 'boolean'],
            'privacy.activity_visible' => ['sometimes', 'boolean'],
        ]);

        // Store settings in session for now (could be moved to user preferences table later)
        if (isset($validated['theme'])) {
            session(['theme' => $validated['theme']]);
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => $validated,
        ]);
    }
}
