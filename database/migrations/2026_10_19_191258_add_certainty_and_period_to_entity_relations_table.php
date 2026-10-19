<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How certain a relation is (see App\Enums\RelationCertainty) and when
     * it held, e.g. “Gupta period” or “2nd c. BCE”. Both optional.
     */
    public function up(): void
    {
        Schema::table('entity_relations', function (Blueprint $table) {
            $table->string('certainty')->nullable()->after('religion_id');
            $table->string('period')->nullable()->after('certainty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_relations', function (Blueprint $table) {
            $table->dropColumn(['certainty', 'period']);
        });
    }
};
