<?php
namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsultationRequested implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function broadcastOn(): array
    {
        // Canal privé du médecin
        return [new PrivateChannel('doctor.' . $this->appointment->doctor_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'patient_name'   => $this->appointment->patient->name,
            'type'           => $this->appointment->type,
            'reason'         => $this->appointment->reason,
            'fee'            => $this->appointment->fee,
            'scheduled_at'   => $this->appointment->scheduled_at,
        ];
    }

    public function broadcastAs(): string { return 'consultation.requested'; }
}