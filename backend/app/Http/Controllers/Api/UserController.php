<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminSistem($request);

        $query = User::with('role')->orderBy('full_name');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        $users = $query->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'message' => 'Users fetched successfully',
            'data' => $users->map(fn ($u) => $this->formatUser($u)),
            'meta' => [
                'page' => $users->currentPage(),
                'limit' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdminSistem($request);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $this->formatUser($user->load('role')),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminSistem($request);

        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'min:3', 'max:150'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role_id' => ['sometimes', 'uuid', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $this->formatUser($user->fresh('role')),
        ]);
    }

    private function authorizeAdminSistem(Request $request): void
    {
        if (!$request->user()->isAdministratorSistem()) {
            abort(403, 'Only Administrator Sistem can manage users.');
        }
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role' => [
                'id' => $user->role->id,
                'name' => $user->role->role_name,
            ],
            'created_at' => $user->created_at,
        ];
    }
}
