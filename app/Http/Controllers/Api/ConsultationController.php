<?php
namespace App\Http\Controllers\Api;

use App\Events\ConsultationAccepted;
use App\Events\ConsultationEnded;
use App\Events\ConsultationRejected;
use App\Events\ConsultationRequested;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Message;
use App\Models\VideoSession;
use App\Services\AgoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConsultationController extends Controller
{
    public function __construct(private AgoraService $agora) {}

    // Patient — demander une consultation après paiement
    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
        ]);

        $appointment = Appointment::with('patient', 'doctor')
            ->where('id', $request->appointment_id)
            ->where('patient_id', $request->user()->id)
            ->firstOrFail();

        if (!$appointment->is_paid) {
            return response()->json(['message' => 'Paiement requis.'], 402);
        }

        // Diffuser la notification au médecin
        broadcast(new ConsultationRequested($appointment));

        return response()->json([
            'message'        => 'Demande envoyée au médecin.',
            'appointment_id' => $appointment->id,
            'type'           => $appointment->type,
        ]);
    }

    // Médecin — accepter
public function accept(int $appointmentId, Request $request): JsonResponse
{
    $appointment = Appointment::with('patient', 'doctor')
        ->where('id', $appointmentId)
        ->where('doctor_id', $request->user()->id)
        ->firstOrFail();

    $appointment->update(['status' => 'confirmed']);

    if ($appointment->type === 'video') {
        $channel = 'dokita-' . $appointmentId . '-' . \Illuminate\Support\Str::random(6);
        $token   = $this->agora->generateToken($channel, $appointment->patient_id);
        $appId   = $this->agora->getAppId();

        VideoSession::updateOrCreate(
            ['appointment_id' => $appointmentId],
            ['agora_channel' => $channel, 'agora_token' => $token, 'status' => 'active', 'started_at' => now()]
        );

        Consultation::updateOrCreate(
            ['appointment_id' => $appointmentId],
            ['patient_id' => $appointment->patient_id, 'doctor_id' => $appointment->doctor_id, 'symptoms' => $appointment->reason ?? '', 'started_at' => now()]
        );

        broadcast(new ConsultationAccepted($appointment, $channel, $token, $appId));

        return response()->json([
            'message' => 'Consultation vidéo démarrée.',
            'channel' => $channel,
            'token'   => $token,
            'app_id'  => $appId,
            'type'    => 'video',
        ]);

} elseif ($appointment->type === 'message') {
    // Créer le premier message du médecin
    try {
        $message = Message::create([
            'sender_id'   => $appointment->doctor_id,
            'receiver_id' => $appointment->patient_id,
            'content'     => 'Bonjour ! Je suis prêt pour votre consultation. Comment puis-je vous aider ?',
        ]);

        Log::info('Message créé pour consultation', [
            'appointment_id' => $appointmentId,
            'message_id'     => $message->id,
            'doctor_id'      => $appointment->doctor_id,
            'patient_id'     => $appointment->patient_id,
        ]);
    } catch (\Exception $e) {
        Log::error('Erreur création message: ' . $e->getMessage());
    }

    // Créer la consultation
    Consultation::updateOrCreate(
        ['appointment_id' => $appointmentId],
        [
            'patient_id' => $appointment->patient_id,
            'doctor_id'  => $appointment->doctor_id,
            'symptoms'   => $appointment->reason ?? '',
            'started_at' => now(),
        ]
    );

    $appointment->update(['status' => 'confirmed']);

    // Broadcast avec le type pour que le patient navigue
    broadcast(new ConsultationAccepted($appointment, '', '', ''));

    return response()->json([
        'message' => 'Chat de consultation ouvert.',
        'type'    => 'message',
    ]);
}

    return response()->json(['message' => 'Consultation acceptée.']);
}

    // Médecin — refuser
    public function reject(int $appointmentId, Request $request): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        $appointment = Appointment::where('id', $appointmentId)
            ->where('doctor_id', $request->user()->id)
            ->firstOrFail();

        $appointment->update(['status' => 'cancelled']);

        broadcast(new ConsultationRejected($appointment, $request->reason ?? 'Le médecin n\'est pas disponible.'));

        return response()->json(['message' => 'Consultation refusée.']);
    }

    // Terminer la consultation
    public function end(int $appointmentId, Request $request): JsonResponse
    {
        $request->validate([
            'diagnosis'      => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

$appointment = Appointment::where('id', $appointmentId)
    ->where(fn($q) => $q->where('patient_id', $request->user()->id)
                        ->orWhere('doctor_id', $request->user()->id))
    ->firstOrFail();

        VideoSession::where('appointment_id', $appointmentId)->update(['status' => 'ended', 'ended_at' => now()]);

        Consultation::where('appointment_id', $appointmentId)->update([
            'diagnosis'      => $request->diagnosis,
            'treatment_plan' => $request->treatment_plan,
            'notes'          => $request->notes,
            'ended_at'       => now(),
        ]);

        $appointment->update(['status' => 'completed']);

        broadcast(new ConsultationEnded($appointmentId, $appointment->patient_id, $appointment->doctor_id));

        return response()->json(['message' => 'Consultation terminée.']);
    }

    // Salle d'attente patient
    public function waitingRoom(int $appointmentId, Request $request): JsonResponse
    {
        $appointment = Appointment::with('doctor.doctor', 'videoSession')
            ->where('id', $appointmentId)
            ->where('patient_id', $request->user()->id)
            ->firstOrFail();

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
                'id'        => $appointment->doctor->id,
                'name'      => $appointment->doctor->name,
                'specialty' => $appointment->doctor->doctor?->specialty,
                'avatar'    => $appointment->doctor->avatar,
            ],
            'session' => $appointment->videoSession ? [
                'channel' => $appointment->videoSession->agora_channel,
                'token'   => $appointment->videoSession->agora_token,
                'status'  => $appointment->videoSession->status,
                'app_id'  => config('services.agora.app_id'),
            ] : null,
        ]);
    }

    // Notifications médecin
    public function doctorNotifications(Request $request): JsonResponse
    {
        $pending = Appointment::with('patient')
            ->where('doctor_id', $request->user()->id)
            ->where('status', 'pending')
            ->where('is_paid', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($a) => [
                'appointment_id' => $a->id,
                'patient_name'   => $a->patient->name,
                'type'           => $a->type,
                'reason'         => $a->reason,
                'fee'            => $a->fee,
                'created_at'     => $a->created_at->diffForHumans(),
            ]);

        return response()->json($pending);
    }
}