<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminCall;
use App\Models\AdminCorrespondence;
use App\Models\AdminDocument;
use App\Models\AdminMeeting;
use App\Models\AdminVisitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdministrationController extends AdminController
{
    private const MODULES = [
        'visiteurs' => ['title' => 'Gestion des visiteurs', 'description' => 'Suivi des visiteurs à 360°', 'model' => AdminVisitor::class],
        'appels' => ['title' => 'Gestion des appels', 'description' => 'Journal des appels entrants et sortants', 'model' => AdminCall::class],
        'courriers' => ['title' => 'Gestion des courriers', 'description' => 'Enregistrement et suivi des courriers', 'model' => AdminCorrespondence::class],
        'reunions' => ['title' => 'Gestion des réunions', 'description' => 'Planification et comptes-rendus', 'model' => AdminMeeting::class],
        'documents' => ['title' => 'Gestion des documents', 'description' => 'Centralisation des documents administratifs', 'model' => AdminDocument::class],
    ];

    public function index()
    {
        return $this->page('administration', ['title' => 'Administration', 'modules' => self::MODULES]);
    }

    public function module(string $module)
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $this->authorizeModule($module);
        $config = self::MODULES[$module];
        $records = ($config['model'])::latest()->paginate(15)->withQueryString();
        return $this->page('administration-module', [
            'title' => $config['title'],
            'module' => $module,
            'config' => $config,
            'records' => $records,
        ]);
    }

    public function store(Request $request, string $module)
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $this->authorizeModule($module, 'edit');
        $data = $this->validated($request, $module);
        $model = self::MODULES[$module]['model'];
        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('administration/' . $module, 'public');
        }
        unset($data['file']);
        $model::create($data);
        return redirect()->route('admin.administration.module', $module)->with('success', 'Enregistrement créé avec succès.');
    }

    public function update(Request $request, string $module, int $record)
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $this->authorizeModule($module, 'edit');
        $model = self::MODULES[$module]['model'];
        $item = $model::findOrFail($record);
        $data = $this->validated($request, $module, true);
        if ($request->hasFile('file')) {
            if ($item->file_path) {
                Storage::disk('public')->delete($item->file_path);
            }
            $data['file_path'] = $request->file('file')->store('administration/' . $module, 'public');
        }
        unset($data['file']);
        $item->update($data);
        return redirect()->route('admin.administration.module', $module)->with('success', 'Enregistrement mis à jour.');
    }

    public function destroy(string $module, int $record)
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $this->authorizeModule($module, 'delete');
        $model = self::MODULES[$module]['model'];
        $item = $model::findOrFail($record);
        if ($item->file_path) {
            Storage::disk('public')->delete($item->file_path);
        }
        $item->delete();
        return back()->with('success', 'Enregistrement supprimé.');
    }

    private function validated(Request $request, string $module, bool $update = false): array
    {
        $rules = match ($module) {
            'visiteurs' => ['name' => 'required|string|max:255', 'company' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:50', 'email' => 'nullable|email|max:255', 'purpose' => 'nullable|string|max:255', 'host' => 'nullable|string|max:255', 'check_in_at' => 'nullable|date', 'check_out_at' => 'nullable|date|after_or_equal:check_in_at', 'status' => 'required|in:expected,inside,completed,cancelled', 'notes' => 'nullable|string'],
            'appels' => ['contact_name' => 'required|string|max:255', 'phone' => 'nullable|string|max:50', 'subject' => 'nullable|string|max:255', 'direction' => 'required|in:incoming,outgoing', 'call_at' => 'nullable|date', 'duration' => 'nullable|integer|min:0', 'status' => 'required|in:planned,completed,missed', 'notes' => 'nullable|string'],
            'courriers' => ['reference' => 'required|string|max:100', 'type' => 'required|in:incoming,outgoing', 'subject' => 'required|string|max:255', 'sender' => 'nullable|string|max:255', 'recipient' => 'nullable|string|max:255', 'received_at' => 'nullable|date', 'status' => 'required|in:received,in_progress,processed,archived', 'notes' => 'nullable|string', 'file' => 'nullable|file|max:10240'],
            'reunions' => ['title' => 'required|string|max:255', 'location' => 'nullable|string|max:255', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'organizer' => 'nullable|string|max:255', 'attendees' => 'nullable|string', 'status' => 'required|in:planned,held,cancelled', 'minutes' => 'nullable|string'],
            'documents' => ['title' => 'required|string|max:255', 'category' => 'nullable|string|max:255', 'document_date' => 'nullable|date', 'status' => 'required|in:active,archived', 'description' => 'nullable|string', 'file' => ($update ? 'nullable' : 'nullable') . '|file|max:10240'],
        };
        return $request->validate($rules);
    }
}
