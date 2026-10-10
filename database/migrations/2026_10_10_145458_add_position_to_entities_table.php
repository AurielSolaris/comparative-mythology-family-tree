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
     * Admins order entities by hand; existing ones start in name order.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('slug')->index();
        });

        DB::table('entities')->orderBy('name')->orderBy('id')->pluck('id')->each(
            fn (int $id, int $index) => DB::table('entities')->where('id', $id)->update(['position' => $index + 1]),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
