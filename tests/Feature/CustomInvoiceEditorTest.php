<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CommercialClient;
use App\Models\CustomInvoice;
use App\Models\InvoicePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCompany;
use Tests\TestCase;

/**
 * Éditeur de facture personnalisée : vrais clients, toutes les lignes,
 * taux de TVA choisi et paiement immédiat réellement encaissé.
 */
class CustomInvoiceEditorTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCompany;

    private function line(string $name, float $quantity, float $price, array $specs = []): array
    {
        return ['item_name' => $name, 'item_category' => 'livre', 'unit' => 'Exemplaire', 'quantity' => $quantity, 'price' => $price] + $specs;
    }

    public function test_l_editeur_propose_les_vrais_clients_et_retient_celui_choisi(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $client = CommercialClient::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Éditions du Plateau', 'phone' => '0707070707', 'email' => 'contact@plateau.ci',
        ]);

        $this->actingAs($admin)->get(route('admin.commercial.custom-invoice.create'))
            ->assertOk()
            ->assertSee('Éditions du Plateau')
            ->assertDontSee('Boutique Kévin');

        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.store'), [
            'customer' => (string) $client->id, 'objet' => 'Roman', 'items' => [$this->line('Impression', 100, 2000)],
        ])->assertSessionHasNoErrors();

        $invoice = CustomInvoice::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();
        $this->assertSame('Éditions du Plateau', $invoice->client_name);
        $this->assertSame('0707070707', $invoice->client_phone);
    }

    public function test_toutes_les_lignes_et_leurs_caracteristiques_sont_conservees_et_reaffichees(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);

        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.store'), [
            'customer' => 'new', 'new_client_name' => 'M. Kouadio', 'items' => [
                $this->line('Roman Lagune', 500, 2400, ['book_type' => 'livre', 'page_count' => 240, 'binding_type' => 'dos_carre_colle']),
                $this->line('Marque-pages', 5, 12000, ['book_type' => 'autre', 'book_type_other' => 'Marque-page']),
            ],
        ])->assertSessionHasNoErrors();

        $invoice = CustomInvoice::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();
        $this->assertCount(2, $invoice->items);
        $this->assertSame('240', (string) $invoice->items[0]['page_count']);
        $this->assertSame('Marque-page', $invoice->items[1]['book_type_other']);

        // L'ancienne vue n'affichait que la première ligne : les deux sont transmises au formulaire.
        $this->actingAs($admin)->get(route('admin.commercial.custom-invoice.edit', $invoice))
            ->assertOk()
            ->assertSee('Roman Lagune')
            ->assertSee('Marque-pages');
    }

    public function test_le_taux_de_tva_choisi_est_applique(): void
    {
        $alpha = $this->makeCompany('alpha');
        $reduced = $this->rate($alpha, 'TVA9');

        $this->actingAs($this->makeAdmin($alpha))->post(route('admin.commercial.custom-invoice.store'), [
            'customer' => 'new', 'new_client_name' => 'Client', 'tax_rate_id' => $reduced->id, 'items' => [$this->line('Brochure', 1, 100000)],
        ])->assertSessionHasNoErrors();

        $invoice = CustomInvoice::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();
        $this->assertSame(9000.0, (float) $invoice->tax_amount);
        $this->assertSame(109000.0, (float) $invoice->total_ttc);
    }

    public function test_un_paiement_a_la_creation_doit_indiquer_ou_l_argent_est_entre(): void
    {
        $alpha = $this->makeCompany('alpha');
        $admin = $this->makeAdmin($alpha);
        $bank = BankAccount::withoutGlobalScope('entreprise')->create([
            'entreprise_id' => $alpha->id, 'name' => 'Compte courant', 'bank_name' => 'NSIA', 'account_number' => '1',
            'account_type' => 'compte_courant', 'current_balance' => 0, 'status' => 'credit',
        ]);
        $base = ['customer' => 'new', 'new_client_name' => 'M. Kouadio', 'items' => [$this->line('Brochure', 1, 100000)], 'paid_amount' => 50000];

        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.store'), $base)->assertSessionHasErrors('payment_channel');
        $this->assertSame(0, CustomInvoice::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->count());

        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.store'), $base + ['payment_channel' => 'bank'])->assertSessionHasErrors('bank_account_id');

        $this->actingAs($admin)->post(route('admin.commercial.custom-invoice.store'), $base + ['payment_channel' => 'bank', 'bank_account_id' => $bank->id])
            ->assertSessionHasNoErrors();
        $invoice = CustomInvoice::withoutGlobalScope('entreprise')->where('entreprise_id', $alpha->id)->firstOrFail();
        $this->assertSame(50000.0, (float) $bank->fresh()->current_balance, 'Le paiement entre en banque.');
        $this->assertSame(50000.0, (float) $invoice->paid_amount);
        $this->assertTrue(InvoicePayment::withoutGlobalScope('entreprise')->where('payable_id', $invoice->id)->where('method', 'bank')->exists());
    }
}
