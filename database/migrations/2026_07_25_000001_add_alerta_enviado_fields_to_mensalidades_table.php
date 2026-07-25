<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensalidades', function (Blueprint $table) {
            $table->date('alerta_5_enviado_em')->nullable()->after('boleto_status');
            $table->date('alerta_3_enviado_em')->nullable()->after('alerta_5_enviado_em');
            $table->date('alerta_0_enviado_em')->nullable()->after('alerta_3_enviado_em');
        });
    }

    public function down(): void
    {
        Schema::table('mensalidades', function (Blueprint $table) {
            $table->dropColumn(['alerta_5_enviado_em', 'alerta_3_enviado_em', 'alerta_0_enviado_em']);
        });
    }
};
