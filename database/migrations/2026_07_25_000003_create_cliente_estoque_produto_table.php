<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cliente_estoque_produto')) {
            return;
        }

        Schema::create('cliente_estoque_produto', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_cliente');
            $table->unsignedBigInteger('id_estoque_produto');
            $table->unsignedInteger('quantidade')->default(1);
            $table->timestamps();

            $table->index('id_cliente', 'idx_cliente_estoque_produto_cliente');
            $table->index('id_estoque_produto', 'idx_cliente_estoque_produto_produto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_estoque_produto');
    }
};
