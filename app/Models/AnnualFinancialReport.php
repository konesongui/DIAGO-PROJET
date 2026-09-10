<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class AnnualFinancialReport extends Model
{
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id', 'fiscal_year', 'data', 'total_assets', 'total_liabilities',
        'net_result', 'currency', 'generated_at', 'seen_at', 'downloaded_at', 'downloaded_by_user_id',
    ];

    protected $casts = [
        'data' => 'array',
        'total_assets' => 'decimal:2',
        'total_liabilities' => 'decimal:2',
        'net_result' => 'decimal:2',
        'generated_at' => 'datetime',
        'seen_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ];

    /** Tant que le PDF n'est pas telecharge, le bilan reste a lire. */
    public function isUnread(): bool
    {
        return $this->downloaded_at === null;
    }

    public function label(): string
    {
        return 'Bilan financier ' . $this->fiscal_year;
    }
}
