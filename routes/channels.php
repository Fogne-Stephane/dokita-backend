<?php
use Illuminate\Support\Facades\Broadcast;

// Canal patient
Broadcast::channel('patient.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Canal médecin
Broadcast::channel('doctor.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Canal chat
Broadcast::channel('chat.{id1}.{id2}', function ($user, $id1, $id2) {
    return (int) $user->id === (int) $id1 || (int) $user->id === (int) $id2;
});