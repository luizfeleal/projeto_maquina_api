<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('estoque_produtos')) {
            return;
        }

        Schema::create('estoque_produtos', function (Blueprint $table) {
            $table->id();
            $table->string('lote', 100)->nullable();
            $table->string('nome_produto', 150);
            $table->text('descricao')->nullable();
            $table->unsignedInteger('quantidade')->default(0);
            $table->decimal('valor', 10, 2)->default(0);
            $table->boolean('cobrar_mensal')->default(false);
            $table->timestamps();

            $table->index('lote', 'idx_estoque_produtos_lote');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_produtos');
    }
};
