<?php

use App\Casts\HexColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Colors saved without a leading "#" (e.g. "15803D") don't render; add it. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['photo_categories', 'blog_categories', 'technologies'] as $table) {
            DB::table($table)->whereNotNull('color')->where('color', 'not like', '#%')->orderBy('id')
                ->each(fn ($row) => DB::table($table)->where('id', $row->id)->update(['color' => HexColor::normalize($row->color)]));
        }
    }

    public function down(): void
    {
        // Nothing to undo: "#RRGGBB" is the correct form.
    }
};
