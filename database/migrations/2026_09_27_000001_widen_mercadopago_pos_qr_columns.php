<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tabela mercadopago_pos não tem migration própria neste repositório (foi
 * criada direto no banco), e qr_image/qr_data provavelmente ficaram com um
 * tipo pequeno demais (ex.: TEXT ou VARCHAR) — o INSERT do QR do Mercado
 * Pago falhava ao salvar o PNG em base64 ("Data too long for column"),
 * exatamente como o qr_image da tabela qr_codes (Efí) já é LONGTEXT de
 * propósito pelo mesmo motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mercadopago_pos')) {
            return;
        }

        Schema::table('mercadopago_pos', function (Blueprint $table) {
            $table->longText('qr_image')->nullable(false)->change();
            $table->longText('qr_data')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Não reverte para o tipo original: não sabemos qual era (a tabela não
        // tinha migration), e reduzir o tamanho da coluna de volta arriscaria
        // truncar dados de produção salvos enquanto ela esteve como LONGTEXT.
    }
};
