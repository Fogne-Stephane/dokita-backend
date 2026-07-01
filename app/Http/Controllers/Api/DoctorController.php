<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    // Liste publique des médecins
    public function index(Request $request): JsonResponse
    {
        $query = Doctor::with('user', 'healthCenter')
            ->where('is_verified', true);

        if ($request->specialty) {
            $query->where('specialty', $request->specialty);
        }

        if ($request->search) {
            $query->whereHas('user', fn($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
            );
        }

        $doctors = $query->get()->map(fn($d) => [
            'id'                  => $d->id,
            'user_id'             => $d->user_id,
            'name'                => $d->user->name,
            'avatar'              => $d->user->avatar,
            'specialty'           => $d->specialty,
            'experience_years'    => $d->experience_years,
            'consultation_fee'    => $d->consultation_fee,
            'is_available'        => $d->is_available,
            'available_days'      => $d->available_days,
            'available_from'      => $d->available_from,
            'available_to'        => $d->available_to,
            'bio'                 => $d->bio,
            'city'                => $d->user->city ?? null,
            'health_center'       => $d->healthCenter?->name,
        ]);

        return response()->json($doctors);
    }

    // Profil du médecin connecté
    public function show(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;
        return response()->json([
            'user'   => $request->user(),
            'doctor' => $doctor,
        ]);
    }

    // Mise à jour profil médecin
    public function update(Request $request): JsonResponse
    {
        $user   = $request->user();
        $doctor = $user->doctor;

        $user->update($request->only(['name', 'phone']));

        $doctor->update($request->only([
            'specialty', 'bio', 'consultation_fee',
            'consultation_duration', 'is_available',
            'available_days', 'available_from', 'available_to',
        ]));

        return response()->json([
            'message' => 'Profil mis à jour.',
            'doctor'  => $doctor->fresh(),
        ]);
    }

    // Admin — liste tous les médecins
    public function adminIndex(): JsonResponse
    {
        $doctors = Doctor::with('user')->get()->map(fn($d) => [
            'id'             => $d->id,
            'name'           => $d->user->name,
            'email'          => $d->user->email,
            'specialty'      => $d->specialty,
            'license_number' => $d->license_number,
            'experience_years' => $d->experience_years,
            'is_verified'    => $d->is_verified,
            'is_active'      => $d->user->is_active,
            'created_at'     => $d->created_at->format('d M Y'),
        ]);

        return response()->json($doctors);
    }

    // Admin — valider un médecin
    public function verify(int $id): JsonResponse
    {
        $doctor = Doctor::findOrFail($id);
        $doctor->update(['is_verified' => true]);

        return response()->json(['message' => 'Médecin vérifié.']);
    }

    // Admin — rejeter un médecin
    public function reject(int $id): JsonResponse
    {
        $doctor = Doctor::findOrFail($id);
        $doctor->update(['is_verified' => false]);

        return response()->json(['message' => 'Médecin rejeté.']);
    }
}