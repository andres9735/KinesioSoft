@extends('emails.turnos.layout', ['titulo' => 'Oferta de adelanto de turno', 'pie' => 16])

@section('contenido')
    <p style="margin:0 0 11px;font-size:16px;line-height:24px;color:#44516b;"><strong style="color:#1b233d;">Hola{{ filled($paciente ?? null) ? ', '.$paciente : '' }}.</strong> Se liberó un horario para adelantar tu turno.</p>
    <p style="margin:0 0 4px;font-size:13px;line-height:18px;font-weight:700;color:#53627d;">Kinesióloga</p>
    <p style="margin:0 0 18px;font-size:18px;line-height:24px;font-weight:700;color:#1b233d;">{{ $profesional }}</p>

    @foreach (['actual' => ['Horario actual', $actual], 'ofrecido' => ['Horario ofrecido', $ofrecido]] as $clave => [$etiqueta, $detalle])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="{{ $clave === 'ofrecido' ? '#f2efff' : '#f7f8fb' }}" style="width:100%;margin-bottom:10px;border:1px solid {{ $clave === 'ofrecido' ? '#e1d8ff' : '#dee3ee' }};border-radius:11px;background-color:{{ $clave === 'ofrecido' ? '#f2efff' : '#f7f8fb' }};">
            <tr>
                @if ($clave === 'ofrecido')
                    <td width="4" style="width:4px;padding:8px 0;">
                        <table role="presentation" width="4" cellpadding="0" cellspacing="0" border="0"><tr><td height="49" bgcolor="#473ac5" style="height:49px;border-radius:3px;background-color:#473ac5;font-size:1px;">&nbsp;</td></tr></table>
                    </td>
                @endif
                <td style="padding:8px {{ $clave === 'ofrecido' ? '12px' : '16px' }} 11px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                        <tr>
                            <td class="email-column" width="78%" valign="middle" style="width:78%;">
                                <p style="margin:0 0 4px;font-size:13px;line-height:18px;font-weight:700;color:#53627d;">{{ $etiqueta }}</p>
                                <p style="margin:0;font-size:18px;line-height:24px;font-weight:700;color:#1b233d;">{{ $detalle['fecha'] }}</p>
                            </td>
                            <td class="email-column email-time" width="22%" align="left" valign="middle" style="width:22%;font-size:20px;line-height:27px;color:{{ $clave === 'ofrecido' ? '#3b299f' : '#1b233d' }};"><strong>{{ $detalle['hora'] }}</strong><span style="font-size:14px;font-weight:700;"> hs.</span></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    <p style="margin:8px 0 15px;font-size:15px;line-height:22px;color:#44516b;">La oferta vence el <strong style="color:#1b233d;">{{ $vencimiento }} hs.</strong> (hora argentina).</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f0ff" style="width:100%;margin-bottom:15px;border-radius:9px;background-color:#f4f0ff;">
        <tr><td class="email-notice" style="padding:9px 14px 12px;font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:21px;color:#30266e;"><strong>Tu turno conserva su horario y estado<br class="email-notice-break"> hasta que aceptes el adelanto.</strong></td></tr>
    </table>
    <table class="email-button-table" role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#473ac5" style="border-radius:8px;mso-padding-alt:13px 17px;">
        <a class="email-button" href="{{ $url }}" rel="noreferrer" style="display:inline-block;padding:13px 17px;border:1px solid #473ac5;border-radius:8px;background-color:#473ac5;color:#ffffff;font-size:15px;line-height:18px;font-weight:700;text-decoration:none;mso-padding-alt:13px 17px;">Consultar oferta de adelanto</a>
    </td></tr></table>
@endsection
