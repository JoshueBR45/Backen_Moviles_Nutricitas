<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'nutricionista_id',
        'paciente_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
        'notas'
    ];

    // Relaciones
    public function nutricionista()
    {
        return $this->belongsTo(User::class, 'nutricionista_id');
    }

    public function paciente()
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }
}
