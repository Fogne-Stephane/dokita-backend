<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    // Rendez-vous du patient connecté
    public function patientIndex(Request $request): JsonResponse
    {
        $appointments = Appointment::with('doctor')
            ->where('patient_id', $request->user()->id)
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
            ->orderBy('scheduled_at', 'asc')
            ->get()
            ->map(fn($a) => $this->formatAppointment($a));

        return response()->json($appointments);
    }

    // Créer un rendez-vous
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'doctor_id'    => 'required|exists:users,id',
            'scheduled_at' => 'required|date|after:now',
            'type'         => 'required|in:video,in_person',
            'reason'       => 'nullable|string',
        ]);

        $doctor = \App\Models\Doctor::where('user_id', $request->doctor_id)->firstOrFail();

        $appointment = Appointment::create([
            'patient_id'       => $request->user()->id,
            'doctor_id'        => $request->doctor_id,
            'scheduled_at'     => $request->scheduled_at,
            'duration_minutes' => $doctor->consultation_duration ?? 30,
            'type'             => $request->type,
            'reason'           => $request->reason,
            'fee'              => $doctor->consultation_fee,
            'status'           => 'pending',
        ]);

        return response()->json([
            'message'     => 'Rendez-vous créé.',
            'appointment' => $this->formatAppointment($appointment->load('doctor', 'patient')),
        ], 201);
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
            'scheduled_at' => $a->scheduled_at?->format('d M Y à H\hi'),
            'type'         => $a->type,
            'status'       => $a->status,
            'reason'       => $a->reason,
            'fee'          => number_format($a->fee, 0, ',', ' ') . ' XAF',
            'is_paid'      => $a->is_paid,
            'doctor'       => $a->doctor ? [
                'id'       => $a->doctor->id,
                'name'     => $a->doctor->name,
                'specialty'=> $a->doctor->doctor?->specialty,
            ] : null,
            'patient'      => $a->patient ? [
                'id'   => $a->patient->id,
                'name' => $a->patient->name,
            ] : null,
        ];
    }
}