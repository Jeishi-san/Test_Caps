<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users with their roles
     * 
     * All authenticated users can list users for sharing/collaboration purposes.
     * Admins and owners can see all user details including roles.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        
        // All authenticated users can see the user list for sharing
        $users = User::select('id', 'name', 'email', 'username', 'role', 'created_at')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role,
                    'created_at' => $user->created_at?->toISOString(),
                ];
            });

        return response()->json($users);
    }

    /**
     * Update a user's role
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'superadmin', 'user'])],
        ]);

        $user = User::findOrFail($id);
        $currentUser = Auth::user();

        // Only admins can update roles to admin
        if ($request->role === 'admin' && !$currentUser->isAdmin()) {
            return response()->json(['message' => 'Forbidden: Only admins can create admin users.'], 403);
        }

        // Only admins or owners can update other users' roles
        if ($user->id !== $currentUser->id && !$currentUser->isAdmin() && !$currentUser->isOwner()) {
            return response()->json(['message' => 'Forbidden: You do not have permission to update this user\'s role.'], 403);
        }

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'message' => 'User role updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Create a new user (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isOwner()) {
            return response()->json(['message' => 'Forbidden: Only admins can create users.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['sometimes', 'string', Rule::in(['user', 'admin'])],
            'active' => ['sometimes', 'boolean'],
        ]);

        // Only admins can create admin users
        if (isset($validated['role']) && $validated['role'] === 'admin' && !$currentUser->isAdmin()) {
            return response()->json(['message' => 'Forbidden: Only admins can create admin users.'], 403);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'] ?? 'user',
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'active' => $user->active,
            ],
        ], 201);
    }

    /**
     * Update a user (admin only)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::findOrFail($id);

        if (!$currentUser->isAdmin() && !$currentUser->isOwner()) {
            return response()->json(['message' => 'Forbidden: You do not have permission to update this user.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', 'string', Rule::in(['user', 'admin', 'superadmin'])],
            'active' => ['sometimes', 'boolean'],
        ]);

        // Only admins can promote to admin
        if (isset($validated['role']) && $validated['role'] === 'admin' && !$currentUser->isAdmin()) {
            return response()->json(['message' => 'Forbidden: Only admins can promote users to admin.'], 403);
        }

        $user->fill($validated);
        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'active' => $user->active,
            ],
        ]);
    }

    /**
     * Delete a user (admin only)
     */
    public function destroy(int $id): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::findOrFail($id);

        if (!$currentUser->isAdmin() && !$currentUser->isOwner()) {
            return response()->json(['message' => 'Forbidden: You do not have permission to delete this user.'], 403);
        }

        // Prevent self-deletion
        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * List admin users (superadmin/owner only)
     */
    public function listAdmins(): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser->isOwner()) {
            return response()->json(['message' => 'Forbidden: Superadmin access required.'], 403);
        }

        $admins = User::whereIn('role', ['admin', 'superadmin'])
            ->select(['id', 'name', 'email', 'role', 'active', 'created_at'])
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return response()->json($admins);
    }
}
