<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [
        'user_id',
        'birth_date',
        'gender',
        'blood_type',
        'allergies',
        'chronic_diseases',
        'emergency_contact_name',
        'emergency_contact_phone',
        'city',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    // Le patient appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Le patient a plusieurs dossiers médicaux
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class, 'patient_id', 'user_id');
    }

    // Le patient a plusieurs prescriptions
    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'patient_id', 'user_id');
    }
}