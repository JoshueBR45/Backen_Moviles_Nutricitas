<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatoPersonal extends Model
{
    use HasFactory;

    protected $table = 'datos_personales';

    protected $fillable = [
        'user_id',
        'apellidos',
        'alias',
        'cedula',
        'telefono',
        'fecha_nacimiento',
        'foto'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
