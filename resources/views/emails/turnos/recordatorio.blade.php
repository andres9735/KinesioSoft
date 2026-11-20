@extends('emails.turnos.layout', ['titulo' => 'Recordatorio de tu turno'])

@section('contenido')
    <p style="margin:0 0 18.5px;font-size:16px;line-height:24px;color:#44516b;"><strong style="color:#1b233d;">Hola{{ filled($paciente ?? null) ? ', '.$paciente : '' }}.</strong> Te recordamos tu próximo turno en KinesioSoft.</p>
    @include('emails.turnos._fecha')

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:18px;">
        <tr>
            <td class="email-column" width="57%" valign="top" style="width:57%;padding:0 12px 0 0;">
                <p style="margin:0 0 4px;font-size:13px;line-height:18px;font-weight:700;color:#53627d;">Tipo de consulta</p>
                <p style="margin:0;font-size:18px;line-height:24px;color:#1b233d;"><strong>{{ $tipo }}</strong></p>
                <p style="margin:15px 0 4px;font-size:13px;line-height:18px;font-weight:700;color:#53627d;">Kinesióloga</p>
                <p style="margin:0;font-size:18px;line-height:24px;font-weight:700;color:#1b233d;">{{ $profesional }}</p>
            </td>
            <td class="email-column email-column-last" width="43%" valign="top" style="width:43%;">
                <p style="margin:0 0 6px;font-size:13px;line-height:18px;font-weight:700;color:#53627d;">Estado del turno</p>
                @include('emails.turnos._estado')
            </td>
        </tr>
    </table>

    @if ($confirmar)
        <p style="margin:15px 0 11px;font-size:15px;line-height:22px;color:#44516b;">Podés confirmar tu asistencia antes del inicio del turno.</p>
    @endif
    @if ($confirmar || $cancelar)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:{{ $confirmar ? 0 : 18 }}px;">
            <tr>
                @if ($confirmar)
                    <td class="email-button-cell" style="padding-right:10px;mso-padding-alt:13px 17px;">
                        <a class="email-button" href="{{ $url }}" rel="noreferrer" style="display:inline-block;padding:13px 17px;border:1px solid #473ac5;border-radius:8px;background-color:#473ac5;color:#ffffff;font-size:15px;line-height:18px;font-weight:700;text-decoration:none;mso-padding-alt:13px 17px;">Confirmar asistencia</a>
                    </td>
                @endif
                @if ($cancelar)
                    <td class="email-button-cell">
                        <a class="email-button" href="{{ $url }}" rel="noreferrer" style="display:inline-block;padding:13px 17px;border:1px solid #edc5cf;border-radius:8px;background-color:#ffffff;color:#9b2949;font-size:15px;line-height:18px;font-weight:700;text-decoration:none;mso-padding-alt:13px 17px;">Cancelar turno</a>
                    </td>
                @endif
            </tr>
        </table>
    @endif
    @unless ($cancelar)
        <p style="margin:16px 0 0;font-size:14px;line-height:21px;color:#44516b;">La cancelación online requiere al menos 12 horas de anticipación. Para consultar por una cancelación, contactá al consultorio.</p>
    @endunless

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:21px;border-top:1px solid #dee3ee;">
        <tr><td style="padding-top:10px;font-size:13px;line-height:18px;color:#53627d;">
            @if ($confirmar || $cancelar)
                <p style="margin:0 0 4px;">Los enlaces abren la pantalla de tu turno. La decisión se registra cuando la confirmás en esa pantalla.</p>
            @else
                <p style="margin:0 0 4px;">Este recordatorio es informativo; no requiere una nueva confirmación.</p>
            @endif
            <p style="margin:0;">Confirmar expresa tu intención de asistir. Para otras consultas, contactá al consultorio.</p>
        </td></tr>
    </table>
@endsection
