<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatoPersonalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;

// ================= RUTAS PÚBLICAS =================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// =============== SOLO ADMINISTRADOR ===============
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/usuarios', [AuthController::class, 'crearUsuario']);
    Route::post('/usuarios/{id}/cambiar-rol', [AuthController::class, 'cambiarRol']);
    Route::post('/usuarios/{id}/cambiar-password', [AuthController::class, 'cambiarPassword']);
    Route::get('/usuarios-counts', [AuthController::class, 'counts']);
    Route::get('/usuarios', [AuthController::class, 'listarUsuarios']);
    // ...otros endpoints admin
});

// =============== SOLO AUTENTICADOS ================
Route::middleware('auth:sanctum')->group(function () {
    // Perfil y datos personales
    Route::get('/perfil', [AuthController::class, 'perfil']);
    Route::post('/perfil/actualizar-nombre', [AuthController::class, 'actualizarNombre']);
    Route::post('/datos-personales', [DatoPersonalController::class, 'storeOrUpdate']);
    Route::get('/datos-personales', [DatoPersonalController::class, 'show']);

    // Agenda y turnos (CRUD general para citas)
    Route::get('/appointments/tiene-cita-dia', [AppointmentController::class, 'tieneCitaParaElDia']);
    Route::apiResource('appointments', AppointmentController::class);

    // --- Acciones de turnos ---
    // Nutricionista: Generar slots vacíos
    Route::post('/nutricionista/generar-slots', [AppointmentController::class, 'generarSlots']);
    // Paciente: Reservar turno
    Route::post('/turnos/{id}/reservar', [AppointmentController::class, 'reservarSlot']);
    // Ver horarios disponibles de nutricionista
    Route::get('/nutricionista/{id}/horarios-disponibles', [AppointmentController::class, 'horariosDisponibles']);

    // --- Funciones EXCLUSIVAS para nutricionista ---
    Route::get('/nutricionista/turnos', [AppointmentController::class, 'misTurnos']);
    Route::get('/nutricionista/turnos/resumen', [AppointmentController::class, 'resumenTurnos']); // <-- nombre correcto de método
    Route::patch('/nutricionista/turnos/{id}/confirmar', [AppointmentController::class, 'confirmarTurno']);
    Route::patch('/nutricionista/turnos/{id}/cancelar', [AppointmentController::class, 'cancelarTurno']);
    Route::delete('/nutricionista/turnos/{id}', [AppointmentController::class, 'eliminarTurno']);
    Route::get('nutricionista/dashboard', [AppointmentController::class, 'dashboardNutricionista']);
    Route::get('dashboardPaciente', [AppointmentController::class, 'dashboardPaciente']);
    
    
});
Route::middleware('auth:sanctum')->get('/nutricionistas', [AuthController::class, 'listarNutricionistas']);


