@extends('admin.layout')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <div class="text-uppercase text-muted small fw-bold">Console</div>
        <h1 class="h3 fw-bold mb-1">Demandes de packs</h1>
        <p class="text-muted mb-0">Consultez les demandes reçues depuis le site et répondez aux prospects.</p>
    </div>
    <a href="{{ route('console.index') }}" class="btn btn-light">Retour au tableau de bord</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-5">Date</th>
                        <th>Pack</th>
                        <th>Prospect</th>
                        <th>Coordonnées</th>
                        <th>Message</th>
                        <th>Statut</th>
                        <th class="text-end pe-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $packRequest)
                        @php
                            $whatsapp = preg_replace('/\D+/', '', (string) $packRequest->phone);
                            $whatsappMessage = rawurlencode('Bonjour ' . $packRequest->name . ', nous avons bien reçu votre demande pour le pack ' . $packRequest->pack_name . '.');
                        @endphp
                        <tr>
                            <td class="ps-5">{{ $packRequest->created_at->format('d/m/Y H:i') }}</td>
                            <td><span class="badge bg-primary-subtle text-primary">{{ $packRequest->pack_name }}</span></td>
                            <td><div class="fw-bold">{{ $packRequest->name }}</div><div class="text-muted small">{{ $packRequest->email }}</div></td>
                            <td>{{ $packRequest->phone ?: 'Non renseigné' }}</td>
                            <td class="text-muted" style="max-width:240px">{{ $packRequest->message ?: 'Aucun message' }}</td>
                            <td><span class="badge {{ $packRequest->status === 'replied' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ $packRequest->status === 'replied' ? 'Répondu' : 'Nouveau' }}</span></td>
                            <td class="text-end pe-5">
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#reply-{{ $packRequest->id }}">Répondre</button>
                                    @if($whatsapp)
                                        <a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/{{ $whatsapp }}?text={{ $whatsappMessage }}">WhatsApp</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <div class="modal fade" id="reply-{{ $packRequest->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('console.pack-requests.email', $packRequest) }}">
                                        @csrf
                                        <div class="modal-header"><h5 class="modal-title">Répondre à {{ $packRequest->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="text-muted small mb-3">Email : {{ $packRequest->email }}</div>
                                            <textarea name="reply" class="form-control" rows="6" required>Bonjour {{ $packRequest->name }},&#10;&#10;Merci pour votre intérêt pour le pack {{ $packRequest->pack_name }}.&#10;&#10;Cordialement,&#10;L'équipe Diagoma</textarea>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Envoyer par email</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">Aucune demande de pack reçue.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
