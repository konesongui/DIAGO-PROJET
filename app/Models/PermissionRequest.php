<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class PermissionRequest extends Model
{
    use BelongsToEntreprise;

    protected $fillable = [
        'entreprise_id', 'employee_id', 'type', 'start_date', 'end_date',
        'reason', 'status', 'reviewed_by', 'review_comment',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    /** Motifs proposés : l'ancien champ libre rendait tout regroupement impossible. */
    public static function types(): array
    {
        return [
            'medical' => 'Rendez-vous médical',
            'family' => 'Événement familial',
            'administrative' => 'Démarche administrative',
            'training' => 'Formation ou examen',
            'other' => 'Autre motif',
        ];
    }

    /** Libellé du motif, y compris pour les anciennes saisies libres. */
    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? ($this->type ?: 'Autre motif');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
