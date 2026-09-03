<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>@yield('title', 'Lista de compras')</title>

    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">

    @vite(['resources/css/app.css', 'resources/js/list.js'])
</head>

<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <main class="mx-auto w-full max-w-md px-4 py-6">
        @yield('content')
    </main>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function() {});
            });
        }
    </script>
</body>

</html>
