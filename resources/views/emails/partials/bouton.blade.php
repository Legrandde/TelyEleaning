{{--
    Bouton d'appel à l'action compatible avec toutes les boîtes mail (Gmail, Outlook, Apple Mail).
    Variables : $libelle, $url.
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0;">
    <tr>
        <td align="center" bgcolor="#F59E0B" style="border-radius:8px; background-color:#F59E0B;">
            <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:12px 24px; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:bold; line-height:20px; color:#0F172A; text-decoration:none; border-radius:8px;">{{ $libelle }}&nbsp;&rarr;</a>
        </td>
    </tr>
</table>
