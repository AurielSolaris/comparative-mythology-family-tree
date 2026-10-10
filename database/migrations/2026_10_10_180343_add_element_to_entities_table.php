<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Free text rather than a fixed list, since traditions classify
     * differently (fire, water, storm, sky, wood, metal…).
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('element')->nullable()->after('gender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn('element');
        });
    }
};
