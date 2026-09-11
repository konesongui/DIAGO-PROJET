@extends('admin.layout')

@php
    // Caisses et banques suivent la charte (refonte 4b) ; les rapports gardent l'ancienne présentation jusqu'à leur refonte.
    $isTreasury = in_array($moduleType ?? '', ['caisses', 'banques'], true);
    // Écrans présentés avec la charte : trésorerie (4b) et rapports (4d).
    $isRedesigned = in_array($moduleType ?? '', ['caisses', 'banques', 'rapports', 'rapport_financier'], true);
@endphp

@if($isTreasury)
    @section('topbar')
        @php
            $treasuryBalance = $moduleType === 'caisses' ? ($summary[3] ?? null) : ($summary[1] ?? null);
        @endphp
        @if($treasuryBalance)
            <p class="dg-topbar__title">{{ $moduleType === 'caisses' ? 'Solde réel des caisses' : 'Solde total des banques' }} : <span class="dg-amount-positive text-nowrap">{{ $treasuryBalance['value'] }}</span></p>
        @endif
    @endsection
@endif

@section('content')
<div class="{{ $isRedesigned ? 'dg-font dg-scope' : '' }}">
@if($isRedesigned)
    @php
        $headerSubtitle = $moduleType === 'rapport_financier'
            ? 'Ventes, encaissements et dépenses du ' . \Carbon\Carbon::parse($filters['date_debut'])->format('d/m/Y') . ' au ' . \Carbon\Carbon::parse($filters['date_fin'])->format('d/m/Y') . '.'
            : $subtitle;
    @endphp
    <x-dg.page-header :title="$title" :subtitle="$headerSubtitle" :back="route('admin.comptabilite')" back-label="Comptabilité">
        @if($moduleType === 'rapport_financier')
            <x-slot:actions>
                <form method="GET" action="{{ route('admin.comptabilite.rapport_financier') }}" class="dg-period" aria-label="Période du rapport">
                    <input type="date" name="date_debut" value="{{ $filters['date_debut'] }}" class="dg-input" aria-label="Date de début">
                    <span class="dg-period__sep">au</span>
                    <input type="date" name="date_fin" value="{{ $filters['date_fin'] }}" class="dg-input" aria-label="Date de fin">
                    <button type="submit" class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>Filtrer</button>
                </form>
            </x-slot:actions>
        @elseif($moduleType === 'caisses')
            <x-slot:actions>
                <button type="button" class="dg-btn dg-btn--outline" data-bs-toggle="modal" data-bs-target="#createCashAccountModal"><i class="bi bi-plus-lg"></i>Nouvelle caisse</button>
                <button type="button" class="dg-btn dg-btn--primary" data-bs-toggle="modal" data-bs-target="#createCashMovementModal"><i class="bi bi-plus-lg"></i>Nouveau mouvement</button>
            </x-slot:actions>
        @endif
    </x-dg.page-header>
<div>
    <div>
