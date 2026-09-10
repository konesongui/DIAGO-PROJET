<?php

namespace App\Http\Controllers;

use App\Models\Entreprise;
use App\Models\AuditLog;
use App\Models\LandingSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\PackRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SuperAdminConsoleController extends Controller
{
    public function index()
    {
        $entreprises = Entreprise::withCount('users')->latest()->get();
        $today = today();
        $expiringLimit = $today->copy()->addDays(30);
        $subscriptionDate = static fn (Entreprise $entreprise) => Carbon::make(data_get($entreprise->settings, 'subscription_expires_at'));
        $expiring = $entreprises->filter(function (Entreprise $entreprise) use ($today, $expiringLimit, $subscriptionDate) {
            $date = $subscriptionDate($entreprise);
            return $date && !$date->lt($today) && !$date->gt($expiringLimit);
        });
        $expired = $entreprises->filter(function (Entreprise $entreprise) use ($today, $subscriptionDate) {
            $date = $subscriptionDate($entreprise);
            return $date && $date->isBefore($today);
        });

        return view('console.index', [
            'dashboardTitle' => 'Dashboard Super Administrateur',
            'entreprises' => $entreprises,
            'superAdmins' => User::whereNull('entreprise_id')->whereHas('role', fn ($query) => $query->where('name', 'super_admin'))->count(),
            'activeEntreprises' => $entreprises->where('is_active', true)->count(),
            'inactiveEntreprises' => $entreprises->where('is_active', false)->count(),
            'expiringEntreprises' => $expiring,
            'expiredEntreprises' => $expired,
            'activeUsers' => User::whereNotNull('entreprise_id')->where('is_active', true)->count(),
            'inactiveUsers' => User::whereNotNull('entreprise_id')->where('is_active', false)->count(),
            'totalUsers' => User::whereNotNull('entreprise_id')->count(),
            'activationRate' => $entreprises->count() ? round(($entreprises->where('is_active', true)->count() / $entreprises->count()) * 100) : 0,
        ]);
    }

    public function accountTracking()
    {
        return view('console.account-tracking', [
            'entreprises' => Entreprise::withCount('users')->latest()->get(),
        ]);
    }

    public function landingSettings()
    {
        return view('console.landing-settings', [
            'settings' => LandingSetting::current(),
        ]);
    }

    public function packRequests()
    {
        return view('console.pack-requests', [
            'requests' => PackRequest::latest()->get(),
        ]);
    }

    public function replyPackRequestByEmail(Request $request, PackRequest $packRequest)
    {
        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:5000'],
        ]);

        Mail::raw($validated['reply'], function ($message) use ($packRequest) {
            $message->to($packRequest->email, $packRequest->name)
                ->subject('Réponse à votre demande de pack Diagoma');
        });

        $packRequest->update([
            'status' => 'replied',
            'admin_reply' => $validated['reply'],
            'replied_at' => now(),
        ]);

        return back()->with('success', 'La réponse a été envoyée par email.');
    }

    public function updateLandingSettings(Request $request)
    {
        $validated = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:100'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'hero_badge' => ['nullable', 'string', 'max:200'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:800'],
            'primary_cta_label' => ['nullable', 'string', 'max:100'],
            'primary_cta_url' => ['nullable', 'string', 'max:255'],
            'secondary_cta_label' => ['nullable', 'string', 'max:100'],
            'secondary_cta_url' => ['nullable', 'string', 'max:255'],
            'slides_json' => ['nullable', 'string'],
            'modules_json' => ['nullable', 'string'],
            'packs_json' => ['nullable', 'string'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:100'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
        ]);

        $slides = $this->decodeJsonArray($validated['slides_json'] ?? '[]', 'slides');
        $modules = $this->decodeJsonArray($validated['modules_json'] ?? '[]', 'modules');
        $packs = $this->decodeJsonArray($validated['packs_json'] ?? '[]', 'packs');

        $settings = LandingSetting::current();
        $settings = array_replace_recursive($settings, [
            'brand_name' => $validated['brand_name'] ?? $settings['brand_name'],
            'page_title' => $validated['page_title'] ?? $settings['page_title'],
            'meta_description' => $validated['meta_description'] ?? $settings['meta_description'],
            'hero_badge' => $validated['hero_badge'] ?? $settings['hero_badge'],
            'hero_title' => $validated['hero_title'] ?? $settings['hero_title'],
            'hero_subtitle' => $validated['hero_subtitle'] ?? $settings['hero_subtitle'],
            'primary_cta_label' => $validated['primary_cta_label'] ?? $settings['primary_cta_label'],
            'primary_cta_url' => $validated['primary_cta_url'] ?? $settings['primary_cta_url'],
            'secondary_cta_label' => $validated['secondary_cta_label'] ?? $settings['secondary_cta_label'],
            'secondary_cta_url' => $validated['secondary_cta_url'] ?? $settings['secondary_cta_url'],
            'slides' => $slides,
            'modules' => $modules,
            'packs' => $packs,
            'footer_text' => $validated['footer_text'] ?? $settings['footer_text'],
            'contact_email' => $validated['contact_email'] ?? $settings['contact_email'],
            'contact_phone' => $validated['contact_phone'] ?? $settings['contact_phone'],
            'primary_color' => $validated['primary_color'] ?? $settings['primary_color'],
            'secondary_color' => $validated['secondary_color'] ?? $settings['secondary_color'],
        ]);

        LandingSetting::persist($settings);

        return redirect()->route('console.landing')->with('success', 'Le site landing a été mis à jour.');
    }

    private function decodeJsonArray(?string $payload, string $fieldName): array
    {
        $value = $payload ?? '[]';
        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            abort(422, 'Le champ ' . $fieldName . ' doit contenir un tableau JSON valide.');
        }

        return $decoded;
    }

    public function createEntreprise()
    {
        return view('console.entreprises.create', ['moduleRubriques' => $this->moduleRubriques()]);
    }

    public function showEntreprise(Entreprise $entreprise)
    {
        return view('console.entreprises.show', [
            'entreprise' => $entreprise->load('users.role'),
        ]);
    }

    public function impersonateAdmin(Request $request, User $user)
    {
        abort_unless($user->hasRole('admin') && $user->entreprise_id, 404);
        abort_if(!$user->is_active, 422, 'Ce compte administrateur est désactivé.');
        abort_if($request->session()->has('impersonator_id'), 409, 'Une session d’assistance est déjà active.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $actor = Auth::user();
        $request->session()->put([
            'impersonator_id' => $actor->id,
            'impersonated_user_id' => $user->id,
            'impersonation_started_at' => now()->toIso8601String(),
        ]);
        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::create([
            'actor_id' => $actor->id,
            'target_user_id' => $user->id,
            'entreprise_id' => $user->entreprise_id,
            'action' => 'impersonation_started',
            'reason' => $validated['reason'],
            'metadata' => ['ip' => $request->ip()],
        ]);

        return redirect()->route('admin.dashboard');
    }

    public function stopImpersonation(Request $request)
    {
        $originalId = $request->session()->pull('impersonator_id');
        $targetId = $request->session()->pull('impersonated_user_id');
        $startedAt = $request->session()->pull('impersonation_started_at');

        abort_unless($originalId && $targetId, 403);

        AuditLog::create([
            'actor_id' => $originalId,
            'target_user_id' => $targetId,
            'entreprise_id' => Auth::user()->entreprise_id,
            'action' => 'impersonation_stopped',
            'metadata' => ['ip' => $request->ip(), 'started_at' => $startedAt],
        ]);

        Auth::loginUsingId($originalId);
        $request->session()->regenerate();

        return redirect()->route('console.account-tracking');
    }

    public function editEntreprise(Entreprise $entreprise)
    {
        return view('console.entreprises.edit', [
            'entreprise' => $entreprise,
            'administrator' => $entreprise->users()->whereHas('role', fn ($query) => $query->where('name', 'admin'))->first(),
            'moduleRubriques' => $this->moduleRubriques(),
        ]);
    }

    public function updateEntreprise(Request $request, Entreprise $entreprise)
    {
        $administrator = $entreprise->users()->whereHas('role', fn ($query) => $query->where('name', 'admin'))->first();
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:entreprises,slug,' . $entreprise->id],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email,' . ($administrator?->id ?? 'NULL')],
            'rubriques' => ['required', 'array', 'min:1'],
            'rubriques.*' => ['boolean'],
            'ai_assistant_enabled' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $entreprise, $administrator) {
            $settings = $entreprise->settings ?? [];
            $settings = array_merge($settings, [
                'trade_name' => $validated['trade_name'] ?? null,
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'city' => $validated['city'],
                'enabled_rubriques' => $this->normalizeRubriques($validated['rubriques']),
                'ai_assistant_enabled' => !empty($validated['ai_assistant_enabled']),
            ]);
            $entreprise->update(['name' => $validated['company_name'], 'slug' => $validated['slug'], 'settings' => $settings]);
            if ($administrator) {
                $administrator->update(['name' => $validated['admin_name'], 'email' => $validated['admin_email']]);
            }
        });

        return redirect()->route('console.index')->with('success', 'Entreprise modifiée avec succès.');
    }

    public function renewEntreprise(Request $request, Entreprise $entreprise)
    {
        $validated = $request->validate(['subscription_expires_at' => ['required', 'date', 'after_or_equal:today']]);
        $settings = $entreprise->settings ?? [];
        $settings['subscription_expires_at'] = $validated['subscription_expires_at'];
        $entreprise->update(['settings' => $settings, 'is_active' => true]);
        User::where('entreprise_id', $entreprise->id)->update(['is_active' => true]);

        return back()->with('success', 'Réabonnement enregistré et espace réactivé.');
    }

    public function storeEntreprise(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'subscription_expires_at' => ['required', 'date', 'after_or_equal:today'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:entreprises,slug'],
            'database_name' => ['nullable', 'string', 'max:255', 'unique:entreprises,database_name'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'rubriques' => ['required', 'array', 'min:1'],
            'rubriques.*' => ['boolean'],
            'ai_assistant_enabled' => ['nullable', 'boolean'],
        ]);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $slug = $validated['slug'] ?: Str::slug($validated['company_name']);

        if (Entreprise::where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['slug' => 'Ce slug est déjà utilisé.']);
        }

        DB::transaction(function () use ($validated, $adminRole, $slug) {
            $entreprise = Entreprise::create([
                'name' => $validated['company_name'],
                'slug' => $slug,
                'database_name' => $validated['database_name'] ?? null,
                'is_active' => true,
                'created_by' => auth()->id(),
                'settings' => [
                    'locale' => 'fr',
                    'trade_name' => $validated['trade_name'] ?? null,
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                    'city' => $validated['city'],
                    'subscription_expires_at' => $validated['subscription_expires_at'],
                    'enabled_rubriques' => $this->normalizeRubriques($validated['rubriques']),
                    'ai_assistant_enabled' => !empty($validated['ai_assistant_enabled']),
                ],
            ]);

            User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'entreprise_id' => $entreprise->id,
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]);
        });

        return redirect()->route('console.index')->with('success', 'Entreprise et compte administrateur créés avec succès.');
    }

    public function toggleEntreprise(Entreprise $entreprise)
    {
        $entreprise->update(['is_active' => !$entreprise->is_active]);
        User::where('entreprise_id', $entreprise->id)->update(['is_active' => $entreprise->is_active]);

        return back()->with('success', 'Statut de l’entreprise et de ses comptes administrateurs mis à jour.');
    }

    private function moduleRubriques(): array
    {
        return [
            'pilotage' => ['label' => 'Pilotage', 'description' => 'Tableau de bord et rapports de pilotage.', 'icon' => 'bi-bar-chart'],
            'commercial' => ['label' => 'Commercial', 'description' => 'Clients, ventes, stocks et point de vente.', 'icon' => 'bi-cart'],
            'comptabilite' => ['label' => 'Comptabilité', 'description' => 'Caisses, banques et rapports comptables.', 'icon' => 'bi-credit-card'],
            'rh' => ['label' => 'RH & Paie', 'description' => 'Employés, services, fonctions et paie.', 'icon' => 'bi-people'],
            'administration' => ['label' => 'Administration', 'description' => 'Administration et paramétrage de l’entreprise.', 'icon' => 'bi-gear'],
            'succursales' => ['label' => 'Succursales', 'description' => 'Gérez les établissements et suivez leur activité.', 'icon' => 'bi-shop'],
        ];
    }

    private function normalizeRubriques(array $rubriques): array
    {
        $enabled = [];
        foreach (array_keys($this->moduleRubriques()) as $key) {
            $enabled[$key] = !empty($rubriques[$key]);
        }

        return $enabled;
    }
}
