<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mensalidades')) {
            return;
        }

        Schema::create('mensalidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_cliente');
            $table->decimal('valor', 10, 2);
            $table->date('vencimento');
            $table->enum('status', ['pago', 'pendente', 'atrasado'])->default('pendente');
            $table->timestamps();

            $table->index('id_cliente', 'idx_mensalidades_id_cliente');
            $table->index('status', 'idx_mensalidades_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensalidades');
    }
};
