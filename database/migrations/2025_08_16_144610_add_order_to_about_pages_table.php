<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('about_pages', function (Blueprint $table): void {
            $table->integer('order')->default(0)->after('section');
        });
    }

    public function down(): void
    {
        Schema::table('about_pages', function (Blueprint $table): void {
            $table->dropColumn('order');
        });
    }
};
