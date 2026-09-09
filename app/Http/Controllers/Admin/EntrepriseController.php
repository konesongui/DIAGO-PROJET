<?php

namespace App\Http\Controllers\Admin;

use App\Models\Entreprise;
use Illuminate\Http\Request;

class EntrepriseController extends AdminController
{
    public function index()
    {
        return $this->page('entreprises.index', [
            'title' => 'Entreprises',
            'entreprises' => Entreprise::latest()->get(),
        ]);
    }

    public function create()
    {
        return $this->page('entreprises.form', [
            'title' => 'Créer une entreprise',
            'entreprise' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:entreprises,slug'],
            'database_name' => ['nullable', 'string', 'max:255', 'unique:entreprises,database_name'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Entreprise::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'database_name' => $validated['database_name'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'settings' => ['locale' => 'fr'],
        ]);

        return redirect()->route('admin.entreprises.index')->with('success', 'Entreprise créée.');
    }

    public function edit(Entreprise $entreprise)
    {
        return $this->page('entreprises.form', [
            'title' => 'Modifier une entreprise',
            'entreprise' => $entreprise,
        ]);
    }

    public function update(Request $request, Entreprise $entreprise)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:entreprises,slug,' . $entreprise->id],
            'database_name' => ['nullable', 'string', 'max:255', 'unique:entreprises,database_name,' . $entreprise->id],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $entreprise->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'database_name' => $validated['database_name'] ?? $entreprise->database_name,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.entreprises.index')->with('success', 'Entreprise mise à jour.');
    }

    public function destroy(Entreprise $entreprise)
    {
        $entreprise->delete();

        return back()->with('success', 'Entreprise supprimée.');
    }
}