@else
<div class="card border-0 shadow-sm mb-6">
    <div class="card-body p-6">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 mb-6">
            <div>
                <div class="text-uppercase text-muted fs-8 fw-bold ls-1">Comptabilité</div>
                <h3 class="fs-2 fw-bold text-dark mb-1">{{ $title }}</h3>
                <p class="text-muted mb-0">{{ $subtitle }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.comptabilite') }}" class="btn btn-light btn-sm px-4">Retour</a>
            </div>
        </div>
@endif

        @if(($moduleType ?? '') === 'banques')
            <style>
                .bank-filter-bar {
                    background: #ffffff;
                    border: 1px solid #e3e7ef;
                    border-radius: 16px;
                    padding: 14px 16px;
                    margin-bottom: 24px;
                }
                .bank-card {
                    border: 1px solid #edf2f7;
                    border-radius: 18px;
                    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
                    background: #ffffff;
                    height: 100%;
                }
                .bank-card .bank-logo {
                    width: 54px;
                    height: 54px;
                    border-radius: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.1rem;
                    font-weight: 700;
                    letter-spacing: .06em;
                }
                .bank-separator {
                    height: 1px;
                    background: #edf2f7;
                    margin: 18px 0;
                }
                .modal-content { border-radius: 18px; }
                .bank-details-toolbar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: nowrap;
                    padding: 12px 14px;
                    background: #f7f8fb;
                    border: 1px solid #e3e7ef;
                    border-radius: 12px;
                }
                .bank-details-toolbar .toolbar-filters,
                .bank-details-toolbar .toolbar-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: nowrap;
                    min-width: 0;
                    white-space: nowrap;
                }
                .bank-details-toolbar .btn {
                    white-space: nowrap;
                }
                .bank-details-toolbar .form-control,
                .bank-details-toolbar .form-select {
                    min-height: 38px;
                    border-radius: 10px;
                    border-color: #dfe7f4;
                    font-size: 0.85rem;
                }
                .bank-details-modal .modal-dialog {
                    max-width: 1200px;
                    width: min(1200px, calc(100vw - 32px));
                }
                .bank-details-modal .modal-body {
                    max-height: 80vh;
                    overflow-y: auto;
                    padding: 1.25rem;
                }
                .bank-summary-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    gap: 12px;
                }
                .bank-summary-item {
                    background: #f7f8fb;
                    border: 1px solid #e3e7ef;
                    border-radius: 14px;
                    padding: 14px 16px;
                }
                .bank-details-table thead th {
                    background: #f8f9ff;
                    color: #475569;
                    font-size: 0.75rem;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .bank-details-table tbody td {
                    font-size: 0.87rem;
                    vertical-align: middle;
                }
                .cash-movement-table {
                    width: 100%;
                    table-layout: auto;
                    min-width: 1080px;
                }
                .cash-movement-table th,
                .cash-movement-table td {
                    padding: .8rem .7rem;
                    vertical-align: middle;
                }
                .cash-movement-table th {
                    white-space: nowrap;
                }
                @media (max-width: 768px) {
                    .bank-details-toolbar {
                        flex-wrap: wrap;
                    }
                    .bank-details-toolbar .toolbar-filters,
                    .bank-details-toolbar .toolbar-actions {
                        flex-wrap: wrap;
                        width: 100%;
                    }
                }
            </style>

            <form method="GET" action="{{ route('admin.comptabilite.banques') }}" class="mb-6">
                <div class="bank-filter-bar d-flex flex-row align-items-center justify-content-between gap-3 flex-wrap">
                    <div class="d-flex flex-row align-items-center gap-2 flex-wrap flex-grow-1">
                        <div class="input-group" style="width: 190px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-filter"></i></span>
                            <select name="type" class="form-select border-start-0 bg-white">
                                <option value="">Type</option>
                                <option value="compte_courant" {{ (($filters['type'] ?? '') === 'compte_courant') ? 'selected' : '' }}>Compte courant</option>
                                <option value="epargne" {{ (($filters['type'] ?? '') === 'epargne') ? 'selected' : '' }}>Compte épargne</option>
                                <option value="mobile_money" {{ (($filters['type'] ?? '') === 'mobile_money') ? 'selected' : '' }}>Mobile money</option>
                            </select>
                        </div>
                        <div class="input-group" style="width: 170px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-wallet2"></i></span>
                            <select name="solde" class="form-select border-start-0 bg-white">
                                <option value="">Solde</option>
                                <option value="actif" {{ (($filters['solde'] ?? '') === 'actif') ? 'selected' : '' }}>Actif</option>
                                <option value="credit" {{ (($filters['solde'] ?? '') === 'credit') ? 'selected' : '' }}>Crédit</option>
                                <option value="debit" {{ (($filters['solde'] ?? '') === 'debit') ? 'selected' : '' }}>Débit</option>
                            </select>
                        </div>
                        <div class="input-group flex-grow-1" style="min-width: 220px; max-width: 360px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control border-start-0 bg-white" placeholder="Recherche..." />
                        </div>
                        <div class="input-group" style="width: 160px;">
                            <span class="input-group-text bg-white border-end-0">Du</span>
                            <input type="date" name="date_debut" value="{{ $filters['date_debut'] ?? '' }}" class="form-control border-start-0 bg-white" />
                        </div>
                        <div class="input-group" style="width: 160px;">
                            <span class="input-group-text bg-white border-end-0">Au</span>
                            <input type="date" name="date_fin" value="{{ $filters['date_fin'] ?? '' }}" class="form-control border-start-0 bg-white" />
                        </div>
                    </div>
                    <div class="d-flex flex-row align-items-center justify-content-end gap-2 ms-auto flex-wrap">
                        <a href="{{ route('admin.comptabilite.banques') }}" class="btn btn-light btn-sm px-4">Réinitialiser</a>
                        <button type="submit" class="btn btn-light btn-sm px-4"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                        <button type="button" class="btn btn-primary btn-sm px-4" data-bs-toggle="modal" data-bs-target="#createBankAccountModal"><i class="bi bi-plus-lg me-2"></i>Nouvelle banque</button>
                    </div>
                </div>
            </form>

            <div class="dg-kpi-grid">
                @foreach(($summary ?? []) as $item)
                    <x-dg.kpi :label="$item['label']" :value="$item['value']" :hint="$item['change']"
                        :icon="['bi-bank', 'bi-wallet2', 'bi-box-arrow-in-down', 'bi-box-arrow-up'][$loop->index] ?? null"
                        :color="['indigo', 'blue', 'green', 'red'][$loop->index] ?? 'navy'" />
                @endforeach
            </div>

            @php
                $accountTones = ['blue', 'green', 'orange', 'purple'];
            @endphp
            <div class="dg-account-grid">
                @forelse(($bankAccounts ?? []) as $index => $bank)
                    @php
                        $isUp = ($bank['movement_direction'] ?? 'up') === 'up';
                    @endphp
                    <div class="dg-account-card dg-tone-{{ ($bank['account_type'] ?? '') === 'mobile_money' ? 'cyan' : $accountTones[$loop->index % 4] }}">
                        <div class="dg-account-card__head">
                            <div>
                                <div class="dg-account-card__name">{{ $bank['name'] }}</div>
                                @if(($bank['bank'] ?? '') !== '' && strcasecmp($bank['bank'], $bank['name']) !== 0)
                                    <div class="dg-account-card__meta">{{ $bank['bank'] }}</div>
                                @endif
                            </div>
                            <span class="dg-tile dg-tile--sm"><i class="bi {{ ($bank['account_type'] ?? '') === 'mobile_money' ? 'bi-phone' : 'bi-bank' }}" aria-hidden="true"></i></span>
                        </div>
                        <div class="dg-account-card__balance">{{ money((float) ($bank['amount'] ?? 0)) }}</div>
                        <div class="dg-account-card__number">{{ $bank['account_number'] }}</div>
                        <dl class="dg-account-card__lines">
                            <div><dt>Statut</dt><dd><span class="dg-badge dg-badge--{{ ($bank['status'] ?? 'credit') === 'credit' ? 'success' : 'warning' }}">{{ ($bank['status'] ?? 'credit') === 'credit' ? 'Crédit' : 'Débit' }}</span></dd></div>
                            <div><dt>Ouverture période</dt><dd>{{ money((float) ($bank['period_initial_balance'] ?? 0)) }}</dd></div>
                            <div><dt>{{ $bank['movement_label'] ?? 'Solde initial' }}</dt><dd class="{{ $isUp ? 'dg-amount-positive' : 'dg-amount-negative' }}">{{ $isUp ? '+' : '-' }}{{ money((float) ($bank['movement_amount'] ?? 0)) }}</dd></div>
                        </dl>

                        <div class="dg-account-card__actions">
                                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm bank-transaction-trigger"
                                        data-account-id="{{ $bank['id'] ?? $index }}"
                                        data-account-name="{{ $bank['name'] }}"
                                        data-account-bank="{{ $bank['bank'] }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#transactionBankModal"><i class="bi bi-plus-lg"></i>Transaction</button>
                                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm bank-details-trigger"
                                        data-account-id="{{ $bank['id'] ?? $index }}"
                                        data-account-name="{{ $bank['name'] }}"
                                        data-account-bank="{{ $bank['bank'] }}"
                                        data-account-number="{{ $bank['account_number'] }}"
                                        data-account-amount="{{ $bank['amount'] }}"
                                        data-account-current-amount="{{ $bank['current_amount'] ?? $bank['amount'] }}"
                                        data-account-type="{{ $bank['account_type'] ?? 'compte_courant' }}"
                                        data-account-status="{{ $bank['status'] ?? 'credit' }}"
                                        data-account-label="{{ $bank['movement_label'] ?? 'Solde initial' }}"
                                        data-account-movement="{{ $bank['movement_amount'] ?? 0 }}"
                                        data-transactions='@json($bank['transactions'] ?? [])'
                                        data-bs-toggle="modal"
                                        data-bs-target="#detailsBankModal">Détails</button>
                                <button type="button" class="dg-icon-btn dg-icon-btn--sm bank-edit-trigger" title="Modifier" aria-label="Modifier {{ $bank['name'] }}"
                                        data-account-id="{{ $bank['id'] ?? $index }}"
                                        data-account-name="{{ $bank['name'] }}"
                                        data-account-bank="{{ $bank['bank'] }}"
                                        data-account-number="{{ $bank['account_number'] }}"
                                        data-account-type="{{ $bank['account_type'] ?? 'compte_courant' }}"
                                        data-account-status="{{ $bank['status'] ?? 'credit' }}"
                                        data-account-short="{{ $bank['short_name'] ?? 'BC' }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editBankAccountModal-{{ $bank['id'] ?? $index }}"><i class="bi bi-pencil-square"></i></button>
                                <form method="POST" action="{{ route('admin.comptabilite.banques.destroy', $bank['id'] ?? $index) }}" onsubmit="return confirm('Supprimer ce compte bancaire ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dg-icon-btn dg-icon-btn--sm dg-icon-btn--danger" title="Supprimer" aria-label="Supprimer {{ $bank['name'] }}"><i class="bi bi-trash"></i></button>
                                </form>
                        </div>
                    </div>

                    <div class="modal fade" id="editBankAccountModal-{{ $bank['id'] ?? $index }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.comptabilite.banques.update', $bank['id'] ?? $index) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Modifier le compte bancaire</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Nom du compte</label>
                                                <input type="text" name="name" class="form-control" value="{{ $bank['name'] }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Banque</label>
                                                <input type="text" name="bank" class="form-control" value="{{ $bank['bank'] }}" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Sigle</label>
                                                <input type="text" name="short_name" class="form-control" value="{{ $bank['short_name'] ?? 'BC' }}" maxlength="10" required>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Numéro de compte</label>
                                                <input type="text" name="account_number" class="form-control" value="{{ $bank['account_number'] }}" required>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Type</label>
                                                <select name="account_type" class="form-select" required>
                                                    <option value="compte_courant" {{ (($bank['account_type'] ?? 'compte_courant') === 'compte_courant') ? 'selected' : '' }}>Compte courant</option>
                                                    <option value="epargne" {{ (($bank['account_type'] ?? '') === 'epargne') ? 'selected' : '' }}>Compte épargne</option>
                                                    <option value="mobile_money" {{ (($bank['account_type'] ?? '') === 'mobile_money') ? 'selected' : '' }}>Mobile money</option>
                                                </select>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Statut</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="credit" {{ (($bank['status'] ?? 'credit') === 'credit') ? 'selected' : '' }}>Crédit</option>
                                                    <option value="debit" {{ (($bank['status'] ?? 'credit') === 'debit') ? 'selected' : '' }}>Débit</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="dg-card dg-empty-state" style="grid-column: 1 / -1">Aucun compte bancaire trouvé pour ce filtre.</div>
                @endforelse
            </div>

            <div class="modal fade" id="createBankAccountModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.comptabilite.banques.storeAccount') }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Créer un compte bancaire</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nom du compte</label>
                                        <input type="text" name="name" class="form-control" placeholder="Ex: Domiciliation principale" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Banque</label>
                                        <input type="text" name="bank" class="form-control" placeholder="Ex: UBA" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Sigle</label>
                                        <input type="text" name="short_name" class="form-control" placeholder="UBA" maxlength="10" required>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">Numéro de compte</label>
                                        <input type="text" name="account_number" class="form-control" placeholder="000000123456" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Type</label>
                                        <select name="account_type" class="form-select" required>
                                            <option value="compte_courant">Compte courant</option>
                                            <option value="epargne">Compte épargne</option>
                                            <option value="mobile_money">Mobile money</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Solde initial</label>
                                        <input type="number" name="initial_balance" class="form-control" min="0" step="0.01" value="0" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Statut</label>
                                        <select name="status" class="form-select" required>
                                            <option value="credit">Crédit</option>
                                            <option value="debit">Débit</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="transactionBankModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.comptabilite.banques.storeTransaction') }}">
                            @csrf
                            <input type="hidden" name="account_id" id="bankTransactionAccountId">
                            <div class="modal-header">
                                <h5 class="modal-title">Nouvelle transaction</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Compte</label>
                                    <input type="text" id="bankTransactionAccountName" class="form-control" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Type</label>
                                    <select name="transaction_type" class="form-select" required>
                                        <option value="credit">Crédit</option>
                                        <option value="debit">Débit</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Catégorie de dépense</label>
                                    <select name="expense_category_id" class="form-select">
                                        <option value="">Aucune catégorie</option>
                                        @foreach(($expenseCategories ?? []) as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Obligatoire pour une sortie.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Libellé</label>
                                    <input type="text" name="label" class="form-control" placeholder="Ex: Paiement fournisseur" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Montant</label>
                                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Commentaire</label>
                                    <textarea name="comment" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade bank-details-modal" id="detailsBankModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Détails du compte</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="bank-summary-grid mb-4">
                                <div class="bank-summary-item">
                                    <div class="text-muted fs-7 text-uppercase">Compte</div>
                                    <div id="bankDetailsName" class="fw-bold fs-5 text-dark"></div>
                                </div>
                                <div class="bank-summary-item">
                                    <div class="text-muted fs-7 text-uppercase">Banque</div>
                                    <div id="bankDetailsBank" class="fw-bold"></div>
                                </div>
                                <div class="bank-summary-item">
                                    <div class="text-muted fs-7 text-uppercase">Numéro</div>
                                    <div id="bankDetailsNumber" class="fw-bold"></div>
                                </div>
                                <div class="bank-summary-item">
                                    <div class="text-muted fs-7 text-uppercase">Type</div>
                                    <div id="bankDetailsType" class="fw-bold"></div>
                                </div>
                                <div class="bank-summary-item">
                                    <div class="text-muted fs-7 text-uppercase">Solde</div>
                                    <div id="bankDetailsAmount" class="fw-bold fs-4 text-dark"></div>
                                </div>
                                <div class="bank-summary-item">
                                    <div class="text-muted fs-7 text-uppercase">Dernier mouvement</div>
                                    <div id="bankDetailsMovement" class="fw-bold"></div>
                                </div>
                            </div>

                            <div class="mt-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-bold text-dark">Historique des transactions</div>
                                    <span class="badge badge-light-primary">Derniers mouvements</span>
                                </div>

                                <div class="bank-details-toolbar mb-3">
                                    <div class="toolbar-filters">
                                        <input id="bankDetailsSearch" type="text" class="form-control" placeholder="Rechercher..." style="width: 200px;">
                                        <select id="bankDetailsTypeFilter" class="form-select" style="width: 150px;">
                                            <option value="">Tous</option>
                                            <option value="credit">Crédit</option>
                                            <option value="debit">Débit</option>
                                        </select>
                                        <input id="bankDetailsDateStart" type="date" class="form-control" aria-label="Date début" title="Date début" style="width: 155px;">
                                        <input id="bankDetailsDateEnd" type="date" class="form-control" aria-label="Date fin" title="Date fin" style="width: 155px;">
                                    </div>
                                    <div class="toolbar-actions">
                                        <button type="button" class="btn btn-sm btn-light" id="bankDetailsSelectAll">Tout sélectionner</button>
                                        <button type="button" class="btn btn-sm btn-light" id="bankDetailsPrintSelected">Imprimer sélection</button>
                                        <button type="button" class="btn btn-sm btn-light" id="bankDetailsPrintAll">Imprimer tout</button>
                                        <button type="button" class="btn btn-sm btn-success" id="bankDetailsExportExcel">Excel</button>
                                        <button type="button" class="btn btn-sm btn-danger" id="bankDetailsExportPdf">PDF</button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table id="bankDetailsTransactionTable" class="table table-sm align-middle mb-0 bank-details-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 35px;"><input type="checkbox" id="bankDetailsHeaderCheckbox" aria-label="Sélectionner tout" /></th>
                                                <th>Date</th>
                                                <th>Libellé</th>
                                                <th>Type</th>
                                                <th>Entrée</th>
                                                <th>Sortie</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
            <script>
                let bankDetailsRows = [];
                let bankDetailsFilter = { search: '', type: '', dateStart: '', dateEnd: '' };
                let bankDetailsCurrentPage = 1;
                const bankDetailsPageSize = 5;
                let bankDetailsTable = null;
                let bankDetailsCurrentBalance = 0;

                function formatAmount(value) {
                    return window.formatMoney(Number(value || 0));
                }

                function getFilteredRows() {
                    return bankDetailsRows.filter((row) => {
                        const matchesSearch = !bankDetailsFilter.search || [row.date, row.label, row.typeLabel].join(' ').toLowerCase().includes(bankDetailsFilter.search.toLowerCase());
                        const matchesType = !bankDetailsFilter.type || row.type === bankDetailsFilter.type;
                        const matchesStart = !bankDetailsFilter.dateStart || row.dateIso >= bankDetailsFilter.dateStart;
                        const matchesEnd = !bankDetailsFilter.dateEnd || row.dateIso <= bankDetailsFilter.dateEnd;
                        return matchesSearch && matchesType && matchesStart && matchesEnd;
                    });
                }

                function renderBankDetailsRows() {
                    const tableBody = document.querySelector('#bankDetailsTransactionTable tbody');
                    const statsEl = document.getElementById('bankDetailsStats');
                    const prevBtn = document.getElementById('bankDetailsPrev');
                    const nextBtn = document.getElementById('bankDetailsNext');
                    if (!tableBody) return;

                    const filtered = getFilteredRows();
                    const periodOpening = bankDetailsCurrentBalance - bankDetailsRows.reduce((total, row) => total + (row.type === 'credit' ? row.amount : -row.amount), 0)
                        + bankDetailsRows.filter((row) => !bankDetailsFilter.dateStart || row.dateIso < bankDetailsFilter.dateStart)
                            .reduce((total, row) => total + (row.type === 'credit' ? row.amount : -row.amount), 0);
                    const periodRows = bankDetailsRows.filter((row) => (!bankDetailsFilter.dateStart || row.dateIso >= bankDetailsFilter.dateStart)
                        && (!bankDetailsFilter.dateEnd || row.dateIso <= bankDetailsFilter.dateEnd));
                    const periodBalance = periodOpening + periodRows.reduce((total, row) => total + (row.type === 'credit' ? row.amount : -row.amount), 0);
                    const amountEl = document.getElementById('bankDetailsAmount');
                    if (amountEl) amountEl.textContent = formatAmount(periodBalance);
                    const totalPages = Math.max(1, Math.ceil(filtered.length / bankDetailsPageSize));
                    if (bankDetailsCurrentPage > totalPages) {
                        bankDetailsCurrentPage = totalPages;
                    }

                    if (!filtered.length) {
                        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Aucune transaction enregistrée</td></tr>';
                        if (statsEl) statsEl.textContent = '0 résultat';
                        if (prevBtn) prevBtn.disabled = true;
                        if (nextBtn) nextBtn.disabled = true;
                        return;
                    }

                    const startIndex = (bankDetailsCurrentPage - 1) * bankDetailsPageSize;
                    const paginatedRows = filtered.slice(startIndex, startIndex + bankDetailsPageSize);

                    tableBody.innerHTML = paginatedRows.map((row) => {
                        const isCredit = row.type === 'credit';
                        const badgeClass = isCredit ? 'badge-light-success' : 'badge-light-warning';
                        const amountClass = isCredit ? 'text-success' : 'text-danger';
                        const sign = isCredit ? '+' : '-';
                        const entryAmount = isCredit ? formatAmount(row.amount) : window.formatMoney(0);
                        const exitAmount = !isCredit ? formatAmount(row.amount) : window.formatMoney(0);

                        return '<tr data-row-id="' + row.id + '">' +
                            '<td><input type="checkbox" class="bank-details-select-row" value="' + row.id + '" /></td>' +
                            '<td>' + row.date + '</td>' +
                            '<td>' + row.label + '</td>' +
                            '<td><span class="badge ' + badgeClass + '">' + row.typeLabel + '</span></td>' +
                            '<td class="fw-bold ' + (isCredit ? 'text-success' : 'text-muted') + '">' + entryAmount + '</td>' +
                            '<td class="fw-bold ' + (!isCredit ? 'text-danger' : 'text-muted') + '">' + exitAmount + '</td>' +
                            '</tr>';
                    }).join('');

                    if (statsEl) {
                        statsEl.textContent = 'Affichage ' + (startIndex + 1) + '-' + Math.min(startIndex + paginatedRows.length, filtered.length) + ' sur ' + filtered.length + ' résultats';
                    }

                    if (prevBtn) prevBtn.disabled = bankDetailsCurrentPage <= 1;
                    if (nextBtn) nextBtn.disabled = bankDetailsCurrentPage >= totalPages;
                    updateBankDetailsSelectAllState();
                }

                function updateBankDetailsSelectAllState() {
                    const headerCheckbox = document.getElementById('bankDetailsHeaderCheckbox');
                    const rowCheckboxes = document.querySelectorAll('.bank-details-select-row');
                    if (!headerCheckbox || !rowCheckboxes.length) return;
                    const checkedCount = Array.from(rowCheckboxes).filter((cb) => cb.checked).length;
                    headerCheckbox.checked = checkedCount === rowCheckboxes.length && rowCheckboxes.length > 0;
                    headerCheckbox.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;
                }

                function getSelectedRows() {
                    return bankDetailsRows.filter((row) => {
                        const rowCheckbox = document.querySelector('.bank-details-select-row[value="' + row.id + '"]');
                        return rowCheckbox && rowCheckbox.checked;
                    });
                }

                function printRows(rows, title) {
                    const printWindow = window.open('', '_blank', 'width=1200,height=800');
                    if (!printWindow) {
                        alert('Le navigateur a bloqué la fenêtre d’impression. Autorisez les pop-ups pour imprimer la sélection.');
                        return;
                    }

                    const html = '<html><head><title>' + title + '</title>' +
                        '<style>' +
                        'body{font-family:Arial,sans-serif;padding:24px;color:#111} table{width:100%;border-collapse:collapse;margin-top:18px} th,td{border:1px solid #dfe7f4;padding:10px;text-align:left} th{background:#f8f9ff;color:#445066} .title{font-size:22px;font-weight:700;margin-bottom:8px} .meta{font-size:13px;color:#475569;margin-bottom:20px} .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#eaf4ff;color:#0f172a;font-weight:600} .success{color:#15803d;font-weight:700} .danger{color:#b91c1c;font-weight:700}' +
                        '</style></head><body>' +
                        '<div class="title">' + title + '</div>' +
                        '<div class="meta">' + new Date().toLocaleDateString('fr-FR') + '</div>' +
                        '<table><thead><tr><th>Date</th><th>Libellé</th><th>Type</th><th>Entrée</th><th>Sortie</th></tr></thead><tbody>' + rows.map((row) => {
                            const entry = row.type === 'credit' ? '<span class="success">' + formatAmount(row.amount) + '</span>' : window.formatMoney(0);
                            const exit = row.type === 'debit' ? '<span class="danger">' + formatAmount(row.amount) + '</span>' : window.formatMoney(0);
                            return '<tr><td>' + row.date + '</td><td>' + row.label + '</td><td><span class="badge">' + row.typeLabel + '</span></td><td>' + entry + '</td><td>' + exit + '</td></tr>';
                        }).join('') + '</tbody></table></body></html>';

                    printWindow.document.write(html);
                    printWindow.document.close();
                    printWindow.focus();
                    setTimeout(function () { printWindow.print(); }, 500);
                }

                function exportRowsToExcel(rows, fileName) {
                    if (!rows.length) {
                        alert('Aucune donnée à exporter.');
                        return;
                    }

                    const worksheet = XLSX.utils.json_to_sheet(rows.map((row) => ({
                        Date: row.date,
                        Libellé: row.label,
                        Type: row.typeLabel,
                        Entrée: row.type === 'credit' ? row.amount : 0,
                        Sortie: row.type === 'debit' ? row.amount : 0,
                    })));
                    const workbook = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(workbook, worksheet, 'Transactions');
                    XLSX.writeFile(workbook, fileName + '.xlsx');
                }

                function exportRowsToPdf(rows, fileName) {
                    if (!rows.length) {
                        alert('Aucune donnée à exporter.');
                        return;
                    }

                    const docDefinition = {
                        content: [
                            { text: fileName, style: 'header' },
                            {
                                table: {
                                    headerRows: 1,
                                    widths: ['18%', '28%', '18%', '18%', '18%'],
                                    body: [
                                        ['Date', 'Libellé', 'Type', 'Entrée', 'Sortie'],
                                        ...rows.map((row) => [
                                            row.date,
                                            row.label,
                                            row.typeLabel,
                                            row.type === 'credit' ? formatAmount(row.amount) : window.formatMoney(0),
                                            row.type === 'debit' ? formatAmount(row.amount) : window.formatMoney(0),
                                        ])
                                    ]
                                }
                            }
                        ],
                        styles: {
                            header: { fontSize: 18, bold: true, margin: [0, 0, 0, 10] }
                        },
                        defaultStyle: { fontSize: 8 }
                    };

                    pdfMake.createPdf(docDefinition).download(fileName + '.pdf');
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const searchInput = document.getElementById('bankDetailsSearch');
                    const typeFilter = document.getElementById('bankDetailsTypeFilter');
                    const dateStartInput = document.getElementById('bankDetailsDateStart');
                    const dateEndInput = document.getElementById('bankDetailsDateEnd');
                    const prevBtn = document.getElementById('bankDetailsPrev');
                    const nextBtn = document.getElementById('bankDetailsNext');
                    const selectAllBtn = document.getElementById('bankDetailsSelectAll');
                    const printSelectedBtn = document.getElementById('bankDetailsPrintSelected');
                    const printAllBtn = document.getElementById('bankDetailsPrintAll');
                    const exportExcelBtn = document.getElementById('bankDetailsExportExcel');
                    const exportPdfBtn = document.getElementById('bankDetailsExportPdf');
                    const headerCheckbox = document.getElementById('bankDetailsHeaderCheckbox');

                    if (searchInput) {
                        searchInput.addEventListener('input', function () {
                            bankDetailsCurrentPage = 1;
                            bankDetailsFilter.search = this.value.trim();
                            renderBankDetailsRows();
                        });
                    }

                    if (typeFilter) {
                        typeFilter.addEventListener('change', function () {
                            bankDetailsCurrentPage = 1;
                            bankDetailsFilter.type = this.value;
                            renderBankDetailsRows();
                        });
                    }

                    [dateStartInput, dateEndInput].forEach(function (input) {
                        if (input) {
                            input.addEventListener('change', function () {
                                bankDetailsCurrentPage = 1;
                                bankDetailsFilter.dateStart = dateStartInput ? dateStartInput.value : '';
                                bankDetailsFilter.dateEnd = dateEndInput ? dateEndInput.value : '';
                                renderBankDetailsRows();
                            });
                        }
                    });

                    if (prevBtn) {
                        prevBtn.addEventListener('click', function () {
                            if (bankDetailsCurrentPage > 1) {
                                bankDetailsCurrentPage -= 1;
                                renderBankDetailsRows();
                            }
                        });
                    }

                    if (nextBtn) {
                        nextBtn.addEventListener('click', function () {
                            const filtered = getFilteredRows();
                            if (bankDetailsCurrentPage < Math.max(1, Math.ceil(filtered.length / bankDetailsPageSize))) {
                                bankDetailsCurrentPage += 1;
                                renderBankDetailsRows();
                            }
                        });
                    }

                    if (selectAllBtn) {
                        selectAllBtn.addEventListener('click', function () {
                            const rowCheckboxes = document.querySelectorAll('.bank-details-select-row');
                            const shouldCheck = Array.from(rowCheckboxes).some((cb) => !cb.checked);
                            rowCheckboxes.forEach((cb) => cb.checked = shouldCheck);
                            updateBankDetailsSelectAllState();
                        });
                    }

                    if (headerCheckbox) {
                        headerCheckbox.addEventListener('change', function () {
                            document.querySelectorAll('.bank-details-select-row').forEach((cb) => cb.checked = this.checked);
                        });
                    }

                    document.addEventListener('change', function (event) {
                        if (event.target && event.target.classList.contains('bank-details-select-row')) {
                            updateBankDetailsSelectAllState();
                        }
                    });

                    if (printSelectedBtn) {
                        printSelectedBtn.addEventListener('click', function () {
                            const selected = getSelectedRows();
                            if (!selected.length) {
                                alert('Sélectionnez au moins une ligne pour imprimer.');
                                return;
                            }
                            printRows(selected.map((row) => ({
                                id: row.id,
                                date: row.date,
                                label: row.label,
                                type: row.type,
                                typeLabel: row.typeLabel,
                                amount: row.amount
                            })), 'Transactions bancaires sélectionnées');
                        });
                    }

                    if (printAllBtn) {
                        printAllBtn.addEventListener('click', function () {
                            if (!bankDetailsRows.length) {
                                alert('Aucune donnée à imprimer.');
                                return;
                            }
                            printRows(bankDetailsRows.map((row) => ({
                                id: row.id,
                                date: row.date,
                                label: row.label,
                                type: row.type,
                                typeLabel: row.typeLabel,
                                amount: row.amount
                            })), 'Historique complet des transactions');
                        });
                    }

                    if (exportExcelBtn) {
                        exportExcelBtn.addEventListener('click', function () {
                            const selected = getSelectedRows();
                            const rowsToExport = selected.length ? selected : bankDetailsRows;
                            exportRowsToExcel(rowsToExport.map((row) => ({
                                date: row.date,
                                label: row.label,
                                typeLabel: row.typeLabel,
                                amount: row.amount,
                                type: row.type,
                                entree: row.type === 'credit' ? row.amount : 0,
                                sortie: row.type === 'debit' ? row.amount : 0,
                            })), selected.length ? 'transactions-selectionnees' : 'transactions-toutes');
                        });
                    }

                    if (exportPdfBtn) {
                        exportPdfBtn.addEventListener('click', function () {
                            const selected = getSelectedRows();
                            const rowsToExport = selected.length ? selected : bankDetailsRows;
                            exportRowsToPdf(rowsToExport.map((row) => ({
                                date: row.date,
                                label: row.label,
                                typeLabel: row.typeLabel,
                                amount: row.amount,
                                type: row.type,
                            })), selected.length ? 'transactions-selectionnees' : 'transactions-toutes');
                        });
                    }
                });

                document.addEventListener('click', function(event) {
                    const transactionTrigger = event.target.closest('.bank-transaction-trigger');
                    if (transactionTrigger) {
                        const accountId = transactionTrigger.getAttribute('data-account-id');
                        const name = transactionTrigger.getAttribute('data-account-name');
                        const bank = transactionTrigger.getAttribute('data-account-bank');
                        document.getElementById('bankTransactionAccountId').value = accountId;
                        document.getElementById('bankTransactionAccountName').value = name + ' / ' + bank;
                    }

                    const detailsTrigger = event.target.closest('.bank-details-trigger');
                    if (detailsTrigger) {
                        const name = detailsTrigger.getAttribute('data-account-name');
                        const bank = detailsTrigger.getAttribute('data-account-bank');
                        const number = detailsTrigger.getAttribute('data-account-number');
                        const amount = Number(detailsTrigger.getAttribute('data-account-amount') || 0);
                        const currentAmount = Number(detailsTrigger.getAttribute('data-account-current-amount') || amount);
                        const type = detailsTrigger.getAttribute('data-account-type');
                        const status = detailsTrigger.getAttribute('data-account-status');
                        const label = detailsTrigger.getAttribute('data-account-label');
                        const movement = Number(detailsTrigger.getAttribute('data-account-movement') || 0);
                        let transactions = [];
                        try {
                            transactions = JSON.parse(detailsTrigger.getAttribute('data-transactions') || '[]');
                        } catch (e) {
                            transactions = [];
                        }

                        document.getElementById('bankDetailsName').textContent = name;
                        document.getElementById('bankDetailsBank').textContent = bank;
                        document.getElementById('bankDetailsNumber').textContent = number;
                        document.getElementById('bankDetailsType').textContent = type === 'compte_courant' ? 'Compte courant' : type === 'epargne' ? 'Compte épargne' : 'Mobile money';
                        document.getElementById('bankDetailsAmount').textContent = window.formatMoney(amount);
                        document.getElementById('bankDetailsMovement').textContent = label + ' - ' + (status === 'credit' ? '+' : '-') + window.formatMoney(movement);

                        bankDetailsRows = (transactions.length ? transactions.map((tx, index) => ({
                            id: index + 1,
                            date: tx.date || '-',
                            dateIso: tx.date_iso || '',
                            label: tx.label || '-',
                            type: tx.type || 'credit',
                            typeLabel: tx.type === 'credit' ? 'Crédit' : 'Débit',
                            amount: Number(tx.amount || 0)
                        })) : []);

                        bankDetailsCurrentBalance = currentAmount;
                        bankDetailsFilter = { search: '', type: '', dateStart: '', dateEnd: '' };
                        bankDetailsCurrentPage = 1;
                        const searchInput = document.getElementById('bankDetailsSearch');
                        const typeFilter = document.getElementById('bankDetailsTypeFilter');
                        const dateStartInput = document.getElementById('bankDetailsDateStart');
                        const dateEndInput = document.getElementById('bankDetailsDateEnd');
                        if (searchInput) searchInput.value = '';
                        if (typeFilter) typeFilter.value = '';
                        if (dateStartInput) dateStartInput.value = '';
                        if (dateEndInput) dateEndInput.value = '';

                        renderBankDetailsRows();
                    }
                });
            </script>
        @endif

        @if(($moduleType ?? '') === 'caisses')
            <div class="dg-card dg-card--flush mb-6">
                <button class="dg-collapse-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#cashStateCollapse" aria-expanded="false" aria-controls="cashStateCollapse">
                    <span class="d-flex align-items-center gap-3">
                        <span class="dg-tile dg-tone-yellow"><i class="bi bi-cash-stack"></i></span>
                        <span>
                            <span class="dg-collapse-toggle__title">État général des caisses</span>
                            <span class="dg-collapse-toggle__subtitle">Suivi consolidé des caisses et comptes mobile money</span>
                        </span>
                    </span>
                    <span class="dg-collapse-toggle__end">
                        <span class="dg-badge dg-badge--neutral">{{ collect($accounts ?? [])->where('is_active', true)->count() }} compte(s) actif(s)</span>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </span>
                </button>
                <div id="cashStateCollapse" class="collapse">
                    <div class="dg-collapse-body">
                        @if(!empty($accounts) && count($accounts))
                            <div class="dg-kpi-grid">
                                @foreach(($summary ?? []) as $item)
                                    <x-dg.kpi :label="$item['label']" :value="$item['value']" :hint="$item['change']"
                                        :icon="['bi-calendar-event', 'bi-box-arrow-in-down', 'bi-box-arrow-up', 'bi-wallet2'][$loop->index] ?? null"
                                        :color="['indigo', 'green', 'red', 'blue'][$loop->index] ?? 'navy'" />
                                @endforeach
                            </div>

                            <div class="dg-account-grid">
                                @foreach($accounts as $account)
                                    <div class="dg-account-card {{ $account->is_active ? ($account->account_type === 'mobile_money' ? 'dg-tone-cyan' : 'dg-tone-yellow') : 'dg-account-card--muted' }}">
                                        <div class="dg-account-card__head">
                                            <div>
                                                <div class="dg-account-card__name">{{ $account->name }}</div>
                                                <div class="dg-account-card__meta">{{ $account->account_type === 'mobile_money' ? 'Mobile money' : 'Caisse' }}</div>
                                            </div>
                                            <span class="dg-tile dg-tile--sm"><i class="bi {{ $account->account_type === 'mobile_money' ? 'bi-phone' : 'bi-cash' }}" aria-hidden="true"></i></span>
                                        </div>
                                        <div class="dg-account-card__balance">{{ money((float) ($account->period_balance ?? $account->balance)) }}</div>
                                        <dl class="dg-account-card__lines">
                                            <div><dt>Statut</dt><dd><span class="dg-badge dg-badge--{{ $account->is_active ? 'success' : 'neutral' }}">{{ $account->is_active ? 'Actif' : 'Inactif' }}</span></dd></div>
                                            <div><dt>Ouverture période</dt><dd>{{ money((float) ($account->period_initial_balance ?? $account->initial_balance)) }}</dd></div>
                                            <div><dt>Solde à la création</dt><dd>{{ money((float) $account->initial_balance) }}</dd></div>
                                        </dl>
                                        <div class="dg-account-card__actions">
                                            <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#cashAccountDetailsModal-{{ $account->id }}" aria-label="Voir les détails de {{ $account->name }}"><i class="bi bi-eye"></i>Voir</button>
                                            <form method="POST" action="{{ route('admin.comptabilite.caisses.toggleStatus', $account) }}">
                                                @csrf
                                                <button type="submit" class="dg-btn dg-btn--outline dg-btn--sm">{{ $account->is_active ? 'Fermer' : 'Ouvrir' }}</button>
                                            </form>
                                            <button type="button" class="dg-icon-btn dg-icon-btn--sm" data-bs-toggle="modal" data-bs-target="#editCashAccountModal-{{ $account->id }}" title="Modifier" aria-label="Modifier {{ $account->name }}"><i class="bi bi-pencil-square"></i></button>
                                            <form method="POST" action="{{ route('admin.comptabilite.caisses.destroyAccount', $account) }}" onsubmit="return confirm('Supprimer cette caisse ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dg-icon-btn dg-icon-btn--sm dg-icon-btn--danger" title="Supprimer" aria-label="Supprimer {{ $account->name }}"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>

                    <div class="modal fade" id="cashAccountDetailsModal-{{ $account->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title mb-1">Détails de {{ $account->name }}</h5>
                                        <div class="text-muted fs-7">Mouvements du {{ $filters['date_debut'] ?? '-' }} au {{ $filters['date_fin'] ?? '-' }}</div>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                </div>
                                <div class="modal-body">
                                    @php
                                        $accountMovements = collect($movements ?? [])->where('cash_account_id', $account->id);
                                        $accountEntries = $accountMovements->where('movement_type', 'entry')->sum('amount');
                                        $accountExits = $accountMovements->where('movement_type', 'exit')->sum('amount');
                                    @endphp
                                    <div class="row g-3 mb-5">
                                        <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted fs-8 text-uppercase">Solde période</div><div class="fs-4 fw-bold">{{ money((float) ($account->period_balance ?? $account->balance)) }}</div></div></div>
                                        <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted fs-8 text-uppercase">Total entrées</div><div class="fs-4 fw-bold text-success">{{ money((float) $accountEntries) }}</div></div></div>
                                        <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted fs-8 text-uppercase">Total sorties</div><div class="fs-4 fw-bold text-danger">{{ money((float) $accountExits) }}</div></div></div>
                                    </div>
                                    @if($accountMovements->isEmpty())
                                        <div class="alert alert-light-info mb-0">Aucun mouvement pour cette caisse sur la période sélectionnée.</div>
                                    @else
                                        <div class="table-responsive">
                                            <table class="table table-row-dashed align-middle">
                                                <thead><tr><th>Date</th><th>Libellé</th><th>Type</th><th>Montant</th><th>Mode</th><th>Référence</th></tr></thead>
                                                <tbody>
                                                    @foreach($accountMovements as $movement)
                                                        <tr>
                                                            <td>{{ $movement->movement_date?->format('d/m/Y') ?? '-' }}</td>
                                                            <td><span class="fw-bold">{{ $movement->label }}</span>@if($movement->description)<small class="d-block text-muted">{{ $movement->description }}</small>@endif</td>
                                                            <td><span class="badge {{ $movement->movement_type === 'entry' ? 'badge-light-success' : 'badge-light-danger' }}">{{ $movement->movement_type === 'entry' ? 'Entrée' : 'Sortie' }}</span></td>
                                                            <td class="fw-bold">{{ money((float) $movement->amount) }}</td>
                                                            <td>{{ ucfirst($movement->payment_mode ?? 'cash') }}</td>
                                                            <td>{{ $movement->reference ?: '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button></div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="editCashAccountModal-{{ $account->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.comptabilite.caisses.updateAccount', $account) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Modifier la caisse</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nom</label>
                                            <input type="text" name="name" class="form-control" value="{{ $account->name }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Type</label>
                                            <select name="account_type" class="form-select" required>
                                                <option value="caisse" {{ $account->account_type === 'caisse' ? 'selected' : '' }}>Caisse</option>
                                                <option value="mobile_money" {{ $account->account_type === 'mobile_money' ? 'selected' : '' }}>Mobile money</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Solde initial</label>
                                            <input type="number" name="initial_balance" class="form-control" min="0" step="0.01" value="{{ $account->initial_balance }}">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control" rows="3">{{ $account->description }}</textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="dg-empty-state">Aucune caisse enregistrée. Créez votre première caisse avec « Nouvelle caisse ».</div>
                        @endif
                    </div>
                </div>
            </div>

            <form method="GET" class="dg-card row g-3 align-items-end mx-0 mb-6">
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label fw-semibold text-muted">Date début</label>
                    <input type="date" name="date_debut" value="{{ $filters['date_debut'] ?? '' }}" class="form-control" />
                </div>
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label fw-semibold text-muted">Date fin</label>
                    <input type="date" name="date_fin" value="{{ $filters['date_fin'] ?? '' }}" class="form-control" />
                </div>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <label class="form-label fw-semibold text-muted">Recherche</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Référence / libellé" />
                </div>
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label fw-semibold text-muted">Type</label>
                    <select name="type" class="form-select">
                        <option value="">Tous</option>
                        <option value="entry" {{ ($filters['type'] ?? '') === 'entry' ? 'selected' : '' }}>Entrée</option>
                        <option value="exit" {{ ($filters['type'] ?? '') === 'exit' ? 'selected' : '' }}>Sortie</option>
                    </select>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label fw-semibold text-muted">Mode de paiement</label>
                    <select name="payment_mode" class="form-select">
                        <option value="">Tous</option>
                        <option value="cash" {{ ($filters['payment_mode'] ?? '') === 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="mobile_money" {{ ($filters['payment_mode'] ?? '') === 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                        <option value="bank" {{ ($filters['payment_mode'] ?? '') === 'bank' ? 'selected' : '' }}>Banque</option>
                        <option value="transfer" {{ ($filters['payment_mode'] ?? '') === 'transfer' ? 'selected' : '' }}>Virement</option>
                    </select>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label fw-semibold text-muted">Caisse</label>
                    <select name="cash_account_id" class="form-select">
                        <option value="">Toutes les caisses</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ ($filters['cash_account_id'] ?? '') == $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label fw-semibold text-muted">Catégorie</label>
                    <select name="expense_category_id" class="form-select">
                        <option value="">Toutes les catégories</option>
                        @foreach($expenseCategories as $category)
                            <option value="{{ $category->id }}" {{ ($filters['expense_category_id'] ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <label class="form-label fw-semibold text-muted">&nbsp;</label>
                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-light flex-fill text-nowrap"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                        <a href="{{ route('admin.comptabilite.caisses') }}" class="btn btn-light px-3" title="Réinitialiser les filtres" aria-label="Réinitialiser les filtres"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </div>
            </form>

            <div class="dg-card dg-card--table">
                <div class="dg-card__header">
                    <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-green"><i class="bi bi-arrow-left-right"></i></span>Mouvements de caisse</h2>
                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" onclick="window.print()"><i class="bi bi-printer"></i>Imprimer</button>
                </div>
                <div>
                    <div class="table-responsive">
                        <table id="cashMovementTable" class="table table-striped table-hover align-middle mb-0 cash-movement-table">
                            <thead>
                                <tr>
                                    @foreach(($tableColumns ?? []) as $column)
                                        <th>{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Pas de ligne « vide » fusionnée : DataTables la refuse (« Incorrect column count ») et affiche lui-même son message. --}}
                                @foreach(($tableRows ?? []) as $row)
                                    <tr>
                                        @foreach($row as $key => $cell)
                                            @if($key === array_key_last($row))
                                                <td>{!! $cell !!}</td>
                                            @elseif($key === 2)
                                                <td><span class="dg-badge dg-badge--{{ ['Entrée' => 'success', 'Sortie' => 'danger'][$cell] ?? 'neutral' }}">{{ $cell }}</span></td>
                                            @elseif($key === 3 || $key === 6)
                                                <td class="dg-cell-num">{{ $cell }}</td>
                                            @else
                                                <td>{{ $cell }}</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.jQuery && $.fn.DataTable) {
                        $('#cashMovementTable').DataTable({
                            language: {
                                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json',
                                emptyTable: 'Aucun mouvement trouvé pour cette période.'
                            },
                            paging: true,
                            searching: true,
                            ordering: true,
                            pageLength: 10,
                            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'Tous']],
                            order: [[4, 'desc']],
                            columnDefs: [{ targets: -1, orderable: false, searchable: false }]
                        });
                    }
                });
            </script>
        @elseif(($moduleType ?? '') === 'rapports')
            @php
                $typeStyles = [
                    'Entrées' => ['green', 'bi-box-arrow-in-down', 'dg-amount-positive'],
                    'Sorties' => ['red', 'bi-box-arrow-up', 'dg-amount-negative'],
                    'Virements' => ['purple', 'bi-arrow-left-right', ''],
                    'Solde réel' => ['blue', 'bi-wallet2', ''],
                ];
                $flowRow = fn (bool $isIn) => $isIn ? ['green', 'bi-arrow-down-left', 'dg-amount-positive', '+'] : ['red', 'bi-arrow-up-right', 'dg-amount-negative', '-'];
                $treasuryChartHasData = collect($chartData ?? [])->sum('value') > 0;
                $distributionList = collect($distribution ?? []);
            @endphp

            <div class="dg-kpi-grid">
                @foreach(($summary ?? []) as $item)
                    <x-dg.kpi :label="$item['label']" :value="$item['value']" :hint="$item['change']"
                        :icon="['bi-wallet2', 'bi-cash-stack', 'bi-bank', 'bi-arrow-left-right'][$loop->index] ?? null"
                        :color="['blue', 'orange', 'indigo', 'purple'][$loop->index] ?? 'navy'" />
                @endforeach
            </div>

            <div class="dg-grid-2 mb-6">
                <x-dg.card title="Caisses actives" icon="bi-cash-stack" color="orange">
                    <x-slot:actions><span class="dg-badge dg-badge--neutral">{{ ($activeCash ?? collect())->count() }} active(s)</span></x-slot:actions>
                    <div class="dg-account-grid">
                        @forelse(($activeCash ?? collect()) as $account)
                            <div class="dg-account-card {{ $account->account_type === 'mobile_money' ? 'dg-tone-cyan' : 'dg-tone-yellow' }}">
                                <div class="dg-account-card__head">
                                    <div>
                                        <div class="dg-account-card__name">{{ $account->name }}</div>
                                        <div class="dg-account-card__meta">{{ $account->account_type === 'mobile_money' ? 'Mobile money' : 'Caisse' }}</div>
                                    </div>
                                    <span class="dg-tile dg-tile--sm"><i class="bi {{ $account->account_type === 'mobile_money' ? 'bi-phone' : 'bi-cash' }}" aria-hidden="true"></i></span>
                                </div>
                                <div class="dg-account-card__balance">{{ money((float) $account->balance) }}</div>
                                <div class="mt-2"><span class="dg-badge dg-badge--success">Ouverte</span></div>
                            </div>
                        @empty
                            <div class="dg-empty-state" style="grid-column: 1 / -1; min-height: 120px">Aucune caisse active pour cette entreprise.</div>
                        @endforelse
                    </div>
                </x-dg.card>

                <x-dg.card title="Banques actives" icon="bi-bank" color="indigo">
                    <x-slot:actions><span class="dg-badge dg-badge--neutral">{{ count($bankAccounts ?? []) }} compte(s)</span></x-slot:actions>
                    <div class="dg-list">
                        @forelse(($bankAccounts ?? []) as $account)
                            <div class="dg-list-row">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ ['blue', 'green', 'orange', 'purple'][$loop->index % 4] }}"><i class="bi {{ $account->account_type === 'mobile_money' ? 'bi-phone' : 'bi-bank' }}"></i></span>
                                <div class="dg-list-row__body">
                                    <div class="dg-list-row__title">{{ $account->name }}</div>
                                    @if($account->bank_name && strcasecmp($account->bank_name, $account->name) !== 0)
                                        <div class="dg-list-row__sub">{{ $account->bank_name }}</div>
                                    @endif
                                </div>
                                <div class="text-end">
                                    <div class="dg-list-row__value">{{ money((float) $account->current_balance) }}</div>
                                    <span class="dg-badge dg-badge--{{ $account->status === 'credit' ? 'success' : 'warning' }} mt-1">{{ $account->status === 'credit' ? 'Crédit' : 'Débit' }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="dg-empty-state" style="min-height: 120px">Aucun compte bancaire enregistré.</div>
                        @endforelse
                    </div>
                </x-dg.card>
            </div>

            <div class="dg-grid-halves mb-6">
                <x-dg.card title="Statistiques par type de transaction" icon="bi-bar-chart-steps" color="teal">
                    <div class="dg-list">
                        @foreach(($typeStats ?? []) as $stat)
                            @php
                                [$statTone, $statIcon, $statClass] = $typeStyles[$stat['label']] ?? ['navy', 'bi-dot', ''];
                            @endphp
                            <div class="dg-list-row">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ $statTone }}"><i class="bi {{ $statIcon }}"></i></span>
                                <div class="dg-list-row__body"><div class="dg-list-row__title">{{ $stat['label'] }}</div></div>
                                <div class="dg-list-row__value {{ $statClass }}">{{ $stat['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </x-dg.card>

                <x-dg.card title="Dernières opérations de caisse" icon="bi-cash-coin" color="green">
                    <x-slot:actions><span class="dg-badge dg-badge--neutral">5 dernières</span></x-slot:actions>
                    <div class="dg-list">
                        @forelse(($recentCashMovements ?? []) as $movement)
                            @php
                                [$rowTone, $rowIcon, $rowClass, $rowSign] = $flowRow($movement->movement_type === 'entry');
                            @endphp
                            <div class="dg-list-row">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ $rowTone }}"><i class="bi {{ $rowIcon }}"></i></span>
                                <div class="dg-list-row__body">
                                    <div class="dg-list-row__title">{{ $movement->label }}</div>
                                    <div class="dg-list-row__sub">{{ $movement->cashAccount?->name ?? 'Caisse' }} · {{ $movement->movement_date?->format('d/m/Y') ?? '—' }}</div>
                                </div>
                                <div class="dg-list-row__value {{ $rowClass }}">{{ $rowSign }}{{ money((float) $movement->amount) }}</div>
                            </div>
                        @empty
                            <div class="dg-empty-state" style="min-height: 120px">Aucune opération de caisse récente.</div>
                        @endforelse
                    </div>
                </x-dg.card>
            </div>

            <div class="dg-grid-halves mb-6">
                <x-dg.card title="Derniers réapprovisionnements (transferts)" icon="bi-arrow-left-right" color="purple">
                    <x-slot:actions><span class="dg-badge dg-badge--neutral">Virements</span></x-slot:actions>
                    <div class="dg-list">
                        @forelse(($recentTransfers ?? []) as $transfer)
                            @php
                                [$rowTone, $rowIcon, $rowClass, $rowSign] = $flowRow($transfer->transaction_type === 'credit');
                            @endphp
                            <div class="dg-list-row">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ $rowTone }}"><i class="bi {{ $rowIcon }}"></i></span>
                                <div class="dg-list-row__body">
                                    <div class="dg-list-row__title">{{ $transfer->label }}</div>
                                    <div class="dg-list-row__sub">{{ $transfer->transaction_date?->format('d/m/Y') ?? '—' }} · {{ $transfer->bankAccount?->name ?? 'Compte' }}</div>
                                </div>
                                <div class="dg-list-row__value {{ $rowClass }}">{{ $rowSign }}{{ money((float) $transfer->amount) }}</div>
                            </div>
                        @empty
                            <div class="dg-empty-state" style="min-height: 120px">Aucun réapprovisionnement récent.</div>
                        @endforelse
                    </div>
                </x-dg.card>

                <x-dg.card title="Opérations bancaires récentes" icon="bi-bank" color="blue">
                    <x-slot:actions><span class="dg-badge dg-badge--neutral">5 dernières</span></x-slot:actions>
                    <div class="dg-list">
                        @forelse(($recentBankOperations ?? []) as $operation)
                            @php
                                [$rowTone, $rowIcon, $rowClass, $rowSign] = $flowRow($operation->transaction_type === 'credit');
                            @endphp
                            <div class="dg-list-row">
                                <span class="dg-tile dg-tile--sm dg-tone-{{ $rowTone }}"><i class="bi {{ $rowIcon }}"></i></span>
                                <div class="dg-list-row__body">
                                    <div class="dg-list-row__title">{{ $operation->label }}</div>
                                    <div class="dg-list-row__sub">{{ $operation->bankAccount?->name ?? 'Banque' }} · {{ $operation->transaction_date?->format('d/m/Y') ?? '—' }}</div>
                                </div>
                                <div class="dg-list-row__value {{ $rowClass }}">{{ $rowSign }}{{ money((float) $operation->amount) }}</div>
                            </div>
                        @empty
                            <div class="dg-empty-state" style="min-height: 120px">Aucune opération bancaire récente.</div>
                        @endforelse
                    </div>
                </x-dg.card>
            </div>

            <div class="dg-grid-2">
                <x-dg.card title="Évolution des opérations bancaires (6 derniers mois)" icon="bi-bar-chart-line" color="blue" :meta="'Montants en ' . currency_symbol()">
                    <div class="dg-chart">
                        @if($treasuryChartHasData)
                            <canvas id="treasuryBankChart" role="img" aria-label="Volume des opérations bancaires sur six mois"></canvas>
                        @else
                            <div class="dg-chart-empty">
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-bar-chart-line"></i></span>
                                <div><strong>Aucune opération bancaire sur 6 mois</strong>Le volume mensuel des opérations bancaires s’affichera ici.</div>
                            </div>
                        @endif
                    </div>
                </x-dg.card>

                <x-dg.card title="Répartition par type" icon="bi-pie-chart" color="purple">
                    @if($distributionList->isNotEmpty())
                        <div class="dg-donut">
                            <div class="dg-chart"><canvas id="treasuryDistributionChart" role="img" aria-label="Répartition des volumes par type"></canvas></div>
                            <ul class="dg-legend">
                                @foreach($distributionList as $item)
                                    <li title="{{ $item['value'] }}">
                                        <span class="dg-legend__swatch" style="background:{{ $item['color'] }}"></span>
                                        <span class="dg-legend__label">{{ $item['label'] }} <span class="text-nowrap">({{ $item['percent'] }}&nbsp;%)</span></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="dg-chart">
                            <div class="dg-chart-empty">
                                <span class="dg-tile dg-tone-purple"><i class="bi bi-pie-chart"></i></span>
                                <div><strong>Aucun volume à répartir</strong>Les entrées, sorties et transferts s’afficheront ici.</div>
                            </div>
                        </div>
                    @endif
                </x-dg.card>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
            <script>
                (() => {
                    const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });
                    Chart.defaults.font.family = "'Poppins', system-ui, sans-serif";
                    Chart.defaults.color = '#8a93a6';
                    const barCanvas = document.getElementById('treasuryBankChart');
                    if (barCanvas) {
                        const points = @json($chartData ?? []);
                        new Chart(barCanvas, {
                            type: 'bar',
                            data: { labels: points.map(point => point.label), datasets: [{ label: 'Opérations bancaires', data: points.map(point => point.value), backgroundColor: '#2563eb', borderRadius: 6, maxBarThickness: 36 }] },
                            options: {
                                responsive: true, maintainAspectRatio: false,
                                plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => window.formatMoney(context.raw) } } },
                                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: 'rgba(138, 147, 166, .16)' }, border: { display: false }, ticks: { callback: value => compact.format(value) } } }
                            }
                        });
                    }
                    const donutCanvas = document.getElementById('treasuryDistributionChart');
                    if (donutCanvas) {
                        const items = @json($distributionList);
                        new Chart(donutCanvas, {
                            type: 'doughnut',
                            data: { labels: items.map(item => item.label), datasets: [{ data: items.map(item => item.amount), backgroundColor: items.map(item => item.color), borderWidth: 0 }] },
                            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => context.label + ' : ' + window.formatMoney(context.raw) } } } }
                        });
                    }
                })();
            </script>
        @elseif(($moduleType ?? '') === 'rapport_financier')
            @php
                $expenseRows = collect($expenseCategories ?? []);
                $expenseTotalValue = (float) ($expenseTotal ?? 0);
                // Part déjà sortie de caisse ou de banque ; les achats fournisseurs peuvent rester à régler.
                $disbursed = (float) $expenseRows->whereIn('source', ['Caisse', 'Banque'])->sum('amount');
                $disbursedRate = $expenseTotalValue > 0 ? round($disbursed / $expenseTotalValue * 100) : 0;
                $hasCommercial = collect($commercialRows ?? [])->isNotEmpty();
                $hasExpenseMonths = collect($expenseMonths ?? [])->sum('total') > 0;
                $grossResult = (float) ($totalRealise ?? 0) - $expenseTotalValue;
                $netCashResult = (float) ($encaisse ?? 0) - $expenseTotalValue;
            @endphp

            <div class="dg-kpi-grid">
                @foreach(($summary ?? []) as $item)
                    <x-dg.kpi :label="$item['label']" :value="$item['value']"
                        :hint="['factures émises sur la période', 'règlements reçus sur ces factures', 'reste à encaisser'][$loop->index] ?? $item['change']"
                        :icon="['bi-graph-up-arrow', 'bi-wallet2', 'bi-hourglass-split'][$loop->index] ?? null"
                        :color="['green', 'blue', 'orange'][$loop->index] ?? 'navy'" />
                @endforeach
            </div>

            <div class="dg-grid-halves mb-6">
                <x-dg.card title="CA par commercial" icon="bi-bar-chart" color="green" :meta="'Montants en ' . currency_symbol()">
                    <div class="dg-chart">
                        @if((float) ($totalRealise ?? 0) > 0)
                            <canvas id="commercialTotalsChart" role="img" aria-label="Chiffre d’affaires, encaissements et créances"></canvas>
                        @else
                            <div class="dg-chart-empty">
                                <span class="dg-tile dg-tone-green"><i class="bi bi-bar-chart"></i></span>
                                <div><strong>Aucune vente sur la période</strong>Le chiffre d’affaires, les encaissements et les créances s’afficheront ici.</div>
                            </div>
                        @endif
                    </div>
                </x-dg.card>
                <x-dg.card title="Évolution mensuelle par commercial" icon="bi-graph-up" color="blue" :meta="'Montants en ' . currency_symbol()">
                    <div class="dg-chart">
                        @if($hasCommercial)
                            <canvas id="commercialMonthlyChart" role="img" aria-label="Évolution mensuelle des ventes par commercial"></canvas>
                        @else
                            <div class="dg-chart-empty">
                                <span class="dg-tile dg-tone-blue"><i class="bi bi-graph-up"></i></span>
                                <div><strong>Aucune vente sur la période</strong>L’évolution mois par mois de chaque commercial s’affichera ici.</div>
                            </div>
                        @endif
                    </div>
                </x-dg.card>
            </div>

            <x-dg.card title="Détail par utilisateur" icon="bi-people" color="indigo" class="dg-card--table mb-6">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>#</th><th>Utilisateur</th><th class="text-end">CA factures</th><th class="text-end">CA services</th><th class="text-end">CA total</th><th class="text-end">Encaissé</th><th class="text-end">Reste</th></tr></thead>
                        <tbody>
                            @forelse($commercialRows ?? [] as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td class="fw-semibold">{{ $row['user'] }}</td>
                                    <td class="text-end">{{ money($row['invoices']) }}</td>
                                    <td class="text-end">{{ money($row['services']) }}</td>
                                    <td class="text-end dg-cell-num">{{ money($row['total']) }}</td>
                                    <td class="text-end dg-amount-positive">{{ money($row['paid']) }}</td>
                                    <td class="text-end {{ $row['remaining'] > 0 ? 'dg-amount-warning' : '' }}">{{ money($row['remaining']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-6">Aucune vente sur cette période.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dg.card>

            <x-dg.card title="Dépenses globales" icon="bi-truck" color="red" class="mb-6">
                <div class="dg-kpi-grid">
                    <x-dg.kpi label="Total dépenses" :value="money($expenseTotalValue)" icon="bi-receipt" color="red" hint="caisse, banque et fournisseurs" />
                    <x-dg.kpi label="Déjà décaissé" :value="money($disbursed)" icon="bi-cash" color="purple" :hint="$disbursedRate . ' % des dépenses (caisse et banque)'" />
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Source</th><th>Catégorie</th><th class="text-end">Montant total</th></tr></thead>
                        <tbody>
                            @forelse($expenseRows as $row)
                                <tr>
                                    <td><span class="dg-badge dg-badge--{{ ['Caisse' => 'warning', 'Banque' => 'neutral', 'Fournisseurs' => 'danger'][$row['source']] ?? 'neutral' }}">{{ $row['source'] }}</span></td>
                                    <td>{{ $row['category'] }}</td>
                                    <td class="text-end dg-cell-num">{{ money($row['amount']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-6">Aucune dépense sur cette période.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dg.card>

            <x-dg.card title="Évolution mensuelle des dépenses" icon="bi-activity" color="orange" :meta="'Montants en ' . currency_symbol()" class="mb-6">
                <div class="dg-chart mb-4">
                    @if($hasExpenseMonths)
                        <canvas id="expenseMonthlyChart" role="img" aria-label="Évolution mensuelle des dépenses"></canvas>
                    @else
                        <div class="dg-chart-empty">
                            <span class="dg-tile dg-tone-orange"><i class="bi bi-activity"></i></span>
                            <div><strong>Aucune dépense sur la période</strong>Les sorties de caisse, dépenses bancaires et achats fournisseurs s’afficheront ici.</div>
                        </div>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Mois</th><th class="text-end">Sorties de caisse</th><th class="text-end">Dépenses banque (débit)</th><th class="text-end">Achats fournisseurs</th><th class="text-end">Dépenses totales</th></tr></thead>
                        <tbody>
                            @foreach($expenseMonths ?? [] as $row)
                                <tr>
                                    <td>{{ ucfirst($row['label']) }}</td>
                                    <td class="text-end">{{ money($row['cash']) }}</td>
                                    <td class="text-end">{{ money($row['bank']) }}</td>
                                    <td class="text-end">{{ money($row['suppliers']) }}</td>
                                    <td class="text-end dg-cell-num">{{ money($row['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-dg.card>

            <x-dg.card title="Observations du rapport" icon="bi-chat-left-text" color="yellow" class="mb-6">
                <form method="POST" action="{{ route('admin.comptabilite.rapport_financier.observations') }}">
                    @csrf
                    <input type="hidden" name="date_debut" value="{{ $filters['date_debut'] }}">
                    <input type="hidden" name="date_fin" value="{{ $filters['date_fin'] }}">
                    <div class="mb-4">
                        <label class="form-label" for="generalObservation">Observation générale</label>
                        <textarea id="generalObservation" name="general_observation" class="form-control" rows="2" placeholder="Saisir une observation générale sur la période">{{ $observations['general'] ?? '' }}</textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="expenseObservation">Observations sur les dépenses</label>
                        <textarea id="expenseObservation" name="expense_observation" class="form-control" rows="3" placeholder="Renseigner les observations de rapport, anomalies, écarts ou commentaires sur les dépenses">{{ $observations['expenses'] ?? '' }}</textarea>
                    </div>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                </form>
            </x-dg.card>

            <h2 class="dg-section-title">Résultats de la période</h2>
            <div class="dg-kpi-grid mb-0">
                <x-dg.kpi label="Résultat brut" :value="money($grossResult)" icon="bi-bar-chart-line" color="teal" hint="chiffre d’affaires réalisé moins les dépenses" />
                <x-dg.kpi label="Résultat net encaissé" :value="money($netCashResult)" icon="bi-wallet2" color="indigo" hint="montants encaissés moins les dépenses" />
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
            <script>
                (() => {
                    const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });
                    const moneyTooltip = context => (context.dataset.label ? context.dataset.label + ' : ' : '') + window.formatMoney(context.raw);
                    const moneyAxis = { beginAtZero: true, grid: { color: 'rgba(138, 147, 166, .16)' }, border: { display: false }, ticks: { callback: value => compact.format(value) } };
                    Chart.defaults.font.family = "'Poppins', system-ui, sans-serif";
                    Chart.defaults.color = '#8a93a6';
                    const palette = ['#2563eb', '#10b981', '#8b5cf6', '#f97316', '#06b6d4', '#db2777'];

                    const totalsCanvas = document.getElementById('commercialTotalsChart');
                    if (totalsCanvas) {
                        const series = @json($commercialSeries ?? []);
                        new Chart(totalsCanvas, {
                            type: 'bar',
                            data: { labels: series.map(item => item.label), datasets: [{ data: series.map(item => item.value), backgroundColor: ['#10b981', '#2563eb', '#f97316'], borderRadius: 8, maxBarThickness: 64 }] },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: moneyTooltip } } }, scales: { x: { grid: { display: false } }, y: moneyAxis } }
                        });
                    }

                    const monthlyCanvas = document.getElementById('commercialMonthlyChart');
                    if (monthlyCanvas) {
                        const labels = @json(collect($chartData ?? [])->pluck('label')->map(fn ($label) => ucfirst($label))->values());
                        const commercial = @json($commercialMonthly ?? []);
                        new Chart(monthlyCanvas, {
                            type: 'line',
                            data: { labels, datasets: commercial.map((item, index) => ({ label: item.label, data: item.values, borderColor: palette[index % palette.length], backgroundColor: palette[index % palette.length], borderWidth: 3, tension: .35, pointRadius: 3 })) },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: moneyTooltip } } }, scales: { x: { grid: { display: false } }, y: moneyAxis } }
                        });
                    }

                    const expenseCanvas = document.getElementById('expenseMonthlyChart');
                    if (expenseCanvas) {
                        const expenses = @json($expenseMonths ?? []);
                        const line = (label, key, color, dashed) => ({ label, data: expenses.map(item => item[key]), borderColor: color, backgroundColor: color, borderWidth: dashed ? 2 : 3, borderDash: dashed ? [6, 5] : [], tension: .35, pointRadius: 3 });
                        new Chart(expenseCanvas, {
                            type: 'line',
                            data: {
                                labels: expenses.map(item => item.label.charAt(0).toUpperCase() + item.label.slice(1)),
                                datasets: [line('Sorties de caisse', 'cash', '#ef4444'), line('Dépenses banque', 'bank', '#8b5cf6'), line('Achats fournisseurs', 'suppliers', '#0d9488'), line('Dépenses totales', 'total', '#273772', true)]
                            },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: moneyTooltip } } }, scales: { x: { grid: { display: false } }, y: moneyAxis } }
                        });
                    }
                })();
            </script>
        @elseif($isTreasury)
            <div class="dg-card dg-card--table mt-6">
                <div class="dg-card__header">
                    <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-blue"><i class="bi bi-bank"></i></span>Comptes bancaires</h2>
                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" onclick="window.print()"><i class="bi bi-printer"></i>Imprimer</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                @foreach(($tableColumns ?? []) as $column)
                                    <th>{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($tableRows ?? []) as $row)
                                <tr>
                                    @foreach($row as $cell)
                                        @if($loop->index === 4 && in_array($cell, ['Crédit', 'Débit'], true))
                                            <td><span class="dg-badge dg-badge--{{ $cell === 'Crédit' ? 'success' : 'warning' }}">{{ $cell }}</span></td>
                                        @elseif($loop->index === 2)
                                            <td class="dg-cell-num">{!! $cell !!}</td>
                                        @else
                                            <td>{!! $cell !!}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($tableColumns ?? []) }}" class="text-center text-muted py-8">Aucune donnée pour cette période.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-header border-0 bg-white px-5 py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0 fw-bold text-dark">Détails</h4>
                        <button type="button" class="btn btn-light btn-sm" onclick="window.print()">Exporter</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle gs-0 gy-4 mb-0">
                            <thead>
                                <tr class="fw-bold text-muted fs-7 text-uppercase">
                                    @foreach(($tableColumns ?? []) as $column)
                                        <th class="px-5 py-4">{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($tableRows ?? []) as $row)
                                    <tr>
                                        @foreach($row as $cell)
                                            <td class="px-5 py-4 text-dark fw-semibold">{!! $cell !!}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($tableColumns ?? []) }}" class="px-5 py-8 text-center text-muted">Aucune donnée pour cette période.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@if(($moduleType ?? '') === 'caisses')
    <div class="modal fade" id="createCashAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.comptabilite.caisses.storeAccount') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Nouvelle caisse</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom de la caisse</label>
                            <input type="text" name="name" class="form-control" placeholder="Ex: Caisse principale" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="account_type" class="form-select" required>
                                <option value="caisse">Caisse</option>
                                <option value="mobile_money">Mobile money</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Solde initial</label>
                            <input type="number" name="initial_balance" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createCashMovementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.comptabilite.caisses.storeMovement') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Nouveau mouvement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Caisse</label>
                            @php $activeAccounts = ($accounts ?? [])->filter(fn($account) => $account->is_active); @endphp
                            @if($activeAccounts->isEmpty())
                                <div class="alert alert-warning mb-0">Aucune caisse active. Ouvrez une caisse pour enregistrer un mouvement.</div>
                            @else
                                <select name="cash_account_id" class="form-select" required>
                                    @foreach($activeAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="movement_type" class="form-select" required>
                                <option value="entry">Entrée</option>
                                <option value="exit">Sortie</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catégorie de dépense</label>
                            <select name="expense_category_id" class="form-select">
                                <option value="">Aucune catégorie</option>
                                @foreach(($expenseCategories ?? []) as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Obligatoire pour une sortie.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Libellé</label>
                            <input type="text" name="label" class="form-control" placeholder="Ex: Encaissement client" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Montant</label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mode de paiement</label>
                            <select name="payment_mode" class="form-select" required>
                                <option value="cash">Cash</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank">Banque</option>
                                <option value="transfer">Virement</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Référence</label>
                            <input type="text" name="reference" class="form-control" placeholder="Ex: REF-001">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="movement_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-primary" {{ ($activeAccounts ?? collect())->isEmpty() ? 'disabled' : '' }}>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach(($movements ?? []) as $movement)
        <div class="modal fade" id="editMovementModal-{{ $movement->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.comptabilite.caisses.updateMovement', $movement) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Modifier le mouvement</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Caisse</label>
                                <select name="cash_account_id" class="form-select" required>
                                    @foreach($accounts ?? [] as $account)
                                        <option value="{{ $account->id }}" {{ $movement->cash_account_id == $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <select name="movement_type" class="form-select" required>
                                    <option value="entry" {{ $movement->movement_type === 'entry' ? 'selected' : '' }}>Entrée</option>
                                    <option value="exit" {{ $movement->movement_type === 'exit' ? 'selected' : '' }}>Sortie</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catégorie de dépense</label>
                                <select name="expense_category_id" class="form-select">
                                    <option value="">Aucune catégorie</option>
                                    @foreach(($expenseCategories ?? []) as $category)
                                        <option value="{{ $category->id }}" {{ $movement->expense_category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Obligatoire pour une sortie.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Libellé</label>
                                <input type="text" name="label" class="form-control" value="{{ $movement->label }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Montant</label>
                                <input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="{{ $movement->amount }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mode de paiement</label>
                                <select name="payment_mode" class="form-select" required>
                                    <option value="cash" {{ $movement->payment_mode === 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="mobile_money" {{ $movement->payment_mode === 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                                    <option value="bank" {{ $movement->payment_mode === 'bank' ? 'selected' : '' }}>Banque</option>
                                    <option value="transfer" {{ $movement->payment_mode === 'transfer' ? 'selected' : '' }}>Virement</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Référence</label>
                                <input type="text" name="reference" class="form-control" value="{{ $movement->reference }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="movement_date" class="form-control" value="{{ $movement->movement_date->format('Y-m-d') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ $movement->description }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif

<script>
    document.addEventListener('click', function (event) {
        const printTrigger = event.target.closest('[data-print-row]');
        if (!printTrigger) return;

        const rowData = JSON.parse(printTrigger.getAttribute('data-print-row'));
        const win = window.open('', '_blank', 'width=900,height=700');

        win.document.write(`<!DOCTYPE html>
            <html>
                <head>
                    <title>Impression mouvement</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 24px; color: #1f2937; }
                        .box { border: 1px solid #d1d5db; border-radius: 10px; padding: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
                        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
                        .label { font-weight: bold; width: 35%; }
                    </style>
                </head>
                <body>
                    <div class="box">
                        <h2>Fiche mouvement de caisse</h2>
                        <table>
                            <tr><td class="label">Caisse</td><td>${rowData.caisse}</td></tr>
                            <tr><td class="label">Libellé</td><td>${rowData.libelle}</td></tr>
                            <tr><td class="label">Type</td><td>${rowData.type}</td></tr>
                            <tr><td class="label">Montant</td><td>${rowData.montant}</td></tr>
                            <tr><td class="label">Date</td><td>${rowData.date}</td></tr>
                            <tr><td class="label">Mode</td><td>${rowData.mode}</td></tr>
                            <tr><td class="label">Référence</td><td>${rowData.reference}</td></tr>
                            <tr><td class="label">Description</td><td>${rowData.description}</td></tr>
                        </table>
                    </div>
                </body>
            </html>`);

        win.document.close();
        win.focus();
        setTimeout(() => win.print(), 300);
    });
</script>
</div>
@endsection
