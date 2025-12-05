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
            // Tambah deskripsi setelah plat_nomor_terlapor
            $table->text('deskripsi')->nullable()->after('plat_nomor_terlapor');
            
            // Tambah timestamp untuk cancel
            $table->timestamp('cancelled_at')->nullable()->after('waktu_validasi');
            
            // Rename validator_id ke validated_by untuk konsistensi
            // Dan rename waktu_validasi ke validated_at
        });
        
        // Rename kolom (dilakukan terpisah karena SQLite limitation)
        Schema::table('laporan_pelanggaran', function (Blueprint $table) {
            $table->renameColumn('validator_id', 'validated_by');
            $table->renameColumn('waktu_validasi', 'validated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laporan_pelanggaran', function (Blueprint $table) {
            $table->renameColumn('validated_by', 'validator_id');
            $table->renameColumn('validated_at', 'waktu_validasi');
        });
        
        Schema::table('laporan_pelanggaran', function (Blueprint $table) {
            $table->dropColumn(['deskripsi', 'cancelled_at']);
        });
    }
};
