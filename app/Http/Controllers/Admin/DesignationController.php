<?php

namespace App\Http\Controllers\Admin;

use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends AdminController
{
    public function index()
    {
        return $this->page('settings-list', [
            'title' => 'Fonctions',
            'heading' => 'Fonctions / Postes',
            'description' => 'Définissez les fonctions utilisées dans les fiches RH.',
            'items' => Designation::where('entreprise_id', auth()->user()->entreprise_id)->latest()->get(),
            'routePrefix' => 'admin.designations',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string']]);
        Designation::create($data + ['entreprise_id' => auth()->user()->entreprise_id]);
        return back()->with('success', 'Fonction ajoutée.');
    }

    public function update(Request $request, Designation $designation)
    {
        abort_unless($designation->entreprise_id === auth()->user()->entreprise_id, 403);
        $designation->update($request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'is_active' => ['nullable', 'boolean']]));
        return back()->with('success', 'Fonction mise à jour.');
    }

    public function destroy(Designation $designation)
    {
        abort_unless($designation->entreprise_id === auth()->user()->entreprise_id, 403);
        $designation->delete();
        return back()->with('success', 'Fonction supprimée.');
    }
}
