<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DatoPersonal;

class DatoPersonalController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        $request->validate([
            'apellidos' => 'nullable|string|max:255',
            'alias' => 'nullable|string|max:255',
            'cedula' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:50',
            'fecha_nacimiento' => 'nullable|date',
            'foto' => 'nullable|string',
        ]);

        $user = Auth::user();

        $datos = DatoPersonal::firstOrNew(['user_id' => $user->id]);
        $datos->fill($request->only([
            'apellidos', 'alias', 'cedula', 'telefono', 'fecha_nacimiento', 'foto'
        ]));
        $datos->user_id = $user->id;
        $datos->save();

        return response()->json([
            'message' => 'Datos personales guardados correctamente',
            'datos_personales' => $datos
        ]);
    }

    public function show()
    {
        $user = Auth::user();
        $datos = $user->datosPersonales;
        return response()->json($datos);
    }
}
