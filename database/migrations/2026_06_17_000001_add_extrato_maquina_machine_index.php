<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extrato_maquina', function (Blueprint $table) {
            $table->index(['id_maquina', 'id_extrato_maquina'], 'extrato_maquina_id_maquina_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('extrato_maquina', function (Blueprint $table) {
            $table->dropIndex('extrato_maquina_id_maquina_id_idx');
        });
    }
};
