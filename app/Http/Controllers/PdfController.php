<?php

namespace App\Http\Controllers;

use App\Services\PdfParserService;
use App\Services\PdfVisionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    protected PdfParserService $pdfParser;
    protected PdfVisionService $pdfVision;

    public function __construct(PdfParserService $pdfParser, PdfVisionService $pdfVision)
    {
        $this->pdfParser = $pdfParser;
        $this->pdfVision = $pdfVision;
    }

    /**
     * Extraire le texte brut / énoncé depuis un fichier PDF uploadé.
     * Utilise automatiquement Gemini Vision (formules LaTeX + scans) si configuré
     * lorsque le PDF est scanné ou que `force_vision` est demandé.
     */
    public function extractText(Request $request): JsonResponse
    {
        $rules = [
            'pdf'          => 'required|file|mimes:pdf|max:20480', // 20 MB max
            'force_vision' => 'sometimes|boolean',
        ];

        $request->validate($rules, [
            'pdf.required' => 'Veuillez fournir un fichier PDF.',
            'pdf.mimes'    => 'Le fichier doit être au format PDF.',
            'pdf.max'      => 'Le fichier PDF ne doit pas dépasser 20 Mo.',
        ]);

        $file = $request->file('pdf');
        $forceVision = $request->boolean('force_vision');

        try {
            $extractedText = '';
            $source = 'serveur';

            if (!$forceVision) {
                $extractedText = $this->pdfParser->extractText($file);
            }

            // Si PDF scanné (texte vide) ou extraction Vision demandée (formules LaTeX)
            if (($forceVision || mb_strlen($extractedText) < 40) && $this->pdfVision->isConfigured()) {
                try {
                    $visionText = $this->pdfVision->extractWithFormulas($file);
                    if ($visionText !== '') {
                        $extractedText = $visionText;
                        $source = 'gemini_vision';
                    }
                } catch (Exception $visionError) {
                    report($visionError);
                    // Si le parser texte avait déjà renvoyé quelque chose, on le garde
                }
            }

            if (empty($extractedText)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun texte extractible trouvé dans ce PDF (il s\'agit peut-être d\'un document scanné sous forme d\'image pure).',
                    'text'    => '',
                ], 422);
            }

            return response()->json([
                'success'  => true,
                'source'   => $source,
                'filename' => $file->getClientOriginalName(),
                'size'     => $file->getSize(),
                'text'     => $extractedText,
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du traitement du fichier PDF. Vérifiez que le fichier n\'est pas protégé ou corrompu.',
            ], 500);
        }
    }
}
