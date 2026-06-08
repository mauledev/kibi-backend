{{--
    Fallback URL block — shown when the CTA button might fail to render
    (paranoid email clients, plain-text views, etc.).

    Usage:
        @include('emails.partials.fallback-url', ['url' => $activationUrl])

    Required vars:
        $url string — same URL used by the CTA above
--}}
<tr>
    <td align="center" style="padding:24px 32px 8px 32px;">
        <p style="margin:0 0 8px 0; font-size:13px; line-height:20px; color:#6B7280; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            ¿El botón no funciona? Copia y pega esta URL en tu navegador:
        </p>
        <p style="margin:0; font-size:12px; line-height:18px; word-break:break-all; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;">
            <a href="{{ $url }}" style="color:#018BB0; text-decoration:none;">{{ $url }}</a>
        </p>
    </td>
</tr>
