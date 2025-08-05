<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DatoPersonal;
use Illuminate\Support\Facades\Storage;

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
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Cambiado a archivo imagen
        ]);

        $user = Auth::user();

        $datos = DatoPersonal::firstOrNew(['user_id' => $user->id]);
        $datos->fill($request->only([
            'apellidos', 'alias', 'cedula', 'telefono', 'fecha_nacimiento'
        ]));

        // Manejar la foto (archivo)
        if ($request->hasFile('foto')) {
            // Si ya tenía una foto guardada, elimínala del storage
            if ($datos->foto && Storage::exists(str_replace('storage/', 'public/', $datos->foto))) {
                Storage::delete(str_replace('storage/', 'public/', $datos->foto));
            }
            // Guarda la nueva foto en storage/app/public/fotos
            $ruta = $request->file('foto')->store('public/fotos');
            // Guarda solo la ruta relativa que sea accesible desde /storage
            $datos->foto = str_replace('public/', 'storage/', $ruta);
        }

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
