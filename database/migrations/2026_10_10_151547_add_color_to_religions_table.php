<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Hex colour (#rrggbb) chosen by admins; null means one is derived from
     * the religion's slug.
     */
    public function up(): void
    {
        Schema::table('religions', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('religions', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
