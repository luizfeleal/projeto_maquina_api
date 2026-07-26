<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'cliente_cep')) {
                $table->string('cliente_cep', 10)->nullable()->after('cliente_cpf_cnpj');
            }
            if (!Schema::hasColumn('clientes', 'cliente_logradouro')) {
                $table->string('cliente_logradouro', 150)->nullable()->after('cliente_cep');
            }
            if (!Schema::hasColumn('clientes', 'cliente_numero')) {
                $table->string('cliente_numero', 20)->nullable()->after('cliente_logradouro');
            }
            if (!Schema::hasColumn('clientes', 'cliente_bairro')) {
                $table->string('cliente_bairro', 100)->nullable()->after('cliente_numero');
            }
            if (!Schema::hasColumn('clientes', 'cliente_cidade')) {
                $table->string('cliente_cidade', 100)->nullable()->after('cliente_bairro');
            }
            if (!Schema::hasColumn('clientes', 'cliente_uf')) {
                $table->string('cliente_uf', 2)->nullable()->after('cliente_cidade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'cliente_cep',
                'cliente_logradouro',
                'cliente_numero',
                'cliente_bairro',
                'cliente_cidade',
                'cliente_uf',
            ]);
        });
    }
};
