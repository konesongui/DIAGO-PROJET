<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Attribution des numeros de document.
 *
 * Un numero de facture doit etre unique et continu. Le comptage des documents
 * existants ne le garantit pas : deux utilisateurs qui valident au meme instant
 * lisent le meme total et obtiennent le meme numero. Ce service verrouille le
 * compteur le temps de l'incrementer, ce qui serialise les demandes
 * concurrentes.
 */
class DocumentNumberService
{
    /** Formats par type de document : prefixe et granularite du compteur. */
    private const FORMATS = [
        'custom_invoice' => ['prefix' => 'FC', 'period' => 'Ymd', 'pad' => 4],
        'quote' => ['prefix' => 'DEV', 'period' => 'Ymd', 'pad' => 4],
        'proforma' => ['prefix' => 'PRO', 'period' => 'Ymd', 'pad' => 4],
        'order' => ['prefix' => 'BC', 'period' => 'Ymd', 'pad' => 4],
        'invoice' => ['prefix' => 'FAC', 'period' => 'Y', 'pad' => 5],
        'credit_note' => ['prefix' => 'AV', 'period' => 'Y', 'pad' => 5],
        'pos_sale' => ['prefix' => 'POS', 'period' => 'Ymd', 'pad' => 4],
        'journal_entry' => ['prefix' => 'EC', 'period' => 'Y', 'pad' => 6],
    ];

    /**
     * Reserve le prochain numero et retourne la reference complete.
     * Doit etre appelee dans la transaction qui enregistre le document, pour
     * qu'un echec libere le numero au lieu de creer un trou.
     */
    public function next(int $entrepriseId, string $documentType, ?\DateTimeInterface $date = null): string
    {
        $format = self::FORMATS[$documentType] ?? ['prefix' => strtoupper(substr($documentType, 0, 3)), 'period' => 'Ymd', 'pad' => 4];
        $date = $date ?: now();
        $period = $date->format($format['period']);

        $number = $this->reserve($entrepriseId, $documentType, $period);

        return sprintf('%s-%s-%s', $format['prefix'], $period, str_pad((string) $number, $format['pad'], '0', STR_PAD_LEFT));
    }

    /** Incremente le compteur sous verrou et retourne la nouvelle valeur. */
    private function reserve(int $entrepriseId, string $documentType, string $period): int
    {
        $keys = ['entreprise_id' => $entrepriseId, 'document_type' => $documentType, 'period' => $period];

        return DB::transaction(function () use ($keys) {
            $row = DB::table('document_sequences')->where($keys)->lockForUpdate()->first();

            if (! $row) {
                try {
                    DB::table('document_sequences')->insert($keys + [
                        'last_number' => 1, 'created_at' => now(), 'updated_at' => now(),
                    ]);

                    return 1;
                } catch (QueryException $e) {
                    // Une autre requete vient de creer la ligne : on la reprend
                    // sous verrou plutot que d'echouer.
                    $row = DB::table('document_sequences')->where($keys)->lockForUpdate()->first();
                }
            }

            $next = (int) $row->last_number + 1;
            DB::table('document_sequences')->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });
    }
}
