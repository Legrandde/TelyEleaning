<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;

class PdfParserService
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Extraire le texte propre d'un fichier PDF
     *
     * @param string|UploadedFile $pdf Chemin absolu ou instance UploadedFile
     * @return string Texte extrait et formaté
     * @throws Exception
     */
    public function extractText(string|UploadedFile $pdf): string
    {
        $path = $pdf instanceof UploadedFile ? $pdf->getRealPath() : $pdf;

        if (!file_exists($path)) {
            throw new Exception("Le fichier PDF spécifié n'existe pas : {$path}");
        }

        $pdfDoc = $this->parser->parseFile($path);
        $rawText = $pdfDoc->getText();

        // Normalisation des sauts de ligne
        $cleanText = preg_replace("/\r\n|\r/", "\n", $rawText);
        // Suppression des espaces multiples sur une même ligne
        $cleanText = preg_replace('/[ \t]+/', ' ', $cleanText);
        // Suppression des sauts de ligne excessifs (max 2)
        $cleanText = preg_replace("/\n{3,}/", "\n\n", $cleanText);

        return trim($cleanText);
    }
}
