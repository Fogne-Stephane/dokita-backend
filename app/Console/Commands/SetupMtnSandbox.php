<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupMtnSandbox extends Command
{
    protected $signature   = 'mtn:setup';
    protected $description = 'Créer un utilisateur API MTN MoMo sandbox';

    public function handle(): void
    {
        $subscriptionKey = config('services.mtn.subscription_key');

        if (!$subscriptionKey) {
            $this->error('MTN_MOMO_SUBSCRIPTION_KEY manquant dans .env');
            return;
        }

       $client = new Client([
    'base_uri' => 'https://sandbox.momodeveloper.mtn.com',
    'verify'   => false, // Désactive SSL pour le sandbox Windows
]);
        $referenceId = Str::uuid()->toString();

        $this->info('Création de l\'utilisateur API...');

        try {
            // Étape 1 — Créer l'utilisateur
            $client->post('/v1_0/apiuser', [
                'headers' => [
                    'X-Reference-Id'            => $referenceId,
                    'Ocp-Apim-Subscription-Key' => $subscriptionKey,
                    'Content-Type'              => 'application/json',
                ],
                'json' => [
                    'providerCallbackHost' => 'https://dokita.cm',
                ],
            ]);

            $this->info('✅ Utilisateur créé : ' . $referenceId);

            // Étape 2 — Récupérer la clé API
            $response = $client->post("/v1_0/apiuser/{$referenceId}/apikey", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $subscriptionKey,
                ],
            ]);

            $data   = json_decode($response->getBody()->getContents(), true);
            $apiKey = $data['apiKey'];

            $this->info('✅ API Key générée : ' . $apiKey);
            $this->newLine();
            $this->line('══════════════════════════════════════════════');
            $this->info('Ajoute ces lignes dans ton fichier .env :');
            $this->newLine();
            $this->line('MTN_MOMO_API_USER=' . $referenceId);
            $this->line('MTN_MOMO_API_KEY=' . $apiKey);
            $this->newLine();
            $this->line('══════════════════════════════════════════════');

        } catch (\Exception $e) {
            $this->error('Erreur : ' . $e->getMessage());
        }
    }
}