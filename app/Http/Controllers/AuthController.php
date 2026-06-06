<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|max:255',
        ]);

        $throttleKey = 'login:' . strtolower($validated['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            Log::warning('SECURITY: Login throttled', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (Auth::attempt($validated, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            Log::info('AUDIT: Login success', [
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);

            return redirect()->intended(route('journal.index'));
        }

        RateLimiter::hit($throttleKey, 300);

        Log::warning('SECURITY: Login failed', [
            'email' => $validated['email'],
            'ip' => $request->ip(),
        ]);

        return back()
            ->withErrors(['email' => 'Email atau password salah.'])
            ->onlyInput('email');
    }

    public function showRegister()
    {
        $units = Unit::active()->orderBy('name')->get();
        return view('auth.register', compact('units'));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'unit_id' => 'required|exists:units,id',
        ]);

        $validated['name'] = strip_tags(trim($validated['name']));

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'unit_id' => $validated['unit_id'],
            'role' => 'staff_unit',
        ]);

        Log::info('AUDIT: User registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('login')->with('success', 'Registrasi berhasil. Silakan login.');
    }

    public function logout(Request $request)
    {
        $userId = Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('AUDIT: Logout', ['user_id' => $userId, 'ip' => $request->ip()]);

        return redirect('/');
    }
}
