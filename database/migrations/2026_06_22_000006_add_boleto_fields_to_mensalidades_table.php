<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensalidades', function (Blueprint $table) {
            $table->string('efi_charge_id')->nullable()->after('status');
            $table->text('boleto_barcode')->nullable()->after('efi_charge_id');
            $table->text('boleto_link')->nullable()->after('boleto_barcode');
            $table->text('boleto_pdf')->nullable()->after('boleto_link');
            $table->string('boleto_status')->nullable()->after('boleto_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('mensalidades', function (Blueprint $table) {
            $table->dropColumn(['efi_charge_id', 'boleto_barcode', 'boleto_link', 'boleto_pdf', 'boleto_status']);
        });
    }
};
