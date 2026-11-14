<?php

use App\Services\NewsletterService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Diffusion périodique de la newsletter aux abonnés actifs (nouveaux exercices, sujets, ressources)
Artisan::command('newsletter:diffuser', function (): int {
    $bilan = NewsletterService::diffuserNouveautes();
    $this->info("{$bilan['nouveautes']} nouveauté(s), {$bilan['envoyes']} e-mail(s) envoyé(s).");

    return 0;
})->purpose('Envoyer aux abonnés de la newsletter les nouvelles ressources publiées');

Schedule::command('newsletter:diffuser')->everyFifteenMinutes()->withoutOverlapping();

// Purge quotidienne des données expirées (traces de visite > 13 mois : AnalyticsVisit::prunable)
Schedule::command('model:prune')->daily()->withoutOverlapping();
