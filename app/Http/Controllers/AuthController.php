<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        // Server-side validation
        $validated = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        // ✅ DEMO ONLY (no database yet):
        // You can put a simple check here if you want:
        // if ($validated['username'] !== 'admin' || $validated['password'] !== 'admin') {
        //     return back()->withErrors(['username' => 'Invalid credentials'])->withInput();
        // }

        // ✅ Mark user as logged in (session)
        $request->session()->put('logged_in', true);
        $request->session()->put('username', $validated['username']);

        // ✅ Redirect to dashboard
        return redirect()->route('index');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['logged_in', 'username']);
        return redirect()->route('login');
    }
}
