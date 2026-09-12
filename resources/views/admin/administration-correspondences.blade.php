@extends('admin.layout')

@section('content')
@php
    $states = [
        'received' => ['À traiter', 'warning', 'bi-inbox', 'À traiter'],
        'in_progress' => ['En traitement', 'neutral', 'bi-hourglass-split', 'En traitement'],
        'processed' => ['Traité', 'success', 'bi-check-circle', 'Traités'],
        'archived' => ['Archivé', 'neutral', 'bi-archive', 'Archivés'],
    ];
    $byState = $mails->groupBy('status');
    $inPeriod = $mails->filter(fn ($mail) => $mail->received_at && $mail->received_at->between($from, $to));

    $stats = [
        ['Courriers à traiter', $toProcess, 'bi-inbox', 'orange', 'reçus, pas encore ouverts'],
        ['En traitement', $inProgress, 'bi-hourglass-split', 'blue', 'en cours chez un service'],
        ['Traités sur la période', $inPeriod->whereIn('status', ['processed', 'archived'])->count(), 'bi-check-circle', 'green', 'traités ou archivés'],
        ['Courriers de la période', $inPeriod->count(), 'bi-envelope-paper', 'purple', $inPeriod->where('type', 'incoming')->count() . ' arrivée(s) · ' . $inPeriod->where('type', 'outgoing')->count() . ' départ(s)'],
    ];

    $formHasErrors = $errors->any() && old('_mail_form');
    $editedId = old('mail_id');
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.administration')" back-label="Administration">
        <x-slot:actions>
            <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#mailModal"><i class="bi bi-plus-lg"></i>Enregistrer un courrier</button>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($periodError)<div class="alert alert-warning">{{ $periodError }}</div>@endif
    @if($errors->any() && ! $formHasErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-card mb-5">
        <form method="GET" action="{{ route('admin.administration.correspondences') }}" class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="mailFrom">Du</label>
                <input id="mailFrom" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="mailTo">Au</label>
                <input id="mailTo" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
            </div>
            <div class="col-12 col-lg-6 d-flex gap-2">
                <button type="submit" class="dg-btn dg-btn--primary"><i class="bi bi-funnel"></i>Afficher</button>
                <a href="{{ route('admin.administration.correspondences') }}" class="dg-btn dg-btn--outline"><i class="bi bi-arrow-counterclockwise"></i>Mois en cours</a>
            </div>
        </form>
    </div>

    <div class="dg-kpi-grid">
        @foreach($stats as [$label, $value, $icon, $color, $hint])
            <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
        @endforeach
    </div>

    <div class="dg-card dg-card--table">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-envelope-paper"></i></span>Registre des courriers</h2>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="dg-card__meta">{{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span>
                @if($mails->isNotEmpty())
                    <label class="dg-search" style="max-width:240px">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="mailSearch" type="search" placeholder="Référence, objet, contact…" aria-label="Rechercher un courrier">
                    </label>
                @endif
            </div>
        </div>

        @if($mails->isNotEmpty())
            <div class="dg-tabs mb-4" role="group" aria-label="Filtrer par état">
                <button type="button" class="dg-tab is-active" data-state-filter="" aria-pressed="true">Tous ({{ $mails->count() }})</button>
                @foreach($states as $value => [, , , $tabLabel])
                    @if($byState->get($value, collect())->isNotEmpty())
                        <button type="button" class="dg-tab" data-state-filter="{{ $value }}" aria-pressed="false">{{ $tabLabel }} ({{ $byState->get($value)->count() }})</button>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle mb-0 mails-table no-export" id="mailsTable">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Sens</th>
                        <th>Objet</th>
                        <th>Correspondant</th>
                        <th>Pièce jointe</th>
                        <th>État</th>
                        <th class="text-end"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($mails as $mail)
                    @php [$stateLabel, $stateTone, $stateIcon] = $states[$mail->status] ?? $states['received']; @endphp
                    <tr data-state="{{ $mail->status }}">
                        <td>
                            <span class="d-block fw-semibold">{{ $mail->reference }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $mail->received_at?->format('d/m/Y') ?? 'Date non renseignée' }}</span>
                        </td>
                        <td>
                            <span class="dg-badge dg-badge--neutral"><i class="bi {{ $mail->type === 'incoming' ? 'bi-box-arrow-in-down' : 'bi-box-arrow-up' }}"></i>{{ $mail->typeLabel() }}</span>
                        </td>
                        <td><span class="d-block" style="max-width:220px">{{ $mail->subject }}</span></td>
                        <td>
                            <span class="d-block">{{ $mail->counterpart() ?: '—' }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $mail->type === 'outgoing' ? 'destinataire' : 'expéditeur' }}</span>
                        </td>
                        <td>
                            @if($mail->file_path)
                                <a class="dg-link-btn" target="_blank" rel="noopener" href="{{ Storage::disk('public')->url($mail->file_path) }}"><i class="bi bi-paperclip"></i>Ouvrir</a>
                            @else
                                <span class="dg-muted">Aucune</span>
                            @endif
                        </td>
                        <td><span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span></td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                @if($mail->isOpen())
                                    <form method="POST" action="{{ route('admin.administration.correspondences.status', $mail) }}">
                                        @csrf<input type="hidden" name="status" value="processed">
                                        <button class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-check2"></i>Traité</button>
                                    </form>
                                @endif
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour le courrier {{ $mail->reference }}"></button>
                                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mailModal"
                                            data-mail="{{ json_encode([
                                                'id' => $mail->id,
                                                'reference' => $mail->reference,
                                                'type' => $mail->type,
                                                'subject' => $mail->subject,
                                                'sender' => $mail->sender,
                                                'recipient' => $mail->recipient,
                                                'received_at' => optional($mail->received_at)->format('Y-m-d'),
                                                'status' => $mail->status,
                                                'notes' => $mail->notes,
                                                'file' => $mail->file_path ? basename($mail->file_path) : null,
                                                'action' => route('admin.administration.correspondences.update', $mail),
                                            ]) }}"><i class="bi bi-pencil"></i>Modifier</button></li>
                                        @if($mail->status !== 'in_progress' && $mail->status !== 'archived')
                                            <li>
                                                <form method="POST" action="{{ route('admin.administration.correspondences.status', $mail) }}">
                                                    @csrf<input type="hidden" name="status" value="in_progress">
                                                    <button class="dropdown-item"><i class="bi bi-hourglass-split"></i>Mettre en traitement</button>
                                                </form>
                                            </li>
                                        @endif
                                        @if($mail->status !== 'archived')
                                            <li>
                                                <form method="POST" action="{{ route('admin.administration.correspondences.status', $mail) }}">
                                                    @csrf<input type="hidden" name="status" value="archived">
                                                    <button class="dropdown-item"><i class="bi bi-archive"></i>Archiver</button>
                                                </form>
                                            </li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('admin.administration.correspondences.destroy', $mail) }}" onsubmit="return confirm('Supprimer le courrier {{ addslashes($mail->reference) }} et sa pièce jointe ?')">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-5">
                            <div class="dg-chart-empty" style="min-height:200px">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-envelope-paper"></i></span>
                                <div><strong>Aucun courrier sur la période</strong>Enregistrez les courriers arrivés et partis avec leur référence : les courriers non traités restent affichés jusqu’à leur clôture.</div>
                                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#mailModal"><i class="bi bi-plus-lg"></i>Enregistrer un courrier</button>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="dg-chart-empty mt-4 d-none" id="mailsNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-purple"><i class="bi bi-search"></i></span>
            <div><strong>Aucun courrier ne correspond</strong>Modifiez la recherche ou l’onglet.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>La référence identifie le courrier dans le registre : deux courriers ne peuvent pas porter la même. Les courriers à traiter ou en traitement restent affichés même hors de la période choisie.</p>
    </div>

    {{-- Enregistrement et modification d'un courrier. --}}
    <div class="modal fade" id="mailModal" tabindex="-1" aria-labelledby="mailModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg dg-tone-purple">
            <div class="modal-content">
                <form method="POST" id="mailForm" enctype="multipart/form-data"
                    action="{{ $editedId ? route('admin.administration.correspondences.update', $editedId) : route('admin.administration.correspondences.store') }}"
                    data-store-action="{{ route('admin.administration.correspondences.store') }}">
                    @csrf
                    <input type="hidden" name="_mail_form" value="1">
                    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                    <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                    <input type="hidden" name="_method" value="PUT" id="mailMethod" @disabled(! $editedId)>
                    <input type="hidden" name="mail_id" value="{{ $editedId }}" id="mailId" @disabled(! $editedId)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="mailModalTitle"><i class="bi bi-envelope-paper me-2"></i><span>{{ $editedId ? 'Modifier le courrier' : 'Enregistrer un courrier' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if($formHasErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="mailReference">Référence <span class="text-danger">*</span></label>
                                <input id="mailReference" name="reference" class="form-control @error('reference') is-invalid @enderror" required maxlength="100" value="{{ old('reference') }}" placeholder="Ex. CR-2026-041">
                                @error('reference')<span class="dg-field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mailType">Sens <span class="text-danger">*</span></label>
                                <select id="mailType" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                    @foreach(\App\Models\AdminCorrespondence::TYPES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', 'incoming') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="mailSubject">Objet <span class="text-danger">*</span></label>
                                <input id="mailSubject" name="subject" class="form-control @error('subject') is-invalid @enderror" required maxlength="255" value="{{ old('subject') }}">
                            </div>
                            <div class="col-md-6" data-mail-field="sender">
                                <label class="form-label" for="mailSender">Expéditeur <span class="text-danger">*</span></label>
                                <input id="mailSender" name="sender" class="form-control @error('sender') is-invalid @enderror" maxlength="255" value="{{ old('sender') }}">
                                @error('sender')<span class="dg-field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6 d-none" data-mail-field="recipient">
                                <label class="form-label" for="mailRecipient">Destinataire <span class="text-danger">*</span></label>
                                <input id="mailRecipient" name="recipient" class="form-control @error('recipient') is-invalid @enderror" maxlength="255" value="{{ old('recipient') }}">
                                @error('recipient')<span class="dg-field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mailDate"><span data-mail-date-label>Date de réception</span> <span class="text-danger">*</span></label>
                                <input id="mailDate" name="received_at" type="date" class="form-control @error('received_at') is-invalid @enderror" required value="{{ old('received_at', now()->toDateString()) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mailStatus">État <span class="text-danger">*</span></label>
                                <select id="mailStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    @foreach(\App\Models\AdminCorrespondence::STATES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', 'received') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="mailFile">Pièce jointe numérisée</label>
                                <input id="mailFile" name="file" type="file" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                                <span class="form-text">PDF, image ou document bureautique, 10 Mo au plus.</span>
                                @error('file')<span class="dg-field-error">{{ $message }}</span>@enderror
                                <div class="mt-2 d-none" id="mailCurrentFile">
                                    <span class="dg-muted" style="font-size:12.5px"><i class="bi bi-paperclip me-1"></i>Pièce jointe actuelle : <strong data-mail-file-name></strong> — choisir un fichier la remplace.</span>
                                    <label class="form-check d-flex align-items-center gap-2 mt-1">
                                        <input class="form-check-input" type="checkbox" name="remove_file" value="1">
                                        <span class="form-check-label">Retirer la pièce jointe</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="mailNotes">Notes</label>
                                <textarea id="mailNotes" name="notes" rows="2" maxlength="2000" class="form-control @error('notes') is-invalid @enderror" placeholder="Service destinataire, suite donnée…">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .mails-table { min-width: 900px; }
    .dg-scope .mails-table > thead > tr > th, .dg-scope .mails-table > tbody > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('#mailsTable tbody tr[data-state]'));
    const search = document.getElementById('mailSearch');
    const tabs = Array.from(document.querySelectorAll('[data-state-filter]'));
    const noResult = document.getElementById('mailsNoResult');
    let state = '';
    const filterRows = function () {
        const term = search ? search.value.trim().toLowerCase() : '';
        let visible = 0;
        rows.forEach(function (row) {
            const show = (!term || row.textContent.toLowerCase().includes(term)) && (!state || row.dataset.state === state);
            row.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        noResult.classList.toggle('d-none', !rows.length || visible > 0);
    };
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            state = tab.dataset.stateFilter;
            tabs.forEach(function (item) {
                item.classList.toggle('is-active', item === tab);
                item.setAttribute('aria-pressed', item === tab ? 'true' : 'false');
            });
            filterRows();
        });
    });
    search?.addEventListener('input', filterRows);

    const modal = document.getElementById('mailModal');
    const form = document.getElementById('mailForm');
    const method = document.getElementById('mailMethod');
    const id = document.getElementById('mailId');
    const type = document.getElementById('mailType');
    const currentFile = document.getElementById('mailCurrentFile');
    // Un courrier arrivé a un expéditeur, un courrier au départ un destinataire.
    const toggleCounterpart = function () {
        const outgoing = type.value === 'outgoing';
        document.querySelector('[data-mail-field="sender"]').classList.toggle('d-none', outgoing);
        document.querySelector('[data-mail-field="recipient"]').classList.toggle('d-none', !outgoing);
        document.querySelector('[data-mail-date-label]').textContent = outgoing ? 'Date d’envoi' : 'Date de réception';
    };
    type.addEventListener('change', toggleCounterpart);
    toggleCounterpart();

    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const mail = trigger.dataset.mail ? JSON.parse(trigger.dataset.mail) : null;
        form.action = mail ? mail.action : form.dataset.storeAction;
        method.disabled = !mail;
        id.disabled = !mail;
        id.value = mail ? mail.id : '';
        modal.querySelector('.modal-title span').textContent = mail ? 'Modifier le courrier' : 'Enregistrer un courrier';
        form.elements.reference.value = mail ? mail.reference : '';
        form.elements.subject.value = mail ? mail.subject : '';
        form.elements.sender.value = mail && mail.sender ? mail.sender : '';
        form.elements.recipient.value = mail && mail.recipient ? mail.recipient : '';
        form.elements.received_at.value = mail && mail.received_at ? mail.received_at : '{{ now()->toDateString() }}';
        form.elements.type.value = mail ? mail.type : 'incoming';
        form.elements.status.value = mail ? mail.status : 'received';
        form.elements.notes.value = mail && mail.notes ? mail.notes : '';
        form.elements.file.value = '';
        form.elements.remove_file.checked = false;
        currentFile.classList.toggle('d-none', !(mail && mail.file));
        if (mail && mail.file) {
            currentFile.querySelector('[data-mail-file-name]').textContent = mail.file;
        }
        toggleCounterpart();
    });
    @if($formHasErrors)
        window.addEventListener('load', () => new bootstrap.Modal(modal).show());
    @endif
});
</script>
@endsection
