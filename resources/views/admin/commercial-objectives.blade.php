@extends('admin.layout')

@section('content')
@php
    $states = \App\Services\SalesRevenueService::states();
    $percent = fn (?float $value) => $value === null ? '—' : number_format($value, $value < 10 && $value > 0 ? 1 : 0, ',', ' ') . ' %';
    $bar = fn (?float $value) => max(0, min(100, (float) $value));
    $currentYear = now()->year;
    $takenYears = $objectives->mapWithKeys(fn ($item) => [$item->objective_date->year => $item->id]);
    $yearOptions = collect(range(min($takenYears->keys()->min() ?? $currentYear, $currentYear - 1), max($takenYears->keys()->max() ?? $currentYear, $currentYear + 2)));
    $firstFreeYear = $yearOptions->first(fn ($year) => $year >= $currentYear && ! $takenYears->has($year)) ?? $currentYear;
    $employeeLabel = fn ($employee) => $employee->full_name . ($employee->position ? ' — ' . $employee->position : '');

    if ($progress) {
        $year = $progress['year'];
        $lines = collect($progress['lines']);
        $available = max(0, $progress['amount'] - $progress['assigned']);
        $closed = now()->gt($progress['to']);
        $stats = [
            ['Objectif ' . $year, money($progress['amount']), 'bi-bullseye', 'blue', 'chiffre d’affaires HT visé'],
            ['Réalisé', money($progress['realized']), 'bi-graph-up-arrow', 'green', $percent($progress['percent']) . ' de l’objectif'],
            ['Reste à réaliser', money($progress['remaining']), 'bi-hourglass-split', 'orange',
                $progress['state'] === 'reached' ? 'objectif atteint' : ($closed ? 'exercice clos' : 'd’ici le ' . $progress['to']->format('d/m/Y'))],
            ['Réparti', money($progress['assigned']), 'bi-people', 'purple',
                $available > 0 ? money($available) . ' à répartir' : ($lines->isEmpty() ? 'aucun commercial' : 'objectif entièrement réparti')],
        ];
        [$stateLabel, $stateTone, $stateIcon] = $states[$progress['state']];
        $showPace = $progress['pace'] > 0 && $progress['pace'] < 100;
        $hasSales = collect($progress['sources'])->sum('count') > 0;
        $assignedEmployeeIds = $objective->assignments->pluck('employee_id')->all();
    }

    // Après une erreur, la fenêtre concernée se rouvre avec la saisie.
    $objectiveFormErrors = $errors->any() && old('_objective_form');
    $assignFormErrors = $errors->any() && old('_assign_form');
    $assignmentFormErrors = $errors->any() && old('_assignment_form');
    $editedObjective = $objectiveFormErrors && old('objective_id') ? $objectives->firstWhere('id', (int) old('objective_id')) : null;
    $assignRows = $assignFormErrors ? (array) old('assignments', []) : [];
    if (! $assignRows && $progress) {
        $assignRows = [['employee_id' => '', 'amount' => '', 'starts_at' => $progress['from']->format('Y-m-d'), 'ends_at' => $progress['to']->format('Y-m-d')]];
    }
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <button type="button" class="dg-btn {{ $objective ? 'dg-btn--outline' : 'dg-btn--primary' }}" data-bs-toggle="modal" data-bs-target="#objectiveModal"><i class="bi bi-plus-lg"></i>Nouvel objectif</button>
            @if($objective)
                <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-person-plus"></i>Attribuer à un commercial</button>
            @endif
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any() && ! $objectiveFormErrors && ! $assignFormErrors && ! $assignmentFormErrors)<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @if(! $objective)
        <div class="dg-card">
            <div class="dg-chart-empty" style="min-height:280px">
                <span class="dg-tile dg-tone-blue"><i class="bi bi-bullseye"></i></span>
                <div style="max-width:560px"><strong>Aucun objectif commercial</strong>Fixez le chiffre d’affaires HT visé pour l’exercice, répartissez-le entre vos commerciaux, puis suivez la réalisation au fil des factures et des ventes.</div>
                <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#objectiveModal"><i class="bi bi-plus-lg"></i>Définir l’objectif {{ $firstFreeYear }}</button>
            </div>
        </div>
    @else
        @if($objectives->count() > 1)
            <nav class="dg-tabs mb-4" aria-label="Exercices">
                @foreach($objectives as $item)
                    <a href="{{ route('admin.commercial.module', ['objectifs', 'objectif' => $item->id]) }}" class="dg-tab {{ $item->id === $objective->id ? 'is-active' : '' }}" @if($item->id === $objective->id) aria-current="page" @endif>
                        <i class="bi bi-calendar3"></i>Exercice {{ $item->objective_date->year }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="dg-kpi-grid">
            @foreach($stats as [$label, $value, $icon, $color, $hint])
                <x-dg.kpi :label="$label" :value="$value" :icon="$icon" :color="$color" :hint="$hint" />
            @endforeach
        </div>

        <div class="dg-grid-2 mb-5">
            <x-dg.card :title="'Avancement de l’exercice ' . $year" icon="bi-speedometer2" color="green">
                <x-slot:actions>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions sur l’objectif {{ $year }}"></button>
                        <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#objectiveModal"
                                data-objective="{{ json_encode(['id' => $objective->id, 'year' => $year, 'amount' => (float) $objective->amount, 'action' => route('admin.commercial.objectives.update', $objective)]) }}"><i class="bi bi-pencil"></i>Modifier l’objectif</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><form method="POST" action="{{ route('admin.commercial.objectives.destroy', $objective) }}" onsubmit="return confirm('Supprimer l’objectif {{ $year }} et ses {{ $lines->count() }} attribution(s) ? Les ventes ne changent pas.')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer l’objectif</button></form></li>
                        </ul>
                    </div>
                </x-slot:actions>

                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <span class="objective-percent">{{ $percent($progress['percent']) }}</span>
                    <span class="dg-muted">de {{ money($progress['amount']) }} HT</span>
                    <span class="dg-badge dg-badge--{{ $stateTone }} ms-auto"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span>
                </div>
                <div class="objective-bar objective-bar--lg mb-2" role="progressbar" aria-label="Réalisé de l’objectif {{ $year }}" aria-valuenow="{{ round($progress['percent']) }}" aria-valuemin="0" aria-valuemax="100">
                    <span class="objective-bar__fill objective-bar__fill--{{ $stateTone }}" style="width:{{ $bar($progress['percent']) }}%"></span>
                    @if($showPace)<span class="objective-bar__pace" style="left:{{ $bar($progress['pace']) }}%" title="Rythme attendu aujourd’hui"></span>@endif
                </div>
                @if($showPace)
                    <p class="dg-muted mb-4" style="font-size:12.5px"><span class="objective-pace-key"></span>Rythme attendu au {{ now()->format('d/m/Y') }} : {{ $percent($progress['pace']) }} de l’exercice écoulé.</p>
                @else
                    <p class="dg-muted mb-4" style="font-size:12.5px">{{ $closed ? 'Exercice clos le ' . $progress['to']->format('d/m/Y') . '.' : 'L’exercice commence le ' . $progress['from']->format('d/m/Y') . '.' }}</p>
                @endif

                <div class="objective-figures mb-4">
                    <div><span>Réalisé à date</span><strong>{{ money($progress['realized']) }}</strong></div>
                    <div><span>Attendu à date</span><strong>{{ money($progress['expected']) }}</strong></div>
                    <div>
                        <span>{{ $progress['gap'] >= 0 ? 'Avance sur le rythme' : 'Retard sur le rythme' }}</span>
                        <strong class="{{ $progress['gap'] >= 0 ? 'text-success' : 'dg-amount-negative' }}">{{ $progress['gap'] >= 0 ? '+' : '−' }} {{ money(abs($progress['gap'])) }}</strong>
                    </div>
                </div>

                <h3 class="objective-subtitle">Réalisé cumulé, mois par mois</h3>
                <div class="dg-chart dg-chart--sm">
                    @if($hasSales)
                        <canvas id="objectiveChart" aria-label="Réalisé cumulé comparé à l’objectif" role="img"></canvas>
                    @else
                        <div class="dg-chart-empty">
                            <span class="dg-tile dg-tone-green"><i class="bi bi-graph-up-arrow"></i></span>
                            <div><strong>Aucune vente sur l’exercice {{ $year }}</strong>Les factures émises et les ventes au comptoir s’additionneront ici, mois après mois.</div>
                        </div>
                    @endif
                </div>
            </x-dg.card>

            <x-dg.card title="Origine du réalisé" icon="bi-diagram-3" color="teal" meta="HT">
                <table class="dg-mini-table mb-3">
                    @foreach($progress['sources'] as $source => $row)
                        <tr>
                            <td><span class="d-block">{{ $row['label'] }}</span><span class="dg-muted" style="font-size:12.5px">{{ $row['count'] }} document(s)</span></td>
                            <td class="{{ $row['amount'] < 0 ? 'dg-amount-negative' : '' }}">{{ money($row['amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="objective-total"><td>Réalisé {{ $year }}</td><td>{{ money($progress['realized']) }}</td></tr>
                </table>
                @if(abs($progress['unassigned']) >= 0.01)
                    <div class="objective-note">
                        <i class="bi bi-info-circle"></i>
                        <div>
                            <strong>{{ money($progress['unassigned']) }}</strong> ne sont suivis par aucun objectif individuel : ventes au comptoir, qui n’enregistrent pas de vendeur, ventes d’utilisateurs sans attribution ou faites hors de leur période.
                        </div>
                    </div>
                @elseif($hasSales)
                    <div class="objective-note"><i class="bi bi-check-circle"></i><div>Toutes les ventes de l’exercice sont suivies par un objectif individuel.</div></div>
                @endif
            </x-dg.card>
        </div>

        <div class="dg-card dg-card--table">
            <div class="dg-card__header flex-wrap">
                <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-people"></i></span>Objectifs par commercial</h2>
                <span class="dg-card__meta">{{ $lines->count() }} {{ $lines->count() > 1 ? 'commerciaux' : 'commercial' }} · {{ money($progress['assigned']) }} réparti sur {{ money($progress['amount']) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0 objectives-table no-export" id="objectivesTable">
                    <thead>
                        <tr>
                            <th>Commercial</th>
                            <th>Période</th>
                            <th class="text-end">Objectif</th>
                            <th class="text-end">Réalisé</th>
                            <th>Avancement</th>
                            <th class="text-end"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($lines as $line)
                        @php
                            $assignment = $line['assignment'];
                            $employee = $assignment->employee;
                            [$lineLabel, $lineTone, $lineIcon] = $states[$line['state']];
                            $share = $progress['amount'] > 0 ? (float) $assignment->amount / $progress['amount'] * 100 : 0;
                        @endphp
                        <tr>
                            <td>
                                <span class="d-block fw-semibold">{{ $employee?->full_name ?? 'Employé supprimé' }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">
                                    {{ $employee?->position ?: 'Poste non renseigné' }}
                                    @if($employee && $employee->status !== 'active') · <span class="dg-amount-negative">a quitté l’entreprise</span>@endif
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <span class="d-block">{{ $assignment->starts_at->format('d/m/Y') }}</span>
                                <span class="d-block dg-muted" style="font-size:12.5px">au {{ $assignment->ends_at->format('d/m/Y') }}</span>
                            </td>
                            <td class="text-end">
                                <span class="d-block dg-cell-num">{{ money((float) $assignment->amount) }}</span>
                                <span class="d-block dg-muted text-nowrap" style="font-size:12.5px">{{ $percent($share) }} de l’objectif</span>
                            </td>
                            <td class="text-end">
                                @if($line['measurable'])
                                    <span class="d-block dg-cell-num fw-semibold">{{ money($line['realized']) }}</span>
                                    <span class="d-block dg-muted text-nowrap" style="font-size:12.5px">attendu : {{ money($line['expected']) }}</span>
                                @else
                                    <span class="d-block dg-muted">—</span>
                                    <span class="d-block dg-muted text-nowrap" style="font-size:12.5px">sans compte utilisateur</span>
                                @endif
                            </td>
                            <td class="objectives-table__progress">
                                @if($line['measurable'])
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <div class="objective-bar flex-grow-1" role="progressbar" aria-label="Avancement de {{ $employee?->full_name }}" aria-valuenow="{{ round($line['percent']) }}" aria-valuemin="0" aria-valuemax="100">
                                            <span class="objective-bar__fill objective-bar__fill--{{ $lineTone }}" style="width:{{ $bar($line['percent']) }}%"></span>
                                        </div>
                                        <span class="fw-semibold text-nowrap" style="font-size:13px;min-width:42px;text-align:right">{{ $percent($line['percent']) }}</span>
                                    </div>
                                @endif
                                <span class="dg-badge dg-badge--{{ $lineTone }}" style="font-size:11.5px;padding:2px 8px"
                                    @if(! $line['measurable']) title="Reliez l’employé à un compte utilisateur (fiche RH) : ses factures lui seront alors attribuées." @endif><i class="bi {{ $lineIcon }}"></i>{{ $lineLabel }}</span>
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light action-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions" aria-label="Actions pour {{ $employee?->full_name }}"></button>
                                    <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#assignmentModal"
                                            data-assignment="{{ json_encode(['id' => $assignment->id, 'employee_id' => $assignment->employee_id, 'amount' => (float) $assignment->amount, 'starts_at' => $assignment->starts_at->format('Y-m-d'), 'ends_at' => $assignment->ends_at->format('Y-m-d'), 'action' => route('admin.commercial.objectives.assignments.update', $assignment)]) }}"><i class="bi bi-pencil"></i>Modifier</button></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><form method="POST" action="{{ route('admin.commercial.objectives.assignments.destroy', $assignment) }}" onsubmit="return confirm('Retirer l’objectif de {{ addslashes($employee?->full_name ?? 'ce commercial') }} ? Son montant redeviendra disponible.')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button></form></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-5">
                                <div class="dg-chart-empty" style="min-height:200px">
                                    <span class="dg-tile dg-tone-purple"><i class="bi bi-people"></i></span>
                                    <div><strong>Objectif pas encore réparti</strong>Attribuez une part de l’objectif {{ $year }} à chaque commercial : ses factures seront comptées au fil de l’eau.</div>
                                    <button type="button" class="dg-btn dg-btn--primary dg-btn--sm" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-person-plus"></i>Attribuer à un commercial</button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                    @if($lines->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="2">Total des commerciaux</td>
                                <td class="text-end dg-cell-num">{{ money($progress['assigned']) }}</td>
                                <td class="text-end dg-cell-num">{{ money((float) $lines->sum('realized')) }}</td>
                                <td colspan="2" class="dg-muted fw-normal" style="font-size:12.5px">{{ $available > 0 ? money($available) . ' restent à répartir' : 'Objectif entièrement réparti' }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <p class="dg-muted mt-3 mb-0" style="font-size:12.5px">
                <i class="bi bi-info-circle me-1"></i>Le réalisé d’un commercial additionne, sur sa période, le HT des factures de ventes issues de ses devis et des factures personnalisées qu’il a émises, moins leurs avoirs. L’employé doit être relié à un compte utilisateur.
            </p>
        </div>
    @endif

    {{-- Création et modification de l'objectif annuel. --}}
    <div class="modal fade" id="objectiveModal" tabindex="-1" aria-labelledby="objectiveModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="objectiveForm"
                    action="{{ $editedObjective ? route('admin.commercial.objectives.update', $editedObjective) : route('admin.commercial.objectives.store') }}"
                    data-store-action="{{ route('admin.commercial.objectives.store') }}">
                    @csrf
                    <input type="hidden" name="_objective_form" value="1">
                    <input type="hidden" name="_method" value="PUT" id="objectiveMethod" @disabled(! $editedObjective)>
                    <input type="hidden" name="objective_id" value="{{ $editedObjective?->id }}" id="objectiveId" @disabled(! $editedObjective)>
                    <div class="modal-header">
                        <h5 class="modal-title" id="objectiveModalTitle"><i class="bi bi-bullseye me-2"></i><span>{{ $editedObjective ? 'Modifier l’objectif' : 'Nouvel objectif annuel' }}</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        @if($objectiveFormErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                        <div class="row g-3">
                            <div class="col-sm-5">
                                <label class="form-label" for="objectiveYear">Exercice <span class="text-danger">*</span></label>
                                <select id="objectiveYear" name="year" class="form-select @error('year') is-invalid @enderror" required>
                                    @foreach($yearOptions as $optionYear)
                                        <option value="{{ $optionYear }}" data-taken="{{ $takenYears->get($optionYear) }}" @selected((int) old('year', $editedObjective?->objective_date->year ?? $firstFreeYear) === $optionYear)>{{ $optionYear }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-7">
                                <label class="form-label" for="objectiveAmount">Chiffre d’affaires HT visé ({{ currency_symbol() }}) <span class="text-danger">*</span></label>
                                <input id="objectiveAmount" name="amount" type="number" min="0.01" step="0.01" class="form-control @error('amount') is-invalid @enderror" required value="{{ old('amount') }}" placeholder="Ex. 60000000">
                            </div>
                        </div>
                        <p class="form-text mt-3 mb-0">Sont comptées les factures de ventes, les factures personnalisées émises et les ventes au comptoir de l’exercice, hors taxes et avoirs déduits. Un seul objectif par exercice ; les années déjà prises sont grisées.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($objective)
        {{-- Attribution à un ou plusieurs commerciaux. --}}
        <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.commercial.objectives.assignments.store', $objective) }}" id="assignForm">
                        @csrf
                        <input type="hidden" name="_assign_form" value="1">
                        <div class="modal-header">
                            <h5 class="modal-title" id="assignModalTitle"><i class="bi bi-person-plus me-2"></i>Répartir l’objectif {{ $year }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            @if($assignFormErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                            <div class="assign-summary mb-4">
                                <div><span>Objectif {{ $year }}</span><strong>{{ money($progress['amount']) }}</strong></div>
                                <div><span>Déjà réparti</span><strong>{{ money($progress['assigned']) }}</strong></div>
                                <div><span>Cette saisie</span><strong id="assignEntered">{{ money(0) }}</strong></div>
                                <div class="assign-summary__rest"><span id="assignRestLabel">Reste à répartir</span><strong id="assignRest">{{ money($available) }}</strong></div>
                            </div>
                            @if($employees->where('status', 'active')->whereNotIn('id', $assignedEmployeeIds)->isEmpty())
                                <div class="alert alert-warning py-2 mb-3">Tous les employés actifs ont déjà un objectif sur cet exercice. Ajoutez un employé dans RH, ou modifiez une attribution existante.</div>
                            @endif
                            <div id="assignRows">
                                @foreach($assignRows as $index => $row)
                                    <div class="assign-row" data-assign-row>
                                        <div class="assign-row__employee">
                                            <label class="form-label">Commercial <span class="text-danger">*</span></label>
                                            <select name="assignments[{{ $index }}][employee_id]" class="form-select @error('assignments.' . $index . '.employee_id') is-invalid @enderror" required data-assign-employee>
                                                <option value="">Choisir un employé</option>
                                                @foreach($employees as $employee)
                                                    @continue($employee->status !== 'active')
                                                    <option value="{{ $employee->id }}" @selected((string) ($row['employee_id'] ?? '') === (string) $employee->id)
                                                        @disabled(in_array($employee->id, $assignedEmployeeIds))>{{ $employeeLabel($employee) }}{{ in_array($employee->id, $assignedEmployeeIds) ? ' (déjà attribué)' : ($employee->user_id ? '' : ' (sans compte utilisateur)') }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="assign-row__amount">
                                            <label class="form-label">Montant HT <span class="text-danger">*</span></label>
                                            <input name="assignments[{{ $index }}][amount]" type="number" min="0.01" step="0.01" class="form-control @error('assignments.' . $index . '.amount') is-invalid @enderror" required value="{{ $row['amount'] ?? '' }}" data-assign-amount>
                                        </div>
                                        <div class="assign-row__date">
                                            <label class="form-label">Du</label>
                                            <input name="assignments[{{ $index }}][starts_at]" type="date" class="form-control @error('assignments.' . $index . '.starts_at') is-invalid @enderror" required value="{{ $row['starts_at'] ?? '' }}" min="{{ $progress['from']->format('Y-m-d') }}" max="{{ $progress['to']->format('Y-m-d') }}">
                                        </div>
                                        <div class="assign-row__date">
                                            <label class="form-label">Au</label>
                                            <input name="assignments[{{ $index }}][ends_at]" type="date" class="form-control @error('assignments.' . $index . '.ends_at') is-invalid @enderror" required value="{{ $row['ends_at'] ?? '' }}" min="{{ $progress['from']->format('Y-m-d') }}" max="{{ $progress['to']->format('Y-m-d') }}">
                                        </div>
                                        <div class="assign-row__remove">
                                            <button type="button" class="dg-icon-btn dg-icon-btn--sm" data-assign-remove title="Retirer la ligne" aria-label="Retirer la ligne"><i class="bi bi-x-lg"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="dg-btn dg-btn--outline dg-btn--sm mt-1" id="assignAddRow"><i class="bi bi-plus-lg"></i>Ajouter un commercial</button>
                            <p class="form-text mt-3 mb-0">La période reste dans l’exercice {{ $year }}. Un commercial sans compte utilisateur peut recevoir un objectif, mais son réalisé ne peut pas être mesuré.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer la répartition</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modification d'une attribution. --}}
        @php $editedAssignment = $assignmentFormErrors ? $objective->assignments->firstWhere('id', (int) old('assignment_id')) : null; @endphp
        <div class="modal fade" id="assignmentModal" tabindex="-1" aria-labelledby="assignmentModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" id="assignmentForm" action="{{ $editedAssignment ? route('admin.commercial.objectives.assignments.update', $editedAssignment) : '#' }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="_assignment_form" value="1">
                        <input type="hidden" name="assignment_id" value="{{ $editedAssignment?->id }}" id="assignmentId">
                        <div class="modal-header">
                            <h5 class="modal-title" id="assignmentModalTitle"><i class="bi bi-pencil-square me-2"></i>Modifier l’attribution</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            @if($assignmentFormErrors)<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="assignmentEmployee">Commercial <span class="text-danger">*</span></label>
                                    <select id="assignmentEmployee" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" data-assigned="{{ in_array($employee->id, $assignedEmployeeIds) ? 1 : 0 }}" data-active="{{ $employee->status === 'active' ? 1 : 0 }}"
                                                @selected((int) old('employee_id') === $employee->id)>{{ $employeeLabel($employee) }}{{ $employee->status !== 'active' ? ' (a quitté l’entreprise)' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="assignmentAmount">Montant HT ({{ currency_symbol() }}) <span class="text-danger">*</span></label>
                                    <input id="assignmentAmount" name="amount" type="number" min="0.01" step="0.01" class="form-control @error('amount') is-invalid @enderror" required value="{{ old('amount') }}">
                                    <div class="form-text" id="assignmentAvailable" data-others-base="{{ $progress['assigned'] }}" data-total="{{ $progress['amount'] }}"></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="assignmentStart">Du</label>
                                    <input id="assignmentStart" name="starts_at" type="date" class="form-control @error('starts_at') is-invalid @enderror" required value="{{ old('starts_at') }}" min="{{ $progress['from']->format('Y-m-d') }}" max="{{ $progress['to']->format('Y-m-d') }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="assignmentEnd">Au</label>
                                    <input id="assignmentEnd" name="ends_at" type="date" class="form-control @error('ends_at') is-invalid @enderror" required value="{{ old('ends_at') }}" min="{{ $progress['from']->format('Y-m-d') }}" max="{{ $progress['to']->format('Y-m-d') }}">
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
    @endif
</div>

<style>
    .dg-tab.is-active .bi { color: #fff; }
    .objectives-table { min-width: 820px; }
    .objectives-table__progress { width: 230px; }
    .dg-scope .objectives-table > thead > tr > th, .dg-scope .objectives-table > tbody > tr > td, .dg-scope .objectives-table > tfoot > tr > td { padding-left: 10px !important; padding-right: 10px !important; }
    .objectives-table tfoot td { border-top: 2px solid var(--dg-border-strong); font-weight: 700; color: var(--dg-navy); }
    .objective-percent { font-size: 30px; font-weight: 700; line-height: 1; color: var(--dg-text); }
    .objective-subtitle { margin: 0 0 10px; font-size: 14px; font-weight: 600; color: var(--dg-text); }
    .objective-bar { position: relative; height: 8px; border-radius: 99px; background: var(--dg-neutral-bg, #eceff6); }
    .objective-bar--lg { height: 14px; }
    .objective-bar__fill { display: block; height: 100%; border-radius: inherit; background: #059669; }
    .objective-bar__fill--warning { background: #d97706; }
    .objective-bar__fill--danger { background: #dc2626; }
    .objective-bar__fill--neutral { background: #8a93a6; }
    .objective-bar__pace { position: absolute; top: -4px; bottom: -4px; width: 3px; margin-left: -1px; border-radius: 2px; background: var(--dg-navy); }
    .objective-pace-key { display: inline-block; width: 3px; height: 12px; margin-right: 8px; border-radius: 2px; vertical-align: -1px; background: var(--dg-navy); }
    .objective-figures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .objective-figures > div, .assign-summary > div { padding: 12px 14px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius); background: var(--dg-surface); }
    .objective-figures span, .assign-summary span { display: block; font-size: 12.5px; color: var(--dg-muted); }
    .objective-figures strong, .assign-summary strong { font-size: 16px; font-variant-numeric: tabular-nums; }
    .objective-figures strong.text-success { color: #059669 !important; }
    .objective-total td { border-top: 2px solid var(--dg-border-strong) !important; font-weight: 700; color: var(--dg-navy); }
    .objective-note { display: flex; gap: 10px; padding: 12px 14px; border-radius: var(--dg-radius); background: rgba(13, 148, 136, .08); font-size: 13px; color: var(--dg-text); }
    .objective-note .bi { color: #0d9488; }
    .assign-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .assign-summary__rest { background: rgba(5, 150, 105, .08) !important; border-color: rgba(5, 150, 105, .3) !important; }
    .assign-summary__rest strong { color: #059669; }
    .assign-summary__rest.is-over { background: rgba(220, 38, 38, .08) !important; border-color: rgba(220, 38, 38, .3) !important; }
    .assign-summary__rest.is-over strong { color: #dc2626; }
    .assign-row { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1fr) auto; gap: 10px; align-items: end; padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px dashed var(--dg-border); }
    .assign-row__remove { padding-bottom: 4px; }
    @media (max-width: 991px) { .dg-scope .dg-grid-2 { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 767px) {
        .objective-figures, .assign-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .assign-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
        .assign-row__employee { grid-column: 1 / -1; }
        .assign-row__remove { grid-column: 1 / -1; text-align: right; }
    }
</style>
@if($objective && $hasSales)
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    const money = value => window.formatMoney(value);

    // Objectif : création et modification dans la même fenêtre.
    const objectiveModal = document.getElementById('objectiveModal');
    const objectiveForm = document.getElementById('objectiveForm');
    const yearSelect = document.getElementById('objectiveYear');
    const markTakenYears = function (currentId) {
        Array.from(yearSelect.options).forEach(function (option) {
            option.disabled = option.dataset.taken !== '' && option.dataset.taken !== String(currentId || '');
        });
    };
    objectiveModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const objective = trigger.dataset.objective ? JSON.parse(trigger.dataset.objective) : null;
        objectiveForm.action = objective ? objective.action : objectiveForm.dataset.storeAction;
        document.getElementById('objectiveMethod').disabled = !objective;
        document.getElementById('objectiveId').disabled = !objective;
        document.getElementById('objectiveId').value = objective ? objective.id : '';
        objectiveModal.querySelector('.modal-title span').textContent = objective ? 'Modifier l’objectif ' + objective.year : 'Nouvel objectif annuel';
        markTakenYears(objective ? objective.id : null);
        yearSelect.value = objective ? objective.year : @json($firstFreeYear);
        objectiveForm.elements.amount.value = objective ? objective.amount : '';
    });
    markTakenYears(@json($editedObjective?->id));
    @if($objectiveFormErrors)
        window.addEventListener('load', () => new bootstrap.Modal(objectiveModal).show());
    @endif

    @if($objective)
    // Répartition : lignes ajoutées à la volée, total et reste en direct.
    const assignRows = document.getElementById('assignRows');
    const total = @json($progress['amount']);
    const alreadyAssigned = @json($progress['assigned']);
    const restBox = document.querySelector('.assign-summary__rest');
    const refreshAssign = function () {
        const rows = Array.from(assignRows.querySelectorAll('[data-assign-row]'));
        const entered = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('[data-assign-amount]').value) || 0), 0);
        const rest = total - alreadyAssigned - entered;
        document.getElementById('assignEntered').textContent = money(entered);
        document.getElementById('assignRestLabel').textContent = rest < -0.005 ? 'Dépasse l’objectif de' : 'Reste à répartir';
        document.getElementById('assignRest').textContent = money(Math.abs(rest));
        restBox.classList.toggle('is-over', rest < -0.005);
        // Un commercial ne peut figurer qu'une fois.
        const chosen = rows.map(row => row.querySelector('[data-assign-employee]').value).filter(Boolean);
        rows.forEach(function (row) {
            const select = row.querySelector('[data-assign-employee]');
            Array.from(select.options).forEach(function (option) {
                if (option.value === '' || option.dataset.locked === '1') return;
                option.disabled = option.value !== select.value && chosen.includes(option.value);
            });
        });
        rows.forEach(row => { row.querySelector('[data-assign-remove]').disabled = rows.length === 1; });
    };
    assignRows.querySelectorAll('option:disabled').forEach(option => { option.dataset.locked = '1'; });
    document.getElementById('assignAddRow').addEventListener('click', function () {
        const rows = assignRows.querySelectorAll('[data-assign-row]');
        const model = rows[rows.length - 1];
        const row = model.cloneNode(true);
        const index = Date.now();
        row.querySelectorAll('input, select').forEach(function (field) {
            field.name = field.name.replace(/assignments\[[^\]]+\]/, 'assignments[' + index + ']');
            field.classList.remove('is-invalid');
            if (field.tagName === 'SELECT' || field.hasAttribute('data-assign-amount')) field.value = '';
        });
        assignRows.appendChild(row);
        refreshAssign();
        row.querySelector('[data-assign-employee]').focus();
    });
    assignRows.addEventListener('input', refreshAssign);
    assignRows.addEventListener('change', refreshAssign);
    assignRows.addEventListener('click', function (event) {
        const button = event.target.closest('[data-assign-remove]');
        if (!button || assignRows.querySelectorAll('[data-assign-row]').length === 1) return;
        button.closest('[data-assign-row]').remove();
        refreshAssign();
    });
    refreshAssign();

    // Modification d'une attribution.
    const assignmentModal = document.getElementById('assignmentModal');
    const assignmentForm = document.getElementById('assignmentForm');
    const employeeSelect = document.getElementById('assignmentEmployee');
    const available = document.getElementById('assignmentAvailable');
    let currentAmount = 0;
    let currentEmployee = @json($editedAssignment?->employee_id);
    if (currentEmployee !== null) currentAmount = @json((float) ($editedAssignment?->amount ?? 0));
    const lockEmployees = function () {
        Array.from(employeeSelect.options).forEach(function (option) {
            const mine = String(currentEmployee) === option.value;
            option.disabled = !mine && (option.dataset.assigned === '1' || option.dataset.active !== '1');
            option.hidden = !mine && option.dataset.active !== '1';
        });
        available.textContent = 'Disponible pour ce commercial : jusqu’à ' + money(total - alreadyAssigned + currentAmount) + '.';
    };
    assignmentModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger || !trigger.dataset.assignment) return;
        const assignment = JSON.parse(trigger.dataset.assignment);
        assignmentForm.action = assignment.action;
        document.getElementById('assignmentId').value = assignment.id;
        currentEmployee = assignment.employee_id;
        currentAmount = assignment.amount;
        employeeSelect.value = assignment.employee_id;
        assignmentForm.elements.amount.value = assignment.amount;
        assignmentForm.elements.starts_at.value = assignment.starts_at;
        assignmentForm.elements.ends_at.value = assignment.ends_at;
        assignmentForm.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
        lockEmployees();
    });
    lockEmployees();
    @if($assignFormErrors)
        window.addEventListener('load', () => new bootstrap.Modal(document.getElementById('assignModal')).show());
    @endif
    @if($assignmentFormErrors && $editedAssignment)
        window.addEventListener('load', () => new bootstrap.Modal(assignmentModal).show());
    @endif

    @if($hasSales)
    // Réalisé cumulé face à l'objectif réparti linéairement sur l'année.
    const months = @json($progress['months']);
    const lastMonth = @json($progress['year'] < now()->year ? 12 : ($progress['year'] > now()->year ? 0 : now()->month));
    const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });
    Chart.defaults.font.family = "'Poppins', system-ui, sans-serif";
    Chart.defaults.color = '#8a93a6';
    new Chart(document.getElementById('objectiveChart'), {
        type: 'line',
        data: {
            labels: months.map(month => month.label),
            datasets: [{
                label: 'Réalisé cumulé',
                data: months.map((month, index) => index < lastMonth ? month.cumulative : null),
                borderColor: '#059669', backgroundColor: 'rgba(5, 150, 105, .10)', fill: true, tension: .35,
                borderWidth: 3, pointRadius: 3, pointBackgroundColor: '#059669'
            }, {
                label: 'Objectif au rythme régulier',
                data: months.map(month => month.target),
                borderColor: '#273772', borderDash: [6, 5], borderWidth: 2, pointRadius: 0, fill: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, spanGaps: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 14, boxHeight: 3, usePointStyle: false } },
                tooltip: { callbacks: { label: context => context.dataset.label + ' : ' + money(context.raw) } }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(138, 147, 166, .16)' }, ticks: { callback: value => compact.format(value) } }
            }
        }
    });
    @endif
    @endif
});
</script>
@endsection
