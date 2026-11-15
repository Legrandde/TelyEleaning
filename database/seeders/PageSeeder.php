<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Pages institutionnelles par défaut, éditables ensuite dans le page builder.
 * Les passages entre crochets [ ] sont à compléter avec les informations légales réelles.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $page) {
            Page::firstOrCreate(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'statut' => 'publie',
                    'publie_le' => now(),
                    'blocs' => array_map(fn (array $b) => [
                        'id' => (string) Str::uuid(),
                        'type' => $b[0],
                        'props' => $b[1],
                        'masque' => false,
                    ], $page['blocs']),
                ])
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pages(): array
    {
        $heroLegal = fn (string $titre, string $sousTitre) => ['hero', [
            'titre' => $titre, 'sous_titre' => $sousTitre, 'alignement' => 'gauche', 'fond' => 'creme',
            'bouton_texte' => '', 'bouton_lien' => '', 'bouton2_texte' => '', 'bouton2_lien' => '', 'image' => '',
        ]];
        $texte = fn (string $titre, string $contenu) => ['texte', ['titre' => $titre, 'contenu' => $contenu, 'alignement' => 'gauche']];

        return [
            [
                'titre' => 'Mentions légales',
                'slug' => 'mentions-legales',
                'meta_description' => 'Mentions légales de la plateforme Tely : éditeur, hébergeur et propriété intellectuelle.',
                'dans_footer' => true,
                'blocs' => [
                    $heroLegal('Mentions légales', 'Informations relatives à l\'éditeur et à l\'hébergement de la plateforme.'),
                    $texte('Éditeur', "La plateforme Tely est éditée par [Raison sociale], [forme juridique] au capital de [montant], immatriculée sous le numéro [NINEA / RCCM], dont le siège social est situé [adresse].\nDirecteur de la publication : [Nom Prénom].\nContact : [e-mail] — [téléphone]."),
                    $texte('Hébergement', "Le site est hébergé par [Hébergeur], [adresse de l'hébergeur]."),
                    $texte('Propriété intellectuelle', "L'ensemble des contenus publiés sur Tely (exercices, sujets, corrigés, textes, logos) est protégé par le droit de la propriété intellectuelle. Toute reproduction, même partielle, sans autorisation préalable est interdite, sauf usage strictement personnel et pédagogique."),
                ],
            ],
            [
                'titre' => 'Conditions générales d\'utilisation',
                'slug' => 'cgu',
                'meta_description' => 'Conditions générales d\'utilisation de la plateforme Tely.',
                'dans_footer' => true,
                'blocs' => [
                    $heroLegal('Conditions générales d\'utilisation', 'En vigueur au [date]. Merci de les lire attentivement.'),
                    $texte('1. Objet', "Les présentes conditions encadrent l'accès et l'utilisation de la plateforme Tely, qui met à disposition des exercices, des sujets d'examen et des corrigés."),
                    $texte('2. Compte utilisateur', "L'inscription est gratuite. L'utilisateur s'engage à fournir des informations exactes et à préserver la confidentialité de ses identifiants. Tely peut suspendre un compte en cas de manquement aux présentes conditions."),
                    $texte('3. Utilisation des contenus', 'Les contenus sont destinés à un usage personnel et pédagogique. Toute diffusion, revente ou extraction massive est interdite.'),
                    $texte('4. Enseignants contributeurs', "Les enseignants garantissent disposer des droits sur les contenus qu'ils publient et autorisent Tely à les diffuser sur la plateforme."),
                    $texte('5. Responsabilité', "Tely s'efforce d'assurer l'exactitude des contenus mais ne saurait garantir l'absence d'erreurs. Le service peut être interrompu pour maintenance."),
                    $texte('6. Droit applicable', 'Les présentes conditions sont régies par le droit [sénégalais]. Tout litige relève des juridictions compétentes de [ville].'),
                ],
            ],
            [
                'titre' => 'Politique de confidentialité',
                'slug' => 'confidentialite',
                'meta_description' => 'Comment Tely collecte, utilise et protège vos données personnelles.',
                'dans_footer' => true,
                'blocs' => [
                    $heroLegal('Politique de confidentialité', 'Vos données vous appartiennent. Voici comment nous les traitons.'),
                    $texte('Données collectées', "Lors de votre inscription : nom, prénom, adresse e-mail, et éventuellement votre niveau et vos matières préférées. Pendant l'utilisation : les exercices consultés, vos favoris et des statistiques de visite anonymisées."),
                    $texte('Finalités', "Ces données servent à fournir le service (compte, historique, recommandations), à améliorer la plateforme et, si vous y avez consenti, à vous envoyer notre lettre d'information."),
                    $texte('Durée de conservation', 'Les données de compte sont conservées tant que le compte est actif. Vous pouvez supprimer votre compte à tout moment depuis votre espace : vos données personnelles sont alors anonymisées.'),
                    $texte('Vos droits', "Vous disposez d'un droit d'accès, de rectification, d'export et de suppression de vos données, directement depuis la page « Mon compte », ou en écrivant à [e-mail du délégué / contact]."),
                    $texte('Cookies', "Nous utilisons des cookies strictement nécessaires au fonctionnement du site et, avec votre accord, des mesures d'audience. Vous pouvez modifier votre choix à tout moment via le lien « Gérer les cookies » en bas de page."),
                ],
            ],
            [
                'titre' => 'À propos',
                'slug' => 'a-propos',
                'meta_description' => 'Tely aide les élèves à réussir leurs examens grâce à des exercices, des sujets et des corrigés de qualité.',
                'dans_menu' => true,
                'blocs' => [
                    ['hero', [
                        'titre' => 'Rendre la réussite scolaire accessible à tous',
                        'sous_titre' => 'Tely réunit des enseignants passionnés et des milliers d\'élèves autour d\'une même mission : s\'entraîner efficacement pour réussir ses examens.',
                        'bouton_texte' => 'Explorer les exercices', 'bouton_lien' => '/exercices',
                        'bouton2_texte' => 'Créer un compte gratuit', 'bouton2_lien' => '/auth/signup',
                        'image' => '/histoire.png', 'alignement' => 'gauche', 'fond' => 'creme',
                    ]],
                    ['statistiques', ['titre' => 'Tely en chiffres', 'source' => 'auto', 'elements' => []]],
                    ['fonctionnalites', [
                        'titre' => 'Nos engagements', 'sous_titre' => '', 'colonnes' => 3,
                        'elements' => [
                            ['icone' => 'qualite', 'titre' => 'Des contenus vérifiés', 'texte' => 'Chaque exercice est rédigé et corrigé par un enseignant.'],
                            ['icone' => 'acces', 'titre' => 'Accessible partout', 'texte' => 'Sur ordinateur, tablette ou téléphone, à tout moment.'],
                            ['icone' => 'progression', 'titre' => 'Centré sur la progression', 'texte' => 'Historique, favoris et recommandations personnalisées.'],
                        ],
                    ]],
                    ['cta', [
                        'titre' => 'Vous êtes enseignant ?', 'texte' => 'Rejoignez la communauté et partagez vos exercices avec des milliers d\'élèves.',
                        'bouton_texte' => 'Devenir contributeur', 'bouton_lien' => '/#enseignants', 'fond' => 'sombre',
                    ]],
                ],
            ],
            [
                'titre' => 'Contact',
                'slug' => 'contact',
                'meta_description' => 'Une question ? Contactez l\'équipe Tely.',
                'dans_menu' => true,
                'blocs' => [
                    $heroLegal('Contactez-nous', 'Une question, une suggestion ou un partenariat ? Notre équipe vous répond sous 48 h.'),
                    ['faq', [
                        'titre' => 'Questions fréquentes',
                        'elements' => [
                            ['question' => 'L\'inscription est-elle gratuite ?', 'reponse' => 'Oui, la création de compte est gratuite et sans engagement.'],
                            ['question' => 'Comment accéder aux corrigés ?', 'reponse' => 'Connectez-vous à votre compte : les corrigés s\'affichent directement sous chaque exercice qui en dispose.'],
                            ['question' => 'Comment devenir enseignant contributeur ?', 'reponse' => 'Déposez votre candidature depuis la page d\'accueil, section « Enseignants ». Notre équipe l\'étudie sous quelques jours.'],
                            ['question' => 'Comment supprimer mon compte ?', 'reponse' => 'Depuis « Mon compte », section « Zone de danger ». Vos données personnelles sont alors anonymisées.'],
                        ],
                    ]],
                    ['cta', [
                        'titre' => 'Écrivez-nous', 'texte' => 'Nous répondons à tous les messages.',
                        'bouton_texte' => 'Envoyer un e-mail', 'bouton_lien' => 'mailto:contact@tely.com', 'fond' => 'ambre',
                    ]],
                ],
            ],
        ];
    }
}
