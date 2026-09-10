@extends('admin.layout')

@php
    $isEdit = !empty($invoice);
    $invoiceItems = $invoice?->items ?? [[
        'item_name' => 'Impression de livre',
        'item_category' => 'impression',
        'unit' => 'Exemplaire',
        'quantity' => 1,
        'price' => 0,
        'book_type' => '',
        'book_type_other' => '',
        'paper_type' => 'bouffant_creme',
        'paper_type_other' => '',
        'page_count' => 100,
        'printing_type' => 'noir_blanc',
        'cover_type' => 'couche_300g',
        'book_format' => 'poche_11x18',
        'format_other' => '',
        'lamination' => 'brillant',
        'binding_type' => 'dos_carre_colle',
        'binding_other' => '',
        'additional_options' => '',
    ]];
    $invoiceServices = collect($invoice?->global_services ?? [])->pluck('key')->all();
    $invoiceDefaultDiscountType = $invoice && (float) ($invoice->total_discount ?? 0) > 0
        ? ((float) ($invoice->total_ht ?? 0) > 0 && (float) ($invoice->total_discount ?? 0) >= (float) ($invoice->total_ht ?? 0) * 0.5 ? 'percent' : 'amount')
        : 'none';
@endphp

@section('content')
<style>
    .custom-invoice-shell { max-width: 1400px; margin: 0 auto; }
    .custom-invoice-card { background: #fff; border: 1px solid #edf2f7; border-radius: 18px; box-shadow: 0 12px 28px rgba(15,23,42,.04); }
    .custom-invoice-section { border: 1px dashed #dfe7f3; border-radius: 16px; background: #f9fbff; padding: 18px; }
    .custom-invoice-item { border: 1px solid #edf2f7; border-radius: 16px; background: #fff; padding: 18px; margin-bottom: 16px; }
    .field-label { font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
    .other-field { display: none; }
    .service-option { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; }
    .totals-box { background: linear-gradient(135deg, rgba(37,99,235,.07), rgba(14,165,233,.05)); border: 1px solid rgba(37,99,235,.14); border-radius: 16px; padding: 18px; }
    .summary-total { font-size: 2rem; font-weight: 800; color: #0f172a; }
</style>

<div class="custom-invoice-shell">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-5">
        <div>
            <div class="text-uppercase text-muted fs-8 fw-bold">Commercial</div>
            <h2 class="fs-2 fw-bold mb-1">{{ $title ?? 'Facture personnalisée' }}</h2>
            <p class="text-muted mb-0">{{ $subtitle ?? 'Créer une facture sur mesure sur la base d’une demande de document imprimé.' }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.commercial.custom-invoice.index') }}" class="btn btn-light">Retour</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('admin.commercial.custom-invoice.update', $invoice) : route('admin.commercial.custom-invoice.store') }}" class="custom-invoice-card p-4 p-md-5">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="custom-invoice-section mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                <h3 class="h5 fw-bold text-dark mb-0">Informations du client</h3>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="field-label">Client</label>
                    <select name="customer" class="form-select">
                        <option value="">Sélectionner...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client['id'] }}" {{ old('customer', $isEdit ? 'new' : '') == $client['id'] ? 'selected' : '' }}>{{ $client['name'] }} ({{ $client['phone'] }})</option>
                        @endforeach
                        <option value="new" {{ old('customer', $isEdit ? 'new' : '') === 'new' ? 'selected' : '' }}>Nouveau client</option>
                    </select>
                </div>
                <div class="col-md-4 new-client-field" style="display:none;">
                    <label class="field-label">Nom & prénom</label>
                    <input type="text" name="new_client_name" class="form-control" value="{{ old('new_client_name', $invoice?->client_name ?? '') }}" placeholder="Nom & prénom" />
                </div>
                <div class="col-md-4 new-client-field" style="display:none;">
                    <label class="field-label">Téléphone / WhatsApp</label>
                    <input type="text" name="new_client_phone" class="form-control" value="{{ old('new_client_phone', $invoice?->client_phone ?? '') }}" placeholder="Numéro" />
                </div>
                <div class="col-md-4 new-client-field" style="display:none;">
                    <label class="field-label">Email</label>
                    <input type="email" name="new_client_email" class="form-control" value="{{ old('new_client_email', $invoice?->client_email ?? '') }}" placeholder="Email" />
                </div>
                <div class="col-md-4">
                    <label class="field-label">Date de création</label>
                    <input type="date" name="quote_date" class="form-control" value="{{ old('quote_date', $invoice?->quote_date?->format('Y-m-d') ?? date('Y-m-d')) }}" />
                </div>
                <div class="col-md-4">
                    <label class="field-label">Date limite</label>
                    <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', $invoice?->valid_until?->format('Y-m-d') ?? date('Y-m-d', strtotime('+30 days'))) }}" />
                </div>
            </div>
        </div>

        <div class="custom-invoice-section mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                <h3 class="h5 fw-bold text-dark mb-0">Détails contractuels</h3>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="field-label">Termes de paiement</label>
                    <textarea name="payment_terms" class="form-control" rows="3" placeholder="Ex: 50% à la commande, 50% à la livraison...">{{ old('payment_terms', $invoice?->payment_terms ?? '') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="field-label">Termes de livraison</label>
                    <textarea name="delivery_terms" class="form-control" rows="3" placeholder="Ex: Livraison à domicile, transport inclus...">{{ old('delivery_terms', $invoice?->delivery_terms ?? '') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="field-label">Lieu de livraison</label>
                    <textarea name="delivery_location" class="form-control" rows="3" placeholder="Adresse exacte de livraison">{{ old('delivery_location', $invoice?->delivery_location ?? '') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="field-label">Méthode de paiement</label>
                    <select name="payment_method" class="form-select">
                        <option value="">Sélectionner...</option>
                        <option value="Espèces" {{ old('payment_method', $invoice?->payment_method ?? '') == 'Espèces' ? 'selected' : '' }}>Espèces</option>
                        <option value="Chèque" {{ old('payment_method', $invoice?->payment_method ?? '') == 'Chèque' ? 'selected' : '' }}>Chèque</option>
                        <option value="Virement" {{ old('payment_method', $invoice?->payment_method ?? '') == 'Virement' ? 'selected' : '' }}>Virement</option>
                        <option value="Carte bancaire" {{ old('payment_method', $invoice?->payment_method ?? '') == 'Carte bancaire' ? 'selected' : '' }}>Carte bancaire</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="field-label">Objet / type de document</label>
                    <input type="text" name="objet" class="form-control" value="{{ old('objet', $invoice?->subject ?? '') }}" placeholder="Ex: Livre, brochure, catalogue..." />
                </div>
            </div>
        </div>

        <div class="custom-invoice-section mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                <h3 class="h5 fw-bold text-dark mb-0">Services supplémentaires</h3>
            </div>
            <div class="row g-2">
                @forelse($globalServiceCatalog ?? [] as $code => $service)
                <div class="col-md-6">
                    <label class="service-option">
                        <input type="checkbox" name="global_services[]" value="{{ $code }}" class="global-service"
                               data-price="{{ (float) $service['price'] }}"
                               {{ in_array($code, $invoiceServices, true) ? 'checked' : '' }}>
                        <span>{{ $service['label'] }} - <strong>{{ money($service['price']) }}</strong></span>
                    </label>
                </div>
                @empty
                <div class="col-12">
                    <div class="text-muted fs-7 py-3">
                        Aucune prestation forfaitaire n'est configurée. Ajoutez-les depuis le module
                        Services en cochant « proposée sur les factures ».
                    </div>
                </div>
                @endforelse
            </div>
            <div class="totals-box mt-4" id="servicesTotalBox" style="display:none;">
                <div class="text-uppercase text-muted fs-8 fw-bold mb-2">Services additionnels</div>
                <div class="summary-total" id="servicesTotalValue">0 {{ currency_symbol() }}</div>
            </div>
        </div>

        <div class="custom-invoice-section mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <h3 class="h5 fw-bold text-dark mb-0">Articles et caractéristiques</h3>
            </div>

            <div id="itemsContainer" class="d-flex flex-column gap-4">
                <div class="custom-invoice-item item-row order-0" data-index="0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="field-label">Type de document</label>
                            <select name="items[0][book_type]" class="form-select book-type">
                                <option value="">Sélectionner</option>
                                        <option value="livre" {{ old('items.0.book_type', $invoiceItems[0]['book_type'] ?? '') == 'livre' ? 'selected' : '' }}>Livre</option>
                                        <option value="bloc_note" {{ old('items.0.book_type', $invoiceItems[0]['book_type'] ?? '') == 'bloc_note' ? 'selected' : '' }}>Bloc note</option>
                                        <option value="planner" {{ old('items.0.book_type', $invoiceItems[0]['book_type'] ?? '') == 'planner' ? 'selected' : '' }}>Planner</option>
                                        <option value="revue" {{ old('items.0.book_type', $invoiceItems[0]['book_type'] ?? '') == 'revue' ? 'selected' : '' }}>Revue</option>
                                        <option value="catalogue" {{ old('items.0.book_type', $invoiceItems[0]['book_type'] ?? '') == 'catalogue' ? 'selected' : '' }}>Catalogue</option>
                                        <option value="brochure" {{ old('items.0.book_type', $invoiceItems[0]['book_type'] ?? '') == 'brochure' ? 'selected' : '' }}>Brochure</option>
                                        <option value="autre" {{ old('items.0.book_type', $invoiceItems[0]['book_type'] ?? '') == 'autre' ? 'selected' : '' }}>Autre</option>
                            </select>
                                    <input type="text" name="items[0][book_type_other]" class="form-control mt-2 other-field" value="{{ old('items.0.book_type_other', $invoiceItems[0]['book_type_other'] ?? '') }}" placeholder="Précisez le type" />
                        </div>
                        <div class="col-md-6">
                            <label class="field-label">Type de papier souhaité</label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][paper_type]" value="bouffant_creme" {{ old('items.0.paper_type', $invoiceItems[0]['paper_type'] ?? 'bouffant_creme') == 'bouffant_creme' ? 'checked' : '' }}> <span>bouffant crème 80g</span></label>
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][paper_type]" value="offset_blanc" {{ old('items.0.paper_type', $invoiceItems[0]['paper_type'] ?? 'bouffant_creme') == 'offset_blanc' ? 'checked' : '' }}> <span>offset blanc 80g</span></label>
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][paper_type]" value="couche_120g" {{ old('items.0.paper_type', $invoiceItems[0]['paper_type'] ?? 'bouffant_creme') == 'couche_120g' ? 'checked' : '' }}> <span>couché 120g</span></label>
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][paper_type]" value="autre" {{ old('items.0.paper_type', $invoiceItems[0]['paper_type'] ?? 'bouffant_creme') == 'autre' ? 'checked' : '' }}> <span>Autre</span></label>
                            </div>
                            <input type="text" name="items[0][paper_type_other]" class="form-control other-field" value="{{ old('items.0.paper_type_other', $invoiceItems[0]['paper_type_other'] ?? '') }}" placeholder="Autre type de papier et grammage" />
                        </div>
                        <div class="col-md-3">
                            <label class="field-label">Nombre de pages</label>
                            <input type="number" name="items[0][page_count]" class="form-control" min="1" value="{{ old('items.0.page_count', $invoiceItems[0]['page_count'] ?? 100) }}" />
                        </div>
                        <div class="col-md-3">
                            <label class="field-label">Impression</label>
                            <div class="d-flex flex-wrap gap-2">
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][printing_type]" value="noir_blanc" {{ old('items.0.printing_type', $invoiceItems[0]['printing_type'] ?? 'noir_blanc') == 'noir_blanc' ? 'checked' : '' }}> <span>NB</span></label>
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][printing_type]" value="couleur" {{ old('items.0.printing_type', $invoiceItems[0]['printing_type'] ?? 'noir_blanc') == 'couleur' ? 'checked' : '' }}> <span>Couleur</span></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="field-label">Type de couverture</label>
                            <div class="d-flex flex-wrap gap-2">
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][cover_type]" value="couche_300g" {{ old('items.0.cover_type', $invoiceItems[0]['cover_type'] ?? 'couche_300g') == 'couche_300g' ? 'checked' : '' }}> <span>300g</span></label>
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][cover_type]" value="rigide_cartonnee" {{ old('items.0.cover_type', $invoiceItems[0]['cover_type'] ?? 'couche_300g') == 'rigide_cartonnee' ? 'checked' : '' }}> <span>Rigide</span></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="field-label">Format</label>
                            <select name="items[0][book_format]" class="form-select book-format">
                                <option value="poche_11x18" {{ old('items.0.book_format', $invoiceItems[0]['book_format'] ?? 'poche_11x18') == 'poche_11x18' ? 'selected' : '' }}>Poche 11x18</option>
                                <option value="digest_12x19" {{ old('items.0.book_format', $invoiceItems[0]['book_format'] ?? 'poche_11x18') == 'digest_12x19' ? 'selected' : '' }}>Digest 12,5x19,5</option>
                                <option value="a5_14x21" {{ old('items.0.book_format', $invoiceItems[0]['book_format'] ?? 'poche_11x18') == 'a5_14x21' ? 'selected' : '' }}>A5 14x21</option>
                                <option value="royal_16x24" {{ old('items.0.book_format', $invoiceItems[0]['book_format'] ?? 'poche_11x18') == 'royal_16x24' ? 'selected' : '' }}>Royal 16x24</option>
                                <option value="autre" {{ old('items.0.book_format', $invoiceItems[0]['book_format'] ?? 'poche_11x18') == 'autre' ? 'selected' : '' }}>Autre</option>
                            </select>
                            <input type="text" name="items[0][format_other]" class="form-control mt-2 other-field" value="{{ old('items.0.format_other', $invoiceItems[0]['format_other'] ?? '') }}" placeholder="Précisez le format" />
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">Pelliculage</label>
                            <div class="d-flex flex-wrap gap-2">
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][lamination]" value="brillant" {{ old('items.0.lamination', $invoiceItems[0]['lamination'] ?? 'brillant') == 'brillant' ? 'checked' : '' }}> <span>Brillant</span></label>
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][lamination]" value="mat" {{ old('items.0.lamination', $invoiceItems[0]['lamination'] ?? 'brillant') == 'mat' ? 'checked' : '' }}> <span>Mat</span></label>
                                <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="items[0][lamination]" value="aucun" {{ old('items.0.lamination', $invoiceItems[0]['lamination'] ?? 'brillant') == 'aucun' ? 'checked' : '' }}> <span>Aucun</span></label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">Type de reliure</label>
                            <select name="items[0][binding_type]" class="form-select binding-type">
                                <option value="dos_carre_colle" {{ old('items.0.binding_type', $invoiceItems[0]['binding_type'] ?? 'dos_carre_colle') == 'dos_carre_colle' ? 'selected' : '' }}>Dos carré collé</option>
                                <option value="points_metalliques" {{ old('items.0.binding_type', $invoiceItems[0]['binding_type'] ?? 'dos_carre_colle') == 'points_metalliques' ? 'selected' : '' }}>Points métalliques</option>
                                <option value="spirale_metallique" {{ old('items.0.binding_type', $invoiceItems[0]['binding_type'] ?? 'dos_carre_colle') == 'spirale_metallique' ? 'selected' : '' }}>Spirale métallique</option>
                                <option value="autre" {{ old('items.0.binding_type', $invoiceItems[0]['binding_type'] ?? 'dos_carre_colle') == 'autre' ? 'selected' : '' }}>Autre</option>
                            </select>
                            <input type="text" name="items[0][binding_other]" class="form-control mt-2 other-field" value="{{ old('items.0.binding_other', $invoiceItems[0]['binding_other'] ?? '') }}" placeholder="Précisez le type de reliure" />
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">Options supplémentaires</label>
                            <textarea name="items[0][additional_options]" class="form-control" rows="3" placeholder="Autres spécifications ou exigences...">{{ old('items.0.additional_options', $invoiceItems[0]['additional_options'] ?? '') }}</textarea>
                        </div>
                    </div>

                    <div class="row g-3 mt-1 align-items-end">
                        <div class="col-md-2">
                            <label class="field-label">Catégorie</label>
                            <select name="items[0][item_category]" class="form-select">
                                <option value="impression" {{ old('items.0.item_category', $invoiceItems[0]['item_category'] ?? 'impression') == 'impression' ? 'selected' : '' }}>Impression</option>
                                <option value="livre" {{ old('items.0.item_category', $invoiceItems[0]['item_category'] ?? 'impression') == 'livre' ? 'selected' : '' }}>Livre</option>
                                <option value="brochure" {{ old('items.0.item_category', $invoiceItems[0]['item_category'] ?? 'impression') == 'brochure' ? 'selected' : '' }}>Brochure</option>
                                <option value="catalogue" {{ old('items.0.item_category', $invoiceItems[0]['item_category'] ?? 'impression') == 'catalogue' ? 'selected' : '' }}>Catalogue</option>
                                <option value="autre" {{ old('items.0.item_category', $invoiceItems[0]['item_category'] ?? 'impression') == 'autre' ? 'selected' : '' }}>Autre</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="field-label">Article</label>
                            <input type="text" name="items[0][item_name]" class="form-control" value="{{ old('items.0.item_name', $invoiceItems[0]['item_name'] ?? 'Impression de livre') }}" />
                        </div>
                        <div class="col-md-2">
                            <label class="field-label">Unité</label>
                            <input type="text" name="items[0][unit]" class="form-control" value="{{ old('items.0.unit', $invoiceItems[0]['unit'] ?? 'Exemplaire') }}" />
                        </div>
                        <div class="col-md-2">
                            <label class="field-label">Quantité</label>
                            <input type="number" name="items[0][quantity]" class="form-control quantity-input" min="1" value="{{ old('items.0.quantity', $invoiceItems[0]['quantity'] ?? 1) }}" />
                        </div>
                        <div class="col-md-2">
                            <label class="field-label">Prix unitaire</label>
                            <input type="number" name="items[0][price]" class="form-control price-input" step="0.01" min="0" value="{{ old('items.0.price', $invoiceItems[0]['price'] ?? 0) }}" />
                        </div>
                        <div class="col-md-2 text-end">
                            <label class="field-label">Montant net</label>
                            <div class="fw-bold fs-5 total-line">0 {{ currency_symbol() }}</div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 remove-item-btn">Supprimer</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-sm btn-primary" id="addItemBtn"><i class="fas fa-plus me-2"></i>Ajouter un article</button>
            </div>
        </div>

        <div class="totals-box mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <div class="text-uppercase text-muted fs-8 fw-bold">Total HT</div>
                    <div class="summary-total" id="subtotalValue">0 {{ currency_symbol() }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-uppercase text-muted fs-8 fw-bold">Remise</div>
                    <div class="summary-total fs-3" id="discountValue">0 {{ currency_symbol() }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-uppercase text-muted fs-8 fw-bold">TVA</div>
                    <div class="summary-total fs-3" id="vatValue">0 {{ currency_symbol() }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-uppercase text-muted fs-8 fw-bold">Total TTC</div>
                    <div class="summary-total" id="grandTotalValue">0 {{ currency_symbol() }}</div>
                </div>
            </div>
            <div class="row g-3 mt-2 align-items-end">
                <div class="col-md-3">
                    <label class="field-label">Type de remise</label>
                    <select id="discountType" name="discount_type" class="form-select">
                        <option value="none" {{ old('discount_type', $invoiceDefaultDiscountType) == 'none' ? 'selected' : '' }}>Aucune</option>
                        <option value="percent" {{ old('discount_type', $invoiceDefaultDiscountType) == 'percent' ? 'selected' : '' }}>Pourcentage (%)</option>
                        <option value="amount" {{ old('discount_type', $invoiceDefaultDiscountType) == 'amount' ? 'selected' : '' }}>Montant fixe</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="field-label">Valeur de remise</label>
                    <input id="discountValueInput" name="discount_value" type="number" class="form-control" min="0" step="0.01" value="{{ old('discount_value', $invoice?->total_discount ?? 0) }}" />
                </div>
                <div class="col-md-3">
                    <label class="field-label">Montant payé maintenant</label>
                    <input name="paid_amount" type="number" class="form-control" min="0" step="0.01" value="{{ old('paid_amount', $invoice?->paid_amount ?? 0) }}" />
                </div>
            </div>
            <div class="row g-3 mt-2 align-items-end">
                <div class="col-md-4">
                    <label class="field-label">Montant net après remise</label>
                    <div class="form-control bg-light fw-bold" id="netAfterDiscountValue">0 {{ currency_symbol() }}</div>
                </div>
                <div class="col-md-4">
                    <label class="field-label">Mode de paiement</label>
                    <select name="payment_method" class="form-select">
                        <option value="">Sélectionner...</option>
                        <option value="cash" {{ old('payment_method', $invoice?->payment_method ?? 'cash') == 'cash' ? 'selected' : '' }}>Espèces</option>
                        <option value="bank" {{ old('payment_method', $invoice?->payment_method ?? 'cash') == 'bank' ? 'selected' : '' }}>Banque</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 text-muted small">TVA calculée au taux de 18 % sur le montant hors taxe après remise.</div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-light">Annuler</button>
            <button type="submit" class="btn btn-primary px-5">Enregistrer la facture</button>
        </div>
    </form>
</div>

<script>
function showOtherField(selectEl, targetInput) {
        const value = selectEl.value;
        if (targetInput) {
            targetInput.style.display = (value === 'autre' || value === 'other') ? 'block' : 'none';
        }
    }

    function handleDynamicFields() {
        document.querySelectorAll('.book-type').forEach((selectEl) => {
            const target = selectEl.parentElement.querySelector('.other-field');
            selectEl.addEventListener('change', () => showOtherField(selectEl, target));
            showOtherField(selectEl, target);
        });

        document.querySelectorAll('.book-format').forEach((selectEl) => {
            const target = selectEl.parentElement.querySelector('.other-field');
            selectEl.addEventListener('change', () => showOtherField(selectEl, target));
            showOtherField(selectEl, target);
        });

        document.querySelectorAll('.binding-type').forEach((selectEl) => {
            const target = selectEl.parentElement.querySelector('.other-field');
            selectEl.addEventListener('change', () => showOtherField(selectEl, target));
            showOtherField(selectEl, target);
        });

        document.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', function () {
                const container = this.closest('.custom-invoice-item');
                if (!container) return;
                const other = container.querySelectorAll('input[type="text"].other-field');
                other.forEach((field) => {
                    field.style.display = 'none';
                });
                const selected = container.querySelector('input[type="radio"]:checked');
                if (selected && selected.value === 'autre') {
                    const input = selected.closest('label').parentElement.parentElement.querySelector('.other-field');
                    if (input) input.style.display = 'block';
                }
            });
        });
    }

    function formatMoney(value) {window.formatMoney(return Number(value || 0));
    }

    function recalculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach((row) => {
            const qty = Number(row.querySelector('.quantity-input')?.value || 0);
            const price = Number(row.querySelector('.price-input')?.value || 0);
            const amount = qty * price;
            subtotal += amount;
            const totalEl = row.querySelector('.total-line');
            if (totalEl) totalEl.textContent = formatMoney(amount);
        });

        let servicesTotal = 0;
        document.querySelectorAll('.global-service:checked').forEach((checkbox) => {
            servicesTotal += Number(checkbox.dataset.price || 0);
        });

        const servicesBox = document.getElementById('servicesTotalBox');
        const servicesValue = document.getElementById('servicesTotalValue');
        if (servicesBox && servicesValue) {
            if (servicesTotal > 0) {
                servicesBox.style.display = 'block';
                servicesValue.textContent = formatMoney(servicesTotal);
            } else {
                servicesBox.style.display = 'none';
                servicesValue.textContent = window.formatMoney(0);
            }
        }

        const baseAmount = subtotal + servicesTotal;
        const discountType = document.getElementById('discountType')?.value || 'none';
        const discountValueInput = document.getElementById('discountValueInput');
        const discountValue = Number(discountValueInput?.value || 0);

        let discountAmount = 0;
        if (discountType === 'percent') {
            discountAmount = baseAmount * (discountValue / 100);
        } else if (discountType === 'amount') {
            discountAmount = discountValue;
        }

        const netAfterDiscount = Math.max(baseAmount - discountAmount, 0);
        const vat = netAfterDiscount * 0.18;
        const grandTotal = netAfterDiscount + vat;

        const subtotalEl = document.getElementById('subtotalValue');
        const discountEl = document.getElementById('discountValue');
        const vatEl = document.getElementById('vatValue');
        const grandTotalEl = document.getElementById('grandTotalValue');
        const netAfterDiscountEl = document.getElementById('netAfterDiscountValue');

        if (subtotalEl) subtotalEl.textContent = formatMoney(baseAmount);
        if (discountEl) discountEl.textContent = formatMoney(discountAmount);
        if (vatEl) vatEl.textContent = formatMoney(vat);
        if (grandTotalEl) grandTotalEl.textContent = formatMoney(grandTotal);
        if (netAfterDiscountEl) netAfterDiscountEl.textContent = formatMoney(netAfterDiscount);
    }

    function addItemRow() {
        const container = document.getElementById('itemsContainer');
        const rows = container.querySelectorAll('.item-row');
        const nextIndex = rows.length;
        const firstRow = rows[0];
        const newRow = firstRow.cloneNode(true);
        newRow.dataset.index = nextIndex;
        newRow.classList.remove('order-0');
        newRow.classList.add('order-1');

        newRow.querySelectorAll('input, select, textarea').forEach((field) => {
            const name = field.getAttribute('name');
            if (!name) return;
            const updatedName = name.replace(/items\[\d+\]/, 'items[' + nextIndex + ']');
            field.setAttribute('name', updatedName);
            if (field.type === 'radio') {
                field.checked = false;
            } else if (field.tagName !== 'SELECT' && field.name && field.name.includes('paper_type')) {
                field.value = '';
            } else if (field.classList.contains('quantity-input')) {
                field.value = 1;
            } else if (field.classList.contains('price-input')) {
                field.value = 0;
            } else if (field.classList.contains('form-control') || field.classList.contains('form-select')) {
                field.value = '';
            }
        });

        const totalEL = newRow.querySelector('.total-line');
        if (totalEL) totalEL.textContent = window.formatMoney(0);

        container.appendChild(newRow);
        Array.from(container.children).forEach((child, index) => {
            child.classList.remove('order-0', 'order-1');
            child.classList.add(index === 0 ? 'order-0' : 'order-1');
        });
        handleDynamicFields();
        recalculateTotals();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const customerSelect = document.querySelector('select[name="customer"]');
        if (customerSelect) {
            customerSelect.addEventListener('change', function () {
                const isNew = this.value === 'new';
                document.querySelectorAll('.new-client-field').forEach((field) => {
                    field.style.display = isNew ? 'block' : 'none';
                });
            });
        }

        const itemsContainer = document.getElementById('itemsContainer');
        if (itemsContainer) {
            itemsContainer.addEventListener('click', function (event) {
                const removeBtn = event.target.closest('.remove-item-btn');
                if (!removeBtn) return;

                const row = removeBtn.closest('.item-row');
                const rows = itemsContainer.querySelectorAll('.item-row');
                if (rows.length > 1 && row) {
                    row.remove();
                    Array.from(itemsContainer.children).forEach((child, index) => {
                        child.classList.remove('order-0', 'order-1');
                        child.classList.add(index === 0 ? 'order-0' : 'order-1');
                    });
                    recalculateTotals();
                }
            });
        }

        document.getElementById('addItemBtn')?.addEventListener('click', addItemRow);

        document.querySelectorAll('.global-service, .quantity-input, .price-input, #discountType, #discountValueInput').forEach((el) => {
            el.addEventListener('input', recalculateTotals);
            el.addEventListener('change', recalculateTotals);
        });

        handleDynamicFields();
        recalculateTotals();
    });
</script>
@endsection
