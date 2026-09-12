<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use App\Models\Concerns\HasTaxLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CustomInvoice extends Model
{
    use BelongsToEntreprise;
    use HasTaxLabel;

    protected $fillable = [
        'entreprise_id',
        'created_by_user_id',
        'reference',
        'client_name',
        'client_phone',
        'client_email',
        'quote_date',
        'valid_until',
        'payment_terms',
        'delivery_terms',
        'delivery_location',
        'payment_method',
        'cash_account_id',
        'bank_account_id',
        'cash_payment_method',
        'subject',
        'items',
        'global_services',
        'total_ht',
        'total_discount',
        'subtotal_after_discount',
        'tax_amount',
        'tax_rate_id',
        'tax_rate',
        'tax_regime',
        'issued_at',
        'credited_amount',
        'total_ttc',
        'paid_amount',
        'paid_at',
        'status',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'valid_until' => 'date',
        'items' => 'array',
        'global_services' => 'array',
        'total_ht' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'subtotal_after_discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'credited_amount' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Règlements datés, dans l'ordre où ils ont été reçus. */
    public function payments(): MorphMany
    {
        return $this->morphMany(InvoicePayment::class, 'payable')->orderBy('paid_on')->orderBy('id');
    }

    public function creditNotes(): MorphMany
    {
        return $this->morphMany(CreditNote::class, 'creditable')->latest();
    }

    /** Montant dû : le TTC, net des avoirs émis. */
    public function amountDue(): float
    {
        return max(0, (float) $this->total_ttc - (float) $this->credited_amount);
    }

    public function remainingAmount(): float
    {
        return max(0, $this->amountDue() - (float) $this->paid_amount);
    }

    /**
     * État lisible, déduit des montants et des dates : le statut enregistré ne
     * distingue que brouillon et payée.
     */
    public function state(): string
    {
        if ((float) $this->total_ttc > 0 && $this->amountDue() <= 0) {
            return 'cancelled';
        }
        if ($this->remainingAmount() <= 0) {
            return 'paid';
        }
        if ((float) $this->paid_amount > 0) {
            return 'partial';
        }

        return $this->issued_at ? 'issued' : 'draft';
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> libellé, ton du badge, icône, libellé d'onglet */
    public static function states(): array
    {
        return [
            'draft' => ['Brouillon', 'neutral', 'bi-pencil', 'Brouillons'],
            'issued' => ['Émise, impayée', 'danger', 'bi-exclamation-circle', 'Impayées'],
            'partial' => ['Partiellement payée', 'warning', 'bi-hourglass-split', 'Partielles'],
            'paid' => ['Payée', 'success', 'bi-check-lg', 'Payées'],
            'cancelled' => ['Annulée par avoir', 'neutral', 'bi-x-circle', 'Annulées'],
        ];
    }

    /**
     * Lignes au format des documents imprimés (print/item-lines) : les lignes
     * libres, puis les forfaits, qui font aussi partie du total HT.
     */
    public function documentLines(): array
    {
        $categories = self::itemOptions()['item_category'];
        $lines = collect($this->items ?? [])->map(fn (array $item) => [
            'item_name' => $item['item_name'] ?? 'Article',
            'category_name' => ($category = $item['item_category'] ?? null) && $category !== 'autre' ? ($categories[$category] ?? $category) : null,
            'unit' => ($item['unit'] ?? '') ?: '—',
            'quantity' => (float) ($item['quantity'] ?? 0),
            'unit_price' => (float) ($item['price'] ?? 0),
            'specs' => self::itemSpecs($item),
        ]);
        $forfaits = collect($this->global_services ?? [])->map(fn (array $service) => [
            'item_name' => $service['label'] ?? 'Forfait',
            'category_name' => 'Forfait',
            'unit' => 'forfait',
            'quantity' => 1,
            'unit_price' => (float) ($service['price'] ?? 0),
        ]);

        return $lines->concat($forfaits)->values()->all();
    }

    /** Choix proposés par l'éditeur pour une ligne : les codes enregistrés et leurs libellés. */
    public static function itemOptions(): array
    {
        return [
            'item_category' => ['impression' => 'Impression', 'livre' => 'Livre', 'brochure' => 'Brochure', 'catalogue' => 'Catalogue', 'autre' => 'Autre'],
            'book_type' => ['' => 'Sélectionner…', 'livre' => 'Livre', 'bloc_note' => 'Bloc-notes', 'planner' => 'Planner', 'revue' => 'Revue', 'catalogue' => 'Catalogue', 'brochure' => 'Brochure', 'autre' => 'Autre'],
            'paper_type' => ['bouffant_creme' => 'Bouffant crème 80 g', 'offset_blanc' => 'Offset blanc 80 g', 'couche_120g' => 'Couché 120 g', 'autre' => 'Autre'],
            'printing_type' => ['noir_blanc' => 'Noir et blanc', 'couleur' => 'Couleur'],
            'cover_type' => ['couche_300g' => 'Couché 300 g', 'rigide_cartonnee' => 'Rigide cartonnée'],
            'book_format' => ['poche_11x18' => 'Poche 11 × 18', 'digest_12x19' => 'Digest 12,5 × 19,5', 'a5_14x21' => 'A5 14 × 21', 'royal_16x24' => 'Royal 16 × 24', 'autre' => 'Autre'],
            'lamination' => ['brillant' => 'Brillant', 'mat' => 'Mat', 'aucun' => 'Aucun'],
            'binding_type' => ['dos_carre_colle' => 'Dos carré collé', 'points_metalliques' => 'Points métalliques', 'spirale_metallique' => 'Spirale métallique', 'autre' => 'Autre'],
        ];
    }

    /**
     * Caractéristiques d'impression d'une ligne, en clair.
     *
     * L'éditeur soumet toujours papier, format, reliure… avec leur valeur par
     * défaut : elles ne décrivent la ligne que si un type de document, un nombre
     * de pages ou une option a été renseigné.
     *
     * @return array<string, string> libellé => valeur
     */
    public static function itemSpecs(array $item): array
    {
        if (blank($item['book_type'] ?? null) && blank($item['page_count'] ?? null) && blank($item['additional_options'] ?? null)) {
            return [];
        }

        $options = self::itemOptions();
        $label = function (string $field, ?string $otherField = null) use ($item, $options): ?string {
            $value = $item[$field] ?? null;
            if (blank($value)) {
                return null;
            }
            if ($value === 'autre' && $otherField && filled($item[$otherField] ?? null)) {
                return $item[$otherField];
            }

            return $options[$field][$value] ?? $value;
        };

        return array_filter([
            'Type' => $label('book_type', 'book_type_other'),
            'Format' => $label('book_format', 'format_other'),
            'Pages' => filled($item['page_count'] ?? null) ? (string) $item['page_count'] : null,
            'Papier' => $label('paper_type', 'paper_type_other'),
            'Impression' => $label('printing_type'),
            'Couverture' => $label('cover_type'),
            'Pelliculage' => $label('lamination'),
            'Reliure' => $label('binding_type', 'binding_other'),
            'Options' => filled($item['additional_options'] ?? null) ? $item['additional_options'] : null,
        ], fn ($value) => filled($value));
    }
}
