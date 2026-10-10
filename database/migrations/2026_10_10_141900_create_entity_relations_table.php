<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * For "parent" rows, entity_id is the parent and related_entity_id the
     * child. Symmetric kinds (consort, sibling, equivalent) are stored once,
     * with the lower id in entity_id.
     */
    public function up(): void
    {
        Schema::create('entity_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('kind');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'related_entity_id', 'kind']);
            $table->index('related_entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_relations');
    }
};
