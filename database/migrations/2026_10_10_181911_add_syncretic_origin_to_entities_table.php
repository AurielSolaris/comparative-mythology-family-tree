<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The entity this one was syncretised from, or is most like (always a
     * different entity). Cleared if that entity is deleted.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->foreignId('syncretic_origin_id')->nullable()->after('element')->constrained('entities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('syncretic_origin_id');
        });
    }
};
