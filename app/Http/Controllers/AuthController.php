<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
        Log::info('LOGIN step 1: start');
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:3'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            Log::info('LOGIN step 2: validation failed');
            return back()->withErrors(['login' => 'Invalid credentials'])->withInput();
        }

        Log::info('LOGIN step 3: validation passed');
        $validated = $validator->validated();
        $loginInput = trim($validated['username']);

        $credentialSets = [
            ['name' => $loginInput, 'password' => $validated['password']],
            ['email' => $loginInput, 'password' => $validated['password']],
        ];

        foreach ($credentialSets as $i => $credentials) {
            Log::info("LOGIN step 4.$i: attempting auth with key=" . array_key_first($credentials));
            if (!Auth::attempt($credentials)) {
                Log::info("LOGIN step 4.$i: attempt failed");
                continue;
            }

            Log::info('LOGIN step 5: auth succeeded, regenerating session');
            $request->session()->regenerate();
            Log::info('LOGIN step 6: session regenerated');
            $user = Auth::user();
            if (($user->role ?? 'admin') === 'hr') {
                return redirect()->intended(route('employee.records'));
            }
            return redirect()->intended(route('index'));
        }

        Log::info('LOGIN step 7: all attempts failed, returning error');
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
