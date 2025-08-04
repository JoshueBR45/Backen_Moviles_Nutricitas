<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    // ============ LOGIN ============
    public function login(Request $request) 
    { 
        $credentials = $request->only('email', 'password'); 
        if (!Auth::attempt($credentials)) { 
            return response()->json(['message' => 'Credenciales inválidas'], 401); 
        } 
        $user = Auth::user(); 
        $token = $user->createToken('AppToken')->plainTextToken; 
    
        return response()->json([ 
            'token' => $token, 
            'user' => $user 
        ]); 
    } 

    // ============ REGISTRO ============
    public function register(Request $request) 
    { 
        $validator = Validator::make($request->all(), [ 
            'nombre' => 'required|string|max:255', 
            'email' => 'required|email|unique:users,email', 
            'password' => 'required|min:6', 
        ]); 
        if ($validator->fails()) { 
            return response()->json(['errors' => $validator->errors()], 422); 
        } 
        $user = User::create([ 
            'name' => $request->nombre, 
            'email' => $request->email, 
            'password' => Hash::make($request->password), 
            'roles_id' => 3,
        ]); 
        return response()->json([ 
            'message' => 'Usuario registrado correctamente', 
            'user' => $user 
        ], 201); 
    }

    // ============ SOLO ADMIN ============

    // Crear usuario manualmente como admin
    public function crearUsuario(Request $request)
    {
        if (auth()->user()->roles_id != 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), [ 
            'name' => 'required|string|max:255', 
            'email' => 'required|email|unique:users,email', 
            'password' => 'required|min:6', 
            'roles_id' => 'required|exists:roles,id'
        ]); 
        if ($validator->fails()) { 
            return response()->json(['errors' => $validator->errors()], 422); 
        } 
        $user = User::create([ 
            'name' => $request->name, 
            'email' => $request->email, 
            'password' => Hash::make($request->password), 
            'roles_id' => $request->roles_id
        ]); 
        return response()->json([ 
            'message' => 'Usuario creado correctamente', 
            'user' => $user 
        ], 201); 
    }

    public function counts()
    {
        if (auth()->user()->roles_id != 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $totalUsuarios = \App\Models\User::count();
        $totalNutricionistas = \App\Models\User::where('roles_id', 2)->count();
        $totalAdmins = \App\Models\User::where('roles_id', 1)->count();
        $totalPacientes = \App\Models\User::where('roles_id', 3)->count();

        return response()->json([
            'total_usuarios' => $totalUsuarios,
            'nutricionistas' => $totalNutricionistas,
            'admins' => $totalAdmins,
            'pacientes' => $totalPacientes,
        ]);
    }

    public function cambiarRol(Request $request, $id)
    {
        if (auth()->user()->roles_id != 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'roles_id' => 'required|exists:roles,id'
        ]);

        $user = User::findOrFail($id);

        $user->roles_id = $request->roles_id;
        $user->save();

        return response()->json(['message' => 'Rol actualizado correctamente', 'user' => $user]);
    }

    // Cambiar contraseña (solo admin, a cualquier usuario)
    public function cambiarPassword(Request $request, $id)
    {
        if (auth()->user()->roles_id != 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'password' => 'required|min:6|confirmed'
        ]);
        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente', 'user' => $user]);
    }

    public function listarUsuarios()
    {
        if (auth()->user()->roles_id != 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        return response()->json(User::all());
    }

    public function listarNutricionistas()
    {
    // Solo devuelve id, nombre y correo (puedes agregar más datos si quieres)
    return \App\Models\User::where('roles_id', 2)->select('id', 'name', 'email')->get();
    }

    // ============ PERFIL ============
    public function perfil(Request $request) 
    { 
        return response()->json($request->user()); 
    }

    public function actualizarNombre(Request $request)
    {
        $request->validate([
            'nombres' => 'required|string|max:255',
        ]);
        $user = Auth::user();
        $user->name = $request->nombres;
        $user->save();

        return response()->json([
            'message' => 'Nombre actualizado correctamente',
            'usuario' => $user
        ]);
    }
}
