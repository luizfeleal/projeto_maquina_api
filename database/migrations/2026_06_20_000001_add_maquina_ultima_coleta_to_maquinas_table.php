<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            if (!Schema::hasColumn('maquinas', 'maquina_ultima_coleta')) {
                $table->decimal('maquina_ultima_coleta', 10, 2)->nullable()->after('maquina_ultimo_contato');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            if (Schema::hasColumn('maquinas', 'maquina_ultima_coleta')) {
                $table->dropColumn('maquina_ultima_coleta');
            }
        });
    }
};
