<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Validation partagée du contenu en blocs (pages et articles) :
 * profondeur, longueur des textes et liens autorisés.
 */
trait ValideBlocs
{
    private const PROFONDEUR_MAX = 4;

    private const LONGUEUR_TEXTE_MAX = 10000;

    /** Clés de props contenant un lien ou une image : seuls http(s), chemins relatifs, ancres, mailto et tel sont admis. */
    private const CLES_LIEN = ['lien', 'url', 'href', 'image', 'avatar'];

    /**
     * @param  array<int, string>  $champsLien  champs de premier niveau devant contenir un lien sûr
     */
    protected function validerBlocsEtLiens(Validator $validator, array $champsLien = []): void
    {
        foreach ($champsLien as $champ) {
            $valeur = $this->input($champ);
            if (is_string($valeur) && $valeur !== '' && ! self::lienSur($valeur)) {
                $validator->errors()->add($champ, "Le lien fourni n'est pas autorisé.");
            }
        }

        foreach ((array) $this->input('blocs', []) as $i => $bloc) {
            $erreur = $this->verifierValeur(is_array($bloc) ? ($bloc['props'] ?? []) : null, 0, null);
            if ($erreur) {
                $validator->errors()->add("blocs.{$i}.props", $erreur);
            }
        }
    }

    private function verifierValeur(mixed $valeur, int $profondeur, ?string $cle): ?string
    {
        if ($profondeur > self::PROFONDEUR_MAX) {
            return 'Structure de bloc trop profonde.';
        }

        if (is_array($valeur)) {
            if (count($valeur) > 50) {
                return 'Un bloc ne peut pas contenir plus de 50 éléments.';
            }
            foreach ($valeur as $k => $v) {
                $erreur = $this->verifierValeur($v, $profondeur + 1, is_string($k) ? $k : $cle);
                if ($erreur) {
                    return $erreur;
                }
            }

            return null;
        }

        if (is_string($valeur)) {
            if (mb_strlen($valeur) > self::LONGUEUR_TEXTE_MAX) {
                return 'Un texte de bloc dépasse la longueur maximale autorisée.';
            }
            if ($cle !== null && $this->estCleLien($cle) && $valeur !== '' && ! self::lienSur($valeur)) {
                return "Le lien « {$cle} » n'est pas autorisé (http(s), chemin relatif, mailto ou tel uniquement).";
            }

            return null;
        }

        if ($valeur === null || is_bool($valeur) || is_int($valeur) || is_float($valeur)) {
            return null;
        }

        return 'Valeur de bloc invalide.';
    }

    private function estCleLien(string $cle): bool
    {
        foreach (self::CLES_LIEN as $suffixe) {
            if ($cle === $suffixe || str_ends_with($cle, '_'.$suffixe)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lien autorisé : http(s) absolu, chemin relatif au site, ancre, mailto: ou tel:.
     * Les antislashs et caractères de contrôle sont refusés : les navigateurs
     * interprètent « /\evil.com » comme « //evil.com » (lien vers un autre domaine).
     */
    public static function lienSur(string $lien): bool
    {
        $lien = trim($lien);

        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $lien)) {
            return false;
        }

        return (bool) preg_match('#^(https?://[^/]|/(?!/)|\#|mailto:|tel:)#i', $lien);
    }
}
