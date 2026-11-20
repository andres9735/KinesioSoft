@extends('emails.turnos.layout', ['titulo' => 'Cambio de horario confirmado', 'pie' => 13])

@section('contenido')
    <p style="margin:0 0 18.5px;font-size:16px;line-height:24px;color:#44516b;"><strong style="color:#1b233d;">Hola{{ filled($paciente ?? null) ? ', '.$paciente : '' }}.</strong> Tu turno fue adelantado al siguiente horario.</p>
    @include('emails.turnos._fecha', ['etiquetaFecha' => 'Nueva fecha', 'etiquetaHora' => 'Nueva hora'])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:18px;">
        <tr>
            <td class="email-column" width="57%" valign="top" style="width:57%;padding-right:12px;">
                <p style="margin:0 0 4px;font-size:13px;line-height:18px;font-weight:700;color:#53627d;">Kinesióloga</p>
                <p style="margin:0;font-size:18px;line-height:24px;font-weight:700;color:#1b233d;">{{ $profesional }}</p>
            </td>
            <td class="email-column email-column-last" width="43%" valign="top" style="width:43%;">
                <p style="margin:0 0 6px;font-size:13px;line-height:18px;font-weight:700;color:#53627d;">Estado del turno</p>
                @include('emails.turnos._estado')
            </td>
        </tr>
    </table>
    <p style="margin:17px 0 13px;font-size:15px;line-height:22px;color:#44516b;">El cambio de horario conserva el estado de tu turno.</p>
    <table class="email-button-table" role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#473ac5" style="border-radius:8px;mso-padding-alt:13px 17px;">
        <a class="email-button" href="{{ $urlMisTurnos }}" rel="noreferrer" style="display:inline-block;padding:13px 17px;border:1px solid #473ac5;border-radius:8px;background-color:#473ac5;color:#ffffff;font-size:15px;line-height:18px;font-weight:700;text-decoration:none;mso-padding-alt:13px 17px;">Ver mis turnos</a>
    </td></tr></table>
@endsection
