@extends('admin.layout')

@section('content')
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
                @if(($moduleType ?? '') === 'caisses')
                    <button type="button" class="btn btn-light btn-sm px-4" data-bs-toggle="modal" data-bs-target="#createCashAccountModal">Nouvelle caisse</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" data-bs-toggle="modal" data-bs-target="#createCashMovementModal">Nouveau mouvement</button>
                @endif
            </div>
        </div>

        @if(($moduleType ?? '') === 'banques')
            <style>
                .bank-filter-bar {
                    background: #f5f8ff;
                    border: 1px solid #e8edf7;
                    border-radius: 14px;
                    padding: 12px 16px;
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
                    background: #f8faff;
                    border: 1px solid #edf2f7;
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
                    background: #f8fbff;
                    border: 1px solid #eaf0f8;
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
                        <button type="submit" class="btn btn-primary btn-sm px-4">Filtrer</button>
                        <button type="button" class="btn btn-primary btn-sm px-4" data-bs-toggle="modal" data-bs-target="#createBankAccountModal"><i class="bi bi-plus-lg me-2"></i>Nouvelle Banque</button>
                    </div>
                </div>
            </form>

            <div class="row g-4 mb-6">
                @foreach(($summary ?? []) as $item)
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="text-muted fs-7 fw-bold text-uppercase">{{ $item['label'] }}</div>
                                <div class="mt-3 fs-2 fw-bold text-dark">{{ $item['value'] }}</div>
                                <div class="mt-2 fs-7 fw-bold text-primary">{{ $item['change'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-4">
                @forelse(($bankAccounts ?? []) as $index => $bank)
                    <div class="col-xl-3 col-md-6">
                        <div class="bank-card p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bank-logo" style="background: {{ $bank['logo_bg'] ?? '#3B82F6' }}; color: {{ $bank['logo_text'] ?? '#fff' }};">{{ strtoupper(substr($bank['short_name'] ?? 'BC', 0, 2)) }}</div>
                                    <div>
                                        <div class="fw-bold text-dark fs-6 mb-0">{{ $bank['name'] }}</div>
                                        <small class="text-muted">{{ $bank['bank'] }}</small>
                                    </div>
                                </div>
                                <span class="badge {{ ($bank['status'] ?? 'credit') === 'credit' ? 'badge-light-success' : 'badge-light-warning' }} px-3 py-2">
                                    {{ ($bank['status'] ?? 'credit') === 'credit' ? 'Crédit' : 'Débit' }}
                                </span>
                            </div>

                            <div class="text-muted fs-7 text-uppercase mb-1">Compte</div>
                            <div class="fw-bold text-dark fs-5 mb-3">{{ $bank['account_number'] }}</div>

                            <div class="bank-separator"></div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted fs-7">Solde période</span>
                                <span class="fw-bold fs-5 text-dark">{{ number_format((float) ($bank['amount'] ?? 0), 0, ',', ' ') }} FCFA</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted fs-7">Ouverture période</span>
                                <span class="fw-bold fs-7 text-dark">{{ number_format((float) ($bank['period_initial_balance'] ?? 0), 0, ',', ' ') }} FCFA</span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="text-muted fs-7">Dernier mouvement</span>
                                <span class="fs-7 fw-bold {{ ($bank['movement_direction'] ?? 'up') === 'up' ? 'text-success' : 'text-danger' }}">
                                    {{ $bank['movement_label'] ?? 'Solde initial' }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center bg-light rounded-3 px-3 py-2 mb-4">
                                <span class="text-muted fs-7">Mouvement</span>
                                <span class="fw-bold {{ ($bank['movement_direction'] ?? 'up') === 'up' ? 'text-success' : 'text-danger' }}">
                                    {{ (($bank['movement_direction'] ?? 'up') === 'up' ? '+' : '-') }}{{ number_format((float) ($bank['movement_amount'] ?? 0), 0, ',', ' ') }} FCFA
                                </span>
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-light-primary flex-fill bank-transaction-trigger"
                                        data-account-id="{{ $bank['id'] ?? $index }}"
                                        data-account-name="{{ $bank['name'] }}"
                                        data-account-bank="{{ $bank['bank'] }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#transactionBankModal">Transaction</button>
                                <button type="button" class="btn btn-sm btn-light flex-fill bank-details-trigger"
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
                            </div>

                            <div class="d-flex gap-2 mt-3">
                                <button type="button" class="btn btn-sm btn-light bank-edit-trigger"
                                        data-account-id="{{ $bank['id'] ?? $index }}"
                                        data-account-name="{{ $bank['name'] }}"
                                        data-account-bank="{{ $bank['bank'] }}"
                                        data-account-number="{{ $bank['account_number'] }}"
                                        data-account-type="{{ $bank['account_type'] ?? 'compte_courant' }}"
                                        data-account-status="{{ $bank['status'] ?? 'credit' }}"
                                        data-account-short="{{ $bank['short_name'] ?? 'BC' }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editBankAccountModal-{{ $bank['id'] ?? $index }}">Modifier</button>
                                <form method="POST" action="{{ route('admin.comptabilite.banques.destroy', $bank['id'] ?? $index) }}" class="d-inline" onsubmit="return confirm('Supprimer ce compte bancaire ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                </form>
                            </div>
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
                    <div class="col-12">
                        <style>
                            .cash-movement-table { width: 100%; table-layout: auto; }
                            .cash-movement-table th,
                            .cash-movement-table td { padding: .8rem .7rem; vertical-align: middle; }
                            .cash-movement-table th { white-space: nowrap; }
                        </style>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center py-8">
                                <div class="text-muted fs-5">Aucun compte bancaire trouvé pour ce filtre.</div>
                            </div>
                        </div>
                    </div>
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
                    return new Intl.NumberFormat('fr-FR').format(Number(value || 0)) + ' FCFA';
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
                        const entryAmount = isCredit ? formatAmount(row.amount) : '0 FCFA';
                        const exitAmount = !isCredit ? formatAmount(row.amount) : '0 FCFA';

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
                            const entry = row.type === 'credit' ? '<span class="success">' + formatAmount(row.amount) + '</span>' : '0 FCFA';
                            const exit = row.type === 'debit' ? '<span class="danger">' + formatAmount(row.amount) + '</span>' : '0 FCFA';
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
                                            row.type === 'credit' ? formatAmount(row.amount) : '0 FCFA',
                                            row.type === 'debit' ? formatAmount(row.amount) : '0 FCFA',
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
                        document.getElementById('bankDetailsAmount').textContent = new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
                        document.getElementById('bankDetailsMovement').textContent = label + ' - ' + (status === 'credit' ? '+' : '-') + new Intl.NumberFormat('fr-FR').format(movement) + ' FCFA';

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
            <div class="mb-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-0 bg-white px-4 py-3">
                        <button class="btn w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#cashStateCollapse" aria-expanded="false" aria-controls="cashStateCollapse">
                            <span>
                                <span class="text-uppercase text-muted fs-8 fw-bold ls-1 d-block">ÉTAT GÉNÉRAL DES CAISSES</span>
                                <span class="fs-6 fw-bold text-dark">Suivi consolidé des caisses / comptes mobile money</span>
                            </span>
                            <span class="badge badge-light-primary fs-7 fw-bold px-3 py-2">{{ count($accounts ?? []) }} comptes actifs</span>
                        </button>
                    </div>
                    <div id="cashStateCollapse" class="collapse">
                        <div class="card-body border-top">
                            <div class="row g-4">
        @if(($moduleType ?? '') === 'caisses' && !empty($accounts))
            <div class="row g-4 mb-6">
                        <div class="row g-4 mb-6">
            @foreach(($summary ?? []) as $item)
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="text-muted fs-7 fw-bold text-uppercase">{{ $item['label'] }}</div>
                            <div class="mt-3 fs-2 fw-bold text-dark">{{ $item['value'] }}</div>
                            <div class="mt-2 d-flex align-items-center gap-2 {{ str_contains($item['change'], '-') ? 'text-danger' : 'text-success' }} fs-7 fw-bold">
                                <i class="ki-duotone {{ str_contains($item['change'], '-') ? 'ki-arrow-down-right' : 'ki-arrow-up-right' }} fs-5"></i>
                                <span>{{ $item['change'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

                @foreach($accounts as $account)
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="text-muted fs-7 fw-bold text-uppercase">{{ ucfirst($account->account_type) }}</div>
                                        <h6 class="mb-0 fw-bold text-dark">{{ $account->name }}</h6>
                                    </div>
                                    <span class="badge {{ $account->is_active ? 'badge-light-success' : 'badge-light-secondary' }}">{{ $account->is_active ? 'Actif' : 'Inactif' }}</span>
                                </div>
                                <div class="fs-3 fw-bold text-dark">{{ number_format((float) ($account->period_balance ?? $account->balance), 0, ',', ' ') }} FCFA</div>
                                <div class="text-muted fs-7 mt-2">Ouverture période: {{ number_format((float) ($account->period_initial_balance ?? $account->initial_balance), 0, ',', ' ') }} FCFA</div>
                                <div class="text-muted fs-8 mt-1">Initial création: {{ number_format((float) $account->initial_balance, 0, ',', ' ') }} FCFA</div>

                                <div class="d-flex gap-2 mt-4 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#cashAccountDetailsModal-{{ $account->id }}" title="Voir les détails" aria-label="Voir les détails de {{ $account->name }}">👁 Voir</button>
                                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editCashAccountModal-{{ $account->id }}">Modifier</button>
                                    <form method="POST" action="{{ route('admin.comptabilite.caisses.toggleStatus', $account) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm {{ $account->is_active ? 'btn-outline-warning' : 'btn-success' }}">
                                            {{ $account->is_active ? 'Fermer' : 'Ouvrir' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.comptabilite.caisses.destroyAccount', $account) }}" class="d-inline" onsubmit="return confirm('Supprimer cette caisse ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                    </form>
                                </div>
                            </div>
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
                                        <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted fs-8 text-uppercase">Solde période</div><div class="fs-4 fw-bold">{{ number_format((float) ($account->period_balance ?? $account->balance), 0, ',', ' ') }} FCFA</div></div></div>
                                        <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted fs-8 text-uppercase">Total entrées</div><div class="fs-4 fw-bold text-success">{{ number_format((float) $accountEntries, 0, ',', ' ') }} FCFA</div></div></div>
                                        <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted fs-8 text-uppercase">Total sorties</div><div class="fs-4 fw-bold text-danger">{{ number_format((float) $accountExits, 0, ',', ' ') }} FCFA</div></div></div>
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
                                                            <td class="fw-bold">{{ number_format((float) $movement->amount, 0, ',', ' ') }} FCFA</td>
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
        @endif

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" class="row g-3 align-items-end mb-6">
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
                        <button type="submit" class="btn btn-primary flex-fill">Filtrer</button>
                        <a href="{{ route('admin.comptabilite.caisses') }}" class="btn btn-light flex-fill">Réinitialiser</a>
                    </div>
                </div>
            </form>

            <div class="card border-0 shadow-sm">
                <div class="card-header border-0 bg-white px-4 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-muted fs-8 fw-bold ls-1">Détails</div>
                        <h5 class="mb-0 fw-bold text-dark">Mouvements de caisse</h5>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Imprimer</button>
                    </div>
                </div>
                <div class="card-body p-0">
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
                                @forelse(($tableRows ?? []) as $row)
                                    <tr>
                                        @foreach($row as $key => $cell)
                                            @if($key === array_key_last($row))
                                                <td>{!! $cell !!}</td>
                                            @else
                                                <td>{{ $cell }}</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($tableColumns ?? []) }}" class="text-center text-muted py-5">Aucun mouvement trouvé pour cette période.</td>
                                    </tr>
                                @endforelse
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
                                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
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
            <style>
                .treasury-summary-card {
                    border: 1px solid #edf1f5;
                    border-radius: 18px;
                    background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
                    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.04);
                    padding: 1.1rem 1.15rem;
                    height: 100%;
                    border-left: 4px solid #3b82f6;
                }
                .treasury-summary-card:nth-child(2) { border-left-color: #22c55e; }
                .treasury-summary-card:nth-child(3) { border-left-color: #8b5cf6; }
                .treasury-summary-card:nth-child(4) { border-left-color: #f59e0b; }
                .treasury-summary-card .label {
                    color: #64748b;
                    font-size: 0.7rem;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    font-weight: 700;
                }
                .treasury-summary-card .value {
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #0f172a;
                    margin-top: 0.5rem;
                    line-height: 1.2;
                    letter-spacing: -0.02em;
                }
                .treasury-summary-card .change {
                    color: #10b981;
                    font-size: 0.75rem;
                    font-weight: 600;
                    margin-top: 0.5rem;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.35rem;
                }
                .treasury-panel {
                    background: #ffffff;
                    border: 1px solid #edf1f5;
                    border-radius: 18px;
                    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.04);
                    padding: 1.35rem 1.3rem;
                    height: 100%;
                }
                .treasury-panel h5 {
                    color: #0f172a;
                    font-weight: 700;
                    margin-bottom: 0;
                    font-size: 1.05rem;
                }
                .treasury-list-row {
                    border: 1px solid #edf1f5;
                    border-radius: 12px;
                    background: #f8fafc;
                    padding: 0.8rem 0.95rem;
                }
                .treasury-account-card {
                    border: 1px solid #edf1f5;
                    border-radius: 18px;
                    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
                    padding: 1rem 1.1rem;
                    height: 100%;
                }
                .treasury-account-card .bank-logo {
                    width: 46px;
                    height: 46px;
                    border-radius: 14px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 800;
                    color: #0f172a;
                    background: #e2e8f0;
                }
                .treasury-account-card .status-pill {
                    border-radius: 999px;
                    padding: 0.38rem 0.7rem;
                    font-size: 0.7rem;
                    font-weight: 700;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.35rem;
                }
                .status-pill.open {
                    background: rgba(16, 185, 129, 0.12);
                    color: #047857;
                }
                .status-pill.closed {
                    background: rgba(148, 163, 184, 0.12);
                    color: #475569;
                }
                .treasury-chart {
                    display: flex;
                    align-items: end;
                    justify-content: space-between;
                    gap: 0.75rem;
                    min-height: 220px;
                    padding-top: 1rem;
                }
                .treasury-chart .column {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: end;
                    gap: 0.5rem;
                    width: 100%;
                    flex: 1;
                }
                .treasury-chart .column .bar {
                    width: 100%;
                    max-width: 46px;
                    border-radius: 12px 12px 0 0;
                    background: linear-gradient(180deg, #60a5fa 0%, #2563eb 100%);
                    min-height: 24px;
                    box-shadow: inset 0 -10px 18px rgba(37, 99, 235, 0.18);
                }
                .treasury-chart .column small {
                    color: #64748b;
                    font-weight: 600;
                }
                .donut {
                    width: 165px;
                    height: 165px;
                    border-radius: 50%;
                    position: relative;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: conic-gradient(#2563eb 0 42%, #22c55e 42% 72%, #f59e0b 72% 89%, #a855f7 89% 100%);
                }
                .donut::before {
                    content: "";
                    position: absolute;
                    inset: 20px;
                    background: white;
                    border-radius: 50%;
                    box-shadow: inset 0 0 0 1px #edf1f5;
                }
                .donut-inner {
                    position: relative;
                    z-index: 1;
                    text-align: center;
                }
                .donut-inner strong {
                    display: block;
                    font-size: 1.5rem;
                    color: #0f172a;
                }
                .donut-inner small {
                    color: #64748b;
                    font-weight: 600;
                }
                .legend-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 0.75rem;
                    padding: 0.55rem 0;
                    border-bottom: 1px solid #f1f5f9;
                }
                .legend-item:last-child { border-bottom: 0; }
                .legend-left {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.7rem;
                    color: #334155;
                    font-size: 0.9rem;
                    font-weight: 600;
                }
                .legend-dot {
                    width: 10px;
                    height: 10px;
                    border-radius: 50%;
                    display: inline-block;
                }
                .muted-label {
                    color: #64748b;
                    font-size: 0.8rem;
                    font-weight: 700;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .treasury-report-shell {
                    padding: 1.5rem;
                    border-radius: 20px;
                    background: #f3f6fa;
                }
                .treasury-report-hero {
                    position: relative;
                    overflow: hidden;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1.5rem;
                    min-height: 150px;
                    margin-bottom: 1.25rem;
                    padding: 1.8rem 2rem;
                    border-radius: 18px;
                    color: #fff;
                    background: linear-gradient(118deg, #123e68 0%, #1b628d 58%, #35a98a 100%);
                    box-shadow: 0 16px 32px rgba(18, 62, 104, .18);
                }
                .treasury-report-hero::after {
                    content: "";
                    position: absolute;
                    width: 260px;
                    height: 260px;
                    right: -75px;
                    top: -110px;
                    border: 1px solid rgba(255,255,255,.16);
                    border-radius: 50%;
                    box-shadow: 0 0 0 24px rgba(255,255,255,.04), 0 0 0 48px rgba(255,255,255,.03);
                }
                .treasury-report-hero h2 { position: relative; z-index: 1; margin: 0; font-size: 1.45rem; font-weight: 800; letter-spacing: -.03em; }
                .treasury-report-hero p { position: relative; z-index: 1; margin: .45rem 0 0; color: rgba(255,255,255,.74); font-size: .82rem; }
                .treasury-report-badge { position: relative; z-index: 1; padding: .75rem 1rem; border: 1px solid rgba(255,255,255,.22); border-radius: 11px; background: rgba(255,255,255,.1); color: #fff; font-size: .75rem; font-weight: 700; white-space: nowrap; }
                .treasury-report-shell .treasury-summary-card,
                .treasury-report-shell .treasury-panel {
                    border-color: #e5ebf2;
                    box-shadow: 0 7px 20px rgba(31,55,80,.045);
                }
                .treasury-report-shell .treasury-summary-card { border-radius: 15px; background: #fff; }
                .treasury-report-shell .treasury-panel { border-radius: 16px; }
                .treasury-report-shell .treasury-panel h5 { color: #172b4d; font-size: .95rem; }
                .treasury-report-shell .treasury-list-row,
                .treasury-report-shell .treasury-account-card { border-color: #e8eef4; background: #fff; border-radius: 13px; }
                .treasury-report-shell .treasury-chart { min-height: 245px; border-radius: 12px; background: linear-gradient(to top, rgba(148,163,184,.08), rgba(148,163,184,.02)); }
                .treasury-report-shell .treasury-chart .bar { background: linear-gradient(180deg, #4ba9d1 0%, #1b628d 100%); }
                .treasury-report-shell .donut { box-shadow: 0 10px 24px rgba(27,98,141,.12); }
                .treasury-report-shell .badge { font-size: .68rem; font-weight: 700; }
                @media (max-width: 650px) {
                    .treasury-report-shell { padding: .75rem; }
                    .treasury-report-hero { align-items: flex-start; flex-direction: column; padding: 1.35rem; }
                }
            </style>

            <div class="treasury-report-shell">
            <div class="treasury-report-hero">
                <div>
                    <h2>État de la trésorerie</h2>
                    <p>Suivez les liquidités, les caisses, les banques et les opérations récentes.</p>
                </div>
                <div class="treasury-report-badge"><i class="bi bi-shield-check me-2"></i>Vue trésorerie</div>
            </div>
            <div class="row g-4 mb-5">
                @foreach(($summary ?? []) as $item)
                    <div class="col-xl-3 col-md-6">
                        <div class="treasury-summary-card">
                            <div class="label">{{ $item['label'] }}</div>
                            <div class="value">{{ $item['value'] }}</div>
                            <div class="change"><i class="bi bi-arrow-up-right-circle"></i>{{ $item['change'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-4 mb-5">
                <div class="col-xl-7">
                    <div class="treasury-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5>Caisses actives</h5>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2">{{ ($activeCash ?? collect())->count() }} active(s)</span>
                        </div>
                        <div class="row g-3">
                            @forelse(($activeCash ?? collect()) as $account)
                                <div class="col-md-6">
                                    <div class="treasury-account-card">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="bank-logo" style="background: {{ $account->logo_bg ?? '#dbeafe' }}; color: {{ $account->logo_text ?? '#0f172a' }};">
                                                    {{ strtoupper(substr($account->name, 0, 2)) }}
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $account->name }}</div>
                                                    <div class="text-muted small">{{ $account->account_type === 'mobile_money' ? 'Mobile Money' : 'Caisse' }}</div>
                                                </div>
                                            </div>
                                            <span class="status-pill {{ $account->is_active ? 'open' : 'closed' }}">
                                                {{ $account->is_active ? 'Ouverte' : 'Fermée' }}
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="muted-label">Solde</span>
                                            <span class="fw-bold text-dark">{{ number_format((float) $account->balance, 0, ',', ' ') }} FCFA</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="alert alert-light border mb-0">Aucune caisse active pour cette entreprise.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="treasury-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5>Banques actives</h5>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2">{{ count($bankAccounts ?? []) }} compte(s)</span>
                        </div>
                        <div class="d-grid gap-3">
                            @forelse(($bankAccounts ?? []) as $account)
                                <div class="treasury-account-card">
                                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bank-logo" style="background: {{ $account->logo_bg ?? '#dbeafe' }}; color: {{ $account->logo_text ?? '#0f172a' }};">
                                                {{ strtoupper($account->short_name ?? substr($account->bank_name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark">{{ $account->name }}</div>
                                                <div class="text-muted small">{{ $account->bank_name }}</div>
                                            </div>
                                        </div>
                                        <span class="status-pill open">{{ $account->status === 'credit' ? 'Crédit' : 'Débit' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="muted-label">Solde</span>
                                        <span class="fw-bold text-dark">{{ number_format((float) $account->current_balance, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            @empty
                                <div class="alert alert-light border mb-0">Aucun compte bancaire enregistré.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-xl-6">
                    <div class="treasury-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5>Statistiques par type de transaction</h5>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2">Synthèse</span>
                        </div>
                        <div class="d-grid gap-3">
                            @foreach(($typeStats ?? []) as $stat)
                                <div class="treasury-list-row d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold text-dark">{{ $stat['label'] }}</span>
                                    <span class="fw-bold {{ $stat['class'] ?? 'text-dark' }}">{{ $stat['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="treasury-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5>Dernières opérations de caisse</h5>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2">5 derniers</span>
                        </div>
                        <div class="d-grid gap-3">
                            @forelse(($recentCashMovements ?? []) as $movement)
                                <div class="treasury-list-row d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $movement->label }}</div>
                                        <small class="text-muted">{{ $movement->cashAccount?->name ?? 'Caisse' }} · {{ $movement->movement_date?->format('d/m/Y') ?? '-' }}</small>
                                    </div>
                                    <span class="fw-bold {{ $movement->movement_type === 'entry' ? 'text-success' : 'text-danger' }}">
                                        {{ $movement->movement_type === 'entry' ? '+' : '-' }}{{ number_format((float) $movement->amount, 0, ',', ' ') }} FCFA
                                    </span>
                                </div>
                            @empty
                                <div class="alert alert-light border mb-0">Aucune opération récente.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-xl-6">
                    <div class="treasury-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5>Derniers réapprovisionnements (transferts)</h5>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2">Virements</span>
                        </div>
                        <div class="d-grid gap-3">
                            @forelse(($recentTransfers ?? []) as $transfer)
                                <div class="treasury-list-row d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $transfer->label }}</div>
                                        <small class="text-muted">{{ $transfer->transaction_date?->format('d/m/Y') ?? '-' }} · {{ $transfer->bankAccount?->name ?? 'Compte' }}</small>
                                    </div>
                                    <span class="fw-bold {{ $transfer->transaction_type === 'credit' ? 'text-success' : 'text-danger' }}">
                                        {{ $transfer->transaction_type === 'credit' ? '+' : '-' }}{{ number_format((float) $transfer->amount, 0, ',', ' ') }} FCFA
                                    </span>
                                </div>
                            @empty
                                <div class="alert alert-light border mb-0">Aucun réapprovisionnement récent.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="treasury-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5>Opérations bancaires récentes</h5>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2">5 derniers</span>
                        </div>
                        <div class="d-grid gap-3">
                            @forelse(($recentBankOperations ?? []) as $operation)
                                <div class="treasury-list-row d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $operation->label }}</div>
                                        <small class="text-muted">{{ $operation->bankAccount?->name ?? 'Banque' }} · {{ $operation->transaction_date?->format('d/m/Y') ?? '-' }}</small>
                                    </div>
                                    <span class="fw-bold {{ $operation->transaction_type === 'credit' ? 'text-success' : 'text-danger' }}">
                                        {{ $operation->transaction_type === 'credit' ? '+' : '-' }}{{ number_format((float) $operation->amount, 0, ',', ' ') }} FCFA
                                    </span>
                                </div>
                            @empty
                                <div class="alert alert-light border mb-0">Aucune opération bancaire récente.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="treasury-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5>Évolution des opérations bancaires</h5>
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">Derniers 6 mois</span>
                        </div>
                        <div class="treasury-chart">
                            @foreach(($chartData ?? []) as $point)
                                <div class="column">
                                    <span class="fw-bold" style="color:#475569; font-size:0.75rem;">{{ number_format((float) $point['value'], 0, ',', ' ') }}</span>
                                    <span class="bar" style="height: {{ $point['height'] }}%;"></span>
                                    <small>{{ $point['label'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="treasury-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5>Répartition par type</h5>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2">Mix</span>
                        </div>
                        <div class="d-flex justify-content-center mb-4">
                            <div class="donut">
                                <div class="donut-inner">
                                    <strong>100%</strong>
                                    <small>mix</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            @foreach(($distribution ?? []) as $item)
                                <div class="legend-item">
                                    <div class="legend-left">
                                        <span class="legend-dot" style="background: {{ $item['color'] }};"></span>
                                        {{ $item['label'] }}
                                    </div>
                                    <div class="fw-bold text-dark">{{ $item['percent'] }}%</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            </div>
        @elseif(($moduleType ?? '') === 'rapport_financier')
            <style>
                .finance-report-shell {
                    background: #eef3f9;
                    border: 1px solid #e4eaf2;
                    border-radius: 18px;
                    padding: 1.25rem;
                    width: 100%;
                    max-width: none;
                    box-sizing: border-box;
                    min-width: 0;
                }
                .finance-report-shell > * { width: 100%; max-width: none; }
                .finance-report-shell .finance-chart-panel,
                .finance-report-shell .finance-kpi-grid,
                .finance-report-shell .finance-toolbar { max-width: none; }
                .finance-report-shell .table-responsive { width: 100%; }
                .finance-toolbar {
                    display: flex;
                    align-items: end;
                    gap: 1rem;
                    flex-wrap: wrap;
                    padding: 0.5rem 0 1rem;
                }
                .finance-toolbar .field {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                    min-width: 180px;
                }
                .finance-toolbar .field label {
                    font-size: 0.8rem;
                    color: #475569;
                    font-weight: 600;
                }
                .finance-toolbar .field input,
                .finance-toolbar .field select {
                    min-height: 42px;
                    border-radius: 10px;
                    border: 1px solid #dfe7f4;
                    background: #fff;
                    padding: 0.5rem 0.8rem;
                }
                .finance-actions {
                    display: flex;
                    gap: 0.75rem;
                    flex-wrap: wrap;
                    margin-left: auto;
                }
                .finance-actions .btn {
                    min-width: 120px;
                    border-radius: 10px;
                    font-weight: 700;
                }
                .finance-kpi-grid {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(220px, 1fr));
                    gap: 1rem;
                    margin: 0.5rem 0 1.5rem;
                }
                .finance-kpi {
                    background: #f8fafc;
                    border: 1px solid #ebeff5;
                    border-radius: 16px;
                    padding: 1.2rem 1.1rem;
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    min-height: 120px;
                }
                .finance-kpi .icon {
                    width: 52px;
                    height: 52px;
                    border-radius: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.5rem;
                    color: #fff;
                    background: linear-gradient(135deg, #22c55e, #16a34a);
                    box-shadow: 0 12px 25px rgba(34,197,94,0.25);
                }
                .finance-kpi:nth-child(2) .icon {
                    background: linear-gradient(135deg, #38bdf8, #2563eb);
                }
                .finance-kpi:nth-child(3) .icon {
                    background: linear-gradient(135deg, #f59e0b, #ef4444);
                }
                .finance-kpi .value {
                    display: block;
                    font-size: clamp(1.2rem, 2vw, 2.2rem);
                    font-weight: 800;
                    color: #0f172a;
                    letter-spacing: -0.02em;
                    line-height: 1.1;
                }
                .finance-kpi .label {
                    display: block;
                    margin-top: 0.3rem;
                    color: #64748b;
                    font-size: 0.9rem;
                    font-weight: 600;
                }
                .finance-chart-panel {
                    background: #ffffff;
                    border: 1px solid #e5ebf3;
                    border-radius: 18px;
                    overflow: hidden;
                }
                .finance-chart-header {
                    background: linear-gradient(180deg, #0f3f71 0%, #0a2e55 100%);
                    color: #fff;
                    padding: 1rem 1.2rem;
                    font-weight: 700;
                    font-size: 1.1rem;
                }
                .finance-chart-body {
                    background: #f8fafc;
                    padding: 1.2rem;
                }
                .chart-legend {
                    display: flex;
                    justify-content: flex-end;
                    gap: 1rem;
                    margin-bottom: 1rem;
                    flex-wrap: wrap;
                }
                .legend-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    color: #475569;
                    font-size: 0.8rem;
                    font-weight: 600;
                }
                .legend-dot {
                    width: 14px;
                    height: 14px;
                    border-radius: 4px;
                    display: inline-block;
                }
                .finance-bars {
                    display: flex;
                    align-items: end;
                    justify-content: space-between;
                    gap: 1rem;
                    height: 270px;
                    border-left: 1px solid #dfe7f4;
                    border-bottom: 1px solid #dfe7f4;
                    padding: 1rem 1rem 0.5rem;
                    background: linear-gradient(to top, rgba(148,163,184,0.08), rgba(148,163,184,0.02));
                }
                .finance-bar-column {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: end;
                    gap: 0.75rem;
                    height: 100%;
                }
                .finance-bar-stack {
                    width: 100%;
                    max-width: 92px;
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                    justify-content: end;
                    padding: 0 0.35rem;
                }
                .finance-bar {
                    width: 100%;
                    border-radius: 8px 8px 0 0;
                    min-height: 20px;
                    box-shadow: inset 0 -10px 18px rgba(0,0,0,0.08);
                }
                .finance-bar-total { background: linear-gradient(180deg, #22c55e 0%, #1ea672 100%); }
                .finance-bar-encaisse { background: linear-gradient(180deg, #38bdf8 0%, #2563eb 100%); }
                .finance-bar-reste { background: linear-gradient(180deg, #f59e0b 0%, #ef4444 100%); }
                .finance-bar-label {
                    color: #64748b;
                    font-size: 0.75rem;
                    font-weight: 700;
                }
                @media (max-width: 991px) {
                    .finance-kpi-grid { grid-template-columns: 1fr; }
                }
                .finance-report-shell {
                    background: #f3f6fa;
                    border: 0;
                    border-radius: 20px;
                    padding: 1.5rem;
                }
                .finance-report-hero {
                    position: relative;
                    overflow: hidden;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1.5rem;
                    min-height: 150px;
                    margin-bottom: 1.25rem;
                    padding: 1.8rem 2rem;
                    border-radius: 18px;
                    color: #fff;
                    background: linear-gradient(118deg, #123e68 0%, #1b628d 58%, #35a98a 100%);
                    box-shadow: 0 16px 32px rgba(18, 62, 104, .18);
                }
                .finance-report-hero::after {
                    content: "";
                    position: absolute;
                    width: 260px;
                    height: 260px;
                    right: -75px;
                    top: -110px;
                    border: 1px solid rgba(255,255,255,.16);
                    border-radius: 50%;
                    box-shadow: 0 0 0 24px rgba(255,255,255,.04), 0 0 0 48px rgba(255,255,255,.03);
                }
                .finance-report-hero h2 { position: relative; z-index: 1; margin: 0; font-size: 1.45rem; font-weight: 800; letter-spacing: -.03em; }
                .finance-report-hero p { position: relative; z-index: 1; margin: .45rem 0 0; color: rgba(255,255,255,.74); font-size: .82rem; }
                .finance-report-period { position: relative; z-index: 1; padding: .75rem 1rem; border: 1px solid rgba(255,255,255,.22); border-radius: 11px; background: rgba(255,255,255,.1); color: #fff; font-size: .75rem; font-weight: 700; white-space: nowrap; }
                .finance-toolbar { padding: 1rem; margin-bottom: 1.25rem; border: 1px solid #e5ebf2; border-radius: 14px; background: #fff; box-shadow: 0 7px 20px rgba(31, 55, 80, .045); }
                .finance-toolbar .field label { color: #8a99ad; font-size: .68rem; letter-spacing: .08em; text-transform: uppercase; }
                .finance-toolbar .field input { color: #344563; font-weight: 600; }
                .finance-actions .btn { background: #1b628d; border-color: #1b628d; box-shadow: 0 6px 12px rgba(27,98,141,.18); }
                .finance-kpi-grid { gap: .85rem; margin-bottom: 1.25rem; }
                .finance-kpi { position: relative; overflow: hidden; min-height: 112px; border: 1px solid #e7edf4; background: #fff; box-shadow: 0 7px 20px rgba(31,55,80,.045); }
                .finance-kpi::after { content: ""; position: absolute; right: -25px; bottom: -38px; width: 105px; height: 105px; border-radius: 50%; background: rgba(27,98,141,.035); }
                .finance-kpi .label { color: #8a99ad; font-size: .76rem; }
                .finance-chart-panel { border: 1px solid #e5ebf2; border-radius: 16px; box-shadow: 0 7px 20px rgba(31,55,80,.045); }
                .finance-chart-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; background: #fff; color: #172b4d; border-bottom: 1px solid #edf1f5; font-size: .92rem; }
                .finance-chart-header::after { content: "•••"; color: #a5b2c2; letter-spacing: .15em; }
                .finance-chart-body { padding: 1.2rem 1.35rem; background: #fff; }
                .finance-chart-panel > .table-responsive { background: #fff; padding: 0 .85rem .7rem; }
                .finance-chart-panel table thead th { padding: .8rem .6rem; color: #8a99ad; border-bottom: 1px solid #e9eef4; font-size: .68rem; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
                .finance-chart-panel table tbody td { padding: .85rem .6rem; color: #53657e; border-color: #eff3f7; font-size: .82rem; }
                .finance-chart-panel table tbody tr:hover { background: #f8fbfd; }
                .finance-report-shell > form[style] { border: 1px solid #f5dfaa !important; background: #fffaf0 !important; box-shadow: 0 7px 20px rgba(31,55,80,.04); }
                @media (max-width: 650px) {
                    .finance-report-shell { padding: .75rem; }
                    .finance-report-hero { align-items: flex-start; flex-direction: column; padding: 1.35rem; }
                    .finance-period { width: 100%; }
                }
            </style>

            <div class="finance-report-shell">
                <div class="finance-report-hero">
                    <div>
                        <h2>Rapport financier</h2>
                        <p>Analysez les ventes, les encaissements et les dépenses de votre entreprise.</p>
                    </div>
                    <div class="finance-report-period"><i class="bi bi-calendar3 me-2"></i>{{ \Carbon\Carbon::parse($filters['date_debut'])->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($filters['date_fin'])->format('d/m/Y') }}</div>
                </div>
                <form method="GET" action="{{ route('admin.comptabilite.rapport_financier') }}" class="finance-toolbar">
                    <div class="field">
                        <label>Date début</label>
                        <input type="date" name="date_debut" value="{{ $filters['date_debut'] ?? now()->startOfYear()->toDateString() }}" />
                    </div>
                    <div class="field">
                        <label>Date fin</label>
                        <input type="date" name="date_fin" value="{{ $filters['date_fin'] ?? now()->endOfYear()->toDateString() }}" />
                    </div>
                    <div class="finance-actions">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                    </div>
                </form>

                <div class="finance-kpi-grid">
                    @foreach(($summary ?? []) as $item)
                        <div class="finance-kpi">
                            <div class="icon">
                                <i class="bi {{ $loop->index === 0 ? 'bi-graph-up-arrow' : ($loop->index === 1 ? 'bi-wallet2' : 'bi-credit-card') }}"></i>
                            </div>
                            <div>
                                <span class="value">{{ $item['value'] }}</span>
                                <span class="label">{{ $item['change'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="finance-chart-panel">
                    <div class="finance-chart-header">CA par commercial</div>
                    <div class="finance-chart-body">
                        <div class="chart-legend">
                            @foreach(($commercialSeries ?? []) as $series)
                                <span class="legend-chip"><span class="legend-dot" style="background: {{ $series['color'] }};"></span>{{ $series['label'] }}</span>
                            @endforeach
                        </div>
                        <canvas id="commercialTotalsChart" height="150"></canvas>
                    </div>
                </div>
                <div class="finance-chart-panel mt-5">
                        <div class="finance-chart-header">Évolution mensuelle par commercial</div>
                        <div class="finance-chart-body"><canvas id="commercialMonthlyChart" height="115"></canvas></div>
                    </div>
                <div class="finance-chart-panel mt-5">
                    <div class="finance-chart-header">Détail par utilisateur</div>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Utilisateur</th><th>CA Factures</th><th>CA Services</th><th>CA Total</th><th>Encaissé</th><th>Reste</th></tr></thead><tbody>
                    @forelse($commercialRows ?? [] as $row)<tr><td>{{ $loop->iteration }}</td><td class="fw-bold">{{ $row['user'] }}</td><td>{{ number_format($row['invoices'], 0, ',', ' ') }} FCFA</td><td>{{ number_format($row['services'], 0, ',', ' ') }} FCFA</td><td class="fw-bold">{{ number_format($row['total'], 0, ',', ' ') }} FCFA</td><td>{{ number_format($row['paid'], 0, ',', ' ') }} FCFA</td><td>{{ number_format($row['remaining'], 0, ',', ' ') }} FCFA</td></tr>@empty<tr><td colspan="7" class="text-center text-muted">Aucune vente sur cette période.</td></tr>@endforelse
                    </tbody></table></div>
                </div>
                <div class="finance-chart-panel mt-5">
                    <div class="finance-chart-header">Dépenses globales</div>
                    <div class="finance-kpi-grid p-4 mb-0">
                        <div class="finance-kpi"><div class="icon">🚚</div><div><span class="value">{{ number_format($expenseTotal ?? 0, 0, ',', ' ') }} FCFA</span><span class="label">Total dépenses<br><small>Caisse + Banque + Fournisseurs</small></span></div></div>
                        <div class="finance-kpi"><div class="icon" style="background:linear-gradient(135deg,#f59e0b,#e58b12)">💵</div><div><span class="value">{{ number_format($expenseTotal ?? 0, 0, ',', ' ') }} FCFA</span><span class="label">Déjà payé / décaissé<br><small>Taux : 100%</small></span></div></div>
                    </div>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Source</th><th>Catégorie</th><th>Montant total</th></tr></thead><tbody>
                    @forelse($expenseCategories ?? [] as $row)<tr><td>{{ $row['source'] }}</td><td>{{ $row['category'] }}</td><td class="fw-bold">{{ number_format($row['amount'], 0, ',', ' ') }} FCFA</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">Aucune dépense sur cette période.</td></tr>@endforelse
                    </tbody></table></div>
                </div>
                <div class="finance-chart-panel mt-5">
                    <div class="finance-chart-header">Évolution mensuelle des dépenses</div>
                    <div class="finance-chart-body"><canvas id="expenseMonthlyChart" height="120"></canvas></div>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Mois</th><th>Sorties de caisse</th><th>Dépenses banque (Débit)</th><th>Achats fournisseurs</th><th>Dépenses totales</th></tr></thead><tbody>
                    @foreach($expenseMonths ?? [] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ number_format($row['cash'], 0, ',', ' ') }} FCFA</td><td>{{ number_format($row['bank'], 0, ',', ' ') }} FCFA</td><td>{{ number_format($row['suppliers'], 0, ',', ' ') }} FCFA</td><td class="fw-bold">{{ number_format($row['total'], 0, ',', ' ') }} FCFA</td></tr>@endforeach
                    </tbody></table></div>
                </div>
                <form method="POST" action="{{ route('admin.comptabilite.rapport_financier.observations') }}" class="mt-5 p-4" style="background:#fff9e8;border-left:4px solid #f59e0b;border-radius:10px;">
                    @csrf
                    <input type="hidden" name="date_debut" value="{{ $filters['date_debut'] }}">
                    <input type="hidden" name="date_fin" value="{{ $filters['date_fin'] }}">
                    <label class="fw-bold mb-2">📝 Observation générale :</label>
                    <textarea name="general_observation" class="form-control mb-4" rows="2" placeholder="Saisir une observation générale sur la période">{{ $observations['general'] ?? '' }}</textarea>
                    <label class="fw-bold mb-2">📌 Observations sur les dépenses :</label>
                    <textarea name="expense_observation" class="form-control mb-3" rows="3" placeholder="Renseigner les observations de rapport, anomalies, écarts ou commentaires sur les dépenses">{{ $observations['expenses'] ?? '' }}</textarea>
                    <button class="btn btn-warning text-white">💾 Enregistrer</button>
                </form>
                <div class="finance-kpi-grid mt-5">
                    <div class="finance-kpi"><div class="icon" style="background:linear-gradient(135deg,#38a1d6,#2480b5)">⚖</div><div><span class="value">{{ number_format($totalRealise - $expenseTotal, 0, ',', ' ') }} FCFA</span><span class="label">Résultat brut<br><small>Bénéfice généré(e) sur la période</small></span></div></div>
                    <div class="finance-kpi"><div class="icon" style="background:linear-gradient(135deg,#a855c7,#7e3aa0)">⚖</div><div><span class="value">{{ number_format($encaisse - $expenseTotal, 0, ',', ' ') }} FCFA</span><span class="label">Résultat net encaissé<br><small>Trésorerie positive sur la période</small></span></div></div>
                </div>
                <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
                <script>
                    (() => {
                        const labels = @json(collect($chartData ?? [])->pluck('label')->values());
                        const money = value => new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
                        const series = @json($commercialSeries ?? []);
                        new Chart(document.getElementById('commercialTotalsChart'), {type:'bar', data:{labels:series.map(item=>item.label),datasets:[{label:'Montant (FCFA)',data:series.map(item=>item.value),backgroundColor:['#3ecf8e','#44b3ff','#e76f51']}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{ticks:{callback:value=>money(value)}}}}});
                        const commercial = @json($commercialMonthly ?? []);
                        new Chart(document.getElementById('commercialMonthlyChart'), {type:'line',data:{labels:labels,datasets:commercial.map((item,index)=>({label:item.label,data:item.values,borderColor:['#08a85b','#08b9e8','#7c3aed','#f59e0b'][index%4],backgroundColor:'transparent',tension:.35,pointRadius:4}))},options:{responsive:true,plugins:{legend:{position:'top'}},scales:{y:{ticks:{callback:value=>money(value)}}}}});
                        const expenses = @json($expenseMonths ?? []);
                        new Chart(document.getElementById('expenseMonthlyChart'), {type:'line',data:{labels:expenses.map(item=>item.label),datasets:[{label:'Sorties de caisse',data:expenses.map(item=>item.cash),borderColor:'#ff5348',tension:.35},{label:'Dépenses banque (Débit)',data:expenses.map(item=>item.bank),borderColor:'#8054d8',tension:.35},{label:'Achats fournisseurs',data:expenses.map(item=>item.suppliers),borderColor:'#20ad8b',tension:.35},{label:'Dépenses totales',data:expenses.map(item=>item.total),borderColor:'#25384d',borderDash:[5,5],tension:.35}]},options:{responsive:true,plugins:{legend:{position:'top'}},scales:{y:{ticks:{callback:value=>money(value)}}}}});
                    })();
                </script>
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
@endsection
