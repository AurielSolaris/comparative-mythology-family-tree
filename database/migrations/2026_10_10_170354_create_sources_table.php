<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Citations attached to an entity or an entity relation. "file" sources
     * have a path on the local disk (and the uploaded file's name); "link"
     * sources have a URL. "ref" is the line / verse / page being cited.
     */
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->morphs('sourceable');
            $table->string('kind');
            $table->string('url', 2048)->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('ref')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
