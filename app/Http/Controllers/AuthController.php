<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (!Auth::attempt([...$credentials, 'is_active' => true])) {
            return back()->withErrors(['email' => 'Email atau password tidak sesuai, atau akun nonaktif.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        return redirect()->intended(route('meetings.index'));
    }
    public function logout(Request $request)
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
