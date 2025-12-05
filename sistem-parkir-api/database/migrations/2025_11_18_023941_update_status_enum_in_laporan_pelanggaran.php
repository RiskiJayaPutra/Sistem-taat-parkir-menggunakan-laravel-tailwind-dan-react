<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('database.default');
        
        if ($connection === 'pgsql') {
            // PostgreSQL: Drop and recreate the column with new type
            DB::statement("ALTER TABLE laporan_pelanggaran DROP CONSTRAINT IF EXISTS laporan_pelanggaran_status_check");
            DB::statement("ALTER TABLE laporan_pelanggaran ALTER COLUMN status DROP DEFAULT");
            DB::statement("ALTER TABLE laporan_pelanggaran ALTER COLUMN status TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE laporan_pelanggaran ADD CONSTRAINT laporan_pelanggaran_status_check CHECK (status IN ('Pending', 'Valid', 'Ditolak', 'Dibatalkan'))");
            DB::statement("ALTER TABLE laporan_pelanggaran ALTER COLUMN status SET DEFAULT 'Pending'");
        } else {
            // MySQL
            DB::statement("ALTER TABLE laporan_pelanggaran MODIFY COLUMN status ENUM('Pending', 'Valid', 'Ditolak', 'Dibatalkan') DEFAULT 'Pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('database.default');
        
        if ($connection === 'pgsql') {
            DB::statement("ALTER TABLE laporan_pelanggaran DROP CONSTRAINT IF EXISTS laporan_pelanggaran_status_check");
            DB::statement("ALTER TABLE laporan_pelanggaran ALTER COLUMN status DROP DEFAULT");
            DB::statement("ALTER TABLE laporan_pelanggaran ALTER COLUMN status TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE laporan_pelanggaran ADD CONSTRAINT laporan_pelanggaran_status_check CHECK (status IN ('Pending', 'Valid', 'Ditolak'))");
            DB::statement("ALTER TABLE laporan_pelanggaran ALTER COLUMN status SET DEFAULT 'Pending'");
        } else {
            DB::statement("ALTER TABLE laporan_pelanggaran MODIFY COLUMN status ENUM('Pending', 'Valid', 'Ditolak') DEFAULT 'Pending'");
        }
    }
};
