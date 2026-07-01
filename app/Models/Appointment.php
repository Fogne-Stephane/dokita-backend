<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'scheduled_at',
        'duration_minutes',
        'type',
        'status',
        'reason',
        'notes',
        'fee',
        'is_paid',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'is_paid'      => 'boolean',
            'fee'          => 'decimal:2',
        ];
    }

    // Le rendez-vous appartient à un patient
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    // Le rendez-vous appartient à un médecin
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    // Le rendez-vous a une consultation
    public function consultation()
    {
        return $this->hasOne(Consultation::class);
    }

    // Le rendez-vous a un paiement
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    // Le rendez-vous a une session vidéo
    public function videoSession()
    {
        return $this->hasOne(VideoSession::class);
    }
}