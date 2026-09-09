@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <div class="text-uppercase text-muted fs-8 fw-bold">Commercial / Stock</div>
        <h2 class="fs-2 fw-bold mb-1">Inventaire</h2>
        <p class="text-muted mb-0">Comparez le stock théorique avec le stock réellement constaté.</p>
    </div>
    <a href="{{ route('admin.commercial') }}" class="btn btn-light">Retour</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('admin.commercial.inventory.store') }}">
    @csrf
    <div class="card border-0">
        <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Audit du stock</h3>
            <div class="d-flex align-items-center gap-3">
                <label for="audited_at" class="text-muted">Date de l'audit</label>
                <input id="audited_at" name="audited_at" type="date" class="form-control" value="{{ old('audited_at', now()->toDateString()) }}" required>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle" id="inventoryTable">
                <thead><tr><th>Désignation</th><th>Stock</th><th class="text-end">Entrée</th><th class="text-end">Sortie</th><th class="text-end">Stock théorique</th><th style="width:160px">Stock réel</th><th class="text-end">Écart</th></tr></thead>
                <tbody>
                @forelse($items as $index => $item)
                    <tr>
                        <td>
                            <strong>{{ $item['designation'] }}</strong>
                            @if($item['article'] !== '-')<div class="text-muted fs-7">{{ $item['article'] }}</div>@endif
                            <input type="hidden" name="items[{{ $index }}][designation]" value="{{ $item['designation'] }}">
                            <input type="hidden" name="items[{{ $index }}][article]" value="{{ $item['article'] === '-' ? '' : $item['article'] }}">
                            <input type="hidden" name="items[{{ $index }}][unit]" value="{{ $item['unit'] === '-' ? '' : $item['unit'] }}">
                            <input type="hidden" name="items[{{ $index }}][theoretical]" value="{{ $item['theoretical'] }}">
                        </td>
                        <td>{{ $item['unit'] }}</td>
                        <td class="text-end">{{ number_format($item['entry'], 3, ',', ' ') }}</td>
                        <td class="text-end">{{ number_format($item['exit'], 3, ',', ' ') }}</td>
                        <td class="text-end fw-bold theoretical">{{ number_format($item['theoretical'], 3, ',', ' ') }}</td>
                        <td><input name="items[{{ $index }}][actual]" type="number" min="0" step="0.001" class="form-control actual-input" value="{{ old('items.'.$index.'.actual', $item['actual']) }}" required></td>
                        <td class="text-end fw-bold variance">{{ number_format($item['variance'], 3, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-10">Aucun article disponible pour l'inventaire.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($items->isNotEmpty())<div class="card-footer text-end"><button type="submit" class="btn btn-primary">Enregistrer l'inventaire</button></div>@endif
    </div>
</form>
@push('scripts')
<script>
document.querySelectorAll('.actual-input').forEach(function (input) {
    input.addEventListener('input', function () {
        const row = input.closest('tr');
        const theoretical = parseFloat(row.querySelector('input[name$="[theoretical]"]').value) || 0;
        const variance = (parseFloat(input.value) || 0) - theoretical;
        row.querySelector('.variance').textContent = variance.toFixed(3).replace('.', ',');
        row.querySelector('.variance').classList.toggle('text-danger', variance < 0);
        row.querySelector('.variance').classList.toggle('text-success', variance > 0);
    });
});
</script>
@endpush
@endsection
