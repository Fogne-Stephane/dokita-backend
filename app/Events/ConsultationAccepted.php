<?php
namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsultationAccepted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public string $channel,
        public string $token,
        public string $appId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('patient.' . $this->appointment->patient_id)];
    }

public function broadcastWith(): array
{
    return [
        'appointment_id' => $this->appointment->id,
        'type'           => $this->appointment->type, // ← Ajoute le type
        'channel'        => $this->channel,
        'token'          => $this->token,
        'app_id'         => $this->appId,
        'doctor_name'    => $this->appointment->doctor->name,
        'doctor_id'      => $this->appointment->doctor_id,
    ];
}

    public function broadcastAs(): string { return 'consultation.accepted'; }
}