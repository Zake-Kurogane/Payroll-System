<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('layouts.login');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:3'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors(['login' => 'Invalid credentials'])->withInput();
        }

        $validated = $validator->validated();

        if (Auth::attempt(['name' => $validated['username'], 'password' => $validated['password']])) {
            $request->session()->regenerate();
            return redirect()->intended(route('index'));
        }

        return back()->withErrors(['login' => 'Invalid credentials'])->withInput();
    }

    public function me()
    {
        $u = Auth::user();

        return response()->json([
            'name' => $u->name,
            'first_name' => $u->first_name,
            'middle_name' => $u->middle_name,
            'last_name' => $u->last_name,
            'email' => $u->email,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (!empty($validated['new_password'])) {
            if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
            }
            $user->password = Hash::make($validated['new_password']);
        }

        $user->first_name = $validated['first_name'] ?? null;
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->last_name = $validated['last_name'] ?? null;
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user = Auth::user();

        return back()->with('success', 'Profile updated successfully.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
