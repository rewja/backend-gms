<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'waktu_penyelesaian' => 'date',
        'kebutuhan' => 'array',
        'ga_checked_at' => 'datetime',
        'ga_manager_checked_at' => 'datetime',
    ];

    // Relasi user diabaikan; user_id mungkin tidak digunakan di flow meeting-room

    // Relasi: Meeting punya banyak Guest
    public function guests()
    {
        return $this->hasMany(Visitor::class);
    }

    // Relasi untuk GA checker
    public function gaChecker()
    {
        return $this->belongsTo(User::class, 'checked_by_ga');
    }

    // Relasi untuk GA Manager checker
    public function gaManagerChecker()
    {
        return $this->belongsTo(User::class, 'checked_by_ga_manager');
    }

    // Method untuk mengecek apakah meeting sudah di-check oleh GA
    public function isCheckedByGA()
    {
        return $this->ga_check_status !== 'pending';
    }

    // Method untuk mengecek apakah meeting sudah di-check oleh GA Manager
    public function isCheckedByGAManager()
    {
        return $this->ga_manager_check_status !== 'pending';
    }

    // Method untuk mengecek apakah meeting sudah approved oleh kedua checker
    public function isFullyApproved()
    {
        return $this->ga_check_status === 'approved' && $this->ga_manager_check_status === 'approved';
    }

    // Method untuk mengecek apakah meeting ditolak oleh salah satu checker
    public function isRejected()
    {
        return $this->ga_check_status === 'rejected' || $this->ga_manager_check_status === 'rejected';
    }

    // Accessor untuk mendapatkan nama organizer
    // organizer derived from user removed because user_id is dropped

    // Accessor untuk mendapatkan email organizer
    // organizer email accessor removed for the same reason
}
