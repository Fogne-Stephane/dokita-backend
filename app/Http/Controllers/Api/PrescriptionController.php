<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    // Prescriptions du patient
public function patientIndex(Request $request): JsonResponse
{
    $prescriptions = Prescription::with('doctor', 'consultation')
        ->where('patient_id', $request->user()->id)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(fn($p) => [
            'id'           => $p->id,
            'doctor_name'  => $p->doctor->name,
            'specialty'    => $p->doctor->doctor?->specialty ?? 'Médecin',
            'medications'  => $p->medications,
            'instructions' => $p->instructions,
            'valid_until'  => $p->valid_until?->format('d M Y'),
            'created_at'   => $p->created_at->format('d M Y'),
            'is_active'    => $p->valid_until ? $p->valid_until->isFuture() : true,
            'diagnosis'    => $p->consultation?->diagnosis,
        ]);

    return response()->json($prescriptions);
}

    // Prescriptions du médecin
    public function doctorIndex(Request $request): JsonResponse
    {
        $prescriptions = Prescription::with('patient')
            ->where('doctor_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'patient_name' => $p->patient->name,
                'medications'  => $p->medications,
                'instructions' => $p->instructions,
                'valid_until'  => $p->valid_until?->format('d M Y'),
                'created_at'   => $p->created_at->format('d M Y'),
            ]);

        return response()->json($prescriptions);
    }

    // Créer une prescription (médecin)
public function store(Request $request): JsonResponse
{
    $request->validate([
        'patient_id'      => 'required|exists:users,id',
        'consultation_id' => 'nullable|exists:consultations,id',
        'medications'     => 'required|array|min:1',
        'instructions'    => 'nullable|string',
        'valid_until'     => 'nullable|date',
    ]);

    $prescription = Prescription::create([
        'patient_id'      => $request->patient_id,
        'doctor_id'       => $request->user()->id,
        'consultation_id' => $request->consultation_id,
        'medications'     => $request->medications,
        'instructions'    => $request->instructions,
        'valid_until'     => $request->valid_until,
    ]);

    return response()->json([
        'message'      => 'Ordonnance créée.',
        'prescription' => $prescription,
    ], 201);
}
}