<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cred_api_pix', function (Blueprint $table) {
            if (!Schema::hasColumn('cred_api_pix', 'mp_ultima_consulta')) {
                $table->timestamp('mp_ultima_consulta')->nullable()->after('tipo_cred');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cred_api_pix', function (Blueprint $table) {
            if (Schema::hasColumn('cred_api_pix', 'mp_ultima_consulta')) {
                $table->dropColumn('mp_ultima_consulta');
            }
        });
    }
};
