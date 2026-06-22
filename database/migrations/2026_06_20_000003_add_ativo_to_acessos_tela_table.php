<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('acessos_tela', 'ativo')) {
            Schema::table('acessos_tela', function (Blueprint $table) {
                $table->tinyInteger('ativo')->default(1)->after('acesso_tela_nome');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('acessos_tela', 'ativo')) {
            Schema::table('acessos_tela', function (Blueprint $table) {
                $table->dropColumn('ativo');
            });
        }
    }
};
