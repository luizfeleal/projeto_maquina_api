<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('historico_resets')) {
            return;
        }

        Schema::create('historico_resets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_maquina');
            $table->decimal('valor_total', 10, 2);
            $table->decimal('valor_reset', 10, 2);
            $table->date('data');
            $table->timestamps();

            $table->index('id_maquina', 'idx_historico_resets_id_maquina');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_resets');
    }
};
