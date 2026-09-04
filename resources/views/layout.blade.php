<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>@yield('title', 'Lista de compras')</title>

    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" sizes="any" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/list.js'])
</head>

<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    {{-- md+: una tarjeta propia en vez de la misma columna móvil recentrada
         en espacio vacío (hallazgo de /impeccable critique, P3). --}}
    <main
        class="mx-auto w-full max-w-md px-4 py-6 md:max-w-2xl md:rounded-2xl md:bg-white md:px-10 md:py-10 md:shadow-sm md:ring-1 md:ring-gray-200">
        @unless (request()->is('/'))
            {{-- Vuelta al home: única navegación interna, ya que las listas solo se
                 alcanzan por su enlace y "Mis listas" vive en el home. --}}
            <nav class="mb-4">
                <a href="/" class="inline-flex items-center gap-1 text-sm font-medium text-blue-700 hover:underline">
                    <span aria-hidden="true">&larr;</span> Mis listas
                </a>
            </nav>
        @endunless

        @yield('content')
    </main>

    @yield('scripts')
</body>

</html>
