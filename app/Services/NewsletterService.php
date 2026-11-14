<?php

namespace App\Services;

use App\Models\AbonneNewsletter;
use App\Models\Exercice;
use App\Models\Ressource;
use App\Models\SujetExamen;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Service de gestion des e-mails métier et de la newsletter de Tely Elearning :
 * - Mail de bienvenue aux nouveaux inscrits (élève, professeur)
 * - Double opt-in et désabonnement de la newsletter
 * - Diffusion automatique des nouveautés (exercices, sujets d'examen, ressources)
 */
final class NewsletterService
{
    public const DEPART = '__depart__';
    public const VALIDITE_JOURS = 7;
    public const RELANCE_MINUTES = 15;

    /**
     * Envoie le mail de bienvenue lors de la création d'un compte.
     */
    public static function souhaiterBienvenue(User $user, ?string $plainPassword = null): void
    {
        if (blank($user->email)) {
            return;
        }

        $front = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $prenom = trim((string) ($user->prenom ?: $user->nom));
        $salut = $prenom !== '' ? "Bonjour {$prenom}," : 'Bonjour,';

        if ($user->role === 'professeur') {
            $sujet = 'Bienvenue dans l’équipe enseignante — Tely Elearning';
            $identifiants = $plainPassword
                ? "\n\nVos identifiants de connexion :\n• Adresse e-mail : {$user->email}\n• Mot de passe temporaire : {$plainPassword}\n(Nous vous invitons à le personnaliser dès votre première connexion depuis votre profil.)"
                : '';

            $texte = "{$salut}\n\nBienvenue sur Tely Elearning ! Votre espace enseignant est désormais actif. Vous pouvez dès maintenant publier des exercices, des sujets d’examen, des corrigés détaillés ainsi que des supports de cours pour accompagner la réussite des élèves.{$identifiants}\n\nMerci pour votre engagement pédagogique,\nL’équipe Tely Elearning";
            $bouton = ['Accéder à mon espace enseignant', "{$front}/auth/login"];
        } else {
            $sujet = 'Bienvenue sur Tely Elearning !';
            $identifiants = $plainPassword
                ? "\n\nVos identifiants de connexion :\n• Adresse e-mail : {$user->email}\n• Mot de passe : {$plainPassword}"
                : '';

            $texte = "{$salut}\n\nBienvenue sur Tely Elearning ! Votre compte est prêt. Accédez dès maintenant à notre bibliothèque d’exercices, d’anciens sujets d’examen et de corrigés détaillés pour réviser efficacement et progresser à votre rythme.{$identifiants}\n\nBonne révision et pleine réussite,\nL’équipe Tely Elearning";
            $bouton = ['Commencer à m’exercer', "{$front}/exercices"];
        }

        CourrielService::envoyer($user->email, $sujet, $texte, $bouton);
    }

    /**
     * Enregistre une demande d'inscription à la newsletter et envoie l'e-mail de confirmation (ou de bienvenue).
     */
    public static function inscrire(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $abonne = AbonneNewsletter::query()->where('email', $email)->first();
        $aEcrire = false;

        if ($abonne === null) {
            $abonne = AbonneNewsletter::create([
                'email'       => $email,
                'demande_le'  => now(),
                'consenti_le' => now(), // Activé immédiatement avec lien de désabonnement + confirmation
            ]);
            $aEcrire = true;
        } elseif ($abonne->desabonne_le !== null) {
            $abonne->update([
                'demande_le'   => now(),
                'consenti_le'  => now(),
                'desabonne_le' => null,
            ]);
            $aEcrire = true;
        } elseif ($abonne->consenti_le === null && ($abonne->demande_le === null || $abonne->demande_le->addMinutes(self::RELANCE_MINUTES)->isPast())) {
            $abonne->update([
                'demande_le'  => now(),
                'consenti_le' => now(),
            ]);
            $aEcrire = true;
        }

        if ($aEcrire && $abonne !== null) {
            $front = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
            $desabonnement = self::lienDesabonnement($abonne);

            CourrielService::envoyerNonSollicite(
                $email,
                'Bienvenue dans la newsletter de Tely Elearning !',
                "Bonjour,\n\nVotre abonnement à la newsletter de Tely Elearning est bien confirmé !\n\nVous recevrez désormais en priorité nos nouveaux exercices corrigés, les sujets d’examen officiels ajoutés à la bibliothèque, ainsi que nos conseils de révision pour préparer vos épreuves.\n\nVous pouvez à tout moment explorer les dernières publications sur la plateforme ou vous désabonner en un clic via le lien au bas de chaque message.",
                ['Explorer les exercices', "{$front}/exercices"],
                $desabonnement
            );
        }

        return [
            'inscrit' => true,
            'message' => 'Merci ! Votre inscription à la newsletter est confirmée. Un e-mail de bienvenue vient de vous être envoyé.',
        ];
    }

    /**
     * Lien signé de confirmation.
     */
    public static function lienConfirmation(AbonneNewsletter $abonne): string
    {
        return URL::temporarySignedRoute('newsletter.confirmer', now()->addDays(self::VALIDITE_JOURS), ['abonne' => $abonne->id]);
    }

    /**
     * Lien signé de désabonnement en un clic.
     */
    public static function lienDesabonnement(AbonneNewsletter $abonne): string
    {
        return URL::signedRoute('newsletter.desabonner', ['abonne' => $abonne->id]);
    }

    /**
     * Diffuse aux abonnés actifs les nouvelles publications (exercices, sujets, ressources) parues depuis le dernier envoi.
     *
     * @return array{nouveautes: int, envoyes: int}
     */
    public static function diffuserNouveautes(): array
    {
        $toutes = collect(self::collecterNouveautes());
        $connues = DB::table('diffusions_newsletter')->pluck('cle')->flip();

        if (!$connues->has(self::DEPART)) {
            DB::table('diffusions_newsletter')->insertOrIgnore([
                ['cle' => self::DEPART, 'annoncee' => false, 'created_at' => now()],
                ...$toutes->map(fn (array $n): array => ['cle' => $n['cle'], 'annoncee' => false, 'created_at' => now()])->all(),
            ]);

            return ['nouveautes' => 0, 'envoyes' => 0];
        }

        $nouvelles = $toutes->reject(fn (array $n): bool => $connues->has($n['cle']))->values();

        if ($nouvelles->isEmpty()) {
            return ['nouveautes' => 0, 'envoyes' => 0];
        }

        DB::table('diffusions_newsletter')->insertOrIgnore(
            $nouvelles->map(fn (array $n): array => ['cle' => $n['cle'], 'annoncee' => true, 'created_at' => now()])->all()
        );

        $sujet = $nouvelles->count() === 1
            ? "Nouveau sur Tely Elearning : {$nouvelles->first()['titre']}"
            : "{$nouvelles->count()} nouvelles ressources sur Tely Elearning";

        $texte = self::texteBrut($nouvelles->all());
        $envoyes = 0;

        AbonneNewsletter::query()
            ->whereNotNull('consenti_le')
            ->whereNull('desabonne_le')
            ->orderBy('id')
            ->chunkById(200, function ($abonnes) use ($sujet, $nouvelles, $texte, &$envoyes): void {
                foreach ($abonnes as $abonne) {
                    $envoyes += CourrielService::envoyerLettre(
                        $abonne->email,
                        $sujet,
                        $nouvelles->all(),
                        $texte,
                        self::lienDesabonnement($abonne)
                    ) ? 1 : 0;
                }
            });

        return ['nouveautes' => $nouvelles->count(), 'envoyes' => $envoyes];
    }

    /**
     * Notifie immédiatement les abonnés lorsqu'une publication majeure est ajoutée, ou prépare les nouveautés.
     *
     * @return list<array{cle: string, rubrique: string, titre: string, detail: string|null, resume: string|null, action: string, lien: string}>
     */
    public static function collecterNouveautes(): array
    {
        $front = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        $exercices = Exercice::with(['matiere', 'niveau'])
            ->where('est_actif', true)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Exercice $e): array => [
                'cle'      => "exercice:{$e->id}",
                'rubrique' => 'Nouvel Exercice · ' . ucfirst($e->difficulte ?? 'Moyen'),
                'titre'    => $e->titre,
                'detail'   => trim(($e->matiere?->nom ?? '') . ' · ' . ($e->niveau?->libelle ?? ''), ' ·'),
                'resume'   => Str::limit(strip_tags((string) $e->enonce), 220),
                'action'   => 'Voir l’exercice',
                'lien'     => "{$front}/ennoncer?id={$e->id}",
            ]);

        $sujets = SujetExamen::with(['matiere', 'niveau'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (SujetExamen $s): array => [
                'cle'      => "sujet:{$s->id}",
                'rubrique' => 'Sujet d’examen · ' . strtoupper((string) $s->type) . ' ' . ($s->annee ?? ''),
                'titre'    => $s->titre,
                'detail'   => trim(($s->matiere?->nom ?? '') . ' · ' . ($s->niveau?->libelle ?? ''), ' ·'),
                'resume'   => Str::limit(strip_tags((string) $s->ennonce), 220),
                'action'   => 'Consulter le sujet',
                'lien'     => "{$front}/sujets/{$s->id}",
            ]);

        $ressources = Ressource::with(['matiere', 'niveau'])
            ->where('est_public', true)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Ressource $r): array => [
                'cle'      => "ressource:{$r->id}",
                'rubrique' => 'Ressource pédagogique · ' . strtoupper((string) $r->type),
                'titre'    => $r->titre,
                'detail'   => trim(($r->matiere?->nom ?? '') . ' · ' . ($r->niveau?->libelle ?? ''), ' ·'),
                'resume'   => Str::limit(strip_tags((string) $r->description), 220),
                'action'   => 'Accéder à la ressource',
                'lien'     => "{$front}/bibliotheque",
            ]);

        return [...$exercices->all(), ...$sujets->all(), ...$ressources->all()];
    }

    /**
     * @param list<array<string, mixed>> $nouvelles
     */
    private static function texteBrut(array $nouvelles): string
    {
        $blocs = collect($nouvelles)->map(fn (array $n): string => mb_strtoupper((string) $n['rubrique']) . "\n{$n['titre']}"
            . (!empty($n['detail']) ? "\n{$n['detail']}" : '')
            . (!empty($n['resume']) ? "\n\n" . Str::limit((string) $n['resume'], 240) : '')
            . "\n\n{$n['action']} : {$n['lien']}")->implode("\n\n————\n\n");

        return "Bonjour,\n\nVoici les dernières publications sur Tely Elearning :\n\n{$blocs}";
    }
}
