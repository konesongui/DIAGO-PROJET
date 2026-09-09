<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if ($user && !$user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Votre compte est désactivé. Veuillez contacter l’administrateur de votre entreprise.',
            ]);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended($user->hasRole('super_admin') ? '/console' : '/admin/dashboard');
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $slug = str($validated['name'])->slug()->toString();
        $entreprise = Entreprise::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $validated['name'],
                'database_name' => 'tenant_' . uniqid(),
                'is_active' => true,
                'settings' => ['locale' => 'fr'],
                'created_by' => null,
            ]
        );

        $role = Role::where('name', 'manager')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'entreprise_id' => $entreprise->id,
            'role_id' => $role?->id,
        ]);

        Auth::login($user);

        return redirect('/admin/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function setLocale(Request $request)
    {
        $locale = $request->input('locale', $request->query('locale', session('locale', app()->getLocale())));
        $locale = in_array($locale, ['fr', 'en'], true) ? $locale : 'fr';

        if ($user = $request->user()) {
            $entreprise = $user->entreprise;
            if ($entreprise) {
                $settings = is_array($entreprise->settings) ? $entreprise->settings : [];
                $settings['locale'] = $locale;
                $entreprise->update(['settings' => $settings]);
            }
        }

        session(['locale' => $locale]);
        app()->setLocale($locale);

        return $request->expectsJson()
            ? response()->json(['locale' => $locale])
            : redirect()->back();
    }
}
