<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Filament's database notifications query `data->>'format'`. MySQL and SQLite run JSON
 * functions on a text column, Postgres doesn't, so make the column json there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table notifications alter column data type json using data::json');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table notifications alter column data type text');
        }
    }
};
