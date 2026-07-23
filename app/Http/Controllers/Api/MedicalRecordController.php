<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalDocument;
use App\Models\MedicalRecord;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    // Résumé du dossier médical patient
    public function patientRecord(Request $request): JsonResponse
    {
        $user    = $request->user();
        $patient = $user->patient;

        $records = MedicalRecord::where('patient_id', $user->id)
            ->with('doctor', 'documents')
            ->orderBy('record_date', 'desc')
            ->get();

        return response()->json([
            'patient' => [
                'name'             => $user->name,
                'birth_date'       => $patient?->birth_date?->format('d/m/Y'),
                'gender'           => $patient?->gender,
                'blood_type'       => $patient?->blood_type,
                'allergies'        => $patient?->allergies,
                'chronic_diseases' => $patient?->chronic_diseases,
                'city'             => $patient?->city,
            ],
            'records' => $records->map(fn($r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'type'        => $r->type,
                'description' => $r->description,
                'record_date' => $r->record_date?->format('d M Y'),
                'doctor_name' => $r->doctor?->name,
                'documents_count' => $r->documents->count(),
            ]),
            'stats' => [
                'total_records'        => $records->count(),
                'total_consultations'  => Appointment::where('patient_id', $user->id)->where('status', 'completed')->count(),
                'total_prescriptions'  => \App\Models\Prescription::where('patient_id', $user->id)->count(),
                'total_documents'      => MedicalDocument::where('patient_id', $user->id)->count(),
            ],
        ]);
    }

    // Documents médicaux
    public function documents(Request $request): JsonResponse
    {
        $docs = MedicalDocument::where('patient_id', $request->user()->id)
            ->with('medicalRecord')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($d) => [
                'id'        => $d->id,
                'name'      => $d->name,
                'file_type' => $d->file_type,
                'file_size' => $this->formatSize($d->file_size),
                'date'      => $d->created_at->format('d M Y'),
                'category'  => $d->medicalRecord?->title,
            ]);

        return response()->json($docs);
    }

    // Historique consultations
    public function consultations(Request $request): JsonResponse
    {
        $consultations = Appointment::where('patient_id', $request->user()->id)
            ->where('status', 'completed')
            ->with('doctor', 'consultation')
            ->orderBy('scheduled_at', 'desc')
            ->get()
            ->map(fn($a) => [
                'id'            => $a->id,
                'doctor_name'   => $a->doctor->name,
                'specialty'     => $a->doctor->doctor?->specialty,
                'date'          => \Carbon\Carbon::parse($a->scheduled_at)->format('d M Y'),
                'type'          => $a->type,
                'reason'        => $a->reason,
                'diagnosis'     => $a->consultation?->diagnosis,
                'treatment'     => $a->consultation?->treatment_plan,
            ]);

        return response()->json($consultations);
    }

    private function formatSize(?int $bytes): string
    {
        if (!$bytes) return '—';
        if ($bytes < 1024 * 1024) return round($bytes / 1024) . ' KB';
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}