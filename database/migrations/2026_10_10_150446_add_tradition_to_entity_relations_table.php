<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The tradition a relation comes from (e.g. Baal child of Dagan in the
     * Eblaite tradition). Optional; cleared if that religion is deleted.
     */
    public function up(): void
    {
        Schema::table('entity_relations', function (Blueprint $table) {
            $table->foreignId('religion_id')->nullable()->after('kind')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_relations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('religion_id');
        });
    }
};
