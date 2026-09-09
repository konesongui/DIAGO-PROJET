<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends AdminController
{
    public function index()
    {
        return $this->page('settings-list', [
            'title' => 'Services',
            'heading' => 'Services / Départements',
            'description' => 'Organisez les services de votre entreprise.',
            'items' => Department::where('entreprise_id', auth()->user()->entreprise_id)->latest()->get(),
            'routePrefix' => 'admin.departments',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string']]);
        Department::create($data + ['entreprise_id' => auth()->user()->entreprise_id]);
        return back()->with('success', 'Service ajouté.');
    }

    public function update(Request $request, Department $department)
    {
        abort_unless($department->entreprise_id === auth()->user()->entreprise_id, 403);
        $department->update($request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'is_active' => ['nullable', 'boolean']]));
        return back()->with('success', 'Service mis à jour.');
    }

    public function destroy(Department $department)
    {
        abort_unless($department->entreprise_id === auth()->user()->entreprise_id, 403);
        $department->delete();
        return back()->with('success', 'Service supprimé.');
    }
}
