<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DatoPersonal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatoPersonalController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        // Validación de los datos
        $request->validate([
            'apellidos' => 'nullable|string|max:255',
            'alias' => 'nullable|string|max:255',
            'cedula' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:50',
            'fecha_nacimiento' => 'nullable|date',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validación de imagen
        ]);

        $user = Auth::user();

        // Buscar los datos personales existentes o crear uno nuevo
        $datos = DatoPersonal::firstOrNew(['user_id' => $user->id]);

        // Llenamos los campos que pueden ser actualizados
        $datos->fill($request->only([
            'apellidos', 'alias', 'cedula', 'telefono', 'fecha_nacimiento'
        ]));

        // Manejar la foto
        if ($request->hasFile('foto')) {
            // Si el usuario ya tiene una foto, la eliminamos del storage
            if ($datos->foto && Storage::exists('public/' . Str::replaceFirst('storage/', '', $datos->foto))) {
                Storage::delete('public/' . Str::replaceFirst('storage/', '', $datos->foto));
            }

            // Almacenamos la nueva foto en el directorio `public/fotos`
            $ruta = $request->file('foto')->store('public/fotos');

            // Guardamos la ruta de la foto en la base de datos, para acceder desde `storage`
            $datos->foto = Str::replaceFirst('public/', 'storage/', $ruta);
        }

        // Guardamos los datos personales
        $datos->user_id = $user->id;
        $datos->save();

        // Retornamos una respuesta con los datos guardados
        return response()->json([
            'message' => 'Datos personales guardados correctamente',
            'datos_personales' => $datos
        ]);
    }

    // Mostrar los datos personales del usuario autenticado
    public function show()
    {
        $user = Auth::user();
        $datos = $user->datosPersonales;
        return response()->json($datos);
    }
}
