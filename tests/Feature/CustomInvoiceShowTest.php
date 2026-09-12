<?php

namespace Tests\Feature;

use App\Models\CreditNote;
use App\Models\CustomInvoice;
use App\Models\Entreprise;
use App\Services\InvoiceIntegrityService;
use App\Services\PaymentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Fiche d'une facture personnalisée : lignes complètes, action selon l'état,
 * règlements datés, avoirs, duplication et envois au client.
 */
class CustomInvoiceShowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function makeInvoice(Entreprise $entreprise, array $attributes = []): CustomInvoice
    {
        return CustomInvoice::withoutGlobalScope('entreprise')->create($attributes + [
            'entreprise_id' => $entreprise->id, 'reference' => 'FC-' . uniqid(), 'client_name' => 'Éditions du Plateau',
            'client_phone' => '07 11 22 33 44', 'quote_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [['item_name' => 'Mise en page', 'item_category' => 'autre', 'unit' => 'page', 'quantity' => 100, 'price' => 10000]],
            'total_ht' => 1000000, 'subtotal_after_discount' => 1000000, 'tax_rate' => 18, 'tax_amount' => 180000,
            'total_ttc' => 1180000, 'paid_amount' => 0, 'credited_amount' => 0, 'status' => 'draft', 'tax_regime' => 'standard',
        ]);
    }

    public function test_la_fiche_montre_les_forfaits_les_caracteristiques_et_le_detail_des_totaux(): void
    {
        $alpha = $this->makeCompany('alpha');
        $invoice = $this->makeInvoice($alpha, [
            'items' => [['item_name' => 'Impression du roman', 'item_category' => 'livre', 'unit' => 'exemplaire', 'quantity' => 300, 'price' => 2400,
                'book_type' => 'livre', 'book_format' => 'a5_14x21', 'page_count' => 240, 'paper_type' => 'autre', 'paper_type_other' => 'Ivoire 90 g',
                'printing_type' => 'noir_blanc', 'cover_type' => 'couche_300g', 'lamination' => 'mat', 'binding_type' => 'dos_carre_colle']],
            'global_services' => [['key' => 'redaction_statuts', 'label' => 'Rédaction des statuts', 'price' => 150000]],
            'total_ht' => 870000, 'total_discount' => 70000, 'subtotal_after_discount' => 800000, 'tax_amount' => 144000, 'total_ttc' => 944000,
        ]);

        $this->actingAs($this->makeAdmin($alpha))
            ->get(route('admin.commercial.custom-invoice.show', $invoice))
            ->assertOk()
            // Le forfait, compté dans le total HT, figure maintenant parmi les lignes.
            ->assertSee('Rédaction des statuts')
            ->assertSee('A5 14 × 21')
            ->assertSee('Ivoire 90 g')
            ->assertSee('Total HT net')
            ->assertSee('TVA (18 %)')
            ->assertSee(money(944000))
            ->assertDontSee(' CFA</');
    }

    public function test_une_facture_payee_mais_non_emise_peut_encore_etre_emise(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $invoice = $this->makeInvoice($alpha, ['paid_amount' => 100000]);

        // Un paiement verrouille la facture : l'ancien écran masquait alors « Émettre ».
        $this->actingAs($admin)->get(route('admin.commercial.custom-invoice.show', $invoice))
            ->assertOk()
            ->assertSee('Paiement reçu, facture non émise')
            ->assertSee(route('admin.commercial.custom-invoice.issue', $invoice))
            ->assertDontSee(route('admin.commercial.custom-invoice.edit', $invoice));

        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.issue', $invoice))->assertSessionHasNoErrors();
        $this->assertNotNull($invoice->fresh()->issued_at);
    }

    public function test_les_reglements_dates_et_les_avoirs_sont_listes(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $this->actingAs($admin);
        $invoice = $this->makeInvoice($alpha, ['issued_at' => now()->subDays(10), 'paid_amount' => 500000]);
        app(PaymentRecorder::class)->record($invoice, 300000, 'bank', now()->subDays(8), $admin->id);
        app(InvoiceIntegrityService::class)->credit($invoice, 118000, 'Dix pages facturées en trop.', $admin->id);

        $this->get(route('admin.commercial.custom-invoice.show', $invoice->fresh()))
            ->assertOk()
            ->assertSee('Reçu le ' . now()->subDays(8)->format('d/m/Y'))
            // 200 000 payés sans règlement daté correspondant.
            ->assertSee('Paiement non daté')
            ->assertSee(money(200000))
            ->assertSee('Dix pages facturées en trop.')
            // 1 180 000 − 118 000 d'avoir − 500 000 payés.
            ->assertSee(money(562000))
            ->assertSee('data-payment-invoice="' . $invoice->id . '"', false);
    }

    public function test_l_avoir_prend_la_devise_de_l_entreprise_et_ses_erreurs_sont_en_francais(): void
    {
        $alpha = $this->makeCompany('alpha', 'EUR');
        $admin = $this->makeAdmin($alpha);
        $invoice = $this->makeInvoice($alpha, ['issued_at' => now(), 'total_ttc' => 1180.00]);

        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.credit', $invoice), ['credit_form' => 1, 'full' => '0', 'amount' => 100, 'reason' => 'abc'])
            ->assertSessionHasErrors(['reason' => 'Le motif doit contenir au moins 5 caractères.']);

        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.credit', $invoice), ['full' => '0', 'amount' => 100, 'reason' => 'Remise accordée après coup.'])
            ->assertSessionHasNoErrors();

        // La facture personnalisée n'a pas de devise propre : l'avoir était enregistré en XOF.
        $this->assertSame('EUR', CreditNote::withoutGlobalScope('entreprise')->where('creditable_id', $invoice->id)->value('currency'));
    }

    public function test_dupliquer_une_facture_emise_donne_un_brouillon_vierge_date_du_jour(): void
    {
        $alpha = $this->makeCompany('alpha');
        $invoice = $this->makeInvoice($alpha, [
            'quote_date' => now()->subDays(40)->toDateString(), 'valid_until' => now()->subDays(25)->toDateString(),
            'issued_at' => now()->subDays(40), 'paid_amount' => 1180000, 'credited_amount' => 118000, 'status' => 'paid',
        ]);

        $this->actingAs($this->makeAdmin($alpha))->post(route('admin.commercial.custom-invoice.duplicate', $invoice))->assertRedirect();

        $copy = CustomInvoice::withoutGlobalScope('entreprise')->where('id', '!=', $invoice->id)->firstOrFail();
        $this->assertNull($copy->issued_at);
        $this->assertSame(0.0, (float) $copy->credited_amount);
        $this->assertSame(0.0, (float) $copy->paid_amount);
        $this->assertSame('draft', $copy->state());
        $this->assertSame(now()->toDateString(), $copy->quote_date->toDateString());
        $this->assertSame(now()->addDays(15)->toDateString(), $copy->valid_until->toDateString());
        $this->assertCount(1, $copy->items);
    }

    public function test_l_envoi_par_email_part_vraiment_et_refuse_sans_adresse(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $withoutEmail = $this->makeInvoice($alpha);
        $withEmail = $this->makeInvoice($alpha, ['client_email' => 'contact@plateau.ci', 'issued_at' => now(), 'paid_amount' => 180000]);

        // L'ancien bouton annonçait « envoyée » sans rien envoyer.
        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.email', $withoutEmail))->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.email', $withEmail))->assertSessionHasNoErrors();
        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $body = $sent->first()->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString(money(1180000), $body);
        $this->assertStringContainsString('Reste à payer : ' . money(1000000), $body);
        $this->assertStringNotContainsString('XOF', $body);

        // Même message sur WhatsApp : devise de l'entreprise, état réel.
        $location = $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.whatsapp', $withEmail))->headers->get('Location');
        $this->assertStringContainsString(rawurlencode('Partiellement payée'), $location);
        $this->assertStringNotContainsString('XOF', rawurldecode($location));
    }
}
