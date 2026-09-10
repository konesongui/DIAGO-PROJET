@extends('admin.layout')
@section('content')
<style>
.pilotage{max-width:none;width:100%;}.pilotage-hero{background:linear-gradient(130deg,#ffb900,#005fab);border-radius:20px;padding:26px 28px;color:#fff;margin-bottom:22px;box-shadow:0 16px 40px rgba(0,60,110,.18)}.pilotage-hero h2{font-weight:800;margin:0}.pilotage-filter,.dash-card,.stat-card{background:#fff;border:0;border-radius:14px;box-shadow:0 4px 16px rgba(15,40,65,.08)}.pilotage-filter{padding:16px;margin-bottom:22px}.stat-card{padding:20px;text-align:center;height:100%}.stat-icon{width:58px;height:58px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:24px}.stat-number{font-size:22px;font-weight:800;color:#1f2d3d}.stat-text{color:#64748b;font-weight:600}.section-title{margin:26px 0 16px;padding:12px 20px;background:#fff;border-left:4px solid #1b4f80;border-radius:12px;color:#153a5d;font-weight:800;font-size:16px}.dash-card{padding:0;overflow:hidden;height:100%}.dash-card h3{font-size:17px;margin:0;padding:16px 20px;border-bottom:1px solid #edf1f5;color:#1b4f80}.dash-card .chart{padding:16px;height:300px}.dash-card .body{padding:18px}.mini-card{background:#fff;border-radius:14px;padding:20px;box-shadow:0 4px 16px rgba(15,40,65,.08);height:100%}.mini-card h4{font-size:15px;color:#1b4f80}.table-dashboard th{font-size:12px;text-transform:uppercase;color:#64748b}.table-dashboard td{font-size:13px}.metric-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}@media(max-width:1100px){.metric-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:576px){.metric-grid{grid-template-columns:1fr}}
.expense-stats-card{background:#fff;border-radius:16px}.expense-stats-header{display:flex;align-items:flex-start;justify-content:space-between;padding:22px 22px 12px}.expense-stats-header h3{padding:0;margin:0;border:0;color:#1f2937;font-size:16px}.expense-stats-header span{display:block;color:#94a3b8;font-size:12px;font-weight:600;margin-top:5px}.expense-stats-icon{display:flex!important;align-items:center;justify-content:center;width:32px;height:32px;margin:0!important;border-radius:9px;background:#eef5fb;color:#1b4f80!important;font-size:16px!important}.expense-stats-body{padding:0 18px 18px}.expense-chart{height:240px}.expense-empty{height:240px;display:flex;align-items:center;justify-content:center;text-align:center;color:#94a3b8;font-size:13px}
</style>
<style>
    .pilotage { padding: 6px 4px 32px; }
    .pilotage-hero {
        display:flex; align-items:center; justify-content:space-between; gap:24px;
        padding:28px 32px; margin-bottom:24px; border:1px solid #e8eef5;
        border-radius:18px; color:#172b4d; background:linear-gradient(135deg,#fff 0%,#f2f7fc 100%);
        box-shadow:0 8px 24px rgba(15,40,65,.06);
    }
    .pilotage-hero h2 { color:#172b4d; font-size:24px; letter-spacing:-.03em; }
    .pilotage-hero p { color:#7b8ba5; font-size:13px; }
    .pilotage-hero .hero-badge { padding:9px 13px; border-radius:10px; background:#e8f2fb; color:#1b4f80; font-size:12px; font-weight:700; white-space:nowrap; }
    .pilotage-filter { align-items:flex-end; padding:16px 18px; border:1px solid #e8eef5; border-radius:14px; background:#fff; box-shadow:0 5px 18px rgba(15,40,65,.04); }
    .pilotage-filter label { color:#7b8ba5; font-size:11px; text-transform:uppercase; letter-spacing:.06em; }
    .pilotage-filter .form-control { min-width:155px; border-color:#e4eaf1; border-radius:9px; color:#344563; }
    .pilotage-filter .btn { border-radius:9px; background:#1b4f80; border-color:#1b4f80; font-weight:700; }
    .section-title { display:flex; align-items:center; gap:10px; margin:28px 0 14px; padding:0 2px; border:0; background:transparent; color:#172b4d; font-size:15px; letter-spacing:-.01em; }
    .section-title::before { content:''; width:4px; height:20px; border-radius:4px; background:#1b4f80; }
    .metric-grid { gap:14px; }
    .stat-card { position:relative; overflow:hidden; padding:20px 22px; text-align:left; border:1px solid #e9eef4; border-radius:14px; box-shadow:0 5px 18px rgba(15,40,65,.045); }
    .stat-card::after { content:''; position:absolute; right:-22px; bottom:-28px; width:90px; height:90px; border-radius:50%; background:rgba(27,79,128,.035); }
    .stat-icon { width:42px; height:42px; margin:0 0 16px; border-radius:11px; font-size:19px; }
    .stat-number { font-size:19px; letter-spacing:-.02em; }
    .stat-text { margin-top:5px; font-size:12px; color:#8a99ad; }
    .dash-card, .mini-card { border:1px solid #e9eef4; border-radius:15px; box-shadow:0 5px 18px rgba(15,40,65,.045); }
    .dash-card h3 { padding:19px 22px 14px; border-bottom:1px solid #edf1f5; color:#172b4d; font-size:15px; font-weight:700; }
    .dash-card h3::first-letter { color:#1b4f80; }
    .dash-card .chart { height:310px; padding:18px 20px 20px; }
    .expense-stats-header { padding:20px 22px 10px; }
    .expense-stats-header h3 { font-size:15px; }
    .expense-stats-header span { font-size:11px; }
    .mini-card { padding:18px; }
    .mini-card h4 { margin-bottom:14px; color:#172b4d; font-size:13px; font-weight:700; }
    .table-dashboard { --bs-table-bg:transparent; }
    .table-dashboard th { padding-top:0; font-size:10px; letter-spacing:.06em; }
    .table-dashboard td { padding:9px 0; color:#53657e; border-color:#eef2f6; }
    @media (max-width: 767px) {
        .pilotage-hero { align-items:flex-start; flex-direction:column; padding:22px; }
        .pilotage-hero h2 { font-size:20px; }
        .pilotage-filter .form-control { min-width:0; width:100%; }
        .pilotage-filter > div { flex:1 1 100%; }
        .dash-card .chart { height:260px; }
    }
</style>
@php $money = fn($value) => money((float)$value); @endphp
<div class="pilotage">
    <div class="pilotage-hero">
        <div><h2 class="mb-1">{{ __('Global overview') }}</h2><p class="mb-0 mt-2">{{ __('Consolidated view of treasury, commercial performance and human resources.') }}</p></div>
        <div class="hero-badge">{{ __('Consolidated report') }}</div>
    </div>
    <form method="GET" class="pilotage-filter d-flex flex-wrap align-items-end gap-3">
        <div><label class="form-label fw-bold">{{ __('Start date') }}</label><input type="date" name="date_debut" value="{{ $filters['date_debut'] }}" class="form-control"></div>
        <div><label class="form-label fw-bold">{{ __('End date') }}</label><input type="date" name="date_fin" value="{{ $filters['date_fin'] }}" class="form-control"></div>
        <button class="btn btn-primary px-4"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
    </form>
    <div class="section-title">{{ __('Treasury') }}</div>
    <div class="metric-grid mb-5">
        @foreach([['revenue','Total revenue','#e8f5e9','bi-cash-stack'],['cash_exits','Cash outflows','#ffebee','bi-credit-card'],['balance','Current balance','#e3f2fd','bi-wallet2'],['transactions','Transactions','#fff3e0','bi-arrow-left-right']] as [$key,$label,$bg,$icon])
        <div class="stat-card"><div class="stat-icon" style="background:{{ $bg }}"><i class="bi {{ $icon }}"></i></div><div class="stat-number">{{ $key === 'transactions' ? $treasury[$key] : $money($treasury[$key]) }}</div><div class="stat-text">{{ $label }}</div></div>
        @endforeach
    </div>
    <div class="section-title">{{ __('Accounting') }}</div>
    <div class="row g-5 mb-5">
        <div class="col-xl-8"><div class="dash-card"><h3>{{ __('Revenue vs expenses') }}</h3><div class="chart"><canvas id="revenueExpenseChart"></canvas></div></div></div>
        <div class="col-xl-4">
            <div class="dash-card expense-stats-card">
                <div class="expense-stats-header">
                    <div><h3>{{ __('Expense categories') }}</h3><span>{{ $money($accounting['expenses']) }} {{ __('during the period') }}</span></div>
                    <span class="expense-stats-icon">↗</span>
                </div>
                <div class="expense-stats-body">
                    @if($accounting['expense_categories']->isNotEmpty())
                        <div class="expense-chart"><canvas id="expenseChart"></canvas></div>
                    @else
                        <div class="expense-empty">{{ __('No expenses recorded during this period.') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="section-title">Commercial</div>
    <div class="row g-4 mb-5"><div class="col-md-6"><h5>Ventes</h5><div class="metric-grid" style="grid-template-columns:repeat(3,1fr)"><div class="mini-card"><b>{{ $money($commercial['sales']) }}</b><small class="d-block text-muted">Chiffre d'affaires</small></div><div class="mini-card"><b>{{ $commercial['invoices'] }}</b><small class="d-block text-muted">Factures émises</small></div><div class="mini-card"><b>{{ $money($commercial['receivables']) }}</b><small class="d-block text-muted">Créances impayées</small></div></div></div><div class="col-md-6"><h5>Achats</h5><div class="metric-grid" style="grid-template-columns:repeat(2,1fr)"><div class="mini-card"><b>{{ $money($commercial['purchases']) }}</b><small class="d-block text-muted">Total achats</small></div><div class="mini-card"><b>{{ $commercial['supplier_invoices'] }}</b><small class="d-block text-muted">Factures reçues</small></div></div></div></div>
    <div class="section-title">Ressources humaines</div>
    <div class="metric-grid mb-5">@foreach([[$hr['count'],'Effectif total'],[$hr['median_age'].' ans','Âge médian'],[number_format($hr['seniority'],1,',',' ').' ans','Ancienneté moyenne'],[$money($hr['net_average']),'Salaire net moyen']] as [$value,$label])<div class="stat-card"><div class="stat-number">{{ $value }}</div><div class="stat-text">{{ $label }}</div></div>@endforeach</div>
    <div class="row g-5">
        @foreach([['ageChart','Pyramide des âges (effectifs par tranche)',$hr['age_bands']],['turnoverChart','Turnover mensuel (sorties) - '.$filters['date_debut'],$hr['turnover']],['salaryChart','Évolution salaire net moyen (12 mois)',$hr['salary']],['leaveChart','Évolution congés, absences, arrêts - '.$filters['date_debut'],$hr['leave']]] as [$id,$title,$data])<div class="col-xl-6"><div class="dash-card"><h3>{{ $title }}</h3><div class="chart"><canvas id="{{ $id }}"></canvas></div></div></div>@endforeach
    </div>
    <div class="row g-5 mt-1">@foreach([['Contrats',$hr['contracts']],['Nationalité',$hr['nationalities']],['Catégorie professionnelle',$hr['categories']],['Tableau de bord',$hr['departments']]] as [$title,$data])<div class="col-xl-3"><div class="mini-card"><h4>{{ $title }}</h4><table class="table table-dashboard mb-0"><tbody>@forelse($data as $label=>$value)<tr><td>{{ $label ?: 'Non renseigné' }}</td><td class="text-end fw-bold">{{ is_numeric($value) && str_contains($title,'Salaire') ? $money($value) : $value }}</td></tr>@empty<tr><td class="text-muted">Aucune donnée</td></tr>@endforelse</tbody></table></div></div>@endforeach</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const money=v=>window.formatMoney(v), labels=@json($monthLabels);
new Chart(document.getElementById('revenueExpenseChart'),{type:'line',data:{labels:labels,datasets:[{label:'Revenus',data:@json($accounting['revenue_series']),borderColor:'#1b9e5a',tension:.35},{label:'Dépenses',data:@json($accounting['expense_series']),borderColor:'#e45757',tension:.35}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{ticks:{callback:money}}}}});
const expenseCanvas=document.getElementById('expenseChart');
if(expenseCanvas){const expenseContext=expenseCanvas.getContext('2d'),expenseGradient=expenseContext.createLinearGradient(0,0,0,250);expenseGradient.addColorStop(0,'rgba(27,79,128,.28)');expenseGradient.addColorStop(1,'rgba(27,79,128,0)');new Chart(expenseCanvas,{type:'line',data:{labels:@json($accounting['expense_categories']->keys()->values()),datasets:[{label:'Dépenses',data:@json($accounting['expense_categories']->values()),borderColor:'#1b4f80',backgroundColor:expenseGradient,fill:true,tension:.42,borderWidth:3,pointRadius:4,pointHoverRadius:6,pointBackgroundColor:'#fff',pointBorderColor:'#1b4f80',pointBorderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:context=>money(context.raw)}}},scales:{x:{grid:{display:false},ticks:{color:'#94a3b8',font:{size:10},maxRotation:0}},y:{beginAtZero:true,grid:{color:'rgba(148,163,184,.14)'},ticks:{color:'#94a3b8',callback:money}}}}});}
const charts=[['ageChart',@json($hr['age_bands']->keys()),@json($hr['age_bands']->values()),'bar'],['turnoverChart',@json($hr['turnover']->keys()),@json($hr['turnover']->values()),'line'],['salaryChart',@json($hr['salary']->keys()),@json($hr['salary']->values()),'line'],['leaveChart',@json($hr['leave']->keys()),@json($hr['leave']->values()),'line']];charts.forEach(([id,l,d,t])=>new Chart(document.getElementById(id),{type:t,data:{labels:l,datasets:[{label:'Effectif / montant',data:d,borderColor:'#1b4f80',backgroundColor:'#4b9bd1',tension:.35}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{callback:v=>t==='line'?money(v):v}}}}}));
</script>
@endsection
