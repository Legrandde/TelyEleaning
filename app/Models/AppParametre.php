<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppParametre extends Model
{
    use HasFactory;

    protected $table = 'app_parametres';

    protected $fillable = [
        'cle',
        'valeur',
        'groupe',
    ];

    /** Valeurs par défaut : seules ces clés peuvent être enregistrées. */
    public const DEFAUTS = [
        // Général
        'site_nom' => 'Tely Elearning',
        'site_description' => "Plateforme d'exercices, de sujets d'examen et de ressources éducatives.",
        'contact_email' => 'contact@tely.com',
        'contact_telephone' => '',
        'mode_maintenance' => '0',
        'message_maintenance' => 'La plateforme est en cours de maintenance. Merci de revenir dans quelques instants.',
        // Bannière d'annonce
        'annonce_active' => '0',
        'annonce_texte' => '',
        'annonce_lien' => '',
        'annonce_lien_texte' => '',
        'annonce_style' => 'info',
        // Réseaux sociaux
        'reseau_facebook' => '',
        'reseau_instagram' => '',
        'reseau_linkedin' => '',
        'reseau_youtube' => '',
        'reseau_tiktok' => '',
        'reseau_whatsapp' => '',
        // Pédagogie
        'inscriptions_ouvertes' => '1',
        'candidatures_ouvertes' => '1',
        'correction_obligatoire' => '0',
        'taille_max_fichier_mo' => '25',
        // Notifications
        'notif_email_candidature' => '1',
        'notif_email_exercice' => '0',
        'alertes_navigateur' => '1',
        // Sécurité
        'longueur_min_mdp' => '8',
        'expiration_session_min' => '120',
    ];

    /** Clés exposées sans authentification (site public). */
    public const CLES_PUBLIQUES = [
        'site_nom', 'site_description', 'contact_email', 'contact_telephone',
        'mode_maintenance', 'message_maintenance',
        'annonce_active', 'annonce_texte', 'annonce_lien', 'annonce_lien_texte', 'annonce_style',
        'reseau_facebook', 'reseau_instagram', 'reseau_linkedin', 'reseau_youtube', 'reseau_tiktok', 'reseau_whatsapp',
        'inscriptions_ouvertes', 'candidatures_ouvertes',
    ];

    /**
     * Tous les paramètres, valeurs par défaut comprises.
     *
     * @return array<string, string>
     */
    public static function tous(): array
    {
        return array_merge(self::DEFAUTS, static::query()->pluck('valeur', 'cle')->toArray());
    }

    /** Paramètres à valeurs fermées : interrupteurs « 0 »/« 1 » et listes. */
    private const BOOLEENS = [
        'mode_maintenance', 'annonce_active', 'inscriptions_ouvertes', 'candidatures_ouvertes',
        'correction_obligatoire', 'notif_email_candidature', 'notif_email_exercice', 'alertes_navigateur',
    ];

    /** @var array<string, array{0: int, 1: int}> bornes des paramètres numériques */
    private const ENTIERS = [
        'taille_max_fichier_mo' => [1, 200],
        'longueur_min_mdp' => [8, 64],
        'expiration_session_min' => [5, 43200],
    ];

    /**
     * Vérifie qu'une valeur est admise pour la clé (type, liste fermée, bornes).
     */
    public static function valeurValide(string $cle, string $valeur): bool
    {
        if (in_array($cle, self::BOOLEENS, true)) {
            return in_array($valeur, ['0', '1'], true);
        }

        if (isset(self::ENTIERS[$cle])) {
            [$min, $max] = self::ENTIERS[$cle];

            return ctype_digit($valeur) && (int) $valeur >= $min && (int) $valeur <= $max;
        }

        return match ($cle) {
            'annonce_style' => in_array($valeur, ['info', 'promo', 'alerte'], true),
            'contact_email' => $valeur === '' || filter_var($valeur, FILTER_VALIDATE_EMAIL) !== false,
            'contact_telephone' => $valeur === '' || (bool) preg_match('/^[0-9+().\s-]{6,25}$/', $valeur),
            default => true,
        };
    }

    /** Longueur minimale des mots de passe réglée par l'administrateur (8 au minimum). */
    public static function longueurMinMotDePasse(): int
    {
        return max(8, (int) (self::tous()['longueur_min_mdp'] ?? 8));
    }

    public static function actif(string $cle): bool
    {
        return (string) (self::tous()[$cle] ?? '0') === '1';
    }

    /**
     * Obtenir une valeur avec valeur par défaut
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $param = static::where('cle', $key)->first();

        return $param ? $param->valeur : $default;
    }

    /**
     * Définir ou mettre à jour un paramètre
     */
    public static function set(string $key, mixed $value, string $group = 'general'): static
    {
        return static::updateOrCreate(
            ['cle' => $key],
            ['valeur' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'groupe' => $group]
        );
    }
}
