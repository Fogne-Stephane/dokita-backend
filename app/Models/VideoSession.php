<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoSession extends Model
{
    protected $fillable = [
        'appointment_id',
        'agora_channel',
        'agora_token',
        'started_at',
        'ended_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}