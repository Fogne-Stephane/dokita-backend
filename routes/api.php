<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ProfileController;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

Route::get('/doctors', [DoctorController::class, 'index']);
Route::get('/doctors/{id}/slots',   [DoctorController::class, 'availableSlots']);
Route::get('/doctors/{id}/public',   [DoctorController::class, 'publicProfile']);
Route::get('/doctors/{id}/reviews',  [DoctorController::class, 'reviews']);


    Route::middleware('auth:sanctum')->group(function () {
    // Webhooks publics (pas d'auth requise — appelés par MTN/Orange)
    // Dans le groupe auth:sanctum, avant les groupes patient/doctor
    
Route::get('/profile/me',     [ProfileController::class, 'show']);
Route::post('/profile/update', [ProfileController::class, 'update']);
Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::post('/webhooks/mtn',    [PaymentController::class, 'webhookMtn']);
    Route::post('/webhooks/orange', [PaymentController::class, 'webhookOrange']);
    Route::get('/me',      [AuthController::class, 'me']);
    // Fiche médecin + créneaux
Route::get('/doctors/{id}',          [DoctorController::class, 'show']);
Route::get('/doctors/{id}/slots',     [DoctorController::class, 'availableSlots']);
Route::post('/appointments',          [AppointmentController::class, 'store']);
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password',  [AuthController::class, 'resetPassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/online-status', [MessageController::class, 'onlineStatus']);

    Route::middleware(CheckRole::class . ':patient')->prefix('patient')->group(function () {
        Route::get('/doctors/{id}/profile', [DoctorController::class, 'publicProfile']);
        Route::get('/appointments',        [AppointmentController::class, 'patientIndex']);
        Route::post('/appointments',       [AppointmentController::class, 'store']);
        Route::patch('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
        Route::post('/consultations/{id}/end',             [ConsultationController::class, 'end']);
        Route::get('/profile',  [PatientController::class, 'show']);
        Route::put('/profile',  [PatientController::class, 'update']);
        Route::get('/prescriptions', [PrescriptionController::class, 'patientIndex']);
        Route::get('/messages',           [MessageController::class, 'index']);
        Route::post('/messages',          [MessageController::class, 'store']);
        Route::get('/consultations/{appointmentId}/waiting', [ConsultationController::class, 'waitingRoom']);
        Route::get('/messages/{userId}',  [MessageController::class, 'conversation']);
        Route::post('/payments/initiate',      [PaymentController::class, 'initiate']);
        Route::get('/payments/{id}/status',    [PaymentController::class, 'checkStatus']);
        Route::get('/payments/history',        [PaymentController::class, 'history']);
        Route::post('/payments/{id}/simulate-confirm', [PaymentController::class, 'simulateConfirm']);
        Route::get('/medical-record',              [MedicalRecordController::class, 'patientRecord']);
        Route::get('/medical-record/documents',    [MedicalRecordController::class, 'documents']);
        Route::get('/medical-record/consultations',[MedicalRecordController::class, 'consultations']);
        Route::post('/consultations/request',              [ConsultationController::class, 'request']);
        Route::get('/consultations/{id}/waiting',          [ConsultationController::class, 'waitingRoom']);
        Route::get('/messages/consultation/{doctorId}',    [MessageController::class, 'consultationChat']);
        Route::post('/messages/consultation',              [MessageController::class, 'sendConsultation']);
        Route::get('/appointments/pending/{id}', [AppointmentController::class, 'showPending']);
    });

    Route::middleware(CheckRole::class . ':doctor')->prefix('doctor')->group(function () {
        Route::get('/appointments',               [AppointmentController::class, 'doctorIndex']);
        Route::patch('/appointments/{id}/confirm', [AppointmentController::class, 'confirm']);
        Route::patch('/appointments/{id}/cancel',  [AppointmentController::class, 'cancel']);
        Route::get('/patients', [PatientController::class, 'doctorPatients']);
        Route::get('/prescriptions',  [PrescriptionController::class, 'doctorIndex']);
        Route::post('/prescriptions', [PrescriptionController::class, 'store']);
        Route::get('/profile', [DoctorController::class, 'show']);
        Route::put('/profile', [DoctorController::class, 'update']);
        Route::post('/consultations/{appointmentId}/start', [ConsultationController::class, 'start']);
        Route::post('/consultations/{appointmentId}/end',   [ConsultationController::class, 'end']);
        Route::get('/messages',          [MessageController::class, 'index']);
        Route::post('/messages',         [MessageController::class, 'store']);
        Route::get('/messages/{userId}', [MessageController::class, 'conversation']);
        Route::get('/payments', [PaymentController::class, 'doctorPayments']);
        Route::get('/notifications',                       [ConsultationController::class, 'doctorNotifications']);
        Route::post('/consultations/{id}/accept',          [ConsultationController::class, 'accept']);
        Route::post('/consultations/{id}/reject',          [ConsultationController::class, 'reject']);
        Route::post('/consultations/{id}/end',             [ConsultationController::class, 'end']);
    });

    Route::middleware(CheckRole::class . ':admin')->prefix('admin')->group(function () {
        Route::get('/users',                      [PatientController::class, 'adminIndex']);
        Route::patch('/users/{id}/toggle-block',  [PatientController::class, 'toggleBlock']);
        Route::get('/doctors',                    [DoctorController::class, 'adminIndex']);
        Route::patch('/doctors/{id}/verify',      [DoctorController::class, 'verify']);
        Route::patch('/doctors/{id}/reject',      [DoctorController::class, 'reject']);
        
    });
});