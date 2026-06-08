{{--
    Body paragraph row. Supports raw HTML via `$raw` flag for emphasis tags.

    Usage:
        @include('emails.partials.paragraph', ['slot' => 'Texto plano.'])
        @include('emails.partials.paragraph', ['slot' => 'Hola, <strong>nombre</strong>.', 'raw' => true])

    Required vars:
        $slot string — paragraph content
    Optional vars:
        $raw bool — if true, render raw HTML (defaults to false)
--}}
<tr>
    <td align="center" style="padding:14px 40px 0 40px;">
        <p style="margin:0; font-size:15px; line-height:24px; color:#4B5567; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            @if(isset($raw) && $raw)
                {!! $slot !!}
            @else
                {{ $slot }}
            @endif
        </p>
    </td>
</tr>
