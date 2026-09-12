<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

/**
 * Visiteur reçu dans l'entreprise.
 *
 * L'état n'est pas saisi à la main : il découle de l'arrivée et du départ,
 * sauf pour une visite annulée. Sans cela, un visiteur pouvait être « Sur
 * place » sans heure d'arrivée, ou « Terminé » sans être jamais venu.
 */
class AdminVisitor extends Model
{
    use BelongsToEntreprise;

    public const STATES = [
        'expected' => 'Attendue',
        'inside' => 'Sur place',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ];

    protected $table = 'admin_visitors';
    protected $guarded = ['id'];
    protected $casts = ['check_in_at' => 'datetime', 'check_out_at' => 'datetime'];

    /** Recalcule l'état à partir des heures d'arrivée et de départ. */
    public function syncStatus(): static
    {
        if ($this->status === 'cancelled') {
            return $this;
        }

        if ($this->check_out_at) {
            $this->status = 'completed';
        } elseif ($this->check_in_at && ! $this->check_in_at->isFuture()) {
            $this->status = 'inside';
        } else {
            // Sans arrivée, ou avec une arrivée programmée plus tard : la visite est attendue.
            $this->status = 'expected';
        }

        return $this;
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->status] ?? self::STATES['expected'];
    }

    /** Durée de la visite en minutes, une fois le départ enregistré. */
    public function durationMinutes(): ?int
    {
        if (! $this->check_in_at || ! $this->check_out_at) {
            return null;
        }

        return (int) $this->check_in_at->diffInMinutes($this->check_out_at);
    }
}
