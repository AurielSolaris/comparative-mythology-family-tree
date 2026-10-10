<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Religions an entity also belongs to besides its primary one
     * (entities.religion_id), e.g. Baal: Canaanite, also Eblaite.
     */
    public function up(): void
    {
        Schema::create('entity_religion', function (Blueprint $table) {
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('religion_id')->constrained()->cascadeOnDelete();

            $table->primary(['entity_id', 'religion_id']);
            $table->index('religion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_religion');
    }
};
