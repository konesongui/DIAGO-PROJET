@extends('admin.layout')

@section('content')
<div class="dg-font dg-scope">
    <x-dg.page-header :title="$title" :subtitle="$subtitle" :back="route('admin.rh')" back-label="RH & Paie">
        <x-slot:actions>
            <a href="{{ route('admin.rh.attendance.today') }}" class="dg-btn dg-btn--outline"><i class="bi bi-check2-circle"></i>Présences du jour</a>
            <button type="button" class="dg-btn dg-btn--outline" id="qrPrint"><i class="bi bi-printer"></i>Imprimer</button>
            <form method="POST" action="{{ route('admin.rh.qr.renew') }}" onsubmit="return confirm('Générer un nouveau code ? L’ancien code, y compris celui déjà imprimé, ne permettra plus de pointer.')">
                @csrf
                <button class="dg-btn dg-btn--primary"><i class="bi bi-arrow-repeat"></i>Nouveau code</button>
            </form>
        </x-slot:actions>
    </x-dg.page-header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="dg-grid-2">
        <x-dg.card title="Code de pointage" icon="bi-qr-code" color="cyan" :meta="$todayCount . ' pointage(s) aujourd’hui'">
            <div class="qr-frame" id="qrFrame">
                <div id="attendanceQr" aria-label="QR code de pointage" role="img"></div>
                <p class="qr-frame__hint">Ce code reste actif jusqu’à la génération d’un nouveau code.</p>
            </div>
        </x-dg.card>

        <x-dg.card title="Comment pointer" icon="bi-info-circle" color="blue">
            <ol class="qr-steps">
                <li><strong>Scanner le code</strong> avec l’appareil photo du téléphone, à l’arrivée puis au départ.</li>
                <li><strong>Saisir son identifiant</strong> : matricule, téléphone ou adresse e-mail enregistrés sur la fiche.</li>
                <li><strong>Prendre la photo</strong> demandée : elle est jointe au pointage et sert de preuve.</li>
            </ol>
            <div class="qr-note">
                <span>Adresse du pointage</span>
                <code>{{ $scanUrl }}</code>
                <button type="button" class="dg-btn dg-btn--outline dg-btn--sm" id="qrCopy" data-url="{{ $scanUrl }}"><i class="bi bi-clipboard"></i>Copier le lien</button>
            </div>
            <p class="dg-muted mt-3 mb-0" style="font-size:12.5px"><i class="bi bi-info-circle me-1"></i>Seuls les employés en poste peuvent pointer. Le premier passage de la journée enregistre l’arrivée, le second le départ.</p>
        </x-dg.card>
    </div>
</div>

<style>
    .qr-frame { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 24px; border: 1px solid var(--dg-border); border-radius: var(--dg-radius-lg); background: var(--dg-surface); }
    .qr-frame img, .qr-frame canvas { display: block; }
    .qr-frame__hint { margin: 0; font-size: 12.5px; color: var(--dg-muted); text-align: center; }
    .qr-steps { margin: 0 0 18px; padding-left: 18px; font-size: 14px; line-height: 1.7; }
    .qr-steps strong { color: var(--dg-navy); }
    .qr-note { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 14px; border-radius: var(--dg-radius); background: var(--dg-table-head); }
    .qr-note span { width: 100%; font-size: 12.5px; color: var(--dg-muted); }
    .qr-note code { flex: 1 1 220px; min-width: 0; overflow-wrap: anywhere; font-size: 12.5px; color: var(--dg-navy); }
    @media print {
        body * { visibility: hidden; }
        #qrFrame, #qrFrame * { visibility: visible; }
        #qrFrame { position: absolute; inset: 0; margin: auto; border: 0; }
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new QRCode(document.getElementById('attendanceQr'), {
        text: @json($scanUrl),
        width: 280,
        height: 280,
        colorDark: '#273772',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });
    document.getElementById('qrPrint').addEventListener('click', () => window.print());
    const copy = document.getElementById('qrCopy');
    copy.addEventListener('click', function () {
        navigator.clipboard?.writeText(copy.dataset.url).then(function () {
            copy.innerHTML = '<i class="bi bi-check-lg"></i>Lien copié';
            setTimeout(() => { copy.innerHTML = '<i class="bi bi-clipboard"></i>Copier le lien'; }, 2000);
        });
    });
});
</script>
@endsection
