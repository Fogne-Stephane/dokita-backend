<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    // Rendez-vous du patient connecté
public function patientIndex(Request $request): JsonResponse
{
    $appointments = Appointment::with('doctor.doctor')
        ->where('patient_id', $request->user()->id)
        ->where('is_paid', true) // ← Seulement les payés
        ->orderBy('scheduled_at', 'desc')
        ->get()
        ->map(fn($a) => $this->formatAppointment($a));

    return response()->json($appointments);
}

    // Rendez-vous du médecin connecté
public function doctorIndex(Request $request): JsonResponse
{
    $appointments = Appointment::with('patient')
        ->where('doctor_id', $request->user()->id)
        ->where('is_paid', true) // ← Seulement les payés
        ->orderBy('scheduled_at', 'asc')
        ->get()
        ->map(fn($a) => $this->formatAppointment($a));

    return response()->json($appointments);
}

    // Créer un rendez-vous
public function store(Request $request): JsonResponse
{   
    Log::info('Appointment store called', $request->all());
    
    $request->validate([
        'doctor_id'    => 'required|exists:users,id',
        'scheduled_at' => 'required|date',
        'type'         => 'required|in:video,in_person,message',
        'reason'       => 'nullable|string|max:500',
    ]);

    $doctor = \App\Models\Doctor::where('user_id', $request->doctor_id)->first();

    if (!$doctor) {
        return response()->json(['message' => 'Médecin introuvable.'], 404);
    }

    $appointment = Appointment::create([
        'patient_id'       => $request->user()->id,
        'doctor_id'        => $request->doctor_id,
        'scheduled_at'     => $request->scheduled_at,
        'duration_minutes' => $doctor->consultation_duration ?? 30,
        'type'             => $request->type,
        'reason'           => $request->reason ?? '',
        'fee'              => $doctor->consultation_fee ?? 0,
        'status'           => 'pending',
        'is_paid'          => false,
    ]);

    // Broadcast seulement pour RDV en personne planifié
try {
    if ($request->type === 'in_person') {
        $loaded = $appointment->load('patient', 'doctor');
        broadcast(new \App\Events\AppointmentCreated($loaded));
    }
    // Pas de broadcast pour video/message ici — ça se fait après le paiement
} catch (\Exception $e) {
    Log::warning('Broadcast AppointmentCreated failed: ' . $e->getMessage());
}

    return response()->json([
        'message'     => 'Rendez-vous créé.',
        'appointment' => [
            'id'           => $appointment->id,
            'scheduled_at' => $appointment->scheduled_at,
            'type'         => $appointment->type,
            'fee'          => $appointment->fee,
            'status'       => $appointment->status,
        ],
    ], 201);
}     
    // Récupérer un RDV non encore payé (pour le tunnel paiement)
public function showPending(int $id, Request $request): JsonResponse
{
    $appointment = Appointment::with('doctor.doctor')
        ->where('id', $id)
        ->where('patient_id', $request->user()->id)
        ->firstOrFail();

    return response()->json($this->formatAppointment($appointment));
}

    // Confirmer un rendez-vous (médecin)
    public function confirm(int $id, Request $request): JsonResponse
    {
        $appointment = Appointment::where('id', $id)
            ->where('doctor_id', $request->user()->id)
            ->firstOrFail();

        $appointment->update(['status' => 'confirmed']);

        return response()->json(['message' => 'Rendez-vous confirmé.']);
    }

    // Annuler un rendez-vous
    public function cancel(int $id, Request $request): JsonResponse
    {
        $appointment = Appointment::where('id', $id)
            ->where(fn($q) => $q
                ->where('patient_id', $request->user()->id)
                ->orWhere('doctor_id', $request->user()->id)
            )->firstOrFail();

        $appointment->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Rendez-vous annulé.']);
    }

    // Formater un rendez-vous pour l'API
private function formatAppointment(Appointment $a): array
{
    return [
        'id'           => $a->id,
        'scheduled_at' => $a->scheduled_at
            ? \Carbon\Carbon::parse($a->scheduled_at)->format('d M Y à H\hi')
            : null,
        'scheduled_at_raw' => $a->scheduled_at, // ← Ajoute cette ligne
        'type'         => $a->type,
        'status'       => $a->status,
        'reason'       => $a->reason,
        'fee'          => number_format($a->fee, 0, ',', ' ') . ' XAF',
        'fee_raw'      => (float) $a->fee, // ← Ajoute cette ligne
        'is_paid'      => $a->is_paid,
        'doctor'       => $a->doctor ? [
            'id'        => $a->doctor->id,
            'name'      => $a->doctor->name,
            'specialty' => $a->doctor->doctor?->specialty,
        ] : null,
        'patient'      => $a->patient ? [
            'id'   => $a->patient->id,
            'name' => $a->patient->name,
        ] : null,
    ];
}
}