<?php
namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsultationRejected implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Appointment $appointment, public string $reason = '') {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('patient.' . $this->appointment->patient_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'reason'         => $this->reason,
        ];
    }

    public function broadcastAs(): string { return 'consultation.rejected'; }
}