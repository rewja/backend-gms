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
    ];

    // Relasi user diabaikan; user_id mungkin tidak digunakan di flow meeting-room

    // Relasi: Meeting punya banyak Guest
    public function guests()
    {
        return $this->hasMany(Visitor::class);
    }

    // Accessor untuk mendapatkan nama organizer
    // organizer derived from user removed because user_id is dropped

    // Accessor untuk mendapatkan email organizer
    // organizer email accessor removed for the same reason
}
