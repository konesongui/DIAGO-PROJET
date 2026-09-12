@extends('admin.layout')

@section('content')
@php
    $state = $invoice->state();
    [$stateLabel, $stateTone, $stateIcon] = \App\Models\CustomInvoice::states()[$state];
    $paid = (float) $invoice->paid_amount;
    $credited = (float) $invoice->credited_amount;
    $due = $invoice->amountDue();
    $remaining = $invoice->remainingAmount();
    // Avoir émis après paiement : le client a versé plus que ce qu'il doit désormais.
    $overpaid = $credited > 0 ? max(0, round($paid - $due, 2)) : 0;

    $items = collect($invoice->items ?? []);
    $forfaits = collect($invoice->global_services ?? []);
    $discount = (float) $invoice->total_discount;
    $netHt = (float) ($invoice->subtotal_after_discount ?: max(0, (float) $invoice->total_ht - $discount));
    $taxLabel = $invoice->taxLabel();
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
    $categories = \App\Models\CustomInvoice::itemOptions()['item_category'];

    $payments = $invoice->payments;
    // Montant payé sans règlement daté correspondant (paiements saisis avant le suivi des règlements).
    $undated = max(0, round($paid - (float) $payments->sum('amount'), 2));
    $methodLabels = ['cash' => ['Espèces (caisse)', 'bi-cash-stack', 'green'], 'bank' => ['Banque', 'bi-bank', 'blue'], 'transfer' => ['Virement', 'bi-arrow-left-right', 'cyan']];
    $plannedMethod = ['cash' => 'Espèces', 'bank' => 'Banque'][$invoice->payment_method] ?? $invoice->payment_method;

    $overdue = $remaining > 0 && $state !== 'cancelled' && $invoice->valid_until && $invoice->valid_until->lt(today());
    $remainingHint = match (true) {
        $state === 'cancelled' => 'annulée par avoir',
        $remaining <= 0 => 'facture soldée',
        $overdue => 'échue depuis le ' . $invoice->valid_until->format('d/m/Y'),
        (bool) $invoice->valid_until => 'échéance le ' . $invoice->valid_until->format('d/m/Y'),
        default => null,
    };
    $canIssue = ! $invoice->issued_at && $state !== 'cancelled';
    $canPay = $remaining > 0 && $state !== 'cancelled';
    $canCredit = $invoice->issued_at && $creditableAmount > 0;
    $invoiceDate = $invoice->quote_date ?? $invoice->created_at;
    $pageSubtitle = collect([$invoice->client_name ?: 'Client', $invoice->subject])->filter()->implode(' · ');
    $issueConfirm = 'Émettre cette facture ? Elle ne pourra plus être modifiée : une correction passera par un avoir.';
    $reopenCredit = old('credit_form') && $errors->any();
    $creditFull = old('full', '1') === '1';

    $callout = match (true) {
        $state === 'cancelled' => ['red', 'bi-x-circle', 'Annulée par avoir', 'Tout le montant a été porté en avoir : la facture ne reçoit plus de paiement.'],
        ! $invoice->issued_at && $paid > 0 => ['orange', 'bi-exclamation-triangle', 'Paiement reçu, facture non émise', 'Un paiement a été enregistré : la facture ne peut plus être modifiée. Elle n’est pas encore au journal des ventes ; émettez-la pour la rendre définitive.'],
        ! $invoice->issued_at => ['orange', 'bi-pencil-square', 'Brouillon, encore modifiable', 'La facture n’est ni remise au client ni portée au journal des ventes. Émettez-la au moment de l’envoyer : elle sera figée, et toute correction passera par un avoir.'],
        $state === 'paid' && $overpaid > 0 => ['green', 'bi-check2-circle', 'Soldée', 'Émise le ' . $invoice->issued_at->format('d/m/Y') . ' et entièrement payée. L’avoir émis après paiement laisse un trop-perçu de ' . money($overpaid) . ' à rembourser au client.'],
        $state === 'paid' => ['green', 'bi-check2-circle', 'Soldée', 'Émise le ' . $invoice->issued_at->format('d/m/Y') . ' et entièrement payée. Une erreur se corrige par un avoir.'],
        default => ['blue', 'bi-send-check', 'Émise le ' . $invoice->issued_at->format('d/m/Y'), 'Document définitif : il ne se modifie plus et ne se supprime plus. Une erreur se corrige par un avoir.'],
    };
    $paymentTrigger = 'data-bs-toggle="modal" data-bs-target="#paymentModal" data-payment-invoice="' . $invoice->id . '"'
        . ' data-action="' . e(route('admin.commercial.custom-invoice.payment', $invoice)) . '"'
        . ' data-label="' . e($invoice->reference . ' · ' . ($invoice->client_name ?: 'Client')) . '"'
        . ' data-remaining="' . number_format($remaining, 2, '.', '') . '" data-remaining-label="' . e(money($remaining)) . '"';
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header :title="'Facture ' . $invoice->reference" :subtitle="$pageSubtitle" :back="route('admin.commercial.custom-invoice.index')" back-label="Factures personnalisées">
        <x-slot:actions>
            @unless($locked)
                <a href="{{ route('admin.commercial.custom-invoice.edit', $invoice) }}" class="dg-btn dg-btn--outline"><i class="bi bi-pencil"></i>Modifier</a>
            @endunless
            <a href="{{ route('admin.commercial.custom-invoice.print', $invoice) }}" target="_blank" class="dg-btn dg-btn--outline"><i class="bi bi-printer"></i>Imprimer</a>
            <div class="dropdown">
                <button type="button" class="dg-btn dg-btn--outline" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots"></i>Plus</button>
                <ul class="dropdown-menu dropdown-menu-end dg-dropdown">
                    @if($canPay && ! $invoice->issued_at)
                        <li><button type="button" class="dropdown-item" {!! $paymentTrigger !!}><i class="bi bi-cash-coin"></i>Enregistrer un paiement</button></li>
                    @endif
                    @if($canCredit)
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#creditNoteModal"><i class="bi bi-arrow-counterclockwise"></i>Émettre un avoir</button></li>
                    @endif
                    <li><form method="POST" action="{{ route('admin.commercial.custom-invoice.email', $invoice) }}">@csrf<button class="dropdown-item"><i class="bi bi-envelope"></i>Envoyer par e-mail</button></form></li>
                    <li><form method="POST" action="{{ route('admin.commercial.custom-invoice.whatsapp', $invoice) }}">@csrf<button class="dropdown-item"><i class="bi bi-whatsapp"></i>Envoyer par WhatsApp</button></form></li>
                    <li><form method="POST" action="{{ route('admin.commercial.custom-invoice.duplicate', $invoice) }}">@csrf<button class="dropdown-item"><i class="bi bi-copy"></i>Dupliquer</button></form></li>
                    @unless($locked)
                        <li><hr class="dropdown-divider"></li>
                        <li><form method="POST" action="{{ route('admin.commercial.custom-invoice.destroy', $invoice) }}" onsubmit="return confirm('Supprimer définitivement ce brouillon ?')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash"></i>Supprimer</button></form></li>
                    @endunless
                </ul>
            </div>
            @if($canPay && $invoice->issued_at)
                <button type="button" class="dg-btn dg-btn--primary" {!! $paymentTrigger !!}><i class="bi bi-cash-coin"></i>Enregistrer un paiement</button>
            @endif
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-kpi-grid">
        <x-dg.kpi label="Total TTC" :value="money((float) $invoice->total_ttc)" icon="bi-receipt" color="blue" :hint="'HT net : ' . money($netHt)" />
        <x-dg.kpi label="Encaissé" :value="money($paid)" icon="bi-arrow-down-left-circle" color="green" :hint="$payments->count() ? $payments->count() . ' règlement(s)' : ($paid > 0 ? 'règlement non daté' : 'aucun paiement')" />
        <x-dg.kpi label="Reste à payer" :value="$state === 'cancelled' ? '—' : money($remaining)" icon="bi-hourglass-split" color="red"
            :hint="$remainingHint" />
        <x-dg.kpi label="Avoirs" :value="money($credited)" icon="bi-arrow-counterclockwise" color="orange" :hint="$invoice->creditNotes->count() ? $invoice->creditNotes->count() . ' avoir(s) émis' : 'aucun avoir'" />
    </div>

    <div class="dg-callout dg-tone-{{ $callout[0] }} mb-6">
        <span class="dg-tile"><i class="bi {{ $callout[1] }}"></i></span>
        <div style="flex:1 1 320px;min-width:0">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold">{{ $callout[2] }}</span>
                <span class="dg-badge dg-badge--{{ $stateTone }}"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span>
                @if($overdue)<span class="dg-badge dg-badge--danger"><i class="bi bi-alarm"></i>Échue</span>@endif
            </div>
            <div class="dg-muted mt-1" style="font-size:13.5px">{{ $callout[3] }}</div>
        </div>
        @if($canIssue)
            <form method="POST" action="{{ route('admin.commercial.custom-invoice.issue', $invoice) }}" onsubmit="return confirm(@js($issueConfirm))">
                @csrf
                <button class="dg-btn dg-btn--primary"><i class="bi bi-send-check"></i>Émettre la facture</button>
            </form>
        @elseif($canCredit)
            <button type="button" class="dg-btn dg-btn--outline" data-bs-toggle="modal" data-bs-target="#creditNoteModal"><i class="bi bi-arrow-counterclockwise"></i>Émettre un avoir</button>
        @endif
    </div>

    <div class="dg-grid-halves mb-6">
        <x-dg.card title="Client" icon="bi-person" color="blue">
            <div class="cinv-client">
                <div class="cinv-client__name">{{ $invoice->client_name ?: 'Client' }}</div>
                <div class="cinv-client__line"><i class="bi bi-telephone"></i>{{ $invoice->client_phone ?: 'Téléphone non renseigné' }}</div>
                <div class="cinv-client__line {{ $invoice->client_email ? '' : 'dg-muted' }}"><i class="bi bi-envelope"></i>{{ $invoice->client_email ?: 'E-mail non renseigné : l’envoi par e-mail est impossible' }}</div>
                @if($invoice->delivery_location)
                    <div class="cinv-client__line"><i class="bi bi-geo-alt"></i>Livraison : {{ $invoice->delivery_location }}</div>
                @endif
            </div>
        </x-dg.card>
        <x-dg.card title="Informations" icon="bi-info-circle" color="teal">
            <table class="dg-mini-table">
                <tr><td class="dg-muted">Date de la facture</td><td>{{ $invoiceDate?->format('d/m/Y') ?: '—' }}</td></tr>
                <tr>
                    <td class="dg-muted">Échéance</td>
                    <td class="{{ $overdue ? 'dg-amount-negative' : '' }}">{{ $invoice->valid_until?->format('d/m/Y') ?: '—' }}</td>
                </tr>
                <tr><td class="dg-muted">Mode de règlement prévu</td><td>{{ $plannedMethod ?: '—' }}</td></tr>
                <tr><td class="dg-muted">Émise le</td><td>{{ $invoice->issued_at?->format('d/m/Y') ?: 'Non émise' }}</td></tr>
                <tr><td class="dg-muted">Créée par</td><td>{{ $invoice->creator?->name ?: '—' }}</td></tr>
            </table>
        </x-dg.card>
    </div>

    <div class="dg-card dg-card--table mb-6">
        <div class="dg-card__header">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-purple"><i class="bi bi-list-ul"></i></span>Lignes de la facture</h2>
            <span class="dg-card__meta">{{ $items->count() + $forfaits->count() }} ligne(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 cinv-lines no-export no-column-sort">
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th class="text-end">Qté</th>
                        <th>Unité</th>
                        <th class="text-end">Prix unitaire HT</th>
                        <th class="text-end">Total HT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        @php
                            $specs = \App\Models\CustomInvoice::itemSpecs($item);
                            $category = $item['item_category'] ?? null;
                        @endphp
                        <tr>
                            <td>
                                <span class="d-block fw-semibold">{{ $item['item_name'] ?? 'Article' }}</span>
                                @if($category && $category !== 'autre')
                                    <span class="d-block dg-muted" style="font-size:12.5px">{{ $categories[$category] ?? $category }}</span>
                                @endif
                                @if($specs)
                                    <div class="cinv-specs">
                                        @foreach($specs as $specLabel => $specValue)
                                            <span class="cinv-spec"><span>{{ $specLabel }}</span> {{ $specValue }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="text-end dg-cell-num">{{ $quantity($item['quantity'] ?? 0) }}</td>
                            <td>{{ ($item['unit'] ?? '') ?: '—' }}</td>
                            <td class="text-end dg-cell-num">{{ money((float) ($item['price'] ?? 0)) }}</td>
                            <td class="text-end dg-cell-num fw-semibold">{{ money((float) ($item['quantity'] ?? 0) * (float) ($item['price'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                    @foreach($forfaits as $forfait)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $forfait['label'] ?? 'Forfait' }}</span>
                                <span class="cinv-forfait-badge"><i class="bi bi-bookmark-star"></i>Forfait</span>
                            </td>
                            <td class="text-end dg-cell-num">1</td>
                            <td>forfait</td>
                            <td class="text-end dg-cell-num">{{ money((float) ($forfait['price'] ?? 0)) }}</td>
                            <td class="text-end dg-cell-num fw-semibold">{{ money((float) ($forfait['price'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                    @if($items->isEmpty() && $forfaits->isEmpty())
                        <tr><td colspan="5" class="text-center dg-muted py-6">Aucune ligne sur cette facture.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="cinv-summary mb-6">
        <div class="cinv-notes">
            <h3>Notes et conditions</h3>
            @if($invoice->payment_terms || $invoice->delivery_terms)
                @if($invoice->payment_terms)<p><strong>Conditions de règlement :</strong> {{ $invoice->payment_terms }}</p>@endif
                @if($invoice->delivery_terms)<p><strong>Conditions de livraison :</strong> {{ $invoice->delivery_terms }}</p>@endif
            @else
                <p class="dg-muted">Aucune condition particulière n’est indiquée sur cette facture.</p>
            @endif
        </div>
        <div class="dg-card cinv-totals">
            <div class="cinv-totals__row"><span>Total HT</span><strong>{{ money((float) $invoice->total_ht) }}</strong></div>
            @if($discount > 0)
                <div class="cinv-totals__row"><span>Remise</span><strong>− {{ money($discount) }}</strong></div>
                <div class="cinv-totals__row"><span>Total HT net</span><strong>{{ money($netHt) }}</strong></div>
            @endif
            <div class="cinv-totals__row"><span>{{ $taxLabel }}</span><strong>{{ money((float) $invoice->tax_amount) }}</strong></div>
            <div class="cinv-totals__grand"><span>Total TTC</span><strong>{{ money((float) $invoice->total_ttc) }}</strong></div>
            @if($credited > 0)
                <div class="cinv-totals__row cinv-totals__row--sep"><span>Avoirs émis</span><strong>− {{ money($credited) }}</strong></div>
                <div class="cinv-totals__row"><span>Net dû</span><strong>{{ money($due) }}</strong></div>
            @endif
            <div class="cinv-totals__row {{ $credited > 0 ? '' : 'cinv-totals__row--sep' }}"><span>Déjà payé</span><strong>{{ money($paid) }}</strong></div>
            @if($overpaid > 0)
                <div class="cinv-totals__row cinv-totals__row--warn"><span>Trop-perçu à rembourser</span><strong>{{ money($overpaid) }}</strong></div>
            @endif
            @if($state !== 'cancelled')
                <div class="cinv-totals__due"><span>Reste à payer</span><strong>{{ money($remaining) }}</strong></div>
            @endif
        </div>
    </div>

    <div class="dg-grid-halves">
        <x-dg.card title="Paiements reçus" icon="bi-cash-coin" color="green">
            @if($canPay)
                <x-slot:actions>
                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" {!! $paymentTrigger !!}><i class="bi bi-plus-lg"></i>Enregistrer</button>
                </x-slot:actions>
            @endif
            @if($payments->isEmpty() && $undated <= 0)
                <div class="dg-chart-empty" style="height:auto;min-height:150px;padding:20px">
                    <span class="dg-tile dg-tone-green"><i class="bi bi-cash-coin"></i></span>
                    <div><strong>Aucun paiement reçu</strong>{{ $state === 'cancelled' ? 'La facture est annulée par avoir.' : 'Chaque règlement, en caisse ou en banque, apparaîtra ici avec sa date.' }}</div>
                </div>
            @else
                <div class="dg-list">
                    @foreach($payments as $payment)
                        @php [$methodLabel, $methodIcon, $methodColor] = $methodLabels[$payment->method] ?? [$payment->method, 'bi-cash-coin', 'green']; @endphp
                        <div class="dg-list-row">
                            <span class="dg-tile dg-tile--sm dg-tone-{{ $methodColor }}"><i class="bi {{ $methodIcon }}"></i></span>
                            <div class="dg-list-row__body">
                                <div class="dg-list-row__title">{{ $methodLabel }}</div>
                                <div class="dg-list-row__sub">Reçu le {{ $payment->paid_on->format('d/m/Y') }}{{ $payment->reference ? ' · ' . $payment->reference : '' }}</div>
                            </div>
                            <span class="dg-list-row__value dg-amount-positive">{{ money((float) $payment->amount) }}</span>
                        </div>
                    @endforeach
                    @if($undated > 0)
                        <div class="dg-list-row">
                            <span class="dg-tile dg-tile--sm dg-tone-navy"><i class="bi bi-question-lg"></i></span>
                            <div class="dg-list-row__body">
                                <div class="dg-list-row__title">Paiement non daté</div>
                                <div class="dg-list-row__sub">Montant payé enregistré sans règlement détaillé</div>
                            </div>
                            <span class="dg-list-row__value dg-amount-positive">{{ money($undated) }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </x-dg.card>

        <x-dg.card title="Avoirs" icon="bi-arrow-counterclockwise" color="orange">
            @if($canCredit)
                <x-slot:actions>
                    <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" data-bs-toggle="modal" data-bs-target="#creditNoteModal"><i class="bi bi-plus-lg"></i>Émettre</button>
                </x-slot:actions>
            @endif
            @if($invoice->creditNotes->isEmpty())
                <div class="dg-chart-empty" style="height:auto;min-height:150px;padding:20px">
                    <span class="dg-tile dg-tone-orange"><i class="bi bi-arrow-counterclockwise"></i></span>
                    <div><strong>Aucun avoir</strong>{{ $invoice->issued_at ? 'Une erreur sur la facture émise se corrige par un avoir, total ou partiel.' : 'Un brouillon se corrige directement ; les avoirs concernent les factures émises.' }}</div>
                </div>
            @else
                <div class="dg-list">
                    @foreach($invoice->creditNotes as $note)
                        <div class="dg-list-row">
                            <span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-file-earmark-minus"></i></span>
                            <div class="dg-list-row__body">
                                <div class="dg-list-row__title">{{ $note->reference }}{{ $note->is_full ? ' · avoir total' : '' }}</div>
                                <div class="dg-list-row__sub text-wrap">{{ $note->created_at->format('d/m/Y') }} · {{ $note->reason }}</div>
                            </div>
                            <span class="dg-list-row__value dg-amount-negative">− {{ money((float) $note->total_ttc) }}</span>
                        </div>
                    @endforeach
                </div>
                @if($canCredit)
                    <p class="dg-muted mt-3 mb-0" style="font-size:13px">Reste à créditer : <strong class="text-dark">{{ money($creditableAmount) }}</strong></p>
                @endif
            @endif
        </x-dg.card>
    </div>

    @include('admin.partials.custom-invoice-payment-modal')

    @if($canCredit)
        <div class="modal fade dg-tone-orange" id="creditNoteModal" tabindex="-1" aria-labelledby="creditNoteTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.commercial.custom-invoice.credit', $invoice) }}">
                        @csrf
                        <input type="hidden" name="credit_form" value="1">
                        <div class="modal-header">
                            <h5 class="modal-title" id="creditNoteTitle"><i class="bi bi-arrow-counterclockwise me-2"></i>Émettre un avoir</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <div class="cinv-credit-summary mb-4">
                                <span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-receipt"></i></span>
                                <span class="flex-grow-1">
                                    <span class="d-block fw-semibold">{{ $invoice->reference }} · {{ $invoice->client_name ?: 'Client' }}</span>
                                    <span class="d-block dg-muted" style="font-size:13px">Reste à créditer</span>
                                </span>
                                <strong class="cinv-credit-summary__amount">{{ money($creditableAmount) }}</strong>
                            </div>
                            <p class="dg-muted" style="font-size:13.5px">La facture d’origine reste inchangée. L’avoir enregistre la correction, reprend son régime fiscal et contrepasse la vente au journal.</p>
                            <div class="cinv-choice mb-3">
                                <label class="cinv-choice__option">
                                    <input type="radio" class="form-check-input" name="full" value="1" @checked($creditFull)>
                                    <span><strong>Avoir total</strong><span class="d-block dg-muted" style="font-size:12.5px">{{ money($creditableAmount) }}</span></span>
                                </label>
                                <label class="cinv-choice__option">
                                    <input type="radio" class="form-check-input" name="full" value="0" @checked(! $creditFull)>
                                    <span><strong>Avoir partiel</strong><span class="d-block dg-muted" style="font-size:12.5px">montant TTC à préciser</span></span>
                                </label>
                            </div>
                            <div class="mb-4 {{ $creditFull ? 'd-none' : '' }}" id="creditAmountField">
                                <label class="form-label" for="creditAmount">Montant TTC à créditer</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $creditableAmount }}" name="amount" id="creditAmount" class="form-control" value="{{ old('amount') }}" @disabled($creditFull)>
                                <div class="form-text">Maximum : {{ money($creditableAmount) }}</div>
                            </div>
                            <label class="form-label required" for="creditReason">Motif</label>
                            <textarea name="reason" id="creditReason" class="form-control" rows="3" minlength="5" maxlength="500" required
                                      placeholder="Ex. : erreur de quantité, retour marchandise, remise accordée après coup">{{ old('reason') }}</textarea>
                            <div class="form-text">Obligatoire : un avoir sans motif n’est pas justifiable lors d’un contrôle.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Émettre l’avoir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    .dg-list-row__value.dg-amount-positive { color: var(--dg-success); }
    .dg-list-row__value.dg-amount-negative { color: var(--dg-danger); }
    .cinv-client__name { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
    .cinv-client__line { display: flex; align-items: center; gap: 10px; padding: 3px 0; font-size: 14px; }
    .cinv-client__line .bi { color: #2563eb; }
    .cinv-lines th:nth-child(2), .cinv-lines td:nth-child(2) { width: 80px; }
    .cinv-lines th:nth-child(3), .cinv-lines td:nth-child(3) { width: 110px; }
    .cinv-lines th:nth-child(4), .cinv-lines td:nth-child(4),
    .cinv-lines th:nth-child(5), .cinv-lines td:nth-child(5) { width: 170px; white-space: nowrap; }
    .cinv-specs { margin-top: 6px; padding: 6px 10px; border-left: 3px solid #7c3aed; border-radius: 0 var(--dg-radius-sm) var(--dg-radius-sm) 0; background: rgba(124, 58, 237, .06); font-size: 12.5px; line-height: 1.7; color: var(--dg-text); }
    .cinv-spec { white-space: nowrap; }
    .cinv-spec + .cinv-spec::before { content: '·'; margin: 0 7px; color: var(--dg-subtle); }
    .cinv-spec span { color: #7c3aed; font-weight: 600; }
    .cinv-forfait-badge { display: inline-flex; align-items: center; gap: 4px; margin-left: 8px; padding: 2px 9px; border-radius: 999px; background: rgba(219, 39, 119, .09); font-size: 12px; font-weight: 600; color: #db2777; }
    .cinv-forfait-badge .bi { color: inherit; }
    .cinv-summary { display: grid; grid-template-columns: minmax(0, 1fr) 400px; gap: 24px; align-items: start; }
    .cinv-notes h3 { margin: 0 0 6px; font-size: 14px; font-weight: 600; color: var(--dg-muted); }
    .cinv-notes p { margin: 0 0 6px; font-size: 14px; color: var(--dg-muted); max-width: 60ch; }
    .cinv-notes p strong { color: var(--dg-text); font-weight: 600; }
    .cinv-totals { padding: 20px 22px; overflow: hidden; }
    .cinv-totals__row { display: flex; justify-content: space-between; gap: 16px; padding: 4px 0; font-size: 14px; }
    .cinv-totals__row strong { font-weight: 500; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .cinv-totals__row--sep { margin-top: 6px; padding-top: 10px; border-top: 1px solid var(--dg-border); }
    .cinv-totals__row--warn, .cinv-totals__row--warn strong { color: #d97706; font-weight: 600; }
    .cinv-totals__grand { display: flex; justify-content: space-between; align-items: baseline; margin-top: 6px; padding-top: 10px; border-top: 2px solid var(--dg-navy); color: var(--dg-navy); }
    .cinv-totals__grand span { font-size: 16px; font-weight: 700; }
    .cinv-totals__grand strong { font-size: 21px; font-weight: 700; white-space: nowrap; }
    .cinv-totals__due { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin: 14px -22px -20px; padding: 14px 22px; background: var(--dg-navy); color: #fff; }
    .cinv-totals__due span { font-weight: 600; }
    .cinv-totals__due strong { font-size: 19px; color: var(--dg-yellow); white-space: nowrap; }
    .cinv-credit-summary { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--dg-radius); background: rgba(234, 88, 12, .08); border: 1px solid rgba(234, 88, 12, .25); }
    .cinv-credit-summary__amount { font-size: 17px; color: #ea580c; white-space: nowrap; }
    .cinv-choice { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .cinv-choice__option { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius); cursor: pointer; font-size: 14px; }
    .cinv-choice__option:has(input:checked) { border-color: #ea580c; background: rgba(234, 88, 12, .05); }
    .cinv-choice__option .form-check-input { margin: 2px 0 0; flex-shrink: 0; }
    @media (max-width: 991px) { .cinv-summary { grid-template-columns: 1fr; } }
    @media (max-width: 575px) {
        .cinv-choice { grid-template-columns: 1fr; }
        .cinv-lines th:nth-child(4), .cinv-lines td:nth-child(4), .cinv-lines th:nth-child(5), .cinv-lines td:nth-child(5) { width: auto; }
    }
</style>
@if($canCredit)
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Avoir partiel : le montant n'est demandé (et envoyé) que dans ce cas.
    const field = document.getElementById('creditAmountField');
    const amount = document.getElementById('creditAmount');
    document.querySelectorAll('#creditNoteModal input[name="full"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const partial = radio.value === '0' && radio.checked;
            field.classList.toggle('d-none', !partial);
            amount.disabled = !partial;
            amount.required = partial;
            if (partial) amount.focus();
        });
    });
    amount.required = !amount.disabled;
    @if($reopenCredit)
        window.addEventListener('load', function () {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('creditNoteModal')).show();
        });
    @endif
});
</script>
@endif
@endsection
