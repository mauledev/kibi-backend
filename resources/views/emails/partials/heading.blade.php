{{--
    H1 title row.

    Usage:
        @include('emails.partials.heading', ['text' => '¡Estás a un paso!'])

    Required vars:
        $text string
--}}
<tr>
    <td align="center" style="padding:16px 32px 0 32px;">
        <h1 style="margin:0; font-size:30px; line-height:38px; font-weight:700; color:#1A2E38; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            {{ $text }}
        </h1>
    </td>
</tr>
