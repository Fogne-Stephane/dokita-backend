<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Str;

class MtnMomoService
{
    private Client $client;
    private string $baseUrl;
    private string $subscriptionKey;
    private string $environment;
    private string $currency;

    public function __construct()
    {
        $this->baseUrl         = config('services.mtn.base_url');
        $this->subscriptionKey = config('services.mtn.subscription_key');
        $this->environment     = config('services.mtn.environment', 'sandbox');
        $this->currency        = config('services.mtn.currency', 'XAF');

$this->client = new Client([
    'base_uri' => $this->baseUrl,
    'verify'   => false, // Désactive SSL pour le sandbox
    'headers'  => [
        'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        'Content-Type'              => 'application/json',
    ],
]);
    }

    // Étape 1 — Créer un utilisateur API (sandbox seulement, une seule fois)
    public function createApiUser(): array
    {
        $referenceId = Str::uuid()->toString();

        $this->client->post('/v1_0/apiuser', [
            'headers' => [
                'X-Reference-Id'            => $referenceId,
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            ],
            'json' => [
                'providerCallbackHost' => config('services.mtn.callback_url'),
            ],
        ]);

        return ['api_user' => $referenceId];
    }

    // Étape 2 — Créer une clé API pour l'utilisateur
    public function createApiKey(string $apiUserId): string
    {
        $response = $this->client->post("/v1_0/apiuser/{$apiUserId}/apikey", [
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['apiKey'];
    }

    // Étape 3 — Obtenir un token d'accès
    public function getAccessToken(): string
    {
        $apiUser = config('services.mtn.api_user');
        $apiKey  = config('services.mtn.api_key');

        $response = $this->client->post('/collection/token/', [
            'headers' => [
                'Authorization'             => 'Basic ' . base64_encode("{$apiUser}:{$apiKey}"),
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['access_token'];
    }

    // Étape 4 — Initier un paiement (Request to Pay)
    public function requestToPay(
        string $phone,
        float  $amount,
        string $externalId,
        string $note = 'Consultation DOKITA'
    ): array {
        $referenceId  = Str::uuid()->toString();
        $accessToken  = $this->getAccessToken();

        try {
            $this->client->post('/collection/v1_0/requesttopay', [
                'headers' => [
                    'Authorization'             => "Bearer {$accessToken}",
                    'X-Reference-Id'            => $referenceId,
                    'X-Target-Environment'      => $this->environment,
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                ],
                'json' => [
                    'amount'     => (string) $amount,
                    'currency'   => $this->currency,
                    'externalId' => $externalId,
                    'payer'      => [
                        'partyIdType' => 'MSISDN',
                        'partyId'     => $this->formatPhone($phone),
                    ],
                    'payerMessage' => $note,
                    'payeeNote'    => $note,
                ],
            ]);

            return [
                'success'      => true,
                'reference_id' => $referenceId,
                'status'       => 'pending',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    // Vérifier le statut d'un paiement
    public function checkPaymentStatus(string $referenceId): array
    {
        $accessToken = $this->getAccessToken();

        try {
            $response = $this->client->get("/collection/v1_0/requesttopay/{$referenceId}", [
                'headers' => [
                    'Authorization'             => "Bearer {$accessToken}",
                    'X-Target-Environment'      => $this->environment,
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'status'  => strtolower($data['status']), // successful, failed, pending
                'data'    => $data,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    // Formater le numéro de téléphone camerounais
    private function formatPhone(string $phone): string
    {
        // Supprimer les espaces et le +
        $phone = preg_replace('/[\s\+]/', '', $phone);

        // Ajouter le préfixe 237 si pas présent
        if (!str_starts_with($phone, '237')) {
            $phone = '237' . $phone;
        }

        return $phone;
    }
}