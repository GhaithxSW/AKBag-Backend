<?php

namespace App\Http\Controllers\Filament;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/admin');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput();
    }
}
