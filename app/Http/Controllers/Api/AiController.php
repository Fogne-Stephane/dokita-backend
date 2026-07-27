<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $systemPrompt = "Tu es Dokita AI, un assistant médical bienveillant au service des patients camerounais. Tu réponds en français. Tu peux donner des informations médicales générales, aider à comprendre des symptômes et orienter vers un médecin si nécessaire. Tu ne donnes jamais de diagnostic définitif. Pour toute urgence, tu recommandes d'appeler le 15 ou de consulter un médecin immédiatement. Tu connais le contexte camerounais (maladies tropicales, paludisme, typhoïde, etc.).";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => 'https://dokita.cm',
                'X-Title'       => 'Dokita AI',
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                'model'    => 'openai/gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system',  'content' => $systemPrompt],
                    ['role' => 'user',    'content' => $request->message],
                ],
                'max_tokens'  => 500,
                'temperature' => 0.7,
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Service IA indisponible.'], 503);
            }

            $content = $response->json('choices.0.message.content', 'Désolé, je n\'ai pas pu traiter votre demande.');

            return response()->json(['reply' => $content]);

        } catch (\Exception $e) {
            Log::error('AI Chat error: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur interne.'], 500);
        }
    }
}