<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanPelanggaran extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     *
     * @var string
     */
    protected $table = 'laporan_pelanggaran';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array
     */
    protected $fillable = [
        'pelapor_id',
        'plat_nomor_terlapor',
        'deskripsi',
        'url_foto_bukti',
        'foto_pelanggaran',    // JSON array untuk multiple foto
        'status',
        'validated_by',
        'validated_at',
        'cancelled_at',
        'unvalidated_at',      // Field baru untuk unvalidasi
        'unvalidated_by',      // Field baru untuk tracking siapa yang unvalidasi
    ];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array
     */
    protected $casts = [
        'validated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'unvalidated_at' => 'datetime',
        'foto_pelanggaran' => 'array',  // Cast JSON ke array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Mendapatkan data Satpam (User) yang memvalidasi laporan ini.
     */
    public function validator()
    {
        // Relasi ke User (sebagai validator)
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Mendapatkan data User (Mahasiswa) yang MELAPOR.
     */
    public function pelapor()
    {
        // Relasi ke User (sebagai pelapor)
        return $this->belongsTo(User::class, 'pelapor_id');
    }
}