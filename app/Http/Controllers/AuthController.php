<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'matricule' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            if ((bool) Auth::user()?->must_change_password) {
                return redirect()->route('password.first.form');
            }
            return redirect()->route('import.form'); 
        }

        return back()->withErrors([
            'matricule' => 'Les identifiants fournis ne correspondent pas à nos enregistrements.',
        ])->onlyInput('matricule');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function showFirstPasswordForm()
    {
        return view('auth.first_password');
    }

    public function updateFirstPassword(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        $user->password = Hash::make($data['password']);
        $user->must_change_password = false;
        $user->save();

        return redirect()->route('import.form')->with('success', 'Mot de passe mis a jour avec succes.');
    }
}
