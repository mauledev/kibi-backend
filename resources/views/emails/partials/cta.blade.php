{{--
    Bulletproof CTA button — works in Outlook (VML fallback) + all modern clients.

    Usage:
        @include('emails.partials.cta', ['url' => $someUrl, 'text' => 'Activar mi cuenta'])

    Required vars:
        $url   string — destination URL
        $text  string — button label
--}}
<tr>
    <td align="center" style="padding:28px 24px 12px 24px;">
        <!--[if mso]>
        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:50px;v-text-anchor:middle;width:260px;" arcsize="14%" stroke="f" fillcolor="#018BB0">
            <w:anchorlock/>
            <center style="color:#ffffff;font-family:sans-serif;font-size:16px;font-weight:bold;">{{ $text }}</center>
        </v:roundrect>
        <![endif]-->
        <!--[if !mso]><!-- -->
        <a href="{{ $url }}"
           target="_blank"
           style="display:inline-block; background-color:#018BB0; color:#FFFFFF; text-decoration:none; font-weight:700; font-size:16px; line-height:20px; padding:16px 36px; border-radius:8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; box-shadow:0 4px 12px rgba(1,139,176,0.25);">
            {{ $text }}
        </a>
        <!--<![endif]-->
    </td>
</tr>
