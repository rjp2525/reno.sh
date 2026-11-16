<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ExperienceEndDateScope implements Scope
{
    /** Current roles (no end date) first, then most recently ended. Portable across Postgres, MySQL and SQLite. */
    public function apply(Builder $builder, Model $model): void
    {
        $end = $builder->getQuery()->getGrammar()->wrap($model->qualifyColumn('end'));

        $builder->orderByRaw("case when {$end} is null then 0 else 1 end")->orderByDesc('end');
    }
}
