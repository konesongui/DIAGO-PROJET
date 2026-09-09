@extends('admin.layout')

@section('content')
<div class="card border-0">
    <div class="card-body text-center p-8">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-uppercase text-muted fs-8 fw-bold">RH &amp; Paie</div>
            <a href="{{ route('admin.rh') }}" class="btn btn-light">Retour à RH &amp; Paie</a>
        </div>
        <h2 class="fs-2 fw-bold mb-2">Pointage par QR Code</h2>
        <p class="text-muted">Présentez ce code aux employés pour enregistrer leur arrivée ou leur départ.</p>
        <div class="d-inline-block p-5 my-4 rounded-4 bg-light border">
            <div id="attendanceQr" class="mb-3"></div>
            <div class="small text-muted">Ce code reste actif jusqu’à la génération d’un nouveau QR code.</div>
        </div>
        <div class="d-flex justify-content-center gap-3">
            <a href="{{ route('admin.rh.qr.display') }}" class="btn btn-primary">Générer un nouveau code</a>
            <a href="{{ route('admin.rh.attendance.today') }}" class="btn btn-light-primary">Voir les présences du jour</a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('attendanceQr'), {
    text: @json($scanUrl),
    width: 300,
    height: 300,
    colorDark: '#1a2a6c',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
});
</script>
@endpush
