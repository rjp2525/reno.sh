<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A degree in progress has no graduation date yet. */
    public function up(): void
    {
        Schema::table('education', function (Blueprint $table): void {
            $table->date('graduated')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('education', function (Blueprint $table): void {
            $table->date('graduated')->nullable(false)->change();
        });
    }
};
