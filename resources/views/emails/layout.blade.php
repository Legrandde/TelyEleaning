{{--
    Coquille commune des e-mails de Tely Elearning. Tableaux et styles en ligne pour un rendu
    identique sur Gmail, Outlook et Apple Mail. Charte Tely : ambre #F59E0B / #D97706, ardoise #0F172A,
    fond #FAFAF9.

    Variables : $preheader, $front, $desabonnement, et la section `contenu`.
--}}
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Tely Elearning</title>
</head>
<body style="margin:0; padding:0; background-color:#FAFAF9; -webkit-text-size-adjust:100%;">
    {{-- Texte d'aperçu invisible lu par la boîte de réception --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#FAFAF9;">
        {{ $preheader ?? '' }}&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAFAF9;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px;">

                    {{-- En-tête : la marque et le liseré ambre/ardoise --}}
                    <tr>
                        <td style="background-color:#FFFFFF; border-radius:12px 12px 0 0; padding:28px 32px 20px; text-align:left; border-top:1px solid #F3F4F6; border-left:1px solid #F3F4F6; border-right:1px solid #F3F4F6;">
                            <a href="{{ $front }}" style="text-decoration:none;">
                                <span style="font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:800; letter-spacing:0.3px; color:#0F172A;">Tely<span style="color:#F59E0B;">.</span> Elearning</span><br>
                                <span style="font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#64748B;">Exercices, sujets d’examen et corrigés pour réussir</span>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#FFFFFF; padding:0 32px; border-left:1px solid #F3F4F6; border-right:1px solid #F3F4F6;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="65%" height="4" style="background-color:#F59E0B; font-size:0; line-height:0;">&nbsp;</td>
                                    <td width="35%" height="4" style="background-color:#0F172A; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Le contenu de l'e-mail --}}
                    <tr>
                        <td style="background-color:#FFFFFF; border-radius:0 0 12px 12px; padding:28px 32px 32px; font-family:Arial,Helvetica,sans-serif; color:#1E293B; border-bottom:1px solid #F3F4F6; border-left:1px solid #F3F4F6; border-right:1px solid #F3F4F6;">
                            @yield('contenu')
                        </td>
                    </tr>

                    {{-- Pied de page --}}
                    <tr>
                        <td style="padding:24px 32px 8px; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:#64748B; text-align:center;">
                            @if (!empty($desabonnement))
                                Vous recevez cet e-mail parce que vous êtes abonné à la newsletter de Tely Elearning.<br>
                                <a href="{{ $desabonnement }}" style="color:#D97706; text-decoration:underline; font-weight:600;">Se désabonner</a>
                                &nbsp;·&nbsp;
                            @else
                                Cet e-mail vous est envoyé automatiquement par Tely Elearning.<br>
                            @endif
                            <a href="{{ $front }}" style="color:#D97706; text-decoration:underline; font-weight:600;">Visiter la plateforme</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 16px; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:#94A3B8; text-align:center;">
                            © {{ now()->year }} Tely Elearning · Tous droits réservés
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
