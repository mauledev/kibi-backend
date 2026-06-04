<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>@yield('title', config('app.name'))</title>
    <!--[if mso]>
    <style type="text/css">
        table, td { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    </style>
    <![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#FBF9F4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Helvetica, Arial, sans-serif; color:#1A2E38; -webkit-font-smoothing:antialiased;">

    {{-- * Preheader: texto invisible que el cliente muestra en la preview del inbox --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#FBF9F4;">
        <tr>
            <td align="center" style="padding:24px 12px;">

                {{-- * Top accent bar --}}
                <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">
                    <tr>
                        <td style="background-color:#018BB0; height:4px; line-height:4px; font-size:4px; border-radius:4px 4px 0 0;">&nbsp;</td>
                    </tr>
                </table>

                {{-- * White card --}}
                <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#FFFFFF; border-radius:0 0 12px 12px;">

                    {{-- Brand logo --}}
                    <tr>
                        <td align="center" style="padding:32px 24px 8px 24px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="background-color:#018BB0; border-radius:14px; padding:14px 22px;">
                                        <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" width="120" style="display:block; border:0; outline:none; text-decoration:none;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Eyebrow (opcional — el child decide si lo emite) --}}
                    @hasSection('eyebrow')
                        <tr>
                            <td align="center" style="padding:24px 24px 0 24px;">
                                <span style="display:inline-block; padding:6px 12px; background-color:rgba(1,139,176,0.10); color:#0F6E8C; font-size:11px; font-weight:600; letter-spacing:1px; text-transform:uppercase; border-radius:999px;">
                                    @yield('eyebrow')
                                </span>
                            </td>
                        </tr>
                    @endif

                    {{-- Content (donde el child inserta sus rows) --}}
                    @yield('content')

                    {{-- Sign-off --}}
                    <tr>
                        <td align="center" style="padding:24px 32px 32px 32px;">
                            <p style="margin:0; font-size:14px; line-height:22px; color:#1A2E38; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                @yield('signoff', '¡Saludos!')<br>
                                <span style="color:#6B7280;">El equipo de {{ config('app.support_name') }}</span>
                            </p>
                        </td>
                    </tr>
                </table>

                {{-- * Footer --}}
                <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">
                    <tr>
                        <td align="center" style="padding:20px 24px 8px 24px;">
                            <p style="margin:0; font-size:12px; line-height:18px; color:#6f7073; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                ¿Necesitas ayuda? Escríbenos a
                                <a href="mailto:{{ config('app.support_address') }}" style="color:#0F6E8C; text-decoration:none; font-weight:600;">{{ config('app.support_address') }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:8px 24px 24px 24px;">
                            <p style="margin:0; font-size:11px; line-height:16px; color:#9CA3AF; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                @yield('disclaimer', 'Si no esperabas este mensaje, puedes ignorarlo.')<br>
                                © {{ date('Y') }} {{ config('app.name') }} · Plataforma educativa
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
