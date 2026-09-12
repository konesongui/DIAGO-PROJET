<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminCall;
use App\Models\AdminCorrespondence;
use App\Models\AdminDocument;
use App\Models\AdminMeeting;
use App\Models\AdminVisitor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AdministrationController extends AdminController
{
    /** Sous-modules de l'espace Administration : intitulé, pastille et modèle. */
    private const MODULES = [
        'visiteurs' => [
            'title' => 'Registre des visiteurs',
            'description' => 'Qui est attendu, qui est sur place, qui est reparti.',
            'icon' => 'bi-person-badge',
            'color' => 'blue',
            'model' => AdminVisitor::class,
            'route' => 'admin.administration.visitors',
        ],
        'appels' => [
            'title' => 'Journal des appels',
            'description' => 'Appels entrants et sortants, manqués à rappeler.',
            'icon' => 'bi-telephone',
            'color' => 'green',
            'model' => AdminCall::class,
            'route' => 'admin.administration.calls',
        ],
        'courriers' => [
            'title' => 'Courriers',
            'description' => 'Courriers arrivés et partis, avec leur pièce jointe.',
            'icon' => 'bi-envelope-paper',
            'color' => 'purple',
            'model' => AdminCorrespondence::class,
            'route' => 'admin.administration.correspondences',
        ],
        'reunions' => [
            'title' => 'Réunions',
            'description' => 'Convocations, participants et comptes-rendus.',
            'icon' => 'bi-calendar-event',
            'color' => 'orange',
            'model' => AdminMeeting::class,
            'route' => 'admin.administration.meetings',
        ],
        'documents' => [
            'title' => 'Documents administratifs',
            'description' => 'Statuts, contrats et registres classés.',
            'icon' => 'bi-folder2-open',
            'color' => 'teal',
            'model' => AdminDocument::class,
            'route' => 'admin.administration.documents',
        ],
    ];

    public function index()
    {
        $modules = self::MODULES;

        $modules['visiteurs']['count'] = AdminVisitor::count();
        $modules['visiteurs']['pending'] = AdminVisitor::where('status', 'inside')->count();
        $modules['visiteurs']['pendingLabel'] = 'sur place';

        $modules['appels']['count'] = AdminCall::count();
        $modules['appels']['pending'] = AdminCall::where('status', 'missed')->count();
        $modules['appels']['pendingLabel'] = 'manqué(s)';

        $modules['courriers']['count'] = AdminCorrespondence::count();
        $modules['courriers']['pending'] = AdminCorrespondence::whereIn('status', ['received', 'in_progress'])->count();
        $modules['courriers']['pendingLabel'] = 'à traiter';

        $modules['reunions']['count'] = AdminMeeting::count();
        $modules['reunions']['pending'] = AdminMeeting::where('status', 'planned')->where('starts_at', '>=', now())->count();
        $modules['reunions']['pendingLabel'] = 'à venir';

        $modules['documents']['count'] = AdminDocument::count();
        $modules['documents']['pending'] = AdminDocument::where('status', 'active')->count();
        $modules['documents']['pendingLabel'] = 'actif(s)';

        return $this->page('administration', [
            'title' => 'Administration',
            'subtitle' => 'Visiteurs, appels, courriers, réunions et documents de l’entreprise.',
            'modules' => $modules,
        ]);
    }

    /**
     * Registre des visiteurs.
     *
     * La période porte sur l'arrivée (à défaut sur l'enregistrement) ; les
     * visites attendues restent affichées quelle que soit la période, sinon
     * l'accueil perdrait de vue les visiteurs annoncés.
     */
    public function visitors(Request $request)
    {
        [$from, $to, $periodError] = $this->period($request);

        $visits = AdminVisitor::query()
            ->where(function ($query) use ($from, $to) {
                $query->whereRaw('COALESCE(check_in_at, created_at) BETWEEN ? AND ?', [$from, $to])
                    ->orWhere('status', 'expected');
            })
            ->orderByRaw('COALESCE(check_in_at, created_at) DESC')
            ->get();

        return $this->page('administration-visitors', [
            'title' => 'Registre des visiteurs',
            'subtitle' => 'Visites attendues, visiteurs sur place et historique des passages.',
            'visits' => $visits,
            'from' => $from,
            'to' => $to,
            'periodError' => $periodError,
            'inside' => AdminVisitor::where('status', 'inside')->count(),
            'expected' => AdminVisitor::where('status', 'expected')->count(),
            'arrivedToday' => AdminVisitor::whereDate('check_in_at', now()->toDateString())->count(),
        ]);
    }

    public function storeVisitor(Request $request)
    {
        $visitor = new AdminVisitor($this->validatedVisitor($request));
        $visitor->syncStatus()->save();

        return redirect()->route('admin.administration.visitors', $request->only(['from', 'to']))
            ->with('success', 'Visiteur « ' . $visitor->name . ' » enregistré.');
    }

    public function updateVisitor(Request $request, AdminVisitor $visitor)
    {
        $visitor->fill($this->validatedVisitor($request));
        $visitor->syncStatus()->save();

        return redirect()->route('admin.administration.visitors', $request->only(['from', 'to']))
            ->with('success', 'Visite de « ' . $visitor->name . ' » mise à jour.');
    }

    /** Pointage de l'accueil : arrivée, départ ou annulation d'une visite. */
    public function visitorPresence(Request $request, AdminVisitor $visitor)
    {
        $action = $request->input('action');
        $message = null;

        if ($action === 'arrivee') {
            if ($visitor->status === 'cancelled') {
                return back()->with('error', 'Cette visite est annulée : rouvrez-la en modifiant la fiche.');
            }
            if ($visitor->check_in_at && ! $visitor->check_in_at->isFuture()) {
                return back()->with('error', 'L’arrivée de ' . $visitor->name . ' est déjà enregistrée.');
            }
            $visitor->check_in_at = now();
            $message = 'Arrivée de ' . $visitor->name . ' enregistrée à ' . now()->format('H:i') . '.';
        } elseif ($action === 'depart') {
            if (! $visitor->check_in_at || $visitor->check_in_at->isFuture()) {
                return back()->with('error', 'Enregistrez d’abord l’arrivée de ' . $visitor->name . '.');
            }
            if ($visitor->check_out_at) {
                return back()->with('error', 'Le départ de ' . $visitor->name . ' est déjà enregistré.');
            }
            $visitor->check_out_at = now();
            $message = 'Départ de ' . $visitor->name . ' enregistré à ' . now()->format('H:i') . '.';
        } elseif ($action === 'annuler') {
            if ($visitor->check_in_at && ! $visitor->check_in_at->isFuture()) {
                return back()->with('error', 'La visite de ' . $visitor->name . ' a déjà eu lieu : elle ne s’annule pas.');
            }
            $visitor->status = 'cancelled';
            $visitor->save();

            return back()->with('success', 'Visite de ' . $visitor->name . ' annulée.');
        } else {
            abort(404);
        }

        $visitor->syncStatus()->save();

        return back()->with('success', $message);
    }

    public function destroyVisitor(AdminVisitor $visitor)
    {
        $name = $visitor->name;
        $visitor->delete();

        return back()->with('success', 'Visite de ' . $name . ' supprimée du registre.');
    }

    private function validatedVisitor(Request $request): array
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'purpose' => 'nullable|string|max:255',
            'host' => 'nullable|string|max:255',
            'check_in_at' => 'nullable|date',
            'check_out_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ], [], [
            'name' => 'nom du visiteur',
            'check_in_at' => 'heure d’arrivée',
            'check_out_at' => 'heure de départ',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (! $request->filled('check_out_at')) {
                return;
            }
            if (! $request->filled('check_in_at')) {
                $validator->errors()->add('check_out_at', 'Indiquez l’heure d’arrivée : un départ ne s’enregistre pas sans arrivée.');

                return;
            }
            if (strtotime($request->input('check_out_at')) <= strtotime($request->input('check_in_at'))) {
                $validator->errors()->add('check_out_at', 'Le départ doit suivre l’arrivée.');
            }
        });

        return $validator->validate();
    }

    /** Période demandée, repliée sur le mois en cours si elle n'a pas de sens. */
    private function period(Request $request): array
    {
        try {
            $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
            $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $from = $to = null;
        }

        if (! $from || ! $to || $from->gt($to)) {
            return [now()->startOfMonth(), now()->endOfDay(), 'La période choisie est invalide : la date de début doit précéder la date de fin. Affichage du mois en cours.'];
        }

        return [$from, $to, null];
    }

    /**
     * Journal des appels.
     *
     * Comme pour les visites, les appels encore à passer restent affichés quelle
     * que soit la période : ce sont eux qu'il ne faut pas oublier.
     */
    public function calls(Request $request)
    {
        [$from, $to, $periodError] = $this->period($request);

        $calls = AdminCall::query()
            ->where(function ($query) use ($from, $to) {
                $query->whereRaw('COALESCE(call_at, created_at) BETWEEN ? AND ?', [$from, $to])
                    ->orWhere('status', 'planned');
            })
            ->orderByRaw('COALESCE(call_at, created_at) DESC')
            ->get();

        return $this->page('administration-calls', [
            'title' => 'Journal des appels',
            'subtitle' => 'Appels entrants et sortants, appels manqués à rappeler et appels à passer.',
            'calls' => $calls,
            'from' => $from,
            'to' => $to,
            'periodError' => $periodError,
            'missed' => AdminCall::where('status', 'missed')->count(),
            'planned' => AdminCall::where('status', 'planned')->count(),
            'overdue' => AdminCall::where('status', 'planned')->whereNotNull('call_at')->where('call_at', '<', now())->count(),
        ]);
    }

    public function storeCall(Request $request)
    {
        $call = AdminCall::create($this->validatedCall($request));

        return redirect()->route('admin.administration.calls', $request->only(['from', 'to']))
            ->with('success', 'Appel avec « ' . $call->contact_name . ' » enregistré.');
    }

    public function updateCall(Request $request, AdminCall $call)
    {
        $call->update($this->validatedCall($request));

        return redirect()->route('admin.administration.calls', $request->only(['from', 'to']))
            ->with('success', 'Appel avec « ' . $call->contact_name . ' » mis à jour.');
    }

    /** Suite donnée à un appel : abouti (rappel effectué) ou manqué. */
    public function callStatus(Request $request, AdminCall $call)
    {
        $status = $request->input('status');
        abort_unless(in_array($status, ['completed', 'missed'], true), 404);

        if ($call->status === $status) {
            return back()->with('error', 'Cet appel est déjà noté « ' . $call->stateLabel() . ' ».');
        }

        $call->status = $status;
        if ($status === 'completed') {
            // Un appel noté abouti a forcément eu lieu : une heure à venir n'a plus de sens.
            if (! $call->call_at || $call->call_at->isFuture()) {
                $call->call_at = now();
            }
        } else {
            $call->duration = null;
        }
        $call->save();

        return back()->with('success', 'Appel avec ' . $call->contact_name . ' noté « ' . $call->stateLabel() . ' ».');
    }

    public function destroyCall(AdminCall $call)
    {
        $name = $call->contact_name;
        $call->delete();

        return back()->with('success', 'Appel avec ' . $name . ' retiré du journal.');
    }

    private function validatedCall(Request $request): array
    {
        $data = $request->validate([
            'contact_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'subject' => 'nullable|string|max:255',
            'direction' => 'required|in:incoming,outgoing',
            'call_at' => 'required|date',
            'duration' => 'nullable|integer|min:0|max:1440',
            'status' => 'required|in:planned,completed,missed',
            'notes' => 'nullable|string|max:2000',
        ], [], [
            'contact_name' => 'nom du correspondant',
            'call_at' => 'date et heure',
            'duration' => 'durée',
        ]);

        // Un appel manqué ou encore à passer ne porte pas de durée.
        if ($data['status'] !== 'completed') {
            $data['duration'] = null;
        }

        return $data;
    }

    /**
     * Registre des courriers.
     *
     * Un courrier non traité reste affiché hors période : c'est le suivi qui
     * compte, pas la date d'arrivée.
     */
    public function correspondences(Request $request)
    {
        [$from, $to, $periodError] = $this->period($request);

        $mails = AdminCorrespondence::query()
            ->where(function ($query) use ($from, $to) {
                $query->whereRaw('COALESCE(received_at, created_at) BETWEEN ? AND ?', [$from, $to])
                    ->orWhereIn('status', ['received', 'in_progress']);
            })
            ->orderByRaw('COALESCE(received_at, created_at) DESC')
            ->get();

        return $this->page('administration-correspondences', [
            'title' => 'Courriers',
            'subtitle' => 'Courriers arrivés et partis, leur suivi et leur pièce jointe numérisée.',
            'mails' => $mails,
            'from' => $from,
            'to' => $to,
            'periodError' => $periodError,
            'toProcess' => AdminCorrespondence::where('status', 'received')->count(),
            'inProgress' => AdminCorrespondence::where('status', 'in_progress')->count(),
        ]);
    }

    public function storeCorrespondence(Request $request)
    {
        $data = $this->validatedCorrespondence($request);
        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('administration/courriers', 'public');
        }
        $mail = AdminCorrespondence::create($data);

        return redirect()->route('admin.administration.correspondences', $request->only(['from', 'to']))
            ->with('success', 'Courrier ' . $mail->reference . ' enregistré.');
    }

    public function updateCorrespondence(Request $request, AdminCorrespondence $correspondence)
    {
        $data = $this->validatedCorrespondence($request, $correspondence);

        if ($request->hasFile('file')) {
            $this->deleteFile($correspondence->file_path);
            $data['file_path'] = $request->file('file')->store('administration/courriers', 'public');
        } elseif ($request->boolean('remove_file')) {
            $this->deleteFile($correspondence->file_path);
            $data['file_path'] = null;
        }

        $correspondence->update($data);

        return redirect()->route('admin.administration.correspondences', $request->only(['from', 'to']))
            ->with('success', 'Courrier ' . $correspondence->reference . ' mis à jour.');
    }

    /** Avancement du traitement d'un courrier. */
    public function correspondenceStatus(Request $request, AdminCorrespondence $correspondence)
    {
        $status = $request->input('status');
        abort_unless(array_key_exists($status, AdminCorrespondence::STATES), 404);

        if ($correspondence->status === $status) {
            return back()->with('error', 'Le courrier ' . $correspondence->reference . ' est déjà noté « ' . $correspondence->stateLabel() . ' ».');
        }

        $correspondence->update(['status' => $status]);

        return back()->with('success', 'Courrier ' . $correspondence->reference . ' noté « ' . $correspondence->stateLabel() . ' ».');
    }

    public function destroyCorrespondence(AdminCorrespondence $correspondence)
    {
        $reference = $correspondence->reference;
        $this->deleteFile($correspondence->file_path);
        $correspondence->delete();

        return back()->with('success', 'Courrier ' . $reference . ' supprimé du registre.');
    }

    private function validatedCorrespondence(Request $request, ?AdminCorrespondence $mail = null): array
    {
        $validator = validator($request->all(), [
            'reference' => 'required|string|max:100',
            'type' => 'required|in:incoming,outgoing',
            'subject' => 'required|string|max:255',
            'sender' => 'required_if:type,incoming|nullable|string|max:255',
            'recipient' => 'required_if:type,outgoing|nullable|string|max:255',
            'received_at' => 'required|date',
            'status' => 'required|in:received,in_progress,processed,archived',
            'notes' => 'nullable|string|max:2000',
            'file' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ], [
            'sender.required_if' => 'Indiquez l’expéditeur du courrier arrivé.',
            'recipient.required_if' => 'Indiquez le destinataire du courrier au départ.',
            'file.mimes' => 'La pièce jointe doit être un PDF, une image ou un document bureautique.',
        ], [
            'reference' => 'référence',
            'subject' => 'objet',
            'received_at' => 'date',
            'file' => 'pièce jointe',
        ]);

        $validator->after(function ($validator) use ($request, $mail) {
            $reference = trim((string) $request->input('reference'));
            if ($reference === '') {
                return;
            }
            // Deux courriers portant la même référence rendent le registre illisible.
            $exists = AdminCorrespondence::whereRaw('LOWER(reference) = ?', [mb_strtolower($reference)])
                ->when($mail, fn ($query) => $query->where('id', '!=', $mail->id))
                ->exists();
            if ($exists) {
                $validator->errors()->add('reference', 'La référence « ' . $reference . ' » est déjà utilisée par un autre courrier.');
            }
        });

        $data = $validator->validate();
        unset($data['file']);

        return $data;
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Réunions.
     *
     * Restent affichées hors période : les réunions annoncées et les réunions
     * tenues dont le compte-rendu manque encore, qui sont l'une et l'autre du
     * travail à faire.
     */
    public function meetings(Request $request)
    {
        [$from, $to, $periodError] = $this->period($request);

        $meetings = AdminMeeting::query()
            ->where(function ($query) use ($from, $to) {
                $query->whereRaw('COALESCE(starts_at, created_at) BETWEEN ? AND ?', [$from, $to])
                    ->orWhere('status', 'planned')
                    ->orWhere(function ($pending) {
                        $pending->where('status', 'held')->whereRaw("COALESCE(minutes, '') = ''");
                    });
            })
            ->orderByRaw('COALESCE(starts_at, created_at) DESC')
            ->get();

        return $this->page('administration-meetings', [
            'title' => 'Réunions',
            'subtitle' => 'Réunions annoncées, réunions tenues et comptes-rendus.',
            'meetings' => $meetings,
            'from' => $from,
            'to' => $to,
            'periodError' => $periodError,
            'upcoming' => AdminMeeting::where('status', 'planned')->where('starts_at', '>=', now())->count(),
            'missingMinutes' => AdminMeeting::where('status', 'held')->whereRaw("COALESCE(minutes, '') = ''")->count(),
        ]);
    }

    public function storeMeeting(Request $request)
    {
        $meeting = AdminMeeting::create($this->validatedMeeting($request));

        return redirect()->route('admin.administration.meetings', $request->only(['from', 'to']))
            ->with('success', 'Réunion « ' . $meeting->title . ' » enregistrée.');
    }

    public function updateMeeting(Request $request, AdminMeeting $meeting)
    {
        $meeting->update($this->validatedMeeting($request));

        return redirect()->route('admin.administration.meetings', $request->only(['from', 'to']))
            ->with('success', 'Réunion « ' . $meeting->title . ' » mise à jour.');
    }

    /** Suite donnée à une réunion annoncée : tenue ou annulée. */
    public function meetingStatus(Request $request, AdminMeeting $meeting)
    {
        $status = $request->input('status');
        abort_unless(in_array($status, ['planned', 'held', 'cancelled'], true), 404);

        if ($meeting->status === $status) {
            return back()->with('error', 'Cette réunion est déjà notée « ' . $meeting->stateLabel() . ' ».');
        }

        $meeting->update(['status' => $status]);

        return back()->with('success', 'Réunion « ' . $meeting->title . ' » notée « ' . $meeting->stateLabel() . ' »'
            . ($meeting->needsMinutes() ? ' : le compte-rendu reste à rédiger.' : '.'));
    }

    /** Compte-rendu : la réunion passe d'office à « tenue » dès qu'il est rédigé. */
    public function meetingMinutes(Request $request, AdminMeeting $meeting)
    {
        $data = $request->validate([
            'minutes' => 'nullable|string|max:20000',
        ], [], ['minutes' => 'compte-rendu']);

        $meeting->minutes = $data['minutes'] ?? null;
        if ($meeting->hasMinutes() && $meeting->status === 'planned') {
            $meeting->status = 'held';
        }
        $meeting->save();

        return back()->with('success', $meeting->hasMinutes()
            ? 'Compte-rendu de « ' . $meeting->title . ' » enregistré.'
            : 'Compte-rendu de « ' . $meeting->title . ' » effacé.');
    }

    public function destroyMeeting(AdminMeeting $meeting)
    {
        $title = $meeting->title;
        $meeting->delete();

        return back()->with('success', 'Réunion « ' . $title . ' » supprimée.');
    }

    private function validatedMeeting(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'organizer' => 'nullable|string|max:255',
            'attendees' => 'nullable|string|max:2000',
            'status' => 'required|in:planned,held,cancelled',
        ], [
            'ends_at.after' => 'La fin de la réunion doit suivre son début.',
        ], [
            'title' => 'intitulé',
            'starts_at' => 'début',
            'ends_at' => 'fin',
            'organizer' => 'organisateur',
        ]);
    }

    /**
     * Documents administratifs.
     *
     * Pas de période ici : une bibliothèque de documents se consulte par
     * catégorie et par recherche, pas par mois.
     */
    public function documents(Request $request)
    {
        $documents = AdminDocument::query()
            // Les documents courants d'abord, les archivés en fin de liste.
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByRaw('COALESCE(document_date, created_at::date) DESC')
            ->get();

        return $this->page('administration-documents', [
            'title' => 'Documents administratifs',
            'subtitle' => 'Statuts, contrats et registres légaux classés par catégorie.',
            'documents' => $documents,
            'categories' => $documents->pluck('category')->filter()->unique()->sort()->values(),
        ]);
    }

    public function storeDocument(Request $request)
    {
        $data = $this->validatedDocument($request);
        $data['file_path'] = $request->file('file')->store('administration/documents', 'public');
        $document = AdminDocument::create($data);

        return redirect()->route('admin.administration.documents')
            ->with('success', 'Document « ' . $document->title . ' » classé.');
    }

    public function updateDocument(Request $request, AdminDocument $document)
    {
        $data = $this->validatedDocument($request, $document);

        if ($request->hasFile('file')) {
            $this->deleteFile($document->file_path);
            $data['file_path'] = $request->file('file')->store('administration/documents', 'public');
        }

        $document->update($data);

        return redirect()->route('admin.administration.documents')
            ->with('success', 'Document « ' . $document->title . ' » mis à jour.');
    }

    public function documentStatus(Request $request, AdminDocument $document)
    {
        $status = $request->input('status');
        abort_unless(array_key_exists($status, AdminDocument::STATES), 404);

        if ($document->status === $status) {
            return back()->with('error', 'Ce document est déjà noté « ' . $document->stateLabel() . ' ».');
        }

        $document->update(['status' => $status]);

        return back()->with('success', 'Document « ' . $document->title . ' » noté « ' . $document->stateLabel() . ' ».');
    }

    public function destroyDocument(AdminDocument $document)
    {
        $title = $document->title;
        $this->deleteFile($document->file_path);
        $document->delete();

        return back()->with('success', 'Document « ' . $title . ' » supprimé, fichier compris.');
    }

    private function validatedDocument(Request $request, ?AdminDocument $document = null): array
    {
        // Un document sans fichier n'a pas d'objet : le fichier est exigé au classement.
        $fileRule = $document ? 'nullable' : 'required';

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'document_date' => 'required|date',
            'status' => 'required|in:active,archived',
            'description' => 'nullable|string|max:2000',
            'file' => $fileRule . '|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ], [
            'file.required' => 'Choisissez le fichier à classer.',
            'file.mimes' => 'Le document doit être un PDF, une image ou un document bureautique.',
        ], [
            'title' => 'intitulé',
            'category' => 'catégorie',
            'document_date' => 'date du document',
            'file' => 'fichier',
        ]);

        unset($data['file']);
        $data['category'] = $data['category'] ? trim($data['category']) : null;

        return $data;
    }

    /** Ancienne adresse générique : chaque rubrique a maintenant son écran. */
    public function module(string $module)
    {
        abort_unless(isset(self::MODULES[$module]), 404);

        return redirect()->route(self::MODULES[$module]['route']);
    }
}
