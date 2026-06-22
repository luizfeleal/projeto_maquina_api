<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            if (!Schema::hasColumn('maquinas', 'ultimo_valor_reset')) {
                $table->decimal('ultimo_valor_reset', 10, 2)->default(0)->after('maquina_ultima_coleta');
            }
            if (!Schema::hasColumn('maquinas', 'bloqueio_jogada_efi')) {
                $table->boolean('bloqueio_jogada_efi')->default(false)->after('maquina_status');
            }
            if (!Schema::hasColumn('maquinas', 'bloqueio_jogada_pagbank')) {
                $table->boolean('bloqueio_jogada_pagbank')->default(false)->after('bloqueio_jogada_efi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            if (Schema::hasColumn('maquinas', 'ultimo_valor_reset')) {
                $table->dropColumn('ultimo_valor_reset');
            }
        });
    }
};
