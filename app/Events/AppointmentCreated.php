<?php
namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('doctor.' . $this->appointment->doctor_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'patient_name'   => $this->appointment->patient->name,
            'scheduled_at'   => $this->appointment->scheduled_at,
            'type'           => $this->appointment->type,
            'reason'         => $this->appointment->reason,
        ];
    }

    public function broadcastAs(): string { return 'appointment.created'; }
}