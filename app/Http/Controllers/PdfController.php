<?php

namespace App\Http\Controllers;

use App\Services\PdfParserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    protected PdfParserService $pdfParser;

    public function __construct(PdfParserService $pdfParser)
    {
        $this->pdfParser = $pdfParser;
    }

    /**
     * Extraire le texte brut / énoncé depuis un fichier PDF uploadé
     */
    public function extractText(Request $request): JsonResponse
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480', // 20 MB max
        ], [
            'pdf.required' => 'Veuillez fournir un fichier PDF.',
            'pdf.mimes'    => 'Le fichier doit être au format PDF.',
            'pdf.max'      => 'Le fichier PDF ne doit pas dépasser 20 Mo.',
        ]);

        $file = $request->file('pdf');

        try {
            $extractedText = $this->pdfParser->extractText($file);

            if (empty($extractedText)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun texte extractible trouvé dans ce PDF (il s\'agit peut-être d\'un document scanné sous forme d\'image pure).',
                    'text'    => '',
                ], 422);
            }

            return response()->json([
                'success'   => true,
                'filename'  => $file->getClientOriginalName(),
                'size'      => $file->getSize(),
                'text'      => $extractedText,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du traitement du fichier PDF : ' . $e->getMessage(),
            ], 500);
        }
    }
}
