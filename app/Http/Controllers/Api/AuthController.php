<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // -----------------------------------------------
    // INSCRIPTION
    // -----------------------------------------------
    public function register(RegisterRequest $request): JsonResponse
    {
        // 1. Créer le compte utilisateur
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->phone,
            'role'     => $request->role,
        ]);

        // 2. Assigner le rôle Spatie
        $user->assignRole($request->role);

        // 3. Créer le profil selon le rôle
        if ($request->role === 'patient') {
            Patient::create(['user_id' => $user->id]);
        } elseif ($request->role === 'doctor') {
            Doctor::create([
                'user_id'        => $user->id,
                'specialty'      => $request->specialty ?? 'Généraliste',
                'license_number' => $request->license_number ?? 'TEMP-' . $user->id,
            ]);
        }

        // 4. Créer le token Sanctum
        $token = $user->createToken('dokita-token')->plainTextToken;

        // 5. Retourner la réponse
        return response()->json([
            'message' => 'Inscription réussie.',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ], 201);
    }

    // -----------------------------------------------
    // CONNEXION
    // -----------------------------------------------
    public function login(LoginRequest $request): JsonResponse
    {
        // 1. Vérifier email + mot de passe
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect.',
            ], 401);
        }

        // 2. Récupérer l'utilisateur
        $user = Auth::user();

        // 3. Vérifier si le compte est actif
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Votre compte a été désactivé.',
            ], 403);
        }

        // 4. Mettre à jour la dernière connexion
        $user->update(['last_login_at' => now()]);

        // 5. Supprimer les anciens tokens et créer un nouveau
        $user->tokens()->delete();
        $token = $user->createToken('dokita-token')->plainTextToken;

        // 6. Retourner la réponse
        return response()->json([
            'message' => 'Connexion réussie.',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    // -----------------------------------------------
    // PROFIL (utilisateur connecté)
    // -----------------------------------------------
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

          $user->update(['last_login_at' => now()]);
        // Charger le profil selon le rôle
        $profile = null;
        if ($user->role === 'patient') {
            $profile = $user->patient;
        } elseif ($user->role === 'doctor') {
            $profile = $user->doctor;
        }

        return response()->json([
            'user'    => $user,
            'profile' => $profile,
        ]);
    }

    // -----------------------------------------------
    // DÉCONNEXION
    // -----------------------------------------------
    public function logout(Request $request): JsonResponse
    {
        // Supprimer le token actuel
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }
}