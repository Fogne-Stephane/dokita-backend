<?php

namespace App\Services;

class AgoraService
{
    private string $appId;
    private string $certificate;

    public function __construct()
    {
        $this->appId       = config('services.agora.app_id', '');
        $this->certificate = config('services.agora.certificate', '');
    }

    public function generateToken(string $channel, int $uid): string
    {
        // Si pas de certificate → mode test sans token (OK pour sandbox Agora)
        if (empty($this->certificate)) {
            return '';
        }

        $expireTime  = 3600; // 1 heure
        $currentTime = time();
        $privilegeExpiredTs = $currentTime + $expireTime;

        // Agora Token2 simple (sans dépendance externe)
        return $this->buildToken($channel, $uid, $privilegeExpiredTs);
    }

    private function buildToken(string $channel, int $uid, int $expiredTs): string
    {
        $appId          = $this->appId;
        $appCertificate = $this->certificate;

        $role           = 1; // Publisher
        $version        = '006';
        $nonce          = random_int(1, 999999);
        $currentTs      = time();

        // Message à signer
        $message = $appId . $nonce . $currentTs . $expiredTs . $channel . $uid . $role;

        // HMAC-SHA256
        $signature = hash_hmac('sha256', $message, $appCertificate);

        // Construire le token
        $content = pack('NNN', $nonce, $currentTs, $expiredTs)
                 . chr($role)
                 . pack('n', strlen($channel)) . $channel
                 . pack('N', $uid)
                 . hex2bin($signature);

        return $version . $appId . base64_encode($content);
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function isConfigured(): bool
    {
        return !empty($this->appId);
    }
}