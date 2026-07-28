<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maquina_resets_parciais')) {
            return;
        }

        Schema::create('maquina_resets_parciais', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_maquina');
            $table->decimal('valor_ultima_coleta', 10, 2);
            $table->decimal('valor_acumulado_total', 10, 2);
            $table->string('realizado_por', 64);
            $table->text('observacao')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('id_maquina', 'idx_maquina_resets_id_maquina');
            $table->index(['id_maquina', 'created_at'], 'idx_maquina_resets_maquina_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maquina_resets_parciais');
    }
};
