<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

/**
 * Courrier arrivé ou parti, avec sa pièce jointe numérisée.
 *
 * Les états valent pour les deux sens : un courrier au départ se prépare, se
 * traite et s'archive comme un courrier à l'arrivée.
 */
class AdminCorrespondence extends Model
{
    use BelongsToEntreprise;

    public const TYPES = ['incoming' => 'Arrivée', 'outgoing' => 'Départ'];

    public const STATES = [
        'received' => 'À traiter',
        'in_progress' => 'En traitement',
        'processed' => 'Traité',
        'archived' => 'Archivé',
    ];

    protected $table = 'admin_correspondences';
    protected $guarded = ['id'];
    protected $casts = ['received_at' => 'date'];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES['incoming'];
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->status] ?? self::STATES['received'];
    }

    /** Correspondant retenu selon le sens du courrier. */
    public function counterpart(): ?string
    {
        return $this->type === 'outgoing' ? $this->recipient : $this->sender;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['received', 'in_progress'], true);
    }
}
