<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthCenter extends Model
{
    protected $fillable = [
        'name',
        'type',
        'city',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Un centre a plusieurs médecins
    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }
}