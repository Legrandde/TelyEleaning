{{--
    E-mail simple (bienvenue, candidature, mot de passe, confirmation newsletter…) dans la coquille commune.
    Variables : $texte (paragraphes séparés par une ligne vide), $bouton ([libellé, url] ou null),
    $preheader, $front, $desabonnement.
--}}
@extends('emails.layout')

@section('contenu')
    @foreach (preg_split('/\n\s*\n/', trim($texte)) as $paragraphe)
        <p style="margin:0 0 16px; font-size:15px; line-height:23px; color:#334155;">{!! nl2br(e($paragraphe)) !!}</p>
    @endforeach

    @if (!empty($bouton))
        <div style="padding:8px 0 4px;">
            @include('emails.partials.bouton', ['libelle' => $bouton[0], 'url' => $bouton[1]])
        </div>
        <p style="margin:18px 0 0; font-size:12px; line-height:18px; color:#94A3B8;">
            Le bouton ne s’ouvre pas ? Copiez cette adresse dans votre navigateur :<br>
            <span style="color:#64748B; word-break:break-all;">{{ $bouton[1] }}</span>
        </p>
    @endif
@endsection
