<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    // Profil du patient connecté
    public function show(Request $request): JsonResponse
    {
        $user    = $request->user();
        $patient = $user->patient;

        return response()->json([
            'user'    => $user,
            'patient' => $patient,
        ]);
    }

    // Mise à jour profil patient
    public function update(Request $request): JsonResponse
    {
        $user    = $request->user();
        $patient = $user->patient;

        $user->update($request->only(['name', 'phone']));

        $patient->update($request->only([
            'birth_date', 'gender', 'blood_type',
            'allergies', 'chronic_diseases',
            'city', 'address',
            'emergency_contact_name', 'emergency_contact_phone',
        ]));

        return response()->json([
            'message' => 'Profil mis à jour.',
            'patient' => $patient->fresh(),
        ]);
    }

    // Liste des patients d'un médecin
    public function doctorPatients(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;

        $patients = User::whereHas('appointmentsAsPatient', fn($q) =>
            $q->where('doctor_id', $doctorId)
        )
        ->with('patient')
        ->get()
        ->map(fn($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'phone'      => $u->phone,
            'blood_type' => $u->patient?->blood_type,
            'city'       => $u->patient?->city,
            'last_visit' => $u->appointmentsAsPatient()
                ->where('doctor_id', $doctorId)
                ->where('status', 'completed')
                ->latest('scheduled_at')
                ->first()?->scheduled_at?->format('d M Y'),
            'consultations_count' => $u->appointmentsAsPatient()
                ->where('doctor_id', $doctorId)
                ->count(),
        ]);

        return response()->json($patients);
    }

    // Admin — tous les utilisateurs
    public function adminIndex(): JsonResponse
    {
        $users = User::with('patient', 'doctor')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role,
                'is_active'  => $u->is_active,
                'created_at' => $u->created_at->format('d M Y'),
            ]);

        return response()->json($users);
    }

    // Admin — bloquer/débloquer un utilisateur
    public function toggleBlock(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message'   => $user->is_active ? 'Utilisateur activé.' : 'Utilisateur bloqué.',
            'is_active' => $user->is_active,
        ]);
    }
}