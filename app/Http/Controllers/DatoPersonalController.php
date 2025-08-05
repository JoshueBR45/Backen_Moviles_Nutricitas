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
        try {
            // 1. Validación
            $validated = $request->validate([
                'apellidos' => 'nullable|string|max:255',
                'alias' => 'nullable|string|max:255',
                'cedula' => 'nullable|string|max:50',
                'telefono' => 'nullable|string|max:50',
                'fecha_nacimiento' => 'nullable|date',
                'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            ]);

            // 2. Obtener usuario y datos
            $user = Auth::user();
            $datos = DatoPersonal::firstOrNew(['user_id' => $user->id]);

            // 3. Procesar la foto
            if ($request->hasFile('foto')) {
                // 3.1 Eliminar foto anterior
                if ($datos->foto) {
                    $oldPath = str_replace('storage/', 'public/', $datos->foto);
                    if (Storage::exists($oldPath)) {
                        Storage::delete($oldPath);
                    }
                }

                // 3.2 Guardar nueva foto
                $fileName = time() . '_' . $user->id . '.' . 
                        $request->file('foto')->getClientOriginalExtension();
                
                $path = $request->file('foto')->storeAs(
                    'public/fotos/usuarios',
                    $fileName
                );

                $datos->foto = str_replace('public/', 'storage/', $path);
            }

            // 4. Actualizar otros datos
            $datos->fill($request->only([
                'apellidos', 'alias', 'cedula', 'telefono', 'fecha_nacimiento'
            ]));
            $datos->user_id = $user->id;
            $datos->save();

            // 5. Retornar respuesta
            return response()->json([
                'message' => 'Datos actualizados correctamente',
                'datos_personales' => $datos,
                'foto_url' => $datos->foto ? asset($datos->foto) : null
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al actualizar datos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar los datos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Mostrar los datos personales del usuario autenticado
    public function show()
    {
        $user = Auth::user();
        $datos = $user->datosPersonales;
        return response()->json($datos);
    }
}
