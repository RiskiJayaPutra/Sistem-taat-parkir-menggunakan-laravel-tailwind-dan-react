<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('laporan_pelanggaran', function (Blueprint $table) {
            // Tambah kolom untuk multiple foto (JSON array)
            $table->json('foto_pelanggaran')->nullable()->after('url_foto_bukti');
            // Tambah kolom unvalidated_at untuk tracking unvalidasi
            $table->timestamp('unvalidated_at')->nullable()->after('cancelled_at');
            $table->foreignId('unvalidated_by')->nullable()->constrained('users')->onDelete('set null')->after('unvalidated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laporan_pelanggaran', function (Blueprint $table) {
            $table->dropColumn(['foto_pelanggaran', 'unvalidated_at', 'unvalidated_by']);
        });
    }
};
