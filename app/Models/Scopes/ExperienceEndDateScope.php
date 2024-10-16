<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ExperienceEndDateScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->orderByRaw('end IS NULL DESC')
            ->orderBy('end', 'DESC');
    }
}
