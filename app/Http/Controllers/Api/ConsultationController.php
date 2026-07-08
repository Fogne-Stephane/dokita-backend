<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\VideoSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConsultationController extends Controller
{
    // Salle d'attente — infos pour le patient
    public function waitingRoom(int $appointmentId, Request $request): JsonResponse
    {
        $appointment = Appointment::with('doctor', 'patient', 'videoSession')
            ->where('id', $appointmentId)
            ->where('patient_id', $request->user()->id)
            ->firstOrFail();

        $videoSession = $appointment->videoSession;

        return response()->json([
            'appointment' => [
                'id'           => $appointment->id,
                'scheduled_at' => $appointment->scheduled_at,
                'type'         => $appointment->type,
                'status'       => $appointment->status,
                'is_paid'      => $appointment->is_paid,
                'fee'          => $appointment->fee,
                'reason'       => $appointment->reason,
            ],
            'doctor' => [
                'id'       => $appointment->doctor->id,
                'name'     => $appointment->doctor->name,
                'specialty'=> $appointment->doctor->doctor?->specialty,
            ],
            'session' => $videoSession ? [
                'id'      => $videoSession->id,
                'channel' => $videoSession->agora_channel,
                'status'  => $videoSession->status,
            ] : null,
        ]);
    }

    // Démarrer une consultation (médecin)
    public function start(int $appointmentId, Request $request): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)
            ->where('doctor_id', $request->user()->id)
            ->firstOrFail();

        // Créer la session vidéo
        $channel = 'dokita-' . $appointmentId . '-' . Str::random(8);

        $videoSession = VideoSession::updateOrCreate(
            ['appointment_id' => $appointmentId],
            [
                'agora_channel' => $channel,
                'status'        => 'active',
                'started_at'    => now(),
            ]
        );

        // Créer la consultation
        Consultation::updateOrCreate(
            ['appointment_id' => $appointmentId],
            [
                'patient_id' => $appointment->patient_id,
                'doctor_id'  => $appointment->doctor_id,
                'symptoms'   => $appointment->reason ?? 'Consultation générale',
                'started_at' => now(),
            ]
        );

        $appointment->update(['status' => 'confirmed']);

        return response()->json([
            'message' => 'Consultation démarrée.',
            'channel' => $channel,
            'session_id' => $videoSession->id,
        ]);
    }

    // Terminer une consultation (médecin)
    public function end(int $appointmentId, Request $request): JsonResponse
    {
        $request->validate([
            'diagnosis'      => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        $appointment = Appointment::where('id', $appointmentId)
            ->where('doctor_id', $request->user()->id)
            ->firstOrFail();

        // Terminer la session vidéo
        VideoSession::where('appointment_id', $appointmentId)
            ->update(['status' => 'ended', 'ended_at' => now()]);

        // Mettre à jour la consultation
        Consultation::where('appointment_id', $appointmentId)
            ->update([
                'diagnosis'      => $request->diagnosis,
                'treatment_plan' => $request->treatment_plan,
                'notes'          => $request->notes,
                'ended_at'       => now(),
            ]);

        $appointment->update(['status' => 'completed']);

        return response()->json(['message' => 'Consultation terminée.']);
    }
}