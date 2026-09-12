<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

/**
 * Appel entrant ou sortant consigné au standard.
 *
 * La durée n'a de sens que pour un appel abouti : un appel manqué ou encore
 * à passer n'en porte pas.
 */
class AdminCall extends Model
{
    use BelongsToEntreprise;

    public const DIRECTIONS = ['incoming' => 'Entrant', 'outgoing' => 'Sortant'];

    public const STATES = ['planned' => 'À passer', 'completed' => 'Abouti', 'missed' => 'Manqué'];

    protected $table = 'admin_calls';
    protected $guarded = ['id'];
    protected $casts = ['call_at' => 'datetime'];

    public function directionLabel(): string
    {
        return self::DIRECTIONS[$this->direction] ?? self::DIRECTIONS['incoming'];
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->status] ?? self::STATES['planned'];
    }

    /** Appel à passer dont l'heure est dépassée. */
    public function isOverdue(): bool
    {
        return $this->status === 'planned' && $this->call_at && $this->call_at->isPast();
    }

    /** Un appel manqué ou à passer n'a pas de durée. */
    public function effectiveDuration(): ?int
    {
        return $this->status === 'completed' ? $this->duration : null;
    }
}
