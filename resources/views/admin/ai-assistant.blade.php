@extends('admin.layout')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body p-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div><div class="text-uppercase text-muted fs-8 fw-bold">Assistant entreprise</div><h1 class="fs-2 fw-bold mb-1">Assistant IA</h1><p class="text-muted mb-0">Interrogez les données de votre entreprise en langage naturel.</p></div>
            <span class="badge badge-light-success">Activé par la console</span>
        </div>
        <form method="POST" action="{{ route('admin.ai-assistant.ask') }}" class="mb-5">@csrf
            <label class="form-label fw-bold">Votre question</label>
            <div class="input-group input-group-lg"><input name="question" value="{{ $question ?? '' }}" class="form-control" placeholder="Exemple : combien avons-nous d'employés ?" required><button class="btn btn-primary">Analyser</button></div>
        </form>
        <div class="row g-3 mb-5">
            @foreach(['Combien avons-nous d’employés ?', 'Liste les clients de l’entreprise', 'Quel est le chiffre d’affaires ?', 'Montre les congés et les paies'] as $suggestion)
                <div class="col-md-3"><form method="POST" action="{{ route('admin.ai-assistant.ask') }}">@csrf<input type="hidden" name="question" value="{{ $suggestion }}"><button class="btn btn-light w-100 text-start">{{ $suggestion }}</button></form></div>
            @endforeach
        </div>
        @if($answer)<div class="alert alert-primary"><strong>Réponse :</strong> {{ $answer }}</div>@endif
        @if($results && $results->isNotEmpty())
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Type</th><th>Élément</th><th>Détail</th></tr></thead><tbody>@foreach($results as $result)<tr><td><span class="badge badge-light-primary">{{ $result['type'] }}</span></td><td class="fw-bold">{{ $result['title'] }}</td><td>{{ $result['detail'] }}</td></tr>@endforeach</tbody></table></div>
        @elseif($answer)<div class="text-muted">Aucun résultat détaillé pour cette question.</div>@endif
    </div>
</div>
@endsection
