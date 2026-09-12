<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Document administratif classé : statuts, contrats, registres légaux.
 *
 * Un document sans fichier n'a pas d'objet : le fichier est exigé à la création.
 */
class AdminDocument extends Model
{
    use BelongsToEntreprise;

    public const STATES = ['active' => 'Actif', 'archived' => 'Archivé'];

    protected $table = 'admin_documents';
    protected $guarded = ['id'];
    protected $casts = ['document_date' => 'date'];

    public function stateLabel(): string
    {
        return self::STATES[$this->status] ?? self::STATES['active'];
    }

    public function fileExists(): bool
    {
        return $this->file_path && Storage::disk('public')->exists($this->file_path);
    }

    public function extension(): string
    {
        return mb_strtoupper(pathinfo((string) $this->file_path, PATHINFO_EXTENSION)) ?: 'Fichier';
    }

    /** Taille lisible du fichier, ou null s'il a disparu du disque. */
    public function readableSize(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        $bytes = Storage::disk('public')->size($this->file_path);

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' Mo'
            : max(1, (int) round($bytes / 1024)) . ' ko';
    }
}
