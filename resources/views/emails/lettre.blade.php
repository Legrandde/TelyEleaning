{{--
    La lettre aux abonnés : une carte par nouveauté (nouveaux exercices, sujets d'examen, ressources pédagogiques).
    Variables : $nouvelles, $preheader, $front, $desabonnement.
--}}
@extends('emails.layout')

@section('contenu')
    <h1 style="margin:0 0 8px; font-size:22px; line-height:28px; font-weight:800; color:#0F172A;">
        {{ count($nouvelles) === 1 ? 'Nouveau sur Tely Elearning' : count($nouvelles).' nouveautés sur Tely Elearning' }}
    </h1>
    <p style="margin:0 0 24px; font-size:15px; line-height:23px; color:#475569;">
        Bonjour, voici les dernières ressources publiées pour vous accompagner dans vos révisions et votre réussite aux examens.
    </p>

    @foreach ($nouvelles as $n)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0; border-radius:12px; border-collapse:separate; margin:0 0 20px;">
            <tr>
                <td style="padding:20px 22px 22px;">
                    <p style="margin:0 0 8px; font-size:11px; line-height:14px; font-weight:bold; letter-spacing:1.2px; text-transform:uppercase; color:#D97706;">
                        {{ $n['rubrique'] }}
                    </p>
                    <h2 style="margin:0 0 6px; font-size:18px; line-height:24px; font-weight:bold;">
                        <a href="{{ $n['lien'] }}" target="_blank" style="color:#0F172A; text-decoration:none;">{{ $n['titre'] }}</a>
                    </h2>
                    @if (!empty($n['detail']))
                        <p style="margin:0 0 12px; font-size:13px; line-height:18px; color:#64748B;">{{ $n['detail'] }}</p>
                    @endif
                    @if (!empty($n['resume']))
                        <p style="margin:0 0 18px; font-size:14px; line-height:22px; color:#334155;">{{ \Illuminate\Support\Str::limit($n['resume'], 240) }}</p>
                    @endif
                    @include('emails.partials.bouton', ['libelle' => $n['action'], 'url' => $n['lien']])
                </td>
            </tr>
        </table>
    @endforeach
@endsection
