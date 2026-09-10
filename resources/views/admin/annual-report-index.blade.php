@extends('admin.layout')

@section('content')
<div class="container-fluid py-5">
    <div class="card border-0" style="border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.04)">
        <div class="card-header"><h3 class="card-title">Bilans des exercices clos</h3></div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead><tr class="text-muted fs-8 text-uppercase">
                    <th>Exercice</th><th class="text-end">Total actif</th><th class="text-end">Résultat</th>
                    <th>État</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($reports as $r)
                    @php($d = $r->data)
                    <tr>
                        <td class="fw-bold fs-5">{{ $r->fiscal_year }}</td>
                        <td class="text-end">{{ number_format((float) $r->total_assets, $d['decimals'] ?? 0, ',', ' ') }} {{ $d['currency_symbol'] ?? '' }}</td>
                        <td class="text-end fw-semibold {{ $r->net_result >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format((float) $r->net_result, $d['decimals'] ?? 0, ',', ' ') }} {{ $d['currency_symbol'] ?? '' }}
                        </td>
                        <td>
                            @if($r->isUnread())
                                <span class="badge badge-light-warning">Non lu</span>
                            @else
                                <span class="badge badge-light-success">Téléchargé le {{ $r->downloaded_at->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.bilans.show', $r) }}" class="btn btn-sm btn-light">Consulter</a>
                            <a href="{{ route('admin.bilans.download', $r) }}" class="btn btn-sm btn-primary">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-10">
                        Aucun exercice clos ne comporte d'écritures comptables.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
