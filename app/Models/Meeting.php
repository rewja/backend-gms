<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attendees' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // Relasi: Meeting dibuat oleh User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Meeting punya banyak Guest
    public function guests()
    {
        return $this->hasMany(Visitor::class);
    }

    // Accessor untuk mendapatkan nama organizer
    public function getOrganizerNameAttribute($value)
    {
        if ($this->booking_type === 'internal' && $this->user) {
            return $this->user->name;
        }
        return $value;
    }

    // Accessor untuk mendapatkan email organizer
    public function getOrganizerEmailAttribute($value)
    {
        if ($this->booking_type === 'internal' && $this->user) {
            return $this->user->email;
        }
        return $value;
    }
}
