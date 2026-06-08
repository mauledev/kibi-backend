{{--
    Small grey hint text below the CTA (e.g. validity duration).

    Usage:
        @include('emails.partials.hint', ['text' => 'Este enlace es válido por 7 días.'])

    Required vars:
        $text string
--}}
<tr>
    <td align="center" style="padding:0 32px 24px 32px;">
        <p style="margin:0; font-size:13px; line-height:18px; color:#6B7280; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            {{ $text }}
        </p>
    </td>
</tr>
