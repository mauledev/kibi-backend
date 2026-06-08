@extends('emails.layouts.master')

@section('title', 'Activa tu cuenta en Kibi')

@section('preheader', 'Activa tu cuenta de Owner en Kibi y define tu contraseña. Tu enlace expira en 7 días.')

@section('eyebrow', 'Activación de cuenta')

@section('signoff', '¡Bienvenido al equipo!')

@section('content')
    @include('emails.partials.heading', [
        'text' => '¡Estás a un paso!',
    ])

    @include('emails.partials.paragraph', [
        'slot' => 'Hola, recibimos una solicitud para crear tu cuenta de Owner en <strong style="color:#1A2E38;">' . config('app.name') . '</strong>. Para activarla y establecer tu contraseña, da clic en el botón:',
        'raw' => true,
    ])

    @include('emails.partials.cta', [
        'url'  => $activationUrl,
        'text' => 'Activar mi cuenta',
    ])

    @include('emails.partials.hint', [
        'text' => 'Este enlace es válido por 7 días.',
    ])

    @include('emails.partials.divider')

    @include('emails.partials.fallback-url', [
        'url' => $activationUrl,
    ])
@endsection
