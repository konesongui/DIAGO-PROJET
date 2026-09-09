<?php

namespace App\Http\Controllers;

use App\Models\LandingSetting;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index()
    {
        $settings = LandingSetting::current();

        return view('landing', array_merge($settings, [
            'demoGranted' => (bool) session('demo_access_granted', false),
            'demoEmail' => session('demo_email'),
        ]));
    }

    public function requestDemo(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $request->session()->put('demo_email', strtolower($validated['email']));
        $request->session()->put('demo_access_granted', true);

        return redirect()->route('demo')->with('success', 'Votre accès démo a été activé. Consultez l’espace de démonstration.');
    }

    public function requestPack(Request $request)
    {
        $validated = $request->validate([
            'pack_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        \App\Models\PackRequest::create($validated);

        return redirect('/landing#packs')->with('success', 'Votre demande de pack a été envoyée. Notre équipe vous répondra rapidement.');
    }

    public function demo(Request $request)
    {
        if (!$request->session()->get('demo_access_granted')) {
            return redirect()->route('landing')->with('error', 'Veuillez renseigner votre email pour obtenir l’accès à la démo.');
        }

        return view('demo', [
            'demoEmail' => $request->session()->get('demo_email'),
            'modules' => [
                ['name' => 'Finance', 'status' => 'Actif', 'description' => 'Suivi caisse, banques et reporting'],
                ['name' => 'Commercial', 'status' => 'Actif', 'description' => 'Devis, stock et facturation'],
                ['name' => 'RH', 'status' => 'Actif', 'description' => 'Paie, congés et présence'],
                ['name' => 'Administration', 'status' => 'Actif', 'description' => 'Documents et procédures internes'],
            ],
        ]);
    }

    public function leaveDemo(Request $request)
    {
        $request->session()->forget(['demo_access_granted', 'demo_email']);

        return redirect()->route('landing')->with('success', 'Votre session de démonstration a été fermée.');
    }
}
