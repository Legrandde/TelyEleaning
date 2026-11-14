<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsVisit;
use App\Services\GeoIpService;
use App\Services\UserAgentParser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Enregistrer une visite ou action utilisateur client-side
     */
    public function track(Request $request): JsonResponse
    {
        // Validation avant toute requête externe (géolocalisation) ; tailles alignées sur les colonnes
        $request->validate([
            'session_id'  => ['nullable', 'string', 'max:100'],
            'path'        => ['nullable', 'string', 'max:255'],
            'action'      => ['nullable', 'string', 'max:100'],
            'metadata'    => ['nullable', 'array', 'max:20'],
            'os'          => ['nullable', 'string', 'max:50'],
            'browser'     => ['nullable', 'string', 'max:50'],
            'device_type' => ['nullable', 'string', 'in:desktop,mobile,tablet'],
            'timezone'    => ['nullable', 'string', 'max:64'],
        ]);

        $metadata = $request->input('metadata');
        if ($metadata !== null && strlen((string) json_encode($metadata)) > 2000) {
            return response()->json(['message' => 'Métadonnées trop volumineuses.'], 422);
        }

        $ip = $request->ip() ?? '127.0.0.1';
        $ua = $request->header('User-Agent', '');
        $timezone = $request->input('timezone') ?? $request->header('X-Client-Timezone');

        [$detectedOs, $detectedBrowser, $detectedDevice] = UserAgentParser::parse($ua);
        $geo = GeoIpService::resolve($ip, $timezone);

        // L'utilisateur n'est jamais lu depuis la requête : uniquement depuis le token authentifié
        $userId = auth('sanctum')->id();

        $visit = AnalyticsVisit::create([
            'user_id'         => $userId,
            'session_id'      => $request->input('session_id', md5($ip . $ua . date('Y-m-d'))),
            // IP pseudonymisée avant stockage (la géolocalisation a déjà été faite avec l'IP complète)
            'ip_address'      => GeoIpService::anonymiser($ip),
            'path'            => $request->input('path', '/'),
            'action'          => $request->input('action', 'page_view'),
            'metadata'        => $metadata,
            'country'         => $geo['country'] ?? 'Sénégal',
            'country_code'    => $geo['country_code'] ?? $geo['code'] ?? 'SN',
            'city'            => $geo['city'] ?? null,
            'os'              => $request->input('os', $detectedOs),
            'browser'         => $request->input('browser', $detectedBrowser),
            'device_type'     => $request->input('device_type', $detectedDevice),
            'user_agent'      => substr($ua, 0, 500),
            'referer'         => substr((string) $request->header('referer'), 0, 500) ?: null,
        ]);

        return response()->json([
            'status' => 'success',
            'id'     => $visit->id,
        ], 201);
    }

    /**
     * Statistiques réelles pour le tableau de bord administrateur.
     *
     * Définitions :
     *  - visites           = sessions de navigation distinctes
     *  - visiteurs uniques = personnes distinctes (compte connecté, sinon IP pseudonymisée + navigateur)
     *  - pages vues        = événements « page_view »
     *  - actions           = tous les autres événements (exercice consulté, PDF téléchargé…)
     *  - tendances         = évolution en % par rapport à la période précédente de même durée
     */
    public function getStats(Request $request): JsonResponse
    {
        $period = $request->query('period', '30d');
        if (!in_array($period, ['today', '7d', '30d', 'year'], true)) {
            $period = '30d';
        }

        $data = Cache::remember("analytics_stats_v2_{$period}", 120, function () use ($period) {
            $now = Carbon::now();

            $startDate = match ($period) {
                'today' => $now->copy()->startOfDay(),
                '7d'    => $now->copy()->subDays(7)->startOfDay(),
                'year'  => $now->copy()->subYear()->startOfDay(),
                default => $now->copy()->subDays(30)->startOfDay(),
            };
            // Période précédente de même durée, pour les tendances
            $previousStart = $startDate->copy()->subSeconds($startDate->diffInSeconds($now, true));

            $baseQuery = AnalyticsVisit::where('created_at', '>=', $startDate);
            $previousQuery = AnalyticsVisit::where('created_at', '>=', $previousStart)
                ->where('created_at', '<', $startDate);

            $current  = $this->summaryFor($baseQuery);
            $previous = $this->summaryFor($previousQuery);

            // Pas de point de comparaison : on n'invente pas de tendance
            $trend = static function (float $cur, float $prev): ?float {
                if ($prev <= 0) {
                    return null;
                }
                return round((($cur - $prev) / $prev) * 100, 1);
            };

            $visitorKey = $this->visitorKeySql();
            $totalSessions = $current['sessions'];

            // Timeline réelle par jour : sessions et visiteurs distincts
            $timelineData = (clone $baseQuery)
                ->selectRaw("DATE(created_at) as date, COUNT(DISTINCT session_id) as visits, COUNT(DISTINCT {$visitorKey}) as visitors")
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get()
                ->map(fn ($row) => [
                    'date'     => (string) $row->date,
                    'visits'   => (int) $row->visits,
                    'visitors' => (int) $row->visitors,
                ]);

            // Répartitions calculées sur les sessions (et non sur chaque événement)
            $breakdown = function (string $column, string $key) use ($baseQuery, $totalSessions) {
                return (clone $baseQuery)
                    ->select($column, DB::raw('COUNT(DISTINCT session_id) as total'))
                    ->groupBy($column)
                    ->orderByDesc('total')
                    ->get()
                    ->map(fn ($item) => [
                        $key         => $item->{$column},
                        'total'      => (int) $item->total,
                        'percentage' => $totalSessions > 0 ? round(($item->total / $totalSessions) * 100, 1) : 0,
                    ]);
            };

            $countries = (clone $baseQuery)
                ->select('country', 'country_code', DB::raw('COUNT(DISTINCT session_id) as total'))
                ->groupBy('country', 'country_code')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn ($item) => [
                    'country'      => $item->country,
                    'country_code' => $item->country_code,
                    'total'        => (int) $item->total,
                    'percentage'   => $totalSessions > 0 ? round(($item->total / $totalSessions) * 100, 1) : 0,
                ]);

            // Répartition réelle des actions (nombre d'événements)
            $actions = (clone $baseQuery)
                ->select('action', DB::raw('COUNT(*) as total'))
                ->groupBy('action')
                ->orderByDesc('total')
                ->get();

            // Dernières activités réelles enregistrées
            $recentVisits = AnalyticsVisit::with('user:id,nom,prenom,email,avatar,role')
                ->latest()
                ->limit(25)
                ->get()
                ->map(function ($visit) {
                    return [
                        'id'           => $visit->id,
                        'action'       => $visit->action,
                        'path'         => $visit->path,
                        'country'      => $visit->country,
                        'country_code' => $visit->country_code,
                        'city'         => $visit->city,
                        'os'           => $visit->os,
                        'browser'      => $visit->browser,
                        'device_type'  => $visit->device_type,
                        'ip_address'   => GeoIpService::masquer($visit->ip_address),
                        'user'         => $visit->user ? [
                            'id'         => $visit->user->id,
                            'nom'        => $visit->user->nom,
                            'prenom'     => $visit->user->prenom,
                            'email'      => $visit->user->email,
                            'role'       => $visit->user->role,
                            'avatar_url' => $visit->user->avatar_url,
                        ] : null,
                        'created_at'   => $visit->created_at->toIso8601String(),
                        'time_ago'     => $visit->created_at->diffForHumans(),
                    ];
                });

            return [
                'period'  => $period,
                'summary' => [
                    'total_visits'    => $current['sessions'],
                    'unique_visitors' => $current['visitors'],
                    'page_views'      => $current['page_views'],
                    'user_actions'    => $current['actions'],
                    'bounce_rate'     => $current['bounce_rate'] . '%',
                    'avg_duration'    => $this->formatDuration($current['avg_seconds']),
                    'trends'          => [
                        'total_visits'    => $trend($current['sessions'], $previous['sessions']),
                        'unique_visitors' => $trend($current['visitors'], $previous['visitors']),
                        'page_views'      => $trend($current['page_views'], $previous['page_views']),
                        'user_actions'    => $trend($current['actions'], $previous['actions']),
                        // Taux de rebond : écart en points, pas en %
                        'bounce_rate'     => $previous['sessions'] > 0
                            ? round($current['bounce_rate'] - $previous['bounce_rate'], 1)
                            : null,
                    ],
                ],
                'timeline'          => $timelineData,
                'countries'         => $countries,
                'operating_systems' => $breakdown('os', 'os'),
                'browsers'          => $breakdown('browser', 'browser'),
                'devices'           => $breakdown('device_type', 'device'),
                'actions'           => $actions,
                'recent_activities' => $recentVisits,
            ];
        });

        return response()->json($data);
    }

    /**
     * Indicateurs principaux pour une requête donnée (période courante ou précédente).
     *
     * @return array{sessions:int, visitors:int, page_views:int, actions:int, bounce_rate:float, avg_seconds:float}
     */
    private function summaryFor($query): array
    {
        $visitorKey = $this->visitorKeySql();

        $sessions  = (int) (clone $query)->selectRaw('COUNT(DISTINCT session_id) as total')->value('total');
        $visitors  = (int) (clone $query)->selectRaw("COUNT(DISTINCT {$visitorKey}) as total")->value('total');
        $pageViews = (clone $query)->where('action', 'page_view')->count();
        $actions   = (clone $query)->where('action', '!=', 'page_view')->count();

        // Une ligne par session : nombre de pages vues + première / dernière activité
        $perSession = (clone $query)
            ->select(
                'session_id',
                DB::raw("SUM(CASE WHEN action = 'page_view' THEN 1 ELSE 0 END) as views"),
                DB::raw('MIN(created_at) as first_seen'),
                DB::raw('MAX(created_at) as last_seen')
            )
            ->groupBy('session_id')
            ->get();

        // Rebond = session avec au plus une page vue
        $bounces = $perSession->filter(fn ($s) => (int) $s->views <= 1)->count();
        $bounceRate = $sessions > 0 ? round(($bounces / $sessions) * 100, 1) : 0.0;

        // Durée moyenne par session : somme des écarts entre événements successifs,
        // en ignorant les pauses de plus de 30 min (onglet resté ouvert, retour le lendemain…)
        $maxGap = 30 * 60;
        $durations = [];
        $lastSeen = [];
        foreach ((clone $query)->orderBy('created_at')->get(['session_id', 'created_at']) as $event) {
            $sid = (string) $event->session_id;
            $ts = $event->created_at->getTimestamp();
            $durations[$sid] ??= 0;
            if (isset($lastSeen[$sid])) {
                $gap = $ts - $lastSeen[$sid];
                if ($gap > 0 && $gap <= $maxGap) {
                    $durations[$sid] += $gap;
                }
            }
            $lastSeen[$sid] = $ts;
        }
        $avgSeconds = count($durations) > 0 ? array_sum($durations) / count($durations) : 0.0;

        return [
            'sessions'    => $sessions,
            'visitors'    => $visitors,
            'page_views'  => $pageViews,
            'actions'     => $actions,
            'bounce_rate' => (float) $bounceRate,
            'avg_seconds' => $avgSeconds,
        ];
    }

    /**
     * Clé SQL identifiant un visiteur : compte connecté, sinon IP pseudonymisée + navigateur.
     */
    private function visitorKeySql(): string
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return "COALESCE(CONCAT('u', user_id), CONCAT(COALESCE(ip_address, ''), '|', COALESCE(user_agent, '')))";
        }

        // SQLite et PostgreSQL utilisent l'opérateur ||
        return "COALESCE('u' || user_id, COALESCE(ip_address, '') || '|' || COALESCE(user_agent, ''))";
    }

    private function formatDuration(float $seconds): string
    {
        $seconds = (int) round($seconds);
        if ($seconds <= 0) {
            return '0s';
        }
        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $minutes > 0 ? "{$minutes}m {$rest}s" : "{$rest}s";
    }
}
