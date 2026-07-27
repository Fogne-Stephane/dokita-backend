<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
public function show(Request $request): JsonResponse
{
    $user = $request->user();
    
    try {
        $profile = $user->role === 'doctor' 
            ? \App\Models\Doctor::where('user_id', $user->id)->first()
            : \App\Models\Patient::where('user_id', $user->id)->first();
    } catch (\Exception $e) {
        $profile = null;
    }

    return response()->json([
        'user'    => [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'avatar_url' => $user->avatar ? \Illuminate\Support\Facades\Storage::url($user->avatar) : null,
        ],
        'profile' => $profile,
    ]);
}

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->only(['name', 'phone']));

        if ($user->role === 'patient' && $user->patient) {
            $user->patient->update($request->only([
                'birth_date','gender','blood_type','allergies',
                'chronic_diseases','city','address',
                'emergency_contact_name','emergency_contact_phone',
            ]));
        }

        if ($user->role === 'doctor' && $user->doctor) {
            $user->doctor->update($request->only([
                'specialty','bio','consultation_fee','consultation_duration',
                'is_available','available_days','available_from','available_to',
            ]));
        }

        return response()->json([
            'message' => 'Profil mis à jour.',
            'user'    => $user->fresh(),
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();

        // Supprimer l'ancien avatar
        if ($user->avatar) {
            Storage::delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json([
            'message'    => 'Photo mise à jour.',
            'avatar_url' => Storage::url($path),
        ]);
    }
}