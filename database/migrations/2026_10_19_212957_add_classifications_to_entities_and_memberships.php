<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An entity's classification in its primary religion lives on the
     * entity; in each additional religion, on the membership.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('classification')->nullable()->after('religion_id');
            $table->string('classification_term')->nullable()->after('classification');
        });

        Schema::table('entity_religion', function (Blueprint $table) {
            $table->string('classification')->nullable();
            $table->string('classification_term')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_religion', function (Blueprint $table) {
            $table->dropColumn(['classification', 'classification_term']);
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn(['classification', 'classification_term']);
        });
    }
};
