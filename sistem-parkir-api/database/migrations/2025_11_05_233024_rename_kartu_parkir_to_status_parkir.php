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
        // Get database connection
        $connection = config('database.default');
        
        if ($connection === 'pgsql') {
            // PostgreSQL specific
            DB::statement('ALTER TABLE kartu_parkir DROP CONSTRAINT IF EXISTS kartu_parkir_mahasiswa_id_foreign');
            DB::statement('ALTER TABLE kartu_parkir DROP CONSTRAINT IF EXISTS kartu_parkir_pkey');
            DB::statement('ALTER TABLE kartu_parkir DROP COLUMN id_kartu');
            DB::statement('ALTER TABLE kartu_parkir RENAME TO status_parkir');
            DB::statement('ALTER TABLE status_parkir ADD PRIMARY KEY (mahasiswa_id)');
            DB::statement('ALTER TABLE status_parkir ADD CONSTRAINT status_parkir_mahasiswa_id_foreign FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE');
        } else {
            // MySQL fallback
            Schema::table('kartu_parkir', function (Blueprint $table) {
                $table->dropForeign(['mahasiswa_id']);
            });
            
            Schema::rename('kartu_parkir', 'status_parkir');
            
            Schema::table('status_parkir', function (Blueprint $table) {
                $table->dropPrimary(['id_kartu']);
                $table->dropColumn('id_kartu');
                $table->primary('mahasiswa_id');
                $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('database.default');
        
        if ($connection === 'pgsql') {
            DB::statement('ALTER TABLE status_parkir DROP CONSTRAINT IF EXISTS status_parkir_mahasiswa_id_foreign');
            DB::statement('ALTER TABLE status_parkir DROP CONSTRAINT IF EXISTS status_parkir_pkey');
            DB::statement('ALTER TABLE status_parkir ADD COLUMN id_kartu VARCHAR(255)');
            DB::statement('ALTER TABLE status_parkir ADD PRIMARY KEY (id_kartu)');
            DB::statement('ALTER TABLE status_parkir RENAME TO kartu_parkir');
            DB::statement('ALTER TABLE kartu_parkir ADD CONSTRAINT kartu_parkir_mahasiswa_id_foreign FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE');
        } else {
            Schema::table('status_parkir', function (Blueprint $table) {
                $table->dropForeign(['mahasiswa_id']);
                $table->dropPrimary(['mahasiswa_id']);
                $table->string('id_kartu');
                $table->primary('id_kartu');
                $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->onDelete('cascade');
            });
            
            Schema::rename('status_parkir', 'kartu_parkir');
        }
    }
};