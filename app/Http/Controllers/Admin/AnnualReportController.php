<?php

namespace App\Http\Controllers\Admin;

use App\Models\AnnualFinancialReport;
use App\Services\AnnualReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Bilan financier annuel presente au dirigeant.
 */
class AnnualReportController extends AdminController
{
    /** Marque le bilan comme vu, sans le considerer comme lu. */
    public function acknowledge(Request $request, AnnualFinancialReport $report)
    {
        $this->authorizeReport($report);

        // Fermer la fenetre ne vaut pas lecture : le bilan reste signale en
        // notification tant que le PDF n'a pas ete telecharge.
        if (! $report->seen_at) {
            $report->update(['seen_at' => now()]);
        }

        return response()->json(['status' => 'ok', 'still_unread' => $report->isUnread()]);
    }

    /** Telechargement du PDF : c'est lui qui vaut lecture. */
    public function download(AnnualFinancialReport $report)
    {
        $this->authorizeReport($report);

        $report->update([
            'downloaded_at' => $report->downloaded_at ?: now(),
            'downloaded_by_user_id' => $report->downloaded_by_user_id ?: auth()->id(),
            'seen_at' => $report->seen_at ?: now(),
        ]);

        $pdf = Pdf::loadView('admin.annual-report-pdf', [
            'report' => $report,
            'data' => $report->data,
        ])->setPaper('a4');

        return $pdf->download('bilan-financier-' . $report->fiscal_year . '.pdf');
    }

    /** Consultation a l'ecran, depuis la notification. */
    public function show(AnnualFinancialReport $report)
    {
        $this->authorizeReport($report);

        return $this->page('annual-report-show', [
            'title' => $report->label(),
            'subtitle' => 'Bilan et compte de résultat de l’exercice clos',
            'report' => $report,
            'data' => $report->data,
        ]);
    }

    /** Liste des exercices disponibles. */
    public function index(AnnualReportService $service)
    {
        $entrepriseId = auth()->user()->entreprise_id;

        // Genere a la volee les exercices clos qui n'ont pas encore de bilan.
        for ($year = $service->latestClosedYear(); $year >= $service->latestClosedYear() - 4; $year--) {
            $service->reportFor($entrepriseId, $year);
        }

        return $this->page('annual-report-index', [
            'title' => 'Bilans financiers',
            'subtitle' => 'Exercices clos',
            'reports' => AnnualFinancialReport::orderByDesc('fiscal_year')->get(),
        ]);
    }

    private function authorizeReport(AnnualFinancialReport $report): void
    {
        abort_unless($report->entreprise_id === auth()->user()->entreprise_id, 403);
    }
}
