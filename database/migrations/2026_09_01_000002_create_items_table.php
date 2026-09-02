<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('quantity', 50)->nullable();
            $table->string('added_by', 50)->nullable();
            $table->boolean('is_purchased')->default(false);
            $table->unsignedBigInteger('version');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shopping_list_id', 'version']);
            $table->index(['shopping_list_id', 'is_purchased', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
