<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('estoque_placas')) {
            return;
        }

        Schema::create('estoque_placas', function (Blueprint $table) {
            $table->id();
            $table->string('serial', 100)->unique();
            $table->string('status', 50)->default('disponivel');
            $table->unsignedInteger('id_cliente_associado')->nullable();
            $table->timestamps();

            $table->index('id_cliente_associado', 'idx_estoque_placas_id_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_placas');
    }
};
