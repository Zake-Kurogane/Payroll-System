<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = User::query()
            ->orderByDesc('id')
            ->with(['creator:id,name'])
            ->get(['id', 'name', 'email', 'first_name', 'middle_name', 'last_name', 'role', 'created_by', 'created_at'])
            ->map(function (User $u) {
                $full = trim(($u->last_name ? $u->last_name . ', ' : '') . ($u->first_name ?? '') . ($u->middle_name ? ' ' . $u->middle_name : ''));
                return [
                    'id' => $u->id,
                    'username' => $u->name,
                    'email' => $u->email,
                    'first_name' => $u->first_name,
                    'middle_name' => $u->middle_name,
                    'last_name' => $u->last_name,
                    'role' => $u->role,
                    'full_name' => $full !== ',' ? $full : '',
                    'created_by' => $u->creator?->name,
                    'created_at' => $u->created_at ? (string) $u->created_at : null,
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->first_name = $validated['first_name'] ?? null;
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->last_name = $validated['last_name'] ?? null;
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return response()->json(['saved' => true]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ((int) $user->id === (int) Auth::id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();
        return response()->json(['deleted' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(['admin', 'hr'])],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'first_name' => $validated['first_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'role' => $validated['role'],
            'created_by' => Auth::id(),
            'password' => Hash::make($validated['password']),
        ]);

        $roleLabel = strtoupper((string) $validated['role']);
        return back()->with('success', $roleLabel . ' account created.');
    }
}
