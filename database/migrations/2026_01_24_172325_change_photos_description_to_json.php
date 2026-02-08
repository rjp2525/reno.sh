<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE photos SET description = NULL');

        DB::statement('ALTER TABLE photos ALTER COLUMN description TYPE jsonb USING NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE photos ALTER COLUMN description TYPE text USING description::text');
    }
};
