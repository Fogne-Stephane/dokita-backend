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
    // Envoyer le lien de réinitialisation
public function forgotPassword(Request $request): JsonResponse
{
    $request->validate(['email' => 'required|email']);

    $user = \App\Models\User::where('email', $request->email)->first();

    if (!$user) {
        // On retourne succès même si l'email n'existe pas (sécurité)
        return response()->json([
            'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.'
        ]);
    }

    // Générer un token de reset
    $token = \Illuminate\Support\Str::random(64);

    // Sauvegarder en base
    \DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $request->email],
        [
            'email'      => $request->email,
            'token'      => bcrypt($token),
            'created_at' => now(),
        ]
    );

    // Envoyer l'email (via Mailtrap en dev)
    $user->notify(new \App\Notifications\ResetPasswordNotification($token));

    return response()->json([
        'message' => 'Lien de réinitialisation envoyé à ' . $request->email
    ]);
}

// Réinitialiser le mot de passe
public function resetPassword(Request $request): JsonResponse
{
    $request->validate([
        'email'                 => 'required|email',
        'token'                 => 'required|string',
        'password'              => 'required|string|min:8|confirmed',
    ]);

    $record = \DB::table('password_reset_tokens')
        ->where('email', $request->email)
        ->first();

    if (!$record || !\Hash::check($request->token, $record->token)) {
        return response()->json(['message' => 'Token invalide ou expiré.'], 400);
    }

    // Vérifier que le token n'a pas expiré (1 heure)
    if (now()->diffInMinutes($record->created_at) > 60) {
        return response()->json(['message' => 'Token expiré. Refaites une demande.'], 400);
    }

    $user = \App\Models\User::where('email', $request->email)->firstOrFail();
    $user->update(['password' => \Hash::make($request->password)]);

    // Supprimer le token utilisé
    \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

    // Supprimer tous les tokens Sanctum existants
    $user->tokens()->delete();

    return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
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