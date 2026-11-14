<?php

namespace App\Http\Controllers;

use App\Models\AbonneNewsletter;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NewsletterController extends Controller
{
    /**
     * Inscription publique à la newsletter depuis la Landing Page.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $resultat = NewsletterService::inscrire($validated['email']);

        return response()->json($resultat, 201);
    }

    /**
     * Liste des abonnés à la newsletter (Espace Admin).
     */
    public function index(): JsonResponse
    {
        $abonnes = AbonneNewsletter::query()
            ->latest('created_at')
            ->paginate(30);

        $stats = [
            'total'      => AbonneNewsletter::count(),
            'actifs'     => AbonneNewsletter::whereNotNull('consenti_le')->whereNull('desabonne_le')->count(),
            'desabonnes' => AbonneNewsletter::whereNotNull('desabonne_le')->count(),
        ];

        return response()->json([
            'abonnes' => $abonnes,
            'stats'   => $stats,
        ]);
    }

    /**
     * Page de confirmation d'inscription via lien signé.
     */
    public function confirmer(Request $request, AbonneNewsletter $abonne): Response
    {
        if ($abonne->desabonne_le !== null) {
            return $this->page('Lien périmé', 'Cette adresse s’est désabonnée depuis. Pour recevoir de nouveau la newsletter, inscrivez-vous depuis le site.');
        }

        if ($abonne->consenti_le !== null) {
            return $this->page('Inscription confirmée', 'Vous recevrez la newsletter de Tely Elearning à chaque nouveauté. Chaque e-mail comporte un lien pour vous désabonner en un clic.');
        }

        if ($request->isMethod('get')) {
            $action = e($request->fullUrl());

            return $this->page(
                'Confirmer votre inscription',
                'Un clic pour confirmer votre abonnement à la newsletter de Tely Elearning : nouveaux exercices, sujets d’examen et corrigés.',
                "<form method=\"post\" action=\"{$action}\"><button type=\"submit\" style=\"font: inherit; font-weight: 700; padding: .75rem 1.5rem; border: 0; border-radius: .5rem; background: #F59E0B; color: #0F172A; cursor: pointer\">Confirmer mon inscription</button></form>"
            );
        }

        $abonne->forceFill(['consenti_le' => now()])->save();

        return $this->page('Inscription confirmée', 'Vous recevrez la newsletter de Tely Elearning à chaque nouveauté. Chaque e-mail comporte un lien pour vous désabonner.');
    }

    /**
     * Désabonnement via lien signé (supporte GET avec bouton et POST One-Click RFC 8058).
     */
    public function desabonner(Request $request, AbonneNewsletter $abonne): Response
    {
        if ($abonne->desabonne_le !== null) {
            return $this->page('Vous êtes désabonné', 'Vous ne recevrez plus la newsletter de Tely Elearning. Pour vous réabonner, inscrivez-vous de nouveau depuis le site.');
        }

        if ($request->isMethod('get')) {
            $action = e($request->fullUrl());

            return $this->page(
                'Se désabonner de la newsletter',
                'Un clic pour ne plus recevoir la newsletter de Tely Elearning. Vous pourrez vous réinscrire à tout moment depuis le site.',
                "<form method=\"post\" action=\"{$action}\"><button type=\"submit\" style=\"font: inherit; font-weight: 700; padding: .75rem 1.5rem; border: 0; border-radius: .5rem; background: #0F172A; color: #FFFFFF; cursor: pointer\">Me désabonner</button></form>"
            );
        }

        $abonne->forceFill(['desabonne_le' => now()])->save();

        return $this->page('Vous êtes désabonné', 'Vous ne recevrez plus la newsletter de Tely Elearning. Pour vous réabonner, inscrivez-vous de nouveau depuis le site.');
    }

    private function page(string $titre, string $texte, string $geste = ''): Response
    {
        $front = e((string) config('app.frontend_url', 'http://localhost:5173'));
        $titre = e($titre);
        $texte = e($texte);

        return response(<<<HTML
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex">
                <title>{$titre} — Tely Elearning</title>
            </head>
            <body style="font-family: system-ui, -apple-system, sans-serif; background: #FAFAF9; max-width: 36rem; margin: 4rem auto; padding: 0 1.5rem; color: #0F172A; line-height: 1.6">
                <div style="background: #FFFFFF; padding: 2rem; border-radius: 1rem; border: 1px solid #E2E8F0; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                    <div style="font-weight: 800; font-size: 1.25rem; margin-bottom: 1rem;">Tely<span style="color: #F59E0B;">.</span> Elearning</div>
                    <h1 style="font-size: 1.4rem; margin-top: 0;">{$titre}</h1>
                    <p style="color: #475569;">{$texte}</p>
                    <div style="margin: 1.5rem 0;">{$geste}</div>
                    <p style="margin-bottom: 0; font-size: 0.9rem;"><a href="{$front}" style="color: #D97706; font-weight: 600; text-decoration: underline;">Revenir sur Tely Elearning</a></p>
                </div>
            </body>
            </html>
            HTML);
    }
}
