{{--
    Tableau des lignes d'un document de vente (devis, facture, proforma).
    Variable : $lines (tableau de lignes : item_name, category_name, unit, quantity, unit_price), avec en option :
    - specs : caractéristiques « libellé => valeur », comme sur la facture personnalisée ;
    - discount_label et line_total : remise propre à la ligne et total HT après remise, comme sur la proforma.
      La colonne « Remise » n'apparaît que si au moins une ligne en porte une.
--}}
@php
    $lines = collect($lines);
    $withDiscount = $lines->contains(fn ($line) => filled($line['discount_label'] ?? null));
@endphp
<table class="items">
    <thead>
        <tr>
            <th>Désignation</th>
            <th class="center">Qté</th>
            <th class="center">Unité</th>
            <th class="num">Prix unitaire HT</th>
            @if($withDiscount)<th class="num">Remise</th>@endif
            <th class="num">Total HT</th>
        </tr>
    </thead>
    <tbody>
    @forelse($lines as $line)
        <tr>
            <td>
                <span class="strong">{{ $line['item_name'] ?? '—' }}</span>
                @if(!empty($line['category_name']))<span class="muted">{{ $line['category_name'] }}</span>@endif
                @if(!empty($line['specs']))
                    <span class="specs">@foreach($line['specs'] as $specLabel => $specValue)<span><b>{{ $specLabel }}</b> {{ $specValue }}</span> @endforeach</span>
                @endif
            </td>
            <td class="center">{{ rtrim(rtrim(number_format((float) ($line['quantity'] ?? 0), 3, ',', ' '), '0'), ',') }}</td>
            <td class="center">{{ ($line['unit'] ?? '') ?: '—' }}</td>
            <td class="num">{{ money((float) ($line['unit_price'] ?? 0)) }}</td>
            @if($withDiscount)<td class="num">{{ ($line['discount_label'] ?? null) ?: '—' }}</td>@endif
            <td class="num strong">{{ money(isset($line['line_total']) ? (float) $line['line_total'] : (float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0)) }}</td>
        </tr>
    @empty
        <tr><td colspan="{{ $withDiscount ? 6 : 5 }}" class="center">Aucune ligne.</td></tr>
    @endforelse
    </tbody>
</table>
