<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Transcription haute fidélité de PDF scannés (texte + formules LaTeX + repérage des figures)
 * via l'API Gemini Vision.
 */
class PdfVisionService
{
    public function isConfigured(): bool
    {
        return !empty(config('services.gemini.api_key'));
    }

    /**
     * Transcrit un PDF (natif ou scanné) en texte propre avec formules LaTeX ($...$ / $$...$$).
     *
     * @throws RuntimeException
     */
    public function extractWithFormulas(UploadedFile $pdf): string
    {
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY non configurée.');
        }

        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $bytes = file_get_contents($pdf->getRealPath());
        if ($bytes === false) {
            throw new RuntimeException('Impossible de lire le fichier PDF.');
        }

        $prompt = <<<'PROMPT'
Tu es un expert en transcription de sujets d'examen scolaires francophones (BEPC, Baccalauréat, Mathématiques, Physique-Chimie, SVT, Français, Histoire-Géographie).

Transcris EXACTEMENT et INTÉGRALEMENT le contenu de ce document PDF en français :
1. Respecte fidèlement la structure originale (en-tête, Exercice 1, Partie A, 1., a), barèmes, consignes).
2. Convertis TOUTES les formules mathématiques, physiques et chimiques en syntaxe LaTeX compatible KaTeX :
   - Formules dans le texte : `$...$` (ex: `$f(x) = \frac{x^2 + 1}{x - 1}$`, `$\vec{AB}$`, `$\text{H}_2\text{SO}_4$`).
   - Formules centrées / blocs d'équations : `$$...$$` sur leur propre ligne.
3. Lorsqu'une figure géométrique, un graphe, un circuit électrique ou un schéma apparaît dans le sujet, insère exactement à sa place une ligne :
   `> [Figure / Schéma : courte description précise — consulter le PDF original]`
4. Ne résous PAS les exercices, n'ajoute aucun commentaire introductif ni conclusif : renvoie UNIQUEMENT la transcription propre du sujet.
PROMPT;

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($model),
            rawurlencode($apiKey)
        );

        $response = Http::timeout(55)->post($endpoint, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => 'application/pdf',
                                'data'      => base64_encode($bytes),
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
            ],
        ]);

        if (!$response->successful()) {
            Log::warning('Gemini Vision PDF extraction failed', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
            ]);
            throw new RuntimeException('Échec de la transcription Vision IA.');
        }

        $parts = $response->json('candidates.0.content.parts', []);
        $text = collect($parts)
            ->pluck('text')
            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->implode("\n");

        $clean = preg_replace("/\r\n|\r/", "\n", $text);
        $clean = preg_replace("/\n{3,}/", "\n\n", (string) $clean);

        return trim((string) $clean);
    }
}
