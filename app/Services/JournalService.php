<?php

namespace App\Services;

use App\Models\JournalActivite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class JournalService
{
    /**
     * Trace une action du back-office. Ne doit jamais faire échouer la requête métier.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function log(string $action, string $description, ?Model $sujet = null, array $meta = []): void
    {
        try {
            JournalActivite::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'sujet_type' => $sujet ? class_basename($sujet) : null,
                'sujet_id' => $sujet?->getKey(),
                'description' => mb_substr($description, 0, 500),
                'meta' => $meta ?: null,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Journal d\'activité indisponible : '.$e->getMessage());
        }
    }
}
