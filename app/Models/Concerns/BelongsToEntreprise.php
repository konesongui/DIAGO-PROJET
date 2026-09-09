<?php

namespace App\Models\Concerns;

use App\Models\Entreprise;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToEntreprise
{
    protected static function bootBelongsToEntreprise(): void
    {
        static::addGlobalScope('entreprise', function (Builder $builder) {
            $entrepriseId = auth()->user()?->entreprise_id;
            if ($entrepriseId) {
                $builder->where($builder->getModel()->getTable() . '.entreprise_id', $entrepriseId);
            }
        });

        static::creating(function ($model) {
            if (!$model->entreprise_id && auth()->user()?->entreprise_id) {
                $model->entreprise_id = auth()->user()->entreprise_id;
            }
        });
    }

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }
}
