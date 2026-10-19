<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The single "syncretic origin / most like" field on entities is replaced
     * by syncretism relations. Each origin becomes an adoption relation from
     * the origin to the entity (the closest of the new kinds to "syncretised
     * from"), with a note saying where it came from so admins can refine it.
     */
    public function up(): void
    {
        $origins = DB::table('entities')->whereNotNull('syncretic_origin_id')->get(['id', 'syncretic_origin_id']);

        foreach ($origins as $entity) {
            $exists = DB::table('entity_relations')->where([
                'entity_id' => $entity->syncretic_origin_id,
                'related_entity_id' => $entity->id,
                'kind' => 'adoption',
            ])->exists();

            if (! $exists) {
                DB::table('entity_relations')->insert([
                    'entity_id' => $entity->syncretic_origin_id,
                    'related_entity_id' => $entity->id,
                    'kind' => 'adoption',
                    'note' => 'Was the “syncretic origin” field',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('syncretic_origin_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restores the column and fills it back from adoption relations; the
     * relations themselves stay.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->foreignId('syncretic_origin_id')->nullable()->after('element')->constrained('entities')->nullOnDelete();
        });

        foreach (DB::table('entity_relations')->where('kind', 'adoption')->orderBy('id')->get() as $relation) {
            DB::table('entities')->where('id', $relation->related_entity_id)->whereNull('syncretic_origin_id')
                ->update(['syncretic_origin_id' => $relation->entity_id]);
        }
    }
};
