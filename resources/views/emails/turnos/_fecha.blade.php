<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2efff" style="width:100%;background-color:#f2efff;border:1px solid #e1d8ff;border-radius:11px;">
    <tr>
        <td class="email-column" width="54%" valign="top" style="width:54%;padding:12px 18px 19px;">
            <p style="margin:0 0 7px;font-size:13px;line-height:18px;font-weight:700;color:#53627d;">{{ $etiquetaFecha ?? 'Fecha' }}</p>
            <p style="margin:0;font-size:20px;line-height:26px;color:#1b233d;"><strong>{{ $fecha }}</strong></p>
        </td>
        <td class="email-column" width="46%" valign="top" style="width:46%;padding:12px 18px 19px;">
            <p style="margin:0 0 3px;font-size:13px;line-height:18px;font-weight:700;color:#53627d;">{{ $etiquetaHora ?? 'Hora' }}</p>
            <p style="margin:0;font-size:24px;line-height:30px;color:#3b299f;"><strong>{{ $hora }}</strong><span style="font-size:14px;"> hs.</span></p>
        </td>
    </tr>
</table>
