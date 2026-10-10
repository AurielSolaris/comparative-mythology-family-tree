<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Symmetric relations are stored with the lower id first; by default the
     * tree shows entity_id on the left. When sides_swapped is true the
     * related entity is shown on the left instead.
     */
    public function up(): void
    {
        Schema::table('entity_relations', function (Blueprint $table) {
            $table->boolean('sides_swapped')->default(false)->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_relations', function (Blueprint $table) {
            $table->dropColumn('sides_swapped');
        });
    }
};
