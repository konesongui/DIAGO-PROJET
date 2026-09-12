<?php

namespace App\Services;

use App\Models\InventoryAudit;
use App\Models\StockEntry;
use App\Models\StockEntryLine;
use App\Models\StockExit;
use App\Models\StockExitLine;
use App\Models\StockInventory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Stock disponible et sorties par article.
 *
 * Chaque ligne de réception est un lot, rattaché à son fournisseur. Une sortie
 * porte sur un article et puise dans ses lots du plus ancien au plus récent
 * (premier entré, premier sorti) : on peut sortir en une fois une quantité
 * répartie sur plusieurs réceptions, et chaque unité sortie reste reliée au
 * lot, donc au fournisseur, dont elle vient.
 */
class StockService
{
    /** Clé d'un article : même désignation, même référence, même unité. */
    public static function key(?string $designation, ?string $article, ?string $unit): string
    {
        return mb_strtolower(trim((string) $designation) . '|' . trim((string) $article) . '|' . trim((string) $unit));
    }

    /**
     * Lots encore en stock, du plus ancien au plus récent.
     *
     * @return Collection<int, array{line: StockEntryLine, remaining: float}>
     */
    public function lots(int $entrepriseId, bool $withEmpty = false): Collection
    {
        $used = StockExitLine::whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $entrepriseId))
            ->selectRaw('stock_entry_line_id, SUM(quantity) AS quantity')->groupBy('stock_entry_line_id')
            ->pluck('quantity', 'stock_entry_line_id');

        return StockEntryLine::with('stockEntry')
            ->whereHas('stockEntry', fn ($query) => $query->where('entreprise_id', $entrepriseId))
            ->get()
            ->sortBy(fn (StockEntryLine $line) => $line->stockEntry->entry_date->format('Y-m-d') . sprintf('%010d', $line->id))
            ->map(fn (StockEntryLine $line) => ['line' => $line, 'remaining' => round((float) $line->quantity - (float) ($used[$line->id] ?? 0), 3)])
            ->filter(fn (array $lot) => $withEmpty || $lot['remaining'] > 0)
            ->values();
    }

    /** Seuil sous lequel un article est signalé en stock faible (celui des notifications). */
    public const LOW_STOCK = 5;

    /**
     * État du stock par article : reçu, sorti, disponible, valeur restante au
     * prix d'achat de chaque lot, bénéfice potentiel, fournisseurs, lots et sorties.
     */
    public function status(int $entrepriseId): Collection
    {
        $exits = StockExitLine::with(['stockExit.delivery', 'entryLine'])
            ->whereHas('entryLine.stockEntry', fn ($query) => $query->where('entreprise_id', $entrepriseId))
            ->get()
            ->groupBy(fn (StockExitLine $line) => self::key($line->entryLine?->designation ?? $line->designation, $line->entryLine?->article ?? $line->article, $line->entryLine?->unit ?? $line->unit));

        return $this->lots($entrepriseId, true)
            ->groupBy(fn (array $lot) => self::key($lot['line']->designation, $lot['line']->article, $lot['line']->unit))
            ->map(function (Collection $lots, string $key) use ($exits) {
                $first = $lots->first()['line'];
                $latest = $lots->last()['line'];
                $received = round($lots->sum(fn ($lot) => (float) $lot['line']->quantity), 3);
                $available = round($lots->sum('remaining'), 3);

                return [
                    'key' => $key,
                    'designation' => $first->designation,
                    'article' => $first->article,
                    'unit' => $first->unit,
                    'received' => $received,
                    'exited' => round($received - $available, 3),
                    'available' => $available,
                    'state' => $available <= 0 ? 'out' : ($available <= self::LOW_STOCK ? 'low' : 'ok'),
                    'stock_value' => $lots->sum(fn ($lot) => $lot['remaining'] * (float) $lot['line']->purchase_price),
                    'potential_profit' => $lots->sum(fn ($lot) => $lot['remaining'] * (float) $lot['line']->profit_per_unit),
                    'sale_price' => (float) $latest->purchase_price + (float) $latest->profit_per_unit,
                    'last_entry' => $latest->stockEntry->entry_date,
                    'suppliers' => $lots->map(fn ($lot) => $lot['line']->stockEntry->supplier_name)->filter()->unique()->values()->all(),
                    'lots' => $lots->map(fn ($lot) => [
                        'date' => $lot['line']->stockEntry->entry_date,
                        'supplier' => $lot['line']->stockEntry->supplier_name ?: ($lot['line']->stockEntry->stock_inventory_id ? 'Régularisation d’inventaire' : null),
                        'reference' => $lot['line']->stockEntry->supplier_reference,
                        'received' => (float) $lot['line']->quantity,
                        'remaining' => $lot['remaining'],
                        'price' => (float) $lot['line']->purchase_price,
                    ])->reverse()->values()->all(),
                    // Une sortie qui a entamé plusieurs lots n'apparaît qu'une fois, avec sa quantité totale.
                    'exits' => ($exits[$key] ?? collect())->groupBy('stock_exit_id')
                        ->map(fn (Collection $lines) => [
                            'date' => $lines->first()->stockExit?->exit_date,
                            'destination' => $lines->first()->stockExit?->originLabel() ?? '—',
                            'quantity' => round($lines->sum(fn ($line) => (float) $line->quantity), 3),
                            'order' => $lines->first()->stockExit?->exit_date?->format('Y-m-d') . sprintf('%010d', $lines->first()->stock_exit_id),
                        ])->sortByDesc('order')->values()->all(),
                ];
            })
            ->sortBy('designation', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Articles disponibles, lots regroupés.
     *
     * @return Collection<string, array{key: string, designation: string, article: ?string, unit: ?string, available: float, lots: array}>
     */
    public function articles(int $entrepriseId): Collection
    {
        return $this->lots($entrepriseId)
            ->groupBy(fn (array $lot) => self::key($lot['line']->designation, $lot['line']->article, $lot['line']->unit))
            ->map(fn (Collection $lots, string $key) => [
                'key' => $key,
                'designation' => $lots->first()['line']->designation,
                'article' => $lots->first()['line']->article,
                'unit' => $lots->first()['line']->unit,
                'available' => round($lots->sum('remaining'), 3),
                'lots' => $lots->map(fn (array $lot) => ['remaining' => $lot['remaining'], 'price' => (float) $lot['line']->purchase_price])->values()->all(),
            ])
            ->sortBy('designation', SORT_NATURAL | SORT_FLAG_CASE);
    }

    /**
     * Répartit une quantité sur les lots d'un article, du plus ancien au plus récent.
     *
     * @param  callable(StockEntryLine): bool  $matches  lots de l'article voulu
     * @param  array<int, float>  $taken  quantités déjà prises dans cette même sortie, par lot (mises à jour)
     * @param  string|null  $hint  conseil ajouté au message de refus
     * @return array<int, array> lignes de sortie (une par lot entamé)
     *
     * @throws ValidationException si le stock de l'article ne suffit pas
     */
    public function allocate(Collection $lots, callable $matches, float $quantity, string $label, string $errorKey, array &$taken, ?string $hint = null): array
    {
        $candidates = $lots->filter(fn (array $lot) => $matches($lot['line']));
        $available = round($candidates->sum(fn (array $lot) => $lot['remaining'] - ($taken[$lot['line']->id] ?? 0)), 3);
        if ($candidates->isEmpty() || $quantity > $available + 0.0005) {
            $message = $candidates->isEmpty()
                ? "Stock insuffisant ou article introuvable : {$label}."
                : "Stock insuffisant pour {$label} : " . rtrim(rtrim(number_format($available, 3, ',', ' '), '0'), ',') . ' disponible(s), ' . rtrim(rtrim(number_format($quantity, 3, ',', ' '), '0'), ',') . ' demandé(s).';
            throw ValidationException::withMessages([$errorKey => $message . ($hint ? ' ' . $hint : '')]);
        }

        $lines = [];
        $left = $quantity;
        foreach ($candidates as $lot) {
            $line = $lot['line'];
            $free = round($lot['remaining'] - ($taken[$line->id] ?? 0), 3);
            if ($free <= 0) {
                continue;
            }
            $take = min($free, $left);
            $taken[$line->id] = ($taken[$line->id] ?? 0) + $take;
            $lines[] = [
                'stock_entry_line_id' => $line->id, 'designation' => $line->designation, 'article' => $line->article, 'unit' => $line->unit,
                'quantity' => $take, 'unit_price' => (float) $line->purchase_price, 'total_value' => $take * (float) $line->purchase_price,
            ];
            $left = round($left - $take, 3);
            if ($left <= 0) {
                break;
            }
        }

        return $lines;
    }

    /**
     * Valide un inventaire : chaque article compté est comparé au stock
     * théorique du moment et l'écart est passé au stock. Un manquant sort des
     * lots les plus anciens (« écart d'inventaire ») ; un surplus entre au
     * dernier prix d'achat (« régularisation d'inventaire »).
     *
     * @param  array<string, float>  $counted  clé de l'article => quantité comptée
     */
    public function applyInventory(int $entrepriseId, string $date, ?string $note, array $counted, ?int $userId): StockInventory
    {
        $status = $this->status($entrepriseId)->keyBy('key');

        return DB::transaction(function () use ($entrepriseId, $date, $note, $counted, $userId, $status) {
            $label = 'Inventaire du ' . Carbon::parse($date)->format('d/m/Y');
            $inventory = StockInventory::create([
                'entreprise_id' => $entrepriseId, 'inventoried_at' => $date, 'note' => $note,
                'counted_items' => count($counted), 'counted_by_user_id' => $userId,
            ]);
            $lots = $this->lots($entrepriseId, true);
            $taken = [];
            $exitLines = [];
            $entryLines = [];
            foreach ($counted as $key => $quantity) {
                $item = $status[$key];
                $variance = round($quantity - $item['available'], 3);
                InventoryAudit::create([
                    'entreprise_id' => $entrepriseId, 'stock_inventory_id' => $inventory->id,
                    'designation' => $item['designation'], 'article' => $item['article'], 'unit' => $item['unit'],
                    'theoretical_quantity' => $item['available'], 'actual_quantity' => $quantity, 'variance' => $variance,
                    'audited_at' => $date, 'audited_by_user_id' => $userId,
                ]);
                $sameArticle = fn ($line) => self::key($line->designation, $line->article, $line->unit) === $key;
                if ($variance < 0) {
                    array_push($exitLines, ...$this->allocate($lots, $sameArticle, -$variance, $item['designation'], 'items', $taken));
                } elseif ($variance > 0) {
                    $latest = $lots->filter(fn ($lot) => $sameArticle($lot['line']))->last()['line'];
                    $entryLines[] = [
                        'designation' => $item['designation'], 'article' => $item['article'], 'unit' => $item['unit'], 'quantity' => $variance,
                        'purchase_price' => (float) $latest->purchase_price, 'profit_per_unit' => (float) $latest->profit_per_unit,
                        'total_purchase' => $variance * (float) $latest->purchase_price, 'total_profit' => $variance * (float) $latest->profit_per_unit,
                    ];
                }
            }
            if ($exitLines) {
                $exit = StockExit::create([
                    'entreprise_id' => $entrepriseId, 'stock_inventory_id' => $inventory->id, 'reason' => 'inventory', 'note' => $label,
                    'exit_date' => $date, 'total_value' => collect($exitLines)->sum('total_value'),
                ]);
                $exit->lines()->createMany($exitLines);
            }
            if ($entryLines) {
                $entry = StockEntry::create([
                    'entreprise_id' => $entrepriseId, 'stock_inventory_id' => $inventory->id, 'supplier_reference' => $label,
                    'entry_date' => $date, 'total_purchase' => collect($entryLines)->sum('total_purchase'), 'total_profit' => collect($entryLines)->sum('total_profit'),
                ]);
                $entry->lines()->createMany($entryLines);
            }
            $inventory->update([
                'variance_items' => count(array_unique(array_merge(array_column($exitLines, 'designation'), array_column($entryLines, 'designation')))),
                'shortage_value' => collect($exitLines)->sum('total_value'),
                'surplus_value' => collect($entryLines)->sum('total_purchase'),
            ]);

            return $inventory;
        });
    }
}
