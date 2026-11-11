<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsVisit;
use App\Services\GeoIpService;
use App\Services\UserAgentParser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackVisits
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // N'enregistrer que pour les requêtes réussies (2xx) et exclure les routes internes
        if ($response->getStatusCode() < 400 && !$this->shouldIgnore($request)) {
            $this->recordVisit($request);
        }

        return $response;
    }

    private function shouldIgnore(Request $request): bool
    {
        $path = $request->path();

        if (
            str_starts_with($path, 'api/analytics') ||
            str_starts_with($path, 'up') ||
            str_starts_with($path, 'documentation') ||
            str_starts_with($path, 'storage') ||
            $request->isMethod('OPTIONS')
        ) {
            return true;
        }

        return false;
    }

    private function recordVisit(Request $request): void
    {
        try {
            $ip = $request->ip() ?? '127.0.0.1';
            $ua = $request->header('User-Agent', '');
            $timezone = $request->header('X-Client-Timezone') ?? $request->input('timezone');

            [$os, $browser, $device] = UserAgentParser::parse($ua);
            $geo = GeoIpService::resolve($ip, $timezone);

            $path = '/' . ltrim($request->path(), '/');
            $action = $this->determineAction($request, $path);

            $userId = auth('sanctum')->id() ?? $request->user()?->id;

            AnalyticsVisit::create([
                'user_id'         => $userId,
                'session_id'      => $request->header('X-Session-Id') ?? session()->getId() ?: md5($ip . $ua . date('Y-m-d')),
                'ip_address'      => $ip,
                'path'            => $path,
                'action'          => $action,
                'metadata'        => ['method' => $request->method()],
                'country'         => $geo['country'] ?? 'Sénégal',
                'country_code'    => $geo['country_code'] ?? $geo['code'] ?? 'SN',
                'city'            => $geo['city'] ?? null,
                'os'              => $os,
                'browser'         => $browser,
                'device_type'     => $device,
                'user_agent'      => substr($ua, 0, 500),
                'referer'         => $request->header('referer'),
            ]);
        } catch (\Throwable) {
            // Silencieusement ignorer les erreurs de traçage pour ne pas bloquer l'application
        }
    }

    private function determineAction(Request $request, string $path): string
    {
        if (str_contains($path, 'login')) return 'login';
        if (str_contains($path, 'register')) return 'register';
        if (str_contains($path, 'exercices')) return 'view_exercice';
        if (str_contains($path, 'sujets')) return 'view_sujet';
        if (str_contains($path, 'corrections')) return 'view_correction';
        if (str_contains($path, 'ressources')) return 'download_pdf';
        if (str_contains($path, 'matieres')) return 'view_matiere';
        if (str_contains($path, 'niveaux')) return 'view_niveau';
        return 'page_view';
    }
}
