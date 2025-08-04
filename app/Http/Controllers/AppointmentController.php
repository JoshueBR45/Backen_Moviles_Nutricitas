<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    private function isNutricionista($user)
    {
        return $user->roles_id == 2;
    }

    // Listar citas según el rol (trae info relacionada)
    public function index()
    {
        $user = Auth::user();
        if ($this->isNutricionista($user)) {
            return Appointment::with('paciente')
                ->where('nutricionista_id', $user->id)
                ->get();
        } else {
            return Appointment::with(['nutricionista', 'nutricionista.datosPersonales'])
                ->where('paciente_id', $user->id)
                ->get();
        }
    }

    // Crear cita (paciente reserva)
    public function store(Request $request)
    {
        $request->validate([
            'nutricionista_id' => 'required|exists:users,id',
            'fecha' => 'required|date',
            'hora_inicio' => 'required',
        ]);

        if (Carbon::parse($request->fecha) < Carbon::today()) {
            return response()->json(['message' => 'No puedes reservar turnos en fechas pasadas.'], 422);
        }

            // NUEVO: Verificar si el paciente ya tiene una cita (no cancelada) ese día (con cualquier nutricionista)
        $yaTieneCita = Appointment::where('paciente_id', Auth::id())
            ->where('fecha', $request->fecha)
            ->where('estado', '!=', 'cancelado')
            ->exists();

        if ($yaTieneCita) {
            return response()->json([
                'message' => 'Ya tienes una cita reservada para este día. Solo puedes reservar una cita por día. Cancela una cita existente para reservar otra.'
            ], 409);
        }

        $exists = Appointment::where('nutricionista_id', $request->nutricionista_id)
            ->where('fecha', $request->fecha)
            ->where('hora_inicio', $request->hora_inicio)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'El turno ya está reservado para ese horario.'], 409);
        }

    
        $appointment = Appointment::create([
            'nutricionista_id' => $request->nutricionista_id,
            'paciente_id' => Auth::id(),
            'fecha' => $request->fecha,
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
            'estado' => 'pendiente',
            'notas' => $request->notas,
        ]);

        return response()->json($appointment, 201);
    }

    // Generar slots (solo nutricionistas)
    public function generarSlots(Request $request)
    {
        $user = Auth::user();
        if (!$this->isNutricionista($user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'hora_inicio' => 'required',
            'hora_fin' => 'required|after:hora_inicio',
            'descanso_inicio' => 'required',
            'descanso_fin' => 'required|after:descanso_inicio',
        ]);

        $nutricionistaId = $user->id;
        $startDate = Carbon::parse($request->fecha_inicio);
        $endDate = Carbon::parse($request->fecha_fin);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $horaActual = Carbon::parse($request->hora_inicio);
            $finJornada = Carbon::parse($request->hora_fin);

            while ($horaActual->lt($finJornada)) {
                $horaFinSlot = $horaActual->copy()->addHour();
                $descansoInicio = Carbon::parse($request->descanso_inicio);
                $descansoFin = Carbon::parse($request->descanso_fin);

                if (
                    !($horaActual->between($descansoInicio, $descansoFin->subMinute()) ||
                    $horaFinSlot->between($descansoInicio->addMinute(), $descansoFin))
                ) {
                    $exists = Appointment::where('nutricionista_id', $nutricionistaId)
                        ->where('fecha', $date->toDateString())
                        ->where('hora_inicio', $horaActual->format('H:i:s'))
                        ->where('hora_fin', $horaFinSlot->format('H:i:s'))
                        ->exists();

                    if (!$exists) {
                        Appointment::create([
                            'nutricionista_id' => $nutricionistaId,
                            'paciente_id' => null,
                            'fecha' => $date->toDateString(),
                            'hora_inicio' => $horaActual->format('H:i:s'),
                            'hora_fin' => $horaFinSlot->format('H:i:s'),
                            'estado' => 'libre',
                        ]);
                    }
                }
                $horaActual->addHour();
            }
        }
        return response()->json(['message' => 'Slots generados correctamente']);
    }

    // Ver horarios disponibles de un nutricionista en una fecha
    public function horariosDisponibles($nutricionistaId, Request $request)
    {
        $fecha = $request->input('fecha');
        $slots = Appointment::where('nutricionista_id', $nutricionistaId)
            ->where('fecha', $fecha)
            ->where('estado', 'libre')
            ->get();

        return $slots;
    }

    // Reservar un slot libre
    public function reservarSlot(Request $request, $slotId)
    {
        $slot = Appointment::findOrFail($slotId);

        if ($slot->estado !== 'libre' || $slot->paciente_id !== null) {
            return response()->json(['message' => 'Este turno ya no está disponible.'], 409);
        }

        $slot->paciente_id = Auth::id();
        $slot->estado = 'pendiente';
        $slot->save();

        return response()->json(['message' => 'Turno reservado correctamente', 'turno' => $slot]);
    }

    // Ver detalles de una cita (con info paciente y nutricionista)
    public function show($id)
    {
        $appointment = Appointment::with(['paciente', 'nutricionista'])->findOrFail($id);
        return $appointment;
    }

    // Cancelar cita (paciente o nutricionista)
    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);

        if (Auth::id() == $appointment->paciente_id || Auth::id() == $appointment->nutricionista_id) {
            $appointment->estado = 'cancelado';
            $appointment->save();
            return response()->json(['message' => 'Turno cancelado.']);
        }

        return response()->json(['message' => 'No autorizado.'], 403);
    }

    // Listar turnos del nutricionista autenticado, con filtros
    public function misTurnos(Request $request)
    {
        $user = Auth::user();
        if (!$this->isNutricionista($user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $query = Appointment::with('paciente.datosPersonales')
            ->where('nutricionista_id', $user->id);

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->has('fecha')) {
            $query->where('fecha', $request->fecha);
        }

        return $query->orderBy('fecha')->orderBy('hora_inicio')->get();
    }

    // Resumen de turnos (hoy, semana, pendientes, confirmados)
    public function resumenTurnos()
    {
        $user = Auth::user();
        if (!$this->isNutricionista($user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $hoy = Carbon::now()->toDateString();
        $inicioSemana = Carbon::now()->startOfWeek()->toDateString();
        $finSemana = Carbon::now()->endOfWeek()->toDateString();

        return [
            'hoy' => Appointment::where('nutricionista_id', $user->id)->where('fecha', $hoy)->count(),
            'semana' => Appointment::where('nutricionista_id', $user->id)->whereBetween('fecha', [$inicioSemana, $finSemana])->count(),
            'pendientes' => Appointment::where('nutricionista_id', $user->id)->where('estado', 'pendiente')->count(),
            'confirmados' => Appointment::where('nutricionista_id', $user->id)->where('estado', 'confirmado')->count(),
        ];
    }

    // Confirmar turno
    public function confirmarTurno($id)
    {
        $user = Auth::user();
        $turno = Appointment::findOrFail($id);

        if ($turno->nutricionista_id != $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (Carbon::parse($turno->fecha) < Carbon::today()) {
            return response()->json(['message' => 'No puedes confirmar turnos en fechas pasadas.'], 422);
        }

        $turno->estado = 'confirmado';
        $turno->save();

        return response()->json(['message' => 'Turno confirmado', 'turno' => $turno]);
    }

    // Cancelar turno como nutricionista
    public function cancelarTurno($id)
    {
        $user = Auth::user();
        $turno = Appointment::findOrFail($id);

        if ($turno->nutricionista_id != $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $turno->estado = 'cancelado';
        $turno->save();

        return response()->json(['message' => 'Turno cancelado', 'turno' => $turno]);
    }

    // Eliminar turno (solo nutricionista dueño)
    public function eliminarTurno($id)
    {
        $user = Auth::user();
        $turno = Appointment::findOrFail($id);

        if ($turno->nutricionista_id != $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        $turno->delete();
        return response()->json(['message' => 'Turno eliminado']);
    }

  public function dashboardNutricionista(Request $request)
{
    $user = auth()->user();
    $now = Carbon::now();
    $hoy = $now->toDateString();
    $horaActual = $now->format('H:i:s');

    $citasHoy = Appointment::where('nutricionista_id', $user->id)
        ->where('fecha', $hoy)
        ->orderBy('hora_inicio')
        ->with('paciente')
        ->get()
        ->map(function ($cita) use ($horaActual) {
            // Marcar si ya pasó o si es próxima
            $esProxima = $cita->hora_inicio > $horaActual;
            return [
                'hora' => substr($cita->hora_inicio, 0, 5),
                'paciente' => $cita->estado === 'libre'
                    ? 'Disponible'
                    : ($cita->paciente ? $cita->paciente->name : 'Desconocido'),
                'tipo' => 'Consulta',
                'estado' => $cita->estado,
                'proxima' => $esProxima, // true=próxima, false=ya pasó
            ];
        });

    // --- Pacientes recientes ---
    $pacientesRecientes = Appointment::where('nutricionista_id', $user->id)
        ->whereNotNull('paciente_id')
        ->latest('created_at')
        ->with('paciente')
        ->get()
        ->unique('paciente_id')
        ->take(5)
        ->filter(fn($cita) => $cita->paciente)
        ->map(function ($cita) {
            return [
                'nombre' => $cita->paciente->name,
                'tiempo' => $cita->created_at->diffForHumans(),
            ];
        });

    // --- Pacientes más frecuentes ---
    $topPacientes = Appointment::select('paciente_id', \DB::raw('count(*) as total'))
        ->where('nutricionista_id', $user->id)
        ->whereNotNull('paciente_id')
        ->groupBy('paciente_id')
        ->orderByDesc('total')
        ->take(5)
        ->get()
        ->map(function ($item) {
            $paciente = \App\Models\User::find($item->paciente_id);
            return $paciente ? [
                'paciente_id' => $item->paciente_id,
                'nombre' => $paciente->name,
                'total' => $item->total,
            ] : null;
        })
        ->filter();

    // --- Dashboard response ---
    return response()->json([
        'total_citas' => Appointment::where('nutricionista_id', $user->id)->count(),

        'total_pacientes' => Appointment::where('nutricionista_id', $user->id)
            ->whereNotNull('paciente_id')
            ->distinct('paciente_id')
            ->count('paciente_id'),

        'citas_semana' => Appointment::where('nutricionista_id', $user->id)
            ->whereBetween('fecha', [now()->startOfWeek(), now()->endOfWeek()])
            ->count(),

        // --- Solo citas futuras (fecha + hora > ahora) ---
        'citas_proximas' => Appointment::where('nutricionista_id', $user->id)
            ->whereRaw("(fecha::text || ' ' || hora_inicio)::timestamp > ?", [$now])
            ->count(),

        'pendientes' => Appointment::where('nutricionista_id', $user->id)
            ->where('estado', 'pendiente')->count(),

        'confirmadas' => Appointment::where('nutricionista_id', $user->id)
            ->where('estado', 'confirmado')->count(),

        'canceladas' => Appointment::where('nutricionista_id', $user->id)
            ->where('estado', 'cancelado')->count(),

        'libres' => Appointment::where('nutricionista_id', $user->id)
            ->where('estado', 'libre')->count(),

        'citas_hoy' => $citasHoy,
        'pacientes_recientes' => $pacientesRecientes->values(),
        'top_pacientes' => $topPacientes->values(),
        'satisfaccion' => rand(80, 99),
        'nombre_nutricionista' => $user->name,
    ]);
}

    public function dashboardPaciente()
    {
        $user = auth()->user();
        $now = \Carbon\Carbon::now();

        // Próximas citas (solo futuras, no canceladas)
        $proximas = \App\Models\Appointment::with(['nutricionista', 'nutricionista.datosPersonales'])
            ->where('paciente_id', $user->id)
            ->where('estado', '!=', 'cancelado')
            ->where(function ($q) use ($now) {
                $q->where('fecha', '>', $now->toDateString())
                ->orWhere(function ($sub) use ($now) {
                    $sub->where('fecha', $now->toDateString())
                        ->where('hora_inicio', '>=', $now->format('H:i:s'));
                });
            })
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        // Totales rápidos
        $totalReservas = \App\Models\Appointment::where('paciente_id', $user->id)->where('estado', '!=', 'cancelado')->count();
        $totalAtenciones = \App\Models\Appointment::where('paciente_id', $user->id)->where('estado', 'confirmado')->count();

        // Puedes agregar más stats si lo deseas...

        return response()->json([
            'proximas_citas' => $proximas,
            'total_reservas' => $totalReservas,
            'total_atenciones' => $totalAtenciones,
            // ...
        ]);
    }

    public function tieneCitaParaElDia(Request $request)
    {
        $user = Auth::user();
        $fecha = $request->input('fecha'); // formato 'YYYY-MM-DD'

        $existe = Appointment::where('paciente_id', $user->id)
            ->where('fecha', $fecha)
            ->where('estado', '!=', 'cancelado')
            ->exists();

        return response()->json(['tiene_cita' => $existe]);
    }

}
