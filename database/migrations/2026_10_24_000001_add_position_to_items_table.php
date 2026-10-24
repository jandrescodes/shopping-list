<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->bigInteger('position')->nullable()->after('is_purchased');
        });

        // Se rellena antes de exigir NOT NULL: un DEFAULT 0 desordenaría en silencio las listas existentes.
        DB::statement('
            UPDATE items
            JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY shopping_list_id ORDER BY created_at, id) * 1024 AS position
                FROM items
            ) AS ranked ON ranked.id = items.id
            SET items.position = ranked.position
        ');

        Schema::table('items', function (Blueprint $table) {
            $table->bigInteger('position')->nullable(false)->change();
            $table->index(['shopping_list_id', 'is_purchased', 'position']);
            $table->dropIndex(['shopping_list_id', 'is_purchased', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->index(['shopping_list_id', 'is_purchased', 'created_at']);
            $table->dropIndex(['shopping_list_id', 'is_purchased', 'position']);
            $table->dropColumn('position');
        });
    }
};
