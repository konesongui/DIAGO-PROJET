<?php

namespace Tests\Feature;

use App\Models\CommercialClient;
use App\Models\CommercialDelivery;
use App\Models\CommercialInvoice;
use App\Models\CommercialOrder;
use App\Models\CommercialProforma;
use App\Models\CommercialQuote;
use App\Models\CustomInvoice;
use App\Models\Entreprise;
use App\Models\SupplierInvoice;
use App\Services\InvoiceIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Documents imprimables : ventilation fiscale, statut en français et mentions
 * légales lues dans les paramètres de l'entreprise.
 */
class InvoicePrintTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    /** Facture de vente complète : devis avec remise, TVA 18 %, client lié. */
    private function makeSaleInvoice(Entreprise $entreprise, array $invoiceAttributes = []): CommercialInvoice
    {
        $client = CommercialClient::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'name' => 'Société Générale CI', 'phone' => '0102030405',
            'email' => 'achats@sgci.ci', 'tax_id' => '8801234 B',
        ]);
        $lines = [['item_name' => 'Audit', 'quantity' => 2, 'unit' => 'jour', 'unit_price' => 500000]];
        $quote = CommercialQuote::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'client_id' => $client->id, 'client_name' => $client->name,
            'quote_date' => now()->toDateString(), 'total_ht' => 1000000, 'total_discount' => 100000, 'net_ht' => 900000,
            'tax_rate' => 18, 'tax_amount' => 162000, 'total_ttc' => 1062000, 'tax_regime' => 'standard',
            'payment_terms' => '30 jours net', 'lines' => $lines, 'status' => 'validated',
        ]);
        $order = CommercialOrder::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'quote_id' => $quote->id, 'client_name' => $client->name,
            'customer_order_code' => 'BC-118', 'lines' => $lines, 'total_ttc' => 1062000, 'status' => 'delivered',
        ]);
        $delivery = CommercialDelivery::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $entreprise->id, 'order_id' => $order->id, 'client_name' => $client->name,
            'lines' => $lines, 'delivery_type' => 'complete', 'status' => 'validated',
        ]);

        return CommercialInvoice::withoutGlobalScope('entreprise')->create($invoiceAttributes + [
            'entreprise_id' => $entreprise->id, 'delivery_id' => $delivery->id, 'client_name' => $client->name,
            'amount' => 1062000, 'total_ht' => 900000, 'tax_amount' => 162000, 'tax_rate' => 18, 'tax_regime' => 'standard',
            'paid_amount' => 62000, 'status' => 'partially_paid', 'issued_at' => now(),
        ]);
    }

    public function test_la_facture_de_vente_detaille_ht_remise_tva_et_ttc(): void
    {
        $alpha = $this->makeCompany('alpha');
        $invoice = $this->makeSaleInvoice($alpha);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.invoices.print', $invoice))
            ->assertOk()
            ->assertSeeInOrder(['Total HT brut', money(1000000), 'Remise', money(100000), 'Total HT', money(900000),
                'TVA (18 %)', money(162000), 'Total TTC', money(1062000), 'Déjà payé', money(62000), 'Reste à payer', money(1000000)])
            ->assertSee('Partiellement payée')
            ->assertDontSee('Partially_paid')
            ->assertSee('BC-118')
            ->assertSee('30 jours net');
    }

    public function test_une_facture_sans_regime_fiscal_n_affiche_que_le_ttc(): void
    {
        $alpha = $this->makeCompany('alpha');
        $invoice = $this->makeSaleInvoice($alpha, ['tax_regime' => 'unknown', 'tax_amount' => 0, 'total_ht' => 1062000]);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.invoices.print', $invoice))
            ->assertOk()
            ->assertSee('Total TTC')
            ->assertDontSee('TVA (')
            ->assertDontSee('Total HT brut');
    }

    public function test_les_mentions_legales_viennent_des_parametres_de_l_entreprise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $alpha->update(['settings' => array_merge($alpha->settings, [
            'nccm_rccm' => 'CI-ABJ-2019-B-12345', 'taxpayer_account' => '1912345 A',
            'bank_name' => 'NSIA Banque', 'bank_account' => 'CI092 01001',
        ])]);
        $invoice = $this->makeSaleInvoice($alpha);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.invoices.print', $invoice))
            ->assertOk()
            ->assertSee('RCCM : CI-ABJ-2019-B-12345')
            ->assertSee('Compte contribuable : 1912345 A')
            ->assertSee('NSIA Banque, compte n° CI092 01001');
    }
    public function test_la_facture_fournisseur_reprend_les_lignes_et_totaux_extraits(): void
    {
        $alpha = $this->makeCompany('alpha');
        $invoice = SupplierInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'invoice_number' => 'FV-0452', 'supplier_name' => 'MacStore Abidjan',
            'supplier_tax_id' => '1504321 C', 'invoice_date' => now()->toDateString(), 'total_ht' => 1000000,
            'tax_amount' => 180000, 'total_amount' => 1180000, 'tax_regime' => 'standard', 'currency' => 'XOF',
            'status' => 'imported', 'file_path' => 'factures/demo.pdf', 'original_filename' => 'facture.pdf',
            'extracted_data' => ['items' => [['description' => 'Ordinateur portable', 'quantity' => 2, 'unit_price' => 500000]]],
        ]);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.comptabilite.supplierInvoices.print', $invoice))
            ->assertOk()
            ->assertSee('FACTURE FOURNISSEUR')
            ->assertSee('MacStore Abidjan')
            ->assertSee('1504321 C')
            ->assertSee('Taux normal')
            ->assertSeeInOrder(['Ordinateur portable', '1 000 000', 'Total HT', 'TVA', '180 000', 'Total TTC', '1 180 000']);
    }

    public function test_une_facture_fournisseur_incomplete_le_signale(): void
    {
        $alpha = $this->makeCompany('alpha');
        $invoice = SupplierInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'supplier_name' => 'Air Côte d’Ivoire', 'total_amount' => 420000, 'currency' => 'XOF',
            'status' => 'needs_review', 'tax_regime' => 'unknown', 'file_path' => 'factures/demo.pdf', 'original_filename' => 'billet.pdf',
        ]);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.comptabilite.supplierInvoices.print', $invoice))
            ->assertOk()
            ->assertSee('N° à vérifier')
            ->assertSee('Aucune ligne détaillée')
            ->assertSee('Régime fiscal à confirmer');
    }

    public function test_la_fiche_fne_affiche_le_cadre_fiscal_la_tva_et_le_qr_de_verification(): void
    {
        $alpha = $this->makeCompany('alpha');
        $alpha->update(['settings' => array_merge($alpha->settings, [
            'taxpayer_account' => '1912345 A', 'tax_regime' => 'Réel normal', 'tax_center' => 'Plateau 1', 'nccm_rccm' => 'CI-ABJ-2019-B-12345',
        ])]);
        $invoice = $this->makeSaleInvoice($alpha, [
            'fne_status' => 'certified', 'fne_reference' => '9606123E25000000019', 'fne_certified_at' => now(),
            'fne_token' => 'https://verification.exemple.ci/fne/9606123E25000000019',
        ]);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.invoices.printFne', $invoice))
            ->assertOk()
            ->assertSee('FACTURE NORMALISÉE')
            ->assertSeeInOrder(['NCC', '1912345 A', 'Régime d’imposition', 'Réel normal', 'Centre des impôts', 'Plateau 1'])
            ->assertSeeInOrder(['Total HT', money(900000), 'TVA (18 %)', money(162000), 'Total TTC', money(1062000)])
            ->assertSee('data-url="https://verification.exemple.ci/fne/9606123E25000000019"', false)
            ->assertDontSee('Déjà payé')
            ->assertDontSee('✓');
    }

    public function test_la_fiche_fne_n_existe_que_pour_une_facture_certifiee(): void
    {
        $alpha = $this->makeCompany('alpha');
        $invoice = $this->makeSaleInvoice($alpha);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.invoices.printFne', $invoice))
            ->assertNotFound();
    }

    public function test_le_devis_imprime_detaille_les_totaux_et_invite_a_signer(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $invoice = $this->makeSaleInvoice($alpha);
        $quote = $invoice->delivery->order->quote;
        $quote->update(['status' => 'pending_validation', 'reference' => 'DEV-20260905-0002', 'due_date' => now()->addDays(20)->toDateString()]);

        $this->actingAs($admin)->get(route('admin.commercial.quotes.print', $quote))
            ->assertOk()
            ->assertSee('DEVIS')
            ->assertSee('N° DEV-20260905-0002')
            ->assertSeeInOrder(['Total HT brut', money(1000000), 'Remise', money(100000), 'Total HT', money(900000),
                'TVA (18 %)', money(162000), 'Total TTC', money(1062000)])
            ->assertSee('Bon pour accord')
            ->assertSee('En attente de validation');

        $quote->update(['due_date' => now()->subDay()->toDateString(), 'quote_date' => now()->subDays(40)->toDateString()]);
        $this->actingAs($admin)->get(route('admin.commercial.quotes.print', $quote))->assertSee('Expiré');

        $quote->update(['status' => 'validated']);
        $this->actingAs($admin)->get(route('admin.commercial.quotes.print', $quote))
            ->assertSee('Validé')
            ->assertDontSee('Bon pour accord');
    }

    public function test_la_facture_personnalisee_imprimee_reprend_forfaits_caracteristiques_et_totaux(): void
    {
        $alpha = $this->makeCompany('alpha');
        $alpha->update(['settings' => array_merge($alpha->settings, [
            'nccm_rccm' => 'CI-ABJ-2019-B-12345', 'bank_name' => 'NSIA Banque', 'bank_account' => 'CI092 01001',
        ])]);
        $invoice = CustomInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'reference' => 'FC-20260909-0002', 'client_name' => 'Éditions du Plateau',
            'client_email' => 'contact@plateau.ci', 'quote_date' => now()->toDateString(), 'payment_terms' => 'Paiement à réception',
            'items' => [['item_name' => 'Impression du roman', 'item_category' => 'livre', 'unit' => 'exemplaire', 'quantity' => 300, 'price' => 2400,
                'book_type' => 'livre', 'book_format' => 'a5_14x21', 'page_count' => 240]],
            'global_services' => [['key' => 'redaction_statuts', 'label' => 'Rédaction des statuts', 'price' => 150000]],
            'total_ht' => 870000, 'total_discount' => 70000, 'subtotal_after_discount' => 800000, 'tax_rate' => 18, 'tax_amount' => 144000,
            'total_ttc' => 944000, 'paid_amount' => 0, 'credited_amount' => 0, 'status' => 'draft', 'tax_regime' => 'standard',
        ]);

        // L'ancien document omettait les forfaits (le total HT ne se recoupait pas), la remise nette et le taux de TVA.
        $this->actingAs($this->makeAdmin($alpha))->get(route('admin.commercial.custom-invoice.print', $invoice))
            ->assertOk()
            ->assertSee('FACTURE')
            ->assertSee('N° FC-20260909-0002')
            ->assertSee('Rédaction des statuts')
            ->assertSee('A5 14 × 21')
            ->assertSeeInOrder(['Total HT brut', money(870000), 'Remise', money(70000), 'Total HT', money(800000),
                'TVA (18 %)', money(144000), 'Total TTC', money(944000), 'Reste à payer', money(944000)])
            ->assertSee('NSIA Banque, compte n° CI092 01001')
            ->assertSee('RCCM : CI-ABJ-2019-B-12345')
            // Un brouillon imprimé ne peut pas passer pour la facture définitive.
            ->assertSee('Brouillon, non émise')
            ->assertSee('BROUILLON')
            ->assertDontSee(' CFA</');
    }

    public function test_la_facture_personnalisee_emise_detaille_ses_avoirs_et_le_reste_a_payer(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->actingAs($admin);
        $invoice = CustomInvoice::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'reference' => 'FC-20260816-0002', 'client_name' => 'Librairie Carrefour',
            'quote_date' => now()->subDays(20)->toDateString(), 'issued_at' => now()->subDays(20),
            'items' => [['item_name' => 'Conseil en merchandising', 'unit' => 'jour', 'quantity' => 3, 'price' => 150000]],
            'total_ht' => 450000, 'subtotal_after_discount' => 450000, 'tax_rate' => 18, 'tax_amount' => 81000,
            'total_ttc' => 531000, 'paid_amount' => 200000, 'credited_amount' => 0, 'status' => 'draft', 'tax_regime' => 'standard',
        ]);
        app(InvoiceIntegrityService::class)->credit($invoice, 177000, 'Une journée annulée par le client.', $admin->id);

        $this->get(route('admin.commercial.custom-invoice.print', $invoice))
            ->assertOk()
            ->assertSee('Partiellement payée')
            ->assertSee('Une journée annulée par le client.')
            // 531 000 − 177 000 d'avoir = 354 000 dus ; 200 000 payés.
            ->assertSeeInOrder(['Total TTC', money(531000), 'Avoirs', money(177000), 'Net dû', money(354000),
                'Déjà payé', money(200000), 'Reste à payer', money(154000)])
            ->assertDontSee('BROUILLON');
    }

    public function test_la_proforma_imprimee_detaille_remises_par_ligne_tva_et_accord(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $client = CommercialClient::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Société Générale CI', 'tax_id' => '8801234 B', 'address' => 'Plateau',
        ]);
        $this->actingAs($admin)->post(route('admin.commercial.proforma.store'), [
            'client_id' => $client->id, 'creation_date' => '2026-09-11', 'due_date' => now()->addDays(20)->toDateString(),
            'tax_rate_id' => $this->rate($alpha, 'TVA18')->id, 'subject' => 'Audit',
            'lines' => [
                ['type' => 'service', 'item_name' => 'Audit', 'quantity' => 1, 'unit_price' => 1000000, 'discount' => 10, 'discount_type' => 'percent'],
                ['type' => 'product', 'item_name' => 'Licence', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ])->assertSessionHasNoErrors();
        $proforma = CommercialProforma::withoutGlobalScope('entreprise')->firstOrFail();

        // L'ancien document : pas de numéro, pas de mentions légales, « XOF » en dur, remises invisibles.
        $this->actingAs($admin)->get(route('admin.commercial.proforma.print', $proforma))
            ->assertOk()
            ->assertSee('PROFORMA')
            ->assertSee('N° PRO-20260911-0001')
            ->assertSee('Compte contribuable : 8801234 B')
            ->assertSeeInOrder(['Audit', '10 %', money(900000), 'Licence', money(100000)])
            ->assertSeeInOrder(['Total HT brut', money(1100000), 'Remises', money(100000), 'Total HT', money(1000000),
                'TVA (18 %)', money(180000), 'Total TTC', money(1180000)])
            ->assertSee('ce document ne vaut pas facture')
            ->assertSee('Bon pour accord')
            ->assertDontSee(' XOF</');

        $proforma->update(['due_date' => now()->subDay()->toDateString(), 'creation_date' => now()->subDays(30)->toDateString()]);
        $this->actingAs($admin)->get(route('admin.commercial.proforma.print', $proforma))
            ->assertSee('Expirée')
            ->assertDontSee('Bon pour accord');
    }

    public function test_une_proforma_sans_remise_n_affiche_pas_la_colonne_et_nomme_l_exoneration(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $client = CommercialClient::withoutGlobalScope('entreprise')->create(['entreprise_id' => $alpha->id, 'name' => 'Ambassade']);
        $this->actingAs($admin)->post(route('admin.commercial.proforma.store'), [
            'client_id' => $client->id, 'creation_date' => now()->toDateString(), 'tax_rate_id' => $this->rate($alpha, 'EXO')->id,
            'lines' => [['type' => 'service', 'item_name' => 'Accompagnement fiscal', 'quantity' => 3, 'unit_price' => 520000]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('admin.commercial.proforma.print', CommercialProforma::withoutGlobalScope('entreprise')->firstOrFail()))
            ->assertOk()
            ->assertSee('TVA (exonéré)')
            ->assertSeeInOrder(['Total TTC', money(1560000)])
            ->assertDontSee('<th class="num">Remise</th>', false);
    }
}
