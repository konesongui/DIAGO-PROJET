<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use BelongsToEntreprise;

    /**
     * Regimes fiscaux reconnus. Le regime, et non la valeur du taux,
     * determine ce qui est declare a l'administration fiscale.
     */
    public const REGIME_STANDARD = 'standard';
    public const REGIME_REDUCED = 'reduced';
    public const REGIME_EXEMPT = 'exempt';
    public const REGIME_EXPORT = 'export';
    public const REGIME_REVERSE_CHARGE = 'reverse_charge';

    protected $fillable = [
        'entreprise_id', 'name', 'code', 'rate', 'regime',
        'effective_from', 'is_default', 'is_active', 'position',
    ];

    protected $casts = [
        'rate' => 'decimal:3',
        'effective_from' => 'date',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    public static function regimes(): array
    {
        return [
            self::REGIME_STANDARD => 'Taux normal',
            self::REGIME_REDUCED => 'Taux réduit',
            self::REGIME_EXEMPT => 'Exonéré',
            self::REGIME_EXPORT => 'Export',
            self::REGIME_REVERSE_CHARGE => 'Autoliquidation',
        ];
    }

    public function regimeLabel(): string
    {
        return self::regimes()[$this->regime] ?? $this->regime;
    }

    /** Un regime sans TVA facturee reste declarable, avec un taux a zero. */
    public function isZeroRated(): bool
    {
        return in_array($this->regime, [
            self::REGIME_EXEMPT,
            self::REGIME_EXPORT,
            self::REGIME_REVERSE_CHARGE,
        ], true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Taux deja entres en vigueur a la date donnee. */
    public function scopeEffectiveOn(Builder $query, $date): Builder
    {
        return $query->where(function (Builder $inner) use ($date) {
            $inner->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('rate');
    }
}
