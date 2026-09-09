<?php

namespace App\Http\Controllers\Admin;

use App\Models\BankTransaction;
use App\Models\CashMovement;
use App\Models\CommercialClient;
use App\Models\CommercialInvoice;
use App\Models\CustomInvoice;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PosSale;
use Illuminate\Http\Request;

class AiAssistantController extends AdminController
{
    public function index()
    {
        $this->ensureEnabled();

        return $this->page('ai-assistant', [
            'title' => 'Assistant IA',
            'question' => null,
            'answer' => null,
            'results' => collect(),
        ]);
    }

    public function ask(Request $request)
    {
        $this->ensureEnabled();
        $question = trim((string) $request->input('question'));
        abort_if($question === '', 422, 'Veuillez saisir une question.');

        $companyId = auth()->user()->entreprise_id;
        $term = mb_strtolower($question);
        $employees = Employee::where('entreprise_id', $companyId)->get();
        $clients = CommercialClient::where('entreprise_id', $companyId)->get();
        $invoices = CommercialInvoice::where('entreprise_id', $companyId)->get();
        $customInvoices = CustomInvoice::where('entreprise_id', $companyId)->get();
        $payrolls = Payroll::where('entreprise_id', $companyId)->get();
        $leaves = LeaveRequest::where('entreprise_id', $companyId)->with('employee')->get();
        $sales = PosSale::where('entreprise_id', $companyId)->get();
        $cashMovements = CashMovement::where('entreprise_id', $companyId)->get();
        $bankTransactions = BankTransaction::where('entreprise_id', $companyId)->get();

        $results = collect();
        $answer = 'Voici les informations disponibles pour votre entreprise.';

        if (str_contains($term, 'employ') || str_contains($term, 'personnel') || str_contains($term, 'salari')) {
            $results = $employees->map(fn ($item) => ['type' => 'Employé', 'title' => $item->full_name, 'detail' => trim(($item->department ?: 'Département non renseigné') . ' - ' . ($item->status ?: 'Statut non renseigné'))]);
            $answer = 'Votre entreprise compte ' . $employees->count() . ' employé(s).';
        } elseif (str_contains($term, 'client')) {
            $results = $clients->map(fn ($item) => ['type' => 'Client', 'title' => $item->name, 'detail' => trim(($item->city ?: 'Ville non renseignée') . ' - ' . ($item->phone ?: 'Téléphone non renseigné'))]);
            $answer = 'Votre entreprise compte ' . $clients->count() . ' client(s).';
        } elseif (str_contains($term, 'congé') || str_contains($term, 'absence')) {
            $results = $leaves->map(fn ($item) => ['type' => 'Congé', 'title' => $item->employee?->full_name ?? 'Employé', 'detail' => ucfirst((string) $item->status) . ' - ' . ($item->start_date?->format('d/m/Y') ?? '-') . ' au ' . ($item->end_date?->format('d/m/Y') ?? '-')]);
            $answer = 'J’ai trouvé ' . $leaves->count() . ' demande(s) de congé.';
        } elseif (str_contains($term, 'paie') || str_contains($term, 'salaire')) {
            $results = $payrolls->map(fn ($item) => ['type' => 'Paie', 'title' => $item->employee?->full_name ?? 'Bulletin', 'detail' => sprintf('%02d/%04d - %s FCFA', $item->month, $item->year, number_format((float) $item->net_salary, 0, ',', ' '))]);
            $answer = 'Le total net des bulletins disponibles est de ' . number_format((float) $payrolls->sum('net_salary'), 0, ',', ' ') . ' FCFA.';
        } elseif (str_contains($term, 'factur') || str_contains($term, 'chiffre') || str_contains($term, 'vente')) {
            $total = (float) $invoices->sum('amount') + (float) $customInvoices->sum('total_ttc') + (float) $sales->sum('total');
            $results = $invoices->map(fn ($item) => ['type' => 'Facture', 'title' => $item->client_name ?: 'Client', 'detail' => number_format((float) $item->amount, 0, ',', ' ') . ' FCFA - ' . ucfirst((string) $item->status)]);
            $answer = 'Le chiffre d’affaires enregistré est de ' . number_format($total, 0, ',', ' ') . ' FCFA.';
        } elseif (str_contains($term, 'caisse') || str_contains($term, 'banque') || str_contains($term, 'trésor')) {
            $results = $cashMovements->map(fn ($item) => ['type' => 'Caisse', 'title' => $item->label, 'detail' => number_format((float) $item->amount, 0, ',', ' ') . ' FCFA - ' . ucfirst((string) $item->movement_type)]);
            $answer = 'Les mouvements de caisse disponibles représentent ' . number_format((float) $cashMovements->sum('amount'), 0, ',', ' ') . ' FCFA.';
        } else {
            $results = $employees->filter(fn ($item) => str_contains(mb_strtolower((string) $item->full_name), $term))->map(fn ($item) => ['type' => 'Employé', 'title' => $item->full_name, 'detail' => $item->department ?: 'Département non renseigné']);
            $answer = $results->isEmpty() ? 'Je peux rechercher les employés, clients, congés, paies, factures, ventes, caisses et banques de votre entreprise.' : 'Résultats correspondant à votre recherche.';
        }

        return $this->page('ai-assistant', compact('question', 'answer', 'results'));
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) data_get(auth()->user()->entreprise?->settings, 'ai_assistant_enabled', false), 403, 'L’assistant IA n’est pas activé pour cette entreprise.');
    }
}
