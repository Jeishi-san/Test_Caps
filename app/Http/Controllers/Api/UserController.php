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
        $users = User::select('id', 'name', 'email', 'username', 'role', 'active', 'created_at')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role,
                    'active' => $user->active,
                    'created_at' => $user->created_at?->toISOString(),
                ];
            });

        return response()->json($users);
    }

    /**
     * Get a single user by ID
     */
    public function show(int $id): JsonResponse
    {
        $user = User::select('id', 'name', 'email', 'username', 'role', 'active', 'created_at')
            ->findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->role,
            'active' => $user->active,
            'created_at' => $user->created_at?->toISOString(),
        ]);
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

        // Only superadmins can update roles to superadmin
        if ($request->role === 'superadmin' && !$currentUser->isOwner()) {
            return response()->json(['message' => 'Forbidden: Only superadmins can create superadmin users.'], 403);
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
                'active' => $user->active,
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
            'role' => ['sometimes', 'string', Rule::in(['user', 'admin', 'superadmin'])],
            'active' => ['sometimes', 'boolean'],
        ]);

        // Only admins can create admin users, only superadmins can create superadmin users
        if (isset($validated['role'])) {
            if ($validated['role'] === 'superadmin' && !$currentUser->isOwner()) {
                return response()->json(['message' => 'Forbidden: Only superadmins can create superadmin users.'], 403);
            }
            if ($validated['role'] === 'admin' && !$currentUser->isAdmin() && !$currentUser->isOwner()) {
                return response()->json(['message' => 'Forbidden: Only admins can create admin users.'], 403);
            }
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

        // Only admins can promote to admin, only superadmins can promote to superadmin
        if (isset($validated['role'])) {
            if ($validated['role'] === 'superadmin' && !$currentUser->isOwner()) {
                return response()->json(['message' => 'Forbidden: Only superadmins can promote users to superadmin.'], 403);
            }
            if ($validated['role'] === 'admin' && !$currentUser->isAdmin() && !$currentUser->isOwner()) {
                return response()->json(['message' => 'Forbidden: Only admins can promote users to admin.'], 403);
            }
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

        // Prevent admin from deleting superadmin
        if ($user->isOwner() && !$currentUser->isOwner()) {
            return response()->json(['message' => 'Forbidden: You do not have permission to delete this user.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * List admin users (superadmin/owner only)
     */
    public function listAdmins(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser->isOwner()) {
            return response()->json(['message' => 'Forbidden: Superadmin access required.'], 403);
        }

        $perPage = $request->input('per_page', 10);
        $admins = User::whereIn('role', ['admin', 'superadmin'])
            ->select(['id', 'name', 'email', 'role', 'active', 'created_at'])
            ->orderBy('role')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($admins);
    }
}
