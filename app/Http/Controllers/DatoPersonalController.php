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
            // Validación menos estricta para la foto
            $request->validate([
                'apellidos' => 'nullable|string|max:255',
                'alias' => 'nullable|string|max:255',
                'cedula' => 'nullable|string|max:50',
                'telefono' => 'nullable|string|max:50',
                'fecha_nacimiento' => 'nullable|date',
                'foto' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
            ]);

            $user = Auth::user();
            $datos = DatoPersonal::firstOrNew(['user_id' => $user->id]);

            // Procesar la foto
            if ($request->hasFile('foto')) {
                try {
                    // Eliminar foto anterior
                    if ($datos->foto) {
                        $oldPath = str_replace('storage/', 'public/', $datos->foto);
                        if (Storage::exists($oldPath)) {
                            Storage::delete($oldPath);
                        }
                    }

                    // Guardar nueva foto con nombre único
                    $fileName = time() . '_' . uniqid() . '.' . 
                            $request->file('foto')->getClientOriginalExtension();
                    
                    $path = $request->file('foto')->storeAs(
                        'public/fotos/usuarios',
                        $fileName
                    );

                    $datos->foto = str_replace('public/', 'storage/', $path);
                } catch (\Exception $e) {
                    \Log::error('Error procesando foto: ' . $e->getMessage());
                    return response()->json([
                        'message' => 'Error al procesar la imagen',
                        'error' => $e->getMessage()
                    ], 422);
                }
            }

            // Actualizar otros campos
            $datos->fill($request->only([
                'apellidos', 'alias', 'cedula', 'telefono', 'fecha_nacimiento'
            ]));
            
            $datos->user_id = $user->id;
            $datos->save();

            return response()->json([
                'message' => 'Datos actualizados correctamente',
                'datos_personales' => $datos,
                'foto_url' => $datos->foto ? asset($datos->foto) : null
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en storeOrUpdate: ' . $e->getMessage());
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
