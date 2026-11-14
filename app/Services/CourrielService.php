<?php

namespace App\Services;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Service centralisé d'envoi des e-mails de Tely Elearning.
 * Chaque e-mail part en HTML aux couleurs de la charte (`resources/views/emails`) avec sa version en texte brut.
 *
 * - `envoyer` diffère l'expédition SMTP après l'envoi de la réponse HTTP (`app()->terminating(...)`)
 *   afin que l'interface reste instantanée (pas de latence de 1 à 3 s).
 * - `envoyerNonSollicite` protège les formulaires publics (newsletter, candidature) avec un double rate-limiting.
 * - `envoyerMaintenant` et `envoyerLettre` envoient immédiatement (tâches planifiées ou commandes Artisan).
 */
final class CourrielService
{
    public const NON_SOLLICITES_PAR_HEURE = 300;
    public const NON_SOLLICITES_PAR_DESTINATAIRE = 4;
    private const CLE_NON_SOLLICITES = 'courriels-publics:global';

    /**
     * @param array{0: string, 1: string}|null $bouton [libellé, url]
     */
    public static function envoyer(?string $adresse, string $sujet, string $texte, ?array $bouton = null, ?string $desabonnement = null): void
    {
        if ($adresse === null || trim($adresse) === '') {
            return;
        }

        $parti = false;

        app()->terminating(static function () use ($adresse, $sujet, $texte, $bouton, $desabonnement, &$parti): void {
            if ($parti) {
                return;
            }

            $parti = true;
            self::envoyerMaintenant($adresse, $sujet, $texte, $desabonnement, $bouton);
        });
    }

    /**
     * Envoi protégé depuis un formulaire public sans authentification.
     *
     * @param array{0: string, 1: string}|null $bouton
     */
    public static function envoyerNonSollicite(?string $adresse, string $sujet, string $texte, ?array $bouton = null, ?string $desabonnement = null): bool
    {
        if ($adresse === null || trim($adresse) === '') {
            return false;
        }

        $destinataire = 'courriels-publics:' . hash('sha256', mb_strtolower(trim($adresse)));

        if (
            RateLimiter::tooManyAttempts(self::CLE_NON_SOLLICITES, self::NON_SOLLICITES_PAR_HEURE)
            || RateLimiter::tooManyAttempts($destinataire, self::NON_SOLLICITES_PAR_DESTINATAIRE)
        ) {
            Log::warning('E-mail public retenu : plafond d’envoi atteint', ['sujet' => $sujet, 'email' => $adresse]);
            return false;
        }

        RateLimiter::hit(self::CLE_NON_SOLLICITES, 3600);
        RateLimiter::hit($destinataire, 3600);
        self::envoyer($adresse, $sujet, $texte, $bouton, $desabonnement);

        return true;
    }

    /**
     * @param array{0: string, 1: string}|null $bouton
     */
    public static function envoyerMaintenant(string $adresse, string $sujet, string $texte, ?string $desabonnement = null, ?array $bouton = null): bool
    {
        $brut = $texte . ($bouton === null ? '' : "\n\n{$bouton[0]} : {$bouton[1]}");

        return self::partir($adresse, $sujet, 'emails.message', [
            'texte'     => $texte,
            'bouton'    => $bouton,
            'preheader' => self::apercu($texte),
        ], $brut, $desabonnement);
    }

    /**
     * Envoi de la lettre d'information (newsletter) avec cartes de nouveautés.
     *
     * @param list<array<string, mixed>> $nouvelles
     */
    public static function envoyerLettre(string $adresse, string $sujet, array $nouvelles, string $brut, string $desabonnement): bool
    {
        $apercu = collect($nouvelles)->pluck('titre')->implode(' · ');

        return self::partir($adresse, $sujet, 'emails.lettre', [
            'nouvelles' => $nouvelles,
            'preheader' => mb_substr($apercu, 0, 140),
        ], $brut, $desabonnement);
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private static function partir(string $adresse, string $sujet, string $vue, array $donnees, string $brut, ?string $desabonnement): bool
    {
        $front = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $donnees = [...$donnees, 'front' => $front, 'desabonnement' => $desabonnement];
        $brut .= "\n\n—\nTely Elearning" . ($desabonnement === null ? '' : "\nNe plus recevoir ces e-mails : {$desabonnement}");

        try {
            Mail::send(['html' => $vue, 'raw' => $brut], $donnees, function (Message $mail) use ($adresse, $sujet, $desabonnement): void {
                $mail->to($adresse)->subject($sujet);

                if ($desabonnement !== null) {
                    $entetes = $mail->getSymfonyMessage()->getHeaders();
                    $entetes->addTextHeader('List-Unsubscribe', "<{$desabonnement}>");
                    $entetes->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                }
            });

            return true;
        } catch (Throwable $e) {
            Log::warning('E-mail non envoyé', [
                'adresse' => $adresse,
                'sujet'   => $sujet,
                'erreur'  => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function apercu(string $texte): string
    {
        $sansSalut = preg_replace('/^Bonjour[^\n]*\n+/u', '', trim($texte));

        return mb_substr((string) preg_replace('/\s+/u', ' ', (string) $sansSalut), 0, 140);
    }
}
