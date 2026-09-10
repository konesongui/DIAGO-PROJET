<?php

namespace App\Http\Controllers\Admin;

use App\Models\Entreprise;
use App\Models\TaxRate;
use App\Services\CinetPayService;
use App\Services\TaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SettingController extends AdminController
{
    public function index()
    {
        $modules = $this->modules();
        if (!auth()->user()->hasRole('super_admin')) {
            $modules = collect($modules)->reject(fn (array $module) => in_array($module['key'], ['modules', 'landing', 'cinetpay', 'whatsapp'], true))->values()->all();
        }

        return $this->page('settings', [
            'title' => 'Paramètres',
            'modules' => $modules,
        ]);
    }

    public function moduleSettingsIndex()
    {
        $settings = auth()->user()->entreprise?->settings ?? [];
        $enabled = array_merge($this->defaultModuleRubriques(), $settings['enabled_rubriques'] ?? []);

        return $this->page('settings-modules', [
            'title' => 'Modules',
            'rubriques' => $this->moduleRubriques(),
            'enabledRubriques' => $enabled,
        ]);
    }

    public function updateModules(Request $request)
    {
        $validated = $request->validate([
            'rubriques' => ['nullable', 'array'],
            'rubriques.*' => ['boolean'],
        ]);
        $entreprise = Entreprise::whereKey(auth()->user()->entreprise_id)->firstOrFail();
        $enabled = [];
        foreach (array_keys($this->defaultModuleRubriques()) as $key) {
            $enabled[$key] = !empty($validated['rubriques'][$key]);
        }
        $settings = $entreprise->settings ?? [];
        $settings['enabled_rubriques'] = $enabled;
        $entreprise->update(['settings' => $settings]);

        return redirect()->route('admin.settings.modules')->with('success', 'Les rubriques activées ont été enregistrées.');
    }

    public function module(string $module)
    {
        $modules = collect($this->modules())->keyBy('key');
        abort_unless($modules->has($module), 404);

        if ($module === 'general') {
            $entreprise = auth()->user()->entreprise;
            $settings = array_merge([
                'name' => $entreprise?->name ?? '',
                'locale' => 'fr',
                'currency' => 'XOF',
                'currency_symbol' => 'FCFA',
                'date_format' => 'dd/mm/yyyy',
                'week_start' => 'monday',
            ], $entreprise?->settings ?? []);

            $paymentGateways = data_get($settings, 'payment_gateways', []);
            $settings['payment_gateways'] = $paymentGateways;

            return $this->page('settings-general', [
                'title' => $modules[$module]['title'],
                'module' => $modules[$module],
                'entreprise' => $entreprise,
                'settings' => $settings,
            ]);
        }

        if ($module === 'email') {
            $entreprise = auth()->user()->entreprise;
            $storedEmailSettings = $this->emailSettings($entreprise);
            $emailSettings = array_merge([
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'from_address' => $storedEmailSettings['from_address'] ?? '',
                'from_name' => $entreprise?->name ?? config('app.name'),
                'test_recipient' => auth()->user()->email,
            ], $storedEmailSettings);

            return $this->page('settings-email', [
                'title' => $modules[$module]['title'],
                'module' => $modules[$module],
                'emailSettings' => $emailSettings,
            ]);
        }

        if ($module === 'theme') {
            $theme = auth()->user()->entreprise
                ? data_get(auth()->user()->entreprise->settings, 'theme', 'ocean')
                : session('console_theme', 'ocean');

            return $this->page('settings-theme', [
                'title' => $modules[$module]['title'],
                'module' => $modules[$module],
                'theme' => $theme,
            ]);
        }

        if ($module === 'cinetpay') {
            abort_unless(auth()->user()->hasRole('super_admin'), 403, 'Accès réservé au profil console.');

            $settings = auth()->user()->entreprise?->settings ?? [];
            $cinetpay = data_get($settings, 'payment_gateways.cinetpay', []);

            if (empty($cinetpay) && session()->has('console_payment_gateways')) {
                $cinetpay = session('console_payment_gateways.cinetpay', []);
            }

            return $this->page('settings-cinetpay', [
                'title' => $modules[$module]['title'],
                'module' => $modules[$module],
                'settings' => $settings,
                'cinetpay' => $cinetpay,
            ]);
        }

        if ($module === 'whatsapp') {
            abort_unless(auth()->user()->hasRole('super_admin'), 403, 'Accès réservé au profil console.');

            $settings = auth()->user()->entreprise?->settings ?? [];
            $whatsapp = data_get($settings, 'communication.whatsapp', []);

            if (empty($whatsapp) && session()->has('console_communication')) {
                $whatsapp = session('console_communication.whatsapp', []);
            }

            return $this->page('settings-whatsapp', [
                'title' => $modules[$module]['title'],
                'module' => $modules[$module],
                'settings' => $settings,
                'whatsapp' => $whatsapp,
            ]);
        }

        if ($module === 'fiscalite') {
            $entreprise = auth()->user()->entreprise;
            $taxService = app(TaxService::class);

            return $this->page('settings-fiscalite', [
                'title' => $modules[$module]['title'],
                'module' => $modules[$module],
                'rates' => TaxRate::ordered()->get(),
                'taxBasis' => data_get($entreprise?->settings ?? [], 'tax_basis', 'debits'),
                'regimes' => TaxRate::regimes(),
                'currency' => $taxService->currencyFor($entreprise),
                'currencySymbol' => $taxService->currencySymbolFor($entreprise),
                'decimals' => $taxService->decimalsFor($taxService->currencyFor($entreprise)),
            ]);
        }

        return $this->page('settings-module', [
            'title' => $modules[$module]['title'],
            'module' => $modules[$module],
        ]);
    }

    /** Fait generateur de la taxe : a la facturation ou a l'encaissement. */
    public function updateTaxBasis(Request $request)
    {
        $validated = $request->validate([
            'tax_basis' => ['required', Rule::in(['debits', 'collections'])],
        ]);

        $entreprise = Entreprise::whereKey(auth()->user()->entreprise_id)->firstOrFail();
        $settings = $entreprise->settings ?? [];
        $settings['tax_basis'] = $validated['tax_basis'];
        $entreprise->update(['settings' => $settings]);

        return redirect()->route('admin.settings.module', ['module' => 'fiscalite'])
            ->with('success', 'Le fait générateur de la taxe a été enregistré.');
    }

    public function storeTaxRate(Request $request)
    {
        $validated = $this->validateTaxRate($request);
        $validated['entreprise_id'] = auth()->user()->entreprise_id;

        $rate = TaxRate::create($validated);
        $this->enforceSingleDefaultRate($rate);

        return redirect()->route('admin.settings.module', ['module' => 'fiscalite'])
            ->with('success', 'Le taux de taxe a été ajouté.');
    }

    public function updateTaxRate(Request $request, TaxRate $taxRate)
    {
        abort_unless($taxRate->entreprise_id === auth()->user()->entreprise_id, 403);

        $taxRate->update($this->validateTaxRate($request, $taxRate));
        $this->enforceSingleDefaultRate($taxRate);

        return redirect()->route('admin.settings.module', ['module' => 'fiscalite'])
            ->with('success', 'Le taux de taxe a été mis à jour.');
    }

    public function destroyTaxRate(TaxRate $taxRate)
    {
        abort_unless($taxRate->entreprise_id === auth()->user()->entreprise_id, 403);

        // Un taux deja utilise ne doit pas disparaitre : les documents emis
        // doivent rester lisibles. On le desactive au lieu de le supprimer.
        $taxRate->update(['is_active' => false, 'is_default' => false]);

        return redirect()->route('admin.settings.module', ['module' => 'fiscalite'])
            ->with('success', 'Le taux a été désactivé. Les documents déjà émis le conservent.');
    }

    private function validateTaxRate(Request $request, ?TaxRate $current = null): array
    {
        $unique = Rule::unique('tax_rates', 'name')
            ->where(fn ($query) => $query->where('entreprise_id', auth()->user()->entreprise_id));

        if ($current) {
            $unique = $unique->ignore($current->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:120', $unique],
            'code' => ['nullable', 'string', 'max:30'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'regime' => ['required', Rule::in(array_keys(TaxRate::regimes()))],
            'effective_from' => ['nullable', 'date'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }

    /** Un seul taux par defaut a la fois dans une entreprise. */
    private function enforceSingleDefaultRate(TaxRate $rate): void
    {
        if (! $rate->is_default) {
            return;
        }

        TaxRate::where('entreprise_id', $rate->entreprise_id)
            ->where('id', '!=', $rate->id)
            ->update(['is_default' => false]);
    }

    public function updateEmail(Request $request)
    {
        $entreprise = Entreprise::whereKey(auth()->user()->entreprise_id)->firstOrFail();
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'test_recipient' => ['nullable', 'email', 'max:255'],
        ]);

        $current = $this->emailSettings($entreprise);
        if ($validated['password'] === '') {
            $validated['password'] = $current['password'] ?? '';
        }

        $settings = $entreprise->settings ?? [];
        $settings['email'] = $validated;
        $entreprise->update(['settings' => $settings]);

        return back()->with('success', 'Configuration des emails enregistrée.');
    }

    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:ocean,emerald,royal,amber,slate,midnight,coral,lavender,teal,graphite,ruby,forest,sand,metronic_black'],
        ]);

        if ($entreprise = auth()->user()->entreprise) {
            $settings = $entreprise->settings ?? [];
            $settings['theme'] = $validated['theme'];
            $entreprise->update(['settings' => $settings]);
        } else {
            session(['console_theme' => $validated['theme']]);
        }

        return back()->with('success', 'Le thème de couleurs a été enregistré.');
    }

    public function updateCinetPay(Request $request)
    {
        abort_unless(auth()->user()->hasRole('super_admin'), 403, 'Accès réservé au profil console.');

        $validated = $request->validate([
            'cinetpay_enabled' => ['nullable', 'boolean'],
            'cinetpay_site_id' => ['nullable', 'string', 'max:100'],
            'cinetpay_api_key' => ['nullable', 'string', 'max:255'],
            'cinetpay_secret_key' => ['nullable', 'string', 'max:255'],
            'cinetpay_mode' => ['nullable', 'in:test,production'],
            'cinetpay_notify_url' => ['nullable', 'url', 'max:255'],
            'cinetpay_return_url' => ['nullable', 'url', 'max:255'],
        ]);

        $settings = auth()->user()->entreprise?->settings ?? [];
        $settings['payment_gateways'] = array_merge($settings['payment_gateways'] ?? [], [
            'cinetpay' => [
                'enabled' => (bool) ($validated['cinetpay_enabled'] ?? false),
                'site_id' => $validated['cinetpay_site_id'] ?? '',
                'api_key' => $validated['cinetpay_api_key'] ?? '',
                'secret_key' => $validated['cinetpay_secret_key'] ?? '',
                'mode' => $validated['cinetpay_mode'] ?? 'test',
                'notify_url' => $validated['cinetpay_notify_url'] ?? '',
                'return_url' => $validated['cinetpay_return_url'] ?? '',
            ],
        ]);

        if ($entreprise = auth()->user()->entreprise) {
            $entreprise->update(['settings' => $settings]);
        } else {
            session(['console_payment_gateways' => $settings['payment_gateways']]);
        }

        return redirect()->route('admin.settings.module', 'cinetpay')->with('success', 'La configuration CinetPay a été enregistrée.');
    }

    public function updateWhatsapp(Request $request)
    {
        abort_unless(auth()->user()->hasRole('super_admin'), 403, 'Accès réservé au profil console.');

        $validated = $request->validate([
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_api_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_sender_name' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = auth()->user()->entreprise?->settings ?? [];
        $settings['communication'] = array_merge($settings['communication'] ?? [], [
            'whatsapp' => [
                'enabled' => (bool) ($validated['whatsapp_enabled'] ?? false),
                'api_url' => $validated['whatsapp_api_url'] ?? '',
                'token' => $validated['whatsapp_token'] ?? '',
                'phone_number_id' => $validated['whatsapp_phone_number_id'] ?? '',
                'business_account_id' => $validated['whatsapp_business_account_id'] ?? '',
                'template_name' => $validated['whatsapp_template_name'] ?? '',
                'sender_name' => $validated['whatsapp_sender_name'] ?? '',
            ],
        ]);

        if ($entreprise = auth()->user()->entreprise) {
            $entreprise->update(['settings' => $settings]);
        } else {
            session(['console_communication' => $settings['communication']]);
        }

        return redirect()->route('admin.settings.module', 'whatsapp')->with('success', 'La configuration WhatsApp a été enregistrée.');
    }

    public function testEmail(Request $request)
    {
        $entreprise = Entreprise::whereKey(auth()->user()->entreprise_id)->firstOrFail();
        $settings = $this->emailSettings($entreprise);
        $recipient = $request->input('test_recipient', $settings['test_recipient'] ?? auth()->user()->email);
        $request->validate(['test_recipient' => ['nullable', 'email', 'max:255']]);

        Mail::raw('Cet email confirme que la configuration SMTP de Diagoma fonctionne correctement.', function ($message) use ($recipient) {
            $message->to($recipient)->subject('Test de configuration email - Diagoma');
        });

        return back()->with('success', 'Email de test envoyé à ' . $recipient . '.');
    }

    private function emailSettings(?Entreprise $entreprise): array
    {
        $stored = data_get($entreprise?->settings, 'email', []);

        if (is_array($stored)) {
            return $stored;
        }

        // Keep legacy installations usable when email previously stored only an address.
        return is_string($stored) && filter_var($stored, FILTER_VALIDATE_EMAIL)
            ? ['from_address' => $stored]
            : [];
    }

    public function updateGeneral(Request $request)
    {
        $entreprise = Entreprise::whereKey(auth()->user()->entreprise_id)->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trade_register' => ['nullable', 'string', 'max:100'],
            'taxpayer_account' => ['nullable', 'string', 'max:100'],
            'legal_form' => ['nullable', 'string', 'max:100'],
            'cnps_number' => ['nullable', 'string', 'max:100'],
            'po_box' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'tax_center' => ['nullable', 'string', 'max:255'],
            'tax_regime' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:50'],
            'nccm_rccm' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'locale' => ['required', 'in:fr,en'],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'date_format' => ['nullable', 'string', 'max:20'],
            'week_start' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'manager_title' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'subscription_expires_at' => ['nullable', 'date'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'cinetpay_enabled' => ['nullable', 'boolean'],
            'cinetpay_site_id' => ['nullable', 'string', 'max:100'],
            'cinetpay_api_key' => ['nullable', 'string', 'max:255'],
            'cinetpay_secret_key' => ['nullable', 'string', 'max:255'],
            'cinetpay_mode' => ['nullable', 'in:test,production'],
            'cinetpay_notify_url' => ['nullable', 'url', 'max:255'],
            'cinetpay_return_url' => ['nullable', 'url', 'max:255'],
        ]);

        $settings = $entreprise->settings ?? [];

        if (auth()->user()->hasRole('super_admin')) {
            $settings['payment_gateways'] = array_merge(
                $settings['payment_gateways'] ?? [],
                [
                    'cinetpay' => [
                        'enabled' => (bool) ($validated['cinetpay_enabled'] ?? false),
                        'site_id' => $validated['cinetpay_site_id'] ?? '',
                        'api_key' => $validated['cinetpay_api_key'] ?? '',
                        'secret_key' => $validated['cinetpay_secret_key'] ?? '',
                        'mode' => $validated['cinetpay_mode'] ?? 'test',
                        'notify_url' => $validated['cinetpay_notify_url'] ?? '',
                        'return_url' => $validated['cinetpay_return_url'] ?? '',
                    ],
                ]
            );
        }

        foreach (collect($validated)->except(['name', 'logo', 'cinetpay_enabled', 'cinetpay_site_id', 'cinetpay_api_key', 'cinetpay_secret_key', 'cinetpay_mode', 'cinetpay_notify_url', 'cinetpay_return_url'])->all() as $key => $value) {
            $settings[$key] = $value;
        }

        if ($request->hasFile('logo')) {
            $settings['logo'] = $request->file('logo')->store('entreprises/logos', 'public');
        }

        $entreprise->update([
            'name' => $validated['name'],
            'settings' => $settings,
        ]);

        return redirect()->route('admin.settings.module', 'general')->with('success', 'Configuration de l’entreprise enregistrée.');
    }

    public function renewSubscription(Request $request)
    {
        $entreprise = Entreprise::whereKey(auth()->user()->entreprise_id)->firstOrFail();
        $validated = $request->validate([
            'renewal_payment_method' => ['required', 'in:orange_money,mtn_money,moov_money,wave_money,other_mobile_money,mastercard,visa,bank_transfer,cinetpay'],
            'renewal_duration' => ['required', 'in:1,3,6,12'],
        ]);

        $durationMonths = (int) $validated['renewal_duration'];

        if ($validated['renewal_payment_method'] === 'cinetpay') {
            $cinetPayService = app(CinetPayService::class);
            if (! $cinetPayService->isConfigured()) {
                return back()->withErrors([
                    'renewal_payment_method' => 'La configuration CinetPay est incomplète. Ajoutez les variables CINETPAY_SITE_ID et CINETPAY_API_KEY dans votre fichier .env.',
                ])->withInput();
            }

            $pending = [
                'entreprise_id' => $entreprise->id,
                'duration' => $durationMonths,
                'amount' => $this->subscriptionRenewalAmount($durationMonths),
            ];
            $pending['reference'] = 'SUB-RENEW-' . $entreprise->id . '-' . now()->format('YmdHis');
            $request->session()->put('subscription_renewal_pending', $pending);

            try {
                $result = $cinetPayService->buildPaymentRequest(
                    $entreprise,
                    $durationMonths,
                    $pending['amount'],
                    $pending['reference']
                );

                return redirect()->away($result['payment_url']);
            } catch (\Throwable $exception) {
                $request->session()->forget('subscription_renewal_pending');

                return back()->withErrors([
                    'renewal_payment_method' => $exception->getMessage(),
                ])->withInput();
            }
        }

        $this->applySubscriptionRenewal($entreprise, $durationMonths, $validated['renewal_payment_method']);

        return redirect()->route('admin.settings.module', 'general')->with('success', 'Réabonnement enregistré jusqu’au ' . $this->subscriptionExpiryDate($entreprise, $durationMonths)->format('d/m/Y') . '.');
    }

    public function handleRenewalCallback(Request $request)
    {
        $payload = $request->all();
        $status = strtolower((string) ($payload['cpm_result'] ?? $payload['status'] ?? $payload['result'] ?? ''));
        $reference = (string) ($payload['cpm_trans_id'] ?? $payload['transaction_id'] ?? $payload['reference'] ?? '');
        $pending = $request->session()->get('subscription_renewal_pending', []);

        if (empty($pending) && $reference === '') {
            return redirect()->route('admin.settings.module', 'general')->with('error', 'Aucun paiement en attente n’a été détecté.');
        }

        $isSuccessful = in_array($status, ['00', '0', 'success', 'ok', 'paid', 'completed', 'succeeded'], true);
        if (! $isSuccessful) {
            $request->session()->forget('subscription_renewal_pending');

            return redirect()->route('admin.settings.module', 'general')->with('error', 'Le paiement CinetPay n’a pas été validé.');
        }

        $entrepriseId = $pending['entreprise_id'] ?? null;
        $entreprise = $entrepriseId ? Entreprise::find($entrepriseId) : null;

        if (! $entreprise) {
            return redirect()->route('admin.settings.module', 'general')->with('error', 'Entreprise introuvable pour ce renouvellement.');
        }

        $this->applySubscriptionRenewal(
            $entreprise,
            (int) ($pending['duration'] ?? 1),
            'cinetpay',
            (float) ($pending['amount'] ?? 0),
            $reference ?: ($pending['reference'] ?? null)
        );

        $request->session()->forget('subscription_renewal_pending');

        return redirect()->route('admin.settings.module', 'general')->with('success', 'Votre abonnement a été renouvelé avec succès via CinetPay.');
    }

    private function applySubscriptionRenewal(Entreprise $entreprise, int $durationMonths, string $paymentMethod, ?float $amount = null, ?string $reference = null): void
    {
        $settings = $entreprise->settings ?? [];
        $currentExpiry = ! empty($settings['subscription_expires_at'])
            ? Carbon::parse($settings['subscription_expires_at'])
            : now();
        $startDate = $currentExpiry->isFuture() ? $currentExpiry : now();
        $expiresAt = $startDate->copy()->addMonths($durationMonths);

        $settings['subscription_expires_at'] = $expiresAt->toDateString();
        $settings['subscription_payment_method'] = $paymentMethod;
        $settings['subscription_duration_months'] = $durationMonths;
        $settings['subscription_last_renewed_at'] = now()->toDateTimeString();

        if ($amount !== null) {
            $settings['subscription_last_amount'] = round((float) $amount, 2);
        }

        if ($reference) {
            $settings['subscription_last_payment_reference'] = $reference;
        }

        $entreprise->update(['settings' => $settings]);
    }

    private function subscriptionExpiryDate(Entreprise $entreprise, int $durationMonths): Carbon
    {
        $settings = $entreprise->settings ?? [];
        $currentExpiry = ! empty($settings['subscription_expires_at'])
            ? Carbon::parse($settings['subscription_expires_at'])
            : now();
        $startDate = $currentExpiry->isFuture() ? $currentExpiry : now();

        return $startDate->copy()->addMonths($durationMonths);
    }

    private function subscriptionRenewalAmount(int $durationMonths): float
    {
        return match ($durationMonths) {
            1 => 25000,
            3 => 60000,
            6 => 110000,
            12 => 200000,
            default => 25000,
        };
    }

    private function modules(): array
    {
        $modules = [
            [
                'key' => 'general',
                'category' => 'Paramètre général',
                'title' => 'Configuration de l’application',
                'description' => 'Gérez les informations, la devise et les préférences de votre organisation.',
                'icon' => 'bi-gear',
                'route' => 'admin.settings.module',
                'route_parameters' => ['module' => 'general'],
                'route_label' => 'Ouvrir',
            ],
            [
                'key' => 'fiscalite',
                'category' => 'Paramètre fiscal',
                'title' => 'Taxes et TVA',
                'description' => 'Définissez les taux de taxe appliqués à vos devis, factures et ventes.',
                'icon' => 'bi-receipt',
                'route' => 'admin.settings.module',
                'route_parameters' => ['module' => 'fiscalite'],
                'route_label' => 'Ouvrir',
            ],
            [
                'key' => 'email',
                'category' => 'Paramètres de messagerie',
                'title' => 'Configuration des emails',
                'description' => 'Préparez les paramètres utilisés pour les notifications et les envois.',
                'icon' => 'bi-envelope',
                'route' => 'admin.settings.module',
                'route_parameters' => ['module' => 'email'],
                'route_label' => 'Ouvrir',
            ],
            [
                'key' => 'theme',
                'category' => 'Apparence',
                'title' => 'Thème de couleurs',
                'description' => 'Personnalisez les couleurs principales de votre espace Diagoma ERP.',
                'icon' => 'bi-palette',
                'route' => 'admin.settings.module',
                'route_parameters' => ['module' => 'theme'],
                'route_label' => 'Personnaliser',
            ],
            [
                'key' => 'cinetpay',
                'category' => 'Paiements',
                'title' => 'CinetPay',
                'description' => 'Configurez la passerelle de paiement utilisée pour le renouvellement des abonnements.',
                'icon' => 'bi-credit-card',
                'route' => 'admin.settings.module',
                'route_parameters' => ['module' => 'cinetpay'],
                'route_label' => 'Configurer',
            ],
            [
                'key' => 'whatsapp',
                'category' => 'Messagerie',
                'title' => 'WhatsApp',
                'description' => 'Configurez les identifiants WhatsApp Business pour l’envoi de messages et de notifications.',
                'icon' => 'bi-whatsapp',
                'route' => 'admin.settings.module',
                'route_parameters' => ['module' => 'whatsapp'],
                'route_label' => 'Configurer',
            ],
            [
                'key' => 'organisation',
                'category' => 'Organisation',
                'title' => 'Services',
                'description' => 'Gestion des départements et de la structure de l’organisation.',
                'icon' => 'bi-building',
                'route' => 'admin.departments.index',
                'route_label' => 'Ouvrir',
            ],
            [
                'key' => 'designation',
                'category' => 'RH',
                'title' => 'Fonctions',
                'description' => 'Gestion des postes et des fonctions des collaborateurs.',
                'icon' => 'bi-person-badge',
                'route' => 'admin.designations.index',
                'route_label' => 'Ouvrir',
            ],
            [
                'key' => 'roles',
                'category' => 'Sécurité',
                'title' => 'Autorisations des rôles',
                'description' => 'Gérez les rôles et les droits d’accès aux modules de l’application.',
                'icon' => 'bi-shield-lock',
                'route' => 'admin.settings.module',
                'route_parameters' => ['module' => 'roles'],
                'route_label' => 'Ouvrir',
            ],
            [
                'key' => 'users',
                'category' => 'Comptes',
                'title' => 'Utilisateurs',
                'description' => 'Gestion des comptes utilisateurs de l’entreprise.',
                'icon' => 'bi-people',
                'route' => 'admin.users.index',
                'route_label' => 'Ouvrir',
            ],
            [
                'key' => 'backup',
                'category' => 'Sauvegarde',
                'title' => 'Restauration de sauvegarde',
                'description' => 'Centralisez les opérations de sauvegarde et de restauration.',
                'icon' => 'bi-database',
                'route' => 'admin.settings.module',
                'route_parameters' => ['module' => 'backup'],
                'route_label' => 'Ouvrir',
            ],
            [
                'key' => 'landing',
                'category' => 'Public',
                'title' => 'Landing page',
                'description' => 'Personnalisez la page d’accueil publique, le branding et les packs de démonstration.',
                'icon' => 'bi-globe',
                'route' => 'console.landing',
                'route_label' => 'Configurer',
            ],
            [
                'key' => 'modules',
                'category' => 'Modules',
                'title' => 'Modules',
                'description' => 'Accédez aux modules activés dans votre espace Diagoma ERP.',
                'icon' => 'bi-grid-3x3-gap',
                'route' => 'admin.settings.modules',
                'route_label' => 'Ouvrir',
            ],
        ];

        return $modules;
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

    private function defaultModuleRubriques(): array
    {
        return array_fill_keys(array_keys($this->moduleRubriques()), true);
    }
}
