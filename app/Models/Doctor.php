<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $fillable = [
        'user_id',
        'specialty',
        'license_number',
        'experience_years',
        'bio',
        'consultation_fee',
        'consultation_duration',
        'is_verified',
        'is_available',
        'available_days',
        'available_from',
        'available_to',
        'health_center_id',
    ];

    protected function casts(): array
    {
        return [
            'is_verified'   => 'boolean',
            'is_available'  => 'boolean',
            'available_days' => 'array',
            'consultation_fee' => 'decimal:2',
        ];
    }

    // Le médecin appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Le médecin appartient à un centre de santé
    public function healthCenter()
    {
        return $this->belongsTo(HealthCenter::class);
    }

    // Le médecin a plusieurs rendez-vous
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'doctor_id', 'user_id');
    }
}