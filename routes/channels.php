<?php

use Illuminate\Support\Facades\Broadcast;

// Canal privé de chat entre deux utilisateurs
Broadcast::channel('chat.{userId1}.{userId2}', function ($user, $userId1, $userId2) {
    // L'utilisateur peut rejoindre ce canal s'il est l'un des deux participants
    return (int) $user->id === (int) $userId1 ||
           (int) $user->id === (int) $userId2;
});