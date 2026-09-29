<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mercadopago_loja e mercadopago_pos foram criadas manualmente (sem
 * migration) sem as colunas de timestamp que o Eloquent espera por padrão
 * (created_at/updated_at) nem a que o SoftDeletes do model MercadopagoPos
 * exige (deleted_at). Sem elas, tanto criar quanto excluir um registro
 * falha com "Unknown column 'updated_at' in 'field list'" — é o que
 * aconteceu ao salvar um POS novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mercadopago_loja')) {
            Schema::table('mercadopago_loja', function (Blueprint $table) {
                if (!Schema::hasColumn('mercadopago_loja', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('mercadopago_loja', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('mercadopago_pos')) {
            Schema::table('mercadopago_pos', function (Blueprint $table) {
                if (!Schema::hasColumn('mercadopago_pos', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('mercadopago_pos', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
                if (!Schema::hasColumn('mercadopago_pos', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Não reverte: essas colunas são exigidas pelo Eloquent model
        // (timestamps padrão + SoftDeletes) — removê-las quebraria create/
        // delete de novo.
    }
};
