<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Str;

class OrangeMoneyService
{
    private Client $client;
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $merchantKey;

    public function __construct()
    {
        $this->baseUrl       = config('services.orange.base_url');
        $this->clientId      = config('services.orange.client_id');
        $this->clientSecret  = config('services.orange.client_secret');
        $this->merchantKey   = config('services.orange.merchant_key');

$this->client = new Client([
    'base_uri' => 'https://api.orange.com',
    'verify'   => false,
]);
    }

    // Obtenir un token OAuth
    public function getAccessToken(): string
    {
        $response = $this->client->post('/oauth/v3/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(
                    "{$this->clientId}:{$this->clientSecret}"
                ),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['access_token'];
    }

    // Initier un paiement
    public function initPayment(
        string $phone,
        float  $amount,
        string $orderId,
        string $description = 'Consultation DOKITA'
    ): array {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->post(
                '/orange-money-webpay/cm/v1/webpayment',
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'merchant_key'  => $this->merchantKey,
                        'currency'      => 'OUV',
                        'order_id'      => $orderId,
                        'amount'        => $amount,
                        'return_url'    => config('services.orange.callback_url'),
                        'cancel_url'    => config('services.orange.callback_url'),
                        'notif_url'     => config('services.orange.callback_url'),
                        'lang'          => 'fr',
                        'reference'     => $orderId,
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success'      => true,
                'payment_url'  => $data['payment_url'] ?? null,
                'pay_token'    => $data['pay_token'] ?? null,
                'order_id'     => $orderId,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}