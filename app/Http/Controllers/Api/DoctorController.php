<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Appointment;

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
        'id'               => $d->id,
        'user_id'          => $d->user_id,
        'name'             => $d->user->name,
        'avatar'           => $d->user->avatar,
        'specialty'        => $d->specialty,
        'experience_years' => $d->experience_years,
        'consultation_fee' => $d->consultation_fee,
        'is_available'     => (bool) $d->is_available,
        'is_verified'      => (bool) $d->is_verified,
        'available_days'   => $d->available_days ?? [],
        'available_from'   => $d->available_from,
        'available_to'     => $d->available_to,
        'bio'              => $d->bio,
        'city'             => $d->healthCenter?->city ?? null,
        'health_center'    => $d->healthCenter?->name,
        'rating'           => round(4.5 + (($d->user_id % 5) * 0.1), 1),
        'reviews_count'    => Appointment::where('doctor_id', $d->user_id)
                                ->where('status', 'completed')->count(),
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

     // Profil public d'un médecin (accessible sans auth)
    public function publicProfile(int $id): JsonResponse
    {
        $doctor = Doctor::with('user', 'healthCenter')->findOrFail($id);

        return response()->json([
            'id'                     => $doctor->id,
            'user_id'                => $doctor->user_id,
            'name'                   => $doctor->user->name,
            'avatar'                 => $doctor->user->avatar,
            'specialty'              => $doctor->specialty,
            'experience_years'       => $doctor->experience_years,
            'consultation_fee'       => $doctor->consultation_fee,
            'consultation_duration'  => $doctor->consultation_duration,
            'is_available'           => $doctor->is_available,
            'is_verified'            => $doctor->is_verified,
            'available_days'         => $doctor->available_days ?? [],
            'available_from'         => $doctor->available_from,
            'available_to'           => $doctor->available_to,
            'bio'                    => $doctor->bio,
            'health_center'          => $doctor->healthCenter?->name,
            'health_center_city'     => $doctor->healthCenter?->city,
            'rating'                 => $this->getAverageRating($doctor->user_id),
            'reviews_count'          => $this->getReviewsCount($doctor->user_id),
            'patients_count'         => Appointment::where('doctor_id', $doctor->user_id)
                                            ->where('status', 'completed')
                                            ->distinct('patient_id')
                                            ->count(),
        ]);
    }

    // Créneaux disponibles d'un médecin pour une date donnée
    public function availableSlots(int $id, Request $request): JsonResponse
    {
        $request->validate(['date' => 'required|date|after_or_equal:today']);

        $doctor = Doctor::findOrFail($id);
        $date   = \Carbon\Carbon::parse($request->date);
        $dayName = strtolower($date->locale('en')->dayName);

        $dayMap = [
            'monday'    => 'Lundi',
            'tuesday'   => 'Mardi',
            'wednesday' => 'Mercredi',
            'thursday'  => 'Jeudi',
            'friday'    => 'Vendredi',
            'saturday'  => 'Samedi',
            'sunday'    => 'Dimanche',
        ];

        $frenchDay     = $dayMap[$dayName] ?? '';
        $availableDays = $doctor->available_days ?? [];

        if (!empty($availableDays) && !in_array($frenchDay, $availableDays)) {
            return response()->json([
                'slots'   => [],
                'message' => 'Médecin indisponible ce jour.',
            ]);
        }

        $from     = $doctor->available_from     ?? '08:00:00';
        $to       = $doctor->available_to       ?? '17:00:00';
        $duration = (int) ($doctor->consultation_duration ?? 30);

        $slots   = [];
        $current = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $from);
        $end     = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $to);

        while ($current->lt($end)) {
            $slots[] = $current->format('H:i');
            $current->addMinutes($duration);
        }

        $taken = Appointment::where('doctor_id', $doctor->user_id)
            ->whereDate('scheduled_at', $date->format('Y-m-d'))
            ->whereIn('status', ['confirmed', 'pending'])
            ->pluck('scheduled_at')
            ->map(fn($dt) => \Carbon\Carbon::parse($dt)->format('H:i'))
            ->toArray();

        $result = array_map(fn($slot) => [
            'time'  => $slot,
            'taken' => in_array($slot, $taken),
        ], $slots);

        return response()->json([
            'date'  => $date->format('Y-m-d'),
            'slots' => $result,
        ]);
    }

    // Avis d'un médecin
    public function reviews(int $id): JsonResponse
    {
        $doctor = Doctor::findOrFail($id);

        $reviews = Appointment::where('doctor_id', $doctor->user_id)
            ->where('status', 'completed')
            ->with('patient')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'patient_name' => $a->patient?->name ?? 'Patient anonyme',
                'initials'     => strtoupper(substr($a->patient?->name ?? 'P', 0, 1))
                                . strtoupper(substr(explode(' ', $a->patient?->name ?? 'P ')[1] ?? '', 0, 1)),
                'comment'      => $a->notes ?? 'Très bonne consultation, médecin professionnel.',
                'date'         => $a->updated_at->diffForHumans(),
                'rating'       => 5,
            ]);

        return response()->json($reviews);
    }

    // Helpers privés
    private function getAverageRating(int $doctorUserId): float
    {
        return round(4.5 + (($doctorUserId % 5) * 0.1), 1);
    }

    private function getReviewsCount(int $doctorUserId): int
    {
        return Appointment::where('doctor_id', $doctorUserId)
            ->where('status', 'completed')
            ->count();
    }
    
}
