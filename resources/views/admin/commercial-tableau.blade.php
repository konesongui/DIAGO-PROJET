@extends('admin.layout')

@section('content')
@php
    $money = fn ($value) => money((float) $value);
    $percent = fn (?float $value) => $value === null ? '—' : number_format($value, abs($value) < 10 && $value != 0 ? 1 : 0, ',', ' ') . ' %';
    $sources = \App\Services\SalesRevenueService::SOURCES;
    $states = \App\Services\SalesRevenueService::states();
    $receivableTotal = (float) $receivables->sum('remaining');
    $overdue = $receivables->filter(fn ($row) => $row['due'] && $row['due']->lt(now()->startOfDay()));
    $byClient = $receivables->groupBy(fn ($row) => mb_strtolower($row['client']))->map(fn ($items) => [
        'client' => $items->first()['client'], 'count' => $items->count(), 'oldest' => $items->min('date'),
        'total' => (float) $items->sum('total'), 'paid' => (float) $items->sum('paid'), 'remaining' => (float) $items->sum('remaining'),
        'overdue' => $items->contains(fn ($row) => $row['due'] && $row['due']->lt(now()->startOfDay())),
    ])->sortByDesc('remaining')->values();
    $cards = [
        [
            'label' => 'Chiffre d’affaires HT', 'value' => $revenue, 'icon' => 'bi-graph-up-arrow', 'color' => 'blue',
            'hint' => $revenueTrend === null ? 'factures émises et ventes au comptoir, avoirs déduits' : 'vs période précédente',
            'rows' => $rows->map(fn ($row) => $row + ['sub' => $sources[$row['source']]]),
        ],
        [
            'label' => 'Encaissements', 'value' => (float) $collections->sum('amount'), 'icon' => 'bi-cash-coin', 'color' => 'green',
            'hint' => 'règlements de factures et ventes au comptoir',
            'rows' => $collections->map(fn ($row) => $row + ['sub' => $row['method']]),
        ],
        [
            'label' => 'Créances clients', 'value' => $receivableTotal, 'icon' => 'bi-hourglass-split', 'color' => 'orange',
            'hint' => 'à ce jour · ' . $receivables->count() . ' facture(s)' . ($overdue->isNotEmpty() ? ', ' . $overdue->count() . ' échue(s)' : ''),
            'rows' => $receivables->map(fn ($row) => $row + ['amount' => $row['remaining'], 'sub' => 'payé ' . $money($row['paid']) . ' sur ' . $money($row['total']) . ($row['due'] ? ' · échéance ' . $row['due']->format('d/m/Y') : '')]),
        ],
        [
            'label' => 'Devis en attente', 'value' => (float) $pendingQuotes->sum(fn ($quote) => $quote->net_ht ?? $quote->total_ht), 'icon' => 'bi-file-earmark-text', 'color' => 'purple',
            'hint' => $pendingQuotes->count() . ' devis en cours' . ($conversion !== null ? ' · ' . $percent($conversion) . ' de devis validés sur la période' : ''),
            'rows' => $pendingQuotes->map(fn ($quote) => [
                'date' => $quote->quote_date, 'reference' => $quote->reference ?: 'Devis N° ' . $quote->id, 'client' => $quote->client_name ?: 'Client non renseigné',
                'amount' => (float) ($quote->net_ht ?? $quote->total_ht), 'sub' => $quote->due_date ? 'valable jusqu’au ' . $quote->due_date->format('d/m/Y') : 'sans date limite',
                'url' => route('admin.commercial.quotes.edit', $quote),
            ]),
        ],
    ];
    $hasRevenue = $months->contains(fn ($month) => collect($sources)->keys()->contains(fn ($source) => (float) $month[$source] !== 0.0));
    $maxSeller = max(1, (float) $sellers->max('amount'));
    $maxClient = max(1, (float) $clients->max('amount'));
    $maxItem = max(1, (float) $items->max('amount'));
    $byMethod = $collections->groupBy('method')->map(fn ($items, $method) => ['method' => $method, 'amount' => (float) $items->sum('amount'), 'count' => $items->count()])->sortByDesc('amount')->values();
    $methodColors = ['Espèces' => 'orange', 'Banque' => 'blue', 'Virement' => 'indigo'];
    $methodIcons = ['Espèces' => 'bi-cash-stack', 'Banque' => 'bi-bank', 'Virement' => 'bi-arrow-left-right'];
    $emptyBlock = fn (string $tone, string $icon, string $title, string $text) => '<div class="dg-chart-empty" style="min-height:180px"><span class="dg-tile dg-tone-' . $tone . '"><i class="bi ' . $icon . '"></i></span><div><strong>' . e($title) . '</strong>' . e($text) . '</div></div>';
@endphp

<div class="dg-font dg-scope">
    <x-dg.page-header title="Tableau commercial" subtitle="Ventes, encaissements, créances et performance commerciale de la période." :back="route('admin.commercial')" back-label="Commercial">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.commercial.tableau') }}" class="dg-period" aria-label="Période d’analyse">
                <input type="date" name="date_debut" value="{{ $periodFrom }}" class="dg-input" aria-label="Date de début">
                <span class="dg-period__sep">au</span>
                <input type="date" name="date_fin" value="{{ $periodTo }}" class="dg-input" aria-label="Date de fin">
                <button type="submit" class="dg-btn dg-btn--outline"><i class="bi bi-funnel"></i>Filtrer</button>
                <a href="{{ route('admin.commercial.tableau') }}" class="dg-btn dg-btn--outline" title="Revenir à l’année en cours" aria-label="Revenir à l’année en cours"><i class="bi bi-arrow-counterclockwise"></i></a>
            </form>
        </x-slot:actions>
    </x-dg.page-header>

    @if($periodError)<div class="alert alert-warning">{{ $periodError }}</div>@endif

    <div class="dg-kpi-grid">
        @foreach($cards as $index => $card)
            <x-dg.kpi :label="$card['label']" :value="$money($card['value'])" :icon="$card['icon']" :color="$card['color']" :hint="$card['hint']"
                :trend="$index === 0 && $revenueTrend !== null ? ($revenueTrend >= 0 ? '+' : '') . $percent($revenueTrend) : null"
                :trend-direction="$index === 0 && $revenueTrend !== null && $revenueTrend < 0 ? 'down' : 'up'">
                <button type="button" class="dg-link-btn d-flex mt-2" data-bs-toggle="modal" data-bs-target="#salesDetail{{ $index }}">
                    <i class="bi bi-eye"></i>Voir le détail
                </button>
            </x-dg.kpi>
        @endforeach
    </div>

    <div class="dg-grid-2 mb-5">
        <x-dg.card title="Chiffre d’affaires HT par mois" icon="bi-bar-chart-line" color="blue" :meta="'Montants en ' . currency_symbol()">
            <div class="dg-chart">
                @if($hasRevenue)
                    <canvas id="salesMonthsChart" role="img" aria-label="Chiffre d’affaires HT par mois et par origine"></canvas>
                @else
                    {!! $emptyBlock('blue', 'bi-bar-chart-line', 'Aucune vente sur la période', 'Les factures émises et les ventes au comptoir s’additionneront ici, mois par mois.') !!}
                @endif
            </div>
        </x-dg.card>

        <x-dg.card :title="'Objectif ' . \Illuminate\Support\Carbon::parse($periodTo)->year" icon="bi-bullseye" color="green">
            @if($objectiveProgress)
                @php [$stateLabel, $stateTone, $stateIcon] = $states[$objectiveProgress['state']]; @endphp
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="sales-percent">{{ $percent($objectiveProgress['percent']) }}</span>
                    <span class="dg-badge dg-badge--{{ $stateTone }} ms-auto"><i class="bi {{ $stateIcon }}"></i>{{ $stateLabel }}</span>
                </div>
                <div class="sales-bar sales-bar--lg mb-2" role="progressbar" aria-label="Réalisé de l’objectif" aria-valuenow="{{ round($objectiveProgress['percent']) }}" aria-valuemin="0" aria-valuemax="100">
                    <span class="sales-bar__fill sales-bar__fill--{{ $stateTone }}" style="width:{{ min(100, max(0, $objectiveProgress['percent'])) }}%"></span>
                    @if($objectiveProgress['pace'] > 0 && $objectiveProgress['pace'] < 100)<span class="sales-bar__pace" style="left:{{ $objectiveProgress['pace'] }}%" title="Rythme attendu aujourd’hui"></span>@endif
                </div>
                <p class="dg-muted mb-3" style="font-size:12.5px">{{ $money($objectiveProgress['realized']) }} réalisés sur {{ $money($objectiveProgress['amount']) }} HT, exercice à ce jour.</p>
                <ul class="sales-list mb-3">
                    @foreach(collect($objectiveProgress['lines'])->take(4) as $line)
                        <li>
                            <span class="sales-list__name">{{ $line['assignment']->employee?->full_name ?? 'Employé supprimé' }}</span>
                            @if($line['measurable'])
                                <span class="sales-bar flex-grow-1"><span class="sales-bar__fill sales-bar__fill--{{ $states[$line['state']][1] }}" style="width:{{ min(100, max(0, $line['percent'])) }}%"></span></span>
                                <span class="sales-list__value">{{ $percent($line['percent']) }}</span>
                            @else
                                <span class="dg-muted ms-auto" style="font-size:12.5px">non mesurable</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.commercial.module', ['objectifs', 'objectif' => $objective->id]) }}" class="dg-btn dg-btn--outline dg-btn--sm"><i class="bi bi-arrow-right"></i>Voir les objectifs</a>
            @else
                <div class="dg-chart-empty" style="min-height:240px">
                    <span class="dg-tile dg-tone-green"><i class="bi bi-bullseye"></i></span>
                    <div><strong>Aucun objectif pour {{ \Illuminate\Support\Carbon::parse($periodTo)->year }}</strong>Fixez un chiffre d’affaires à atteindre et répartissez-le entre vos commerciaux.</div>
                    <a href="{{ route('admin.commercial.module', 'objectifs') }}" class="dg-btn dg-btn--primary dg-btn--sm"><i class="bi bi-plus-lg"></i>Définir l’objectif</a>
                </div>
            @endif
        </x-dg.card>
    </div>

    <div class="dg-grid-halves mb-5">
        <x-dg.card title="Performance par commercial" icon="bi-people" color="purple" meta="CA HT">
            @if($sellers->isNotEmpty())
                <ul class="sales-ranking">
                    @foreach($sellers as $seller)
                        <li>
                            <div class="d-flex justify-content-between gap-3">
                                <span><span class="d-block fw-semibold">{{ $seller['name'] }}</span><span class="dg-muted" style="font-size:12.5px">{{ $seller['hint'] }}</span></span>
                                <span class="text-end text-nowrap"><span class="d-block fw-semibold dg-cell-num">{{ $money($seller['amount']) }}</span><span class="dg-muted" style="font-size:12.5px">{{ $revenue > 0 ? $percent($seller['amount'] / $revenue * 100) : '—' }} du CA</span></span>
                            </div>
                            <span class="sales-bar mt-2"><span class="sales-bar__fill {{ $seller['is_pos'] ? 'sales-bar__fill--teal' : 'sales-bar__fill--purple' }}" style="width:{{ max(0, $seller['amount'] / $maxSeller * 100) }}%"></span></span>
                        </li>
                    @endforeach
                </ul>
            @else
                {!! $emptyBlock('purple', 'bi-people', 'Aucune vente sur la période', 'Chaque facture est attribuée à l’utilisateur qui a créé le devis ou la facture personnalisée.') !!}
            @endif
        </x-dg.card>

        <x-dg.card title="Meilleurs clients" icon="bi-person-vcard" color="cyan" meta="CA HT">
            @if($clients->isNotEmpty())
                <ul class="sales-ranking">
                    @foreach($clients as $client)
                        <li>
                            <div class="d-flex justify-content-between gap-3">
                                <span class="min-w-0"><span class="d-block fw-semibold text-truncate">{{ $client['name'] }}</span><span class="dg-muted" style="font-size:12.5px">{{ $client['documents'] }} document(s)</span></span>
                                <span class="text-end text-nowrap"><span class="d-block fw-semibold dg-cell-num">{{ $money($client['amount']) }}</span><span class="dg-muted" style="font-size:12.5px">{{ $revenue > 0 ? $percent($client['amount'] / $revenue * 100) : '—' }} du CA</span></span>
                            </div>
                            <span class="sales-bar mt-2"><span class="sales-bar__fill sales-bar__fill--cyan" style="width:{{ $client['amount'] / $maxClient * 100 }}%"></span></span>
                        </li>
                    @endforeach
                </ul>
            @else
                {!! $emptyBlock('cyan', 'bi-person-vcard', 'Aucun client facturé sur la période', 'Les clients qui pèsent le plus dans le chiffre d’affaires s’afficheront ici.') !!}
            @endif
        </x-dg.card>
    </div>

    <div class="dg-grid-2 mb-5">
        <x-dg.card title="Prestations et produits les plus vendus" icon="bi-bag-check" color="indigo" meta="CA HT">
            @if($items->isNotEmpty())
                <ul class="sales-ranking">
                    @foreach($items as $item)
                        <li>
                            <div class="d-flex justify-content-between gap-3">
                                <span class="min-w-0"><span class="d-block fw-semibold text-truncate">{{ $item['name'] }}</span><span class="dg-muted" style="font-size:12.5px">{{ $item['documents'] }} vente(s)</span></span>
                                <span class="fw-semibold dg-cell-num text-nowrap">{{ $money($item['amount']) }}</span>
                            </div>
                            <span class="sales-bar mt-2"><span class="sales-bar__fill sales-bar__fill--indigo" style="width:{{ $item['amount'] / $maxItem * 100 }}%"></span></span>
                        </li>
                    @endforeach
                </ul>
                <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Le HT de chaque vente est réparti sur ses lignes, remises déduites ; les avoirs n’y figurent pas.</p>
            @else
                {!! $emptyBlock('indigo', 'bi-bag-check', 'Aucune prestation vendue sur la période', 'Les lignes des factures et des ventes au comptoir seront classées ici.') !!}
            @endif
        </x-dg.card>

        <x-dg.card title="Encaissements par mode" icon="bi-wallet2" color="green">
            @if($byMethod->isNotEmpty())
                <table class="dg-mini-table">
                    <tbody>
                        @foreach($byMethod as $row)
                            <tr>
                                <td><span class="d-inline-flex align-items-center gap-3"><span class="dg-tile dg-tile--sm dg-tone-{{ $methodColors[$row['method']] ?? 'green' }}"><i class="bi {{ $methodIcons[$row['method']] ?? 'bi-cash' }}"></i></span>
                                    <span><span class="d-block">{{ $row['method'] }}</span><span class="dg-muted" style="font-size:12.5px">{{ $row['count'] }} encaissement(s)</span></span></span></td>
                                <td>{{ $money($row['amount']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="sales-total"><td>Total encaissé</td><td>{{ $money($collections->sum('amount')) }}</td></tr>
                    </tbody>
                </table>
            @else
                {!! $emptyBlock('green', 'bi-wallet2', 'Aucun encaissement sur la période', 'Les règlements de factures et les ventes au comptoir s’afficheront ici, par mode de paiement.') !!}
            @endif
        </x-dg.card>
    </div>

    <div class="dg-card dg-card--table mb-5">
        <div class="dg-card__header flex-wrap">
            <h2 class="dg-card__title"><span class="dg-tile dg-tile--sm dg-tone-orange"><i class="bi bi-hourglass-split"></i></span>Créances clients à ce jour</h2>
            @if($byClient->isNotEmpty())
                <label class="dg-search" style="max-width:260px">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="receivableSearch" type="search" placeholder="Rechercher un client…" aria-label="Rechercher un client">
                </label>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 receivables-table no-export" id="receivablesTable">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Plus ancienne facture</th>
                        <th class="text-end">Facturé TTC</th>
                        <th class="text-end">Déjà payé</th>
                        <th class="text-end">Reste à payer</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($byClient as $row)
                    <tr data-receivable-row>
                        <td>
                            <span class="d-block fw-semibold">{{ $row['client'] }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">{{ $row['count'] }} facture(s) ouverte(s)</span>
                        </td>
                        <td class="text-nowrap">
                            <span class="d-block">{{ $row['oldest']->format('d/m/Y') }}</span>
                            <span class="d-block dg-muted" style="font-size:12.5px">il y a {{ (int) $row['oldest']->copy()->startOfDay()->diffInDays(now()->startOfDay()) }} jour(s)</span>
                        </td>
                        <td class="text-end dg-cell-num">{{ $money($row['total']) }}</td>
                        <td class="text-end dg-cell-num">{{ $money($row['paid']) }}</td>
                        <td class="text-end text-nowrap">
                            <span class="d-block fw-semibold dg-cell-num dg-amount-negative">{{ $money($row['remaining']) }}</span>
                            @if($row['overdue'])<span class="dg-badge dg-badge--danger mt-1" style="font-size:11.5px;padding:2px 8px"><i class="bi bi-exclamation-circle"></i>Échéance dépassée</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-5">
                            <div class="dg-chart-empty" style="min-height:180px">
                                <span class="dg-tile dg-tone-green"><i class="bi bi-check2-circle"></i></span>
                                <div><strong>Aucune créance client</strong>Toutes les factures émises sont réglées.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if($byClient->isNotEmpty())
                    <tfoot><tr><td colspan="4">Total à encaisser</td><td class="text-end dg-cell-num">{{ $money($receivableTotal) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
        <div class="dg-chart-empty mt-4 d-none" id="receivablesNoResult" style="min-height:140px">
            <span class="dg-tile dg-tone-orange"><i class="bi bi-search"></i></span>
            <div><strong>Aucun client ne correspond à votre recherche</strong>Vérifiez l’orthographe du nom.</div>
        </div>
        <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Factures de ventes et factures personnalisées émises, avoirs et règlements déduits. Seule la facture personnalisée porte une échéance.</p>
    </div>

    <div class="dg-grid-halves">
        <x-dg.card title="Activité commerciale de la période" icon="bi-activity" color="blue">
            <table class="dg-mini-table">
                <tbody>
                    @foreach($activity as [$label, $count, $icon, $color, $link])
                        <tr>
                            <td><a href="{{ $link }}" class="d-inline-flex align-items-center gap-3 text-reset text-decoration-none"><span class="dg-tile dg-tile--sm dg-tone-{{ $color }}"><i class="bi {{ $icon }}"></i></span>{{ $label }}</a></td>
                            <td>{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="dg-muted mt-3 mb-0" style="font-size:12.5px">Les livraisons à valider sont comptées à ce jour, quelle que soit la période.</p>
        </x-dg.card>

        <x-dg.card title="Stock à surveiller" icon="bi-box-seam" color="red">
            <x-slot:actions>
                <a href="{{ route('admin.commercial.module', 'etat-stock') }}" class="dg-btn dg-btn--outline dg-btn--sm">État du stock</a>
            </x-slot:actions>
            @if($stockWatch->isNotEmpty())
                <ul class="dg-list">
                    @foreach($stockWatch->take(6) as $article)
                        <li class="dg-list-row">
                            <span class="dg-tile dg-tile--sm dg-tone-{{ $article['state'] === 'out' ? 'red' : 'orange' }}"><i class="bi {{ $article['state'] === 'out' ? 'bi-x-circle' : 'bi-exclamation-triangle' }}"></i></span>
                            <div class="dg-list-row__body">
                                <div class="dg-list-row__title">{{ $article['designation'] }}</div>
                                <div class="dg-list-row__sub">{{ $article['state'] === 'out' ? 'En rupture' : 'Stock faible' }}</div>
                            </div>
                            <span class="dg-list-row__value">{{ rtrim(rtrim(number_format((float) $article['available'], 3, ',', ' '), '0'), ',') }} {{ $article['unit'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if($stockWatch->count() > 6)<p class="dg-muted mt-2 mb-0" style="font-size:12.5px">Et {{ $stockWatch->count() - 6 }} autre(s) article(s).</p>@endif
            @else
                {!! $emptyBlock('green', 'bi-box-seam', 'Aucun article à réapprovisionner', 'Aucun article n’est en rupture ni à ' . \App\Services\StockService::LOW_STOCK . ' unités ou moins.') !!}
            @endif
        </x-dg.card>
    </div>

    @foreach($cards as $index => $card)
        <div class="modal fade dg-modal dg-tone-{{ $card['color'] }}" id="salesDetail{{ $index }}" tabindex="-1" aria-labelledby="salesDetailTitle{{ $index }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="salesDetailTitle{{ $index }}"><i class="bi {{ $card['icon'] }}" aria-hidden="true"></i>{{ $card['label'] }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <p class="dg-muted mb-3" style="font-size:13px">
                            {{ $index === 2 ? 'Factures émises restant à encaisser à ce jour.' : ($index === 3 ? 'Devis en attente de validation et encore valables.' : 'Du ' . \Illuminate\Support\Carbon::parse($periodFrom)->format('d/m/Y') . ' au ' . \Illuminate\Support\Carbon::parse($periodTo)->format('d/m/Y') . '.') }}
                        </p>
                        <div class="dg-table-wrap">
                            <table class="dg-table">
                                <thead><tr><th>Date</th><th>Document</th><th class="dg-cell-actions">Montant</th></tr></thead>
                                <tbody>
                                    @forelse($card['rows'] as $row)
                                        <tr>
                                            <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                            <td>
                                                @if($row['url'] ?? null)<a href="{{ $row['url'] }}" class="dg-cell-strong text-reset">{{ $row['reference'] }}</a>@else<span class="dg-cell-strong">{{ $row['reference'] }}</span>@endif
                                                <span class="dg-cell-sub">{{ $row['client'] }} · {{ $row['sub'] }}</span>
                                            </td>
                                            <td class="dg-cell-num dg-cell-actions {{ $row['amount'] < 0 ? 'dg-amount-negative' : '' }}">{{ $money($row['amount']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="dg-empty">Aucun enregistrement.</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot><tr><td colspan="2">Total</td><td class="dg-cell-num dg-cell-actions">{{ $money($card['value']) }}</td></tr></tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="dg-btn dg-btn--outline" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<style>
    .sales-percent { font-size: 28px; font-weight: 700; line-height: 1; color: var(--dg-text); }
    .sales-bar { position: relative; display: block; height: 8px; border-radius: 99px; background: var(--dg-neutral-bg, #eceff6); }
    .sales-bar--lg { height: 12px; }
    .sales-bar__fill { display: block; height: 100%; border-radius: inherit; background: #059669; }
    .sales-bar__fill--warning { background: #d97706; }
    .sales-bar__fill--danger { background: #dc2626; }
    .sales-bar__fill--neutral { background: #8a93a6; }
    .sales-bar__fill--purple { background: #7c3aed; }
    .sales-bar__fill--teal { background: #0f766e; }
    .sales-bar__fill--cyan { background: #0891b2; }
    .sales-bar__fill--indigo { background: #4338ca; }
    .sales-bar__pace { position: absolute; top: -4px; bottom: -4px; width: 3px; margin-left: -1px; border-radius: 2px; background: var(--dg-navy); }
    .sales-list { margin: 0; padding: 0; list-style: none; }
    .sales-list li { display: flex; align-items: center; gap: 10px; padding: 6px 0; font-size: 13.5px; }
    .sales-list__name { flex: 0 0 42%; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sales-list__value { flex: 0 0 44px; text-align: right; font-weight: 600; }
    .sales-ranking { margin: 0; padding: 0; list-style: none; }
    .sales-ranking li { padding: 10px 0; border-bottom: 1px solid var(--dg-border); font-size: 14px; }
    .sales-ranking li:first-child { padding-top: 0; }
    .sales-ranking li:last-child { border-bottom: 0; padding-bottom: 0; }
    .sales-total td { border-top: 2px solid var(--dg-border-strong) !important; font-weight: 700; color: var(--dg-navy); }
    .receivables-table { min-width: 720px; }
    .receivables-table tfoot td { border-top: 2px solid var(--dg-border-strong); font-weight: 700; color: var(--dg-navy); }
    @media (max-width: 991px) { .dg-scope .dg-grid-2 { grid-template-columns: minmax(0, 1fr); } }
</style>
@if($hasRevenue)
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Recherche dans les créances.
    const search = document.getElementById('receivableSearch');
    if (search) {
        const rows = Array.from(document.querySelectorAll('[data-receivable-row]'));
        const noResult = document.getElementById('receivablesNoResult');
        search.addEventListener('input', function () {
            const term = search.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach(function (row) {
                const show = !term || row.cells[0].textContent.toLowerCase().includes(term);
                row.style.display = show ? '' : 'none';
                visible += show ? 1 : 0;
            });
            noResult.classList.toggle('d-none', visible > 0);
        });
    }

    @if($hasRevenue)
    // Chiffre d'affaires HT par mois : une couleur par origine, les avoirs sous l'axe.
    const months = @json($months);
    const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });
    Chart.defaults.font.family = "'Poppins', system-ui, sans-serif";
    Chart.defaults.color = '#8a93a6';
    const series = [
        ['invoice', 'Factures de ventes', '#2563eb'],
        ['custom_invoice', 'Factures personnalisées', '#7c3aed'],
        ['pos', 'Ventes au comptoir', '#0f766e'],
        ['credit_note', 'Avoirs', '#dc2626'],
    ].filter(([key]) => months.some(month => month[key] !== 0));
    new Chart(document.getElementById('salesMonthsChart'), {
        type: 'bar',
        data: {
            labels: months.map(month => month.label),
            datasets: series.map(([key, label, color]) => ({ label, data: months.map(month => month[key]), backgroundColor: color, borderRadius: 4, maxBarThickness: 30, stack: 'ca' }))
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 12 } },
                tooltip: { callbacks: { label: context => context.dataset.label + ' : ' + window.formatMoney(context.raw) } }
            },
            scales: {
                x: { stacked: true, grid: { display: false }, border: { display: false } },
                y: { stacked: true, border: { display: false }, grid: { color: 'rgba(138, 147, 166, .16)' }, ticks: { callback: value => compact.format(value) } }
            }
        }
    });
    @endif
});
</script>
@endsection
