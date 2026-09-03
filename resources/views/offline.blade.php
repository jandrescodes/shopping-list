<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <title>Sin conexión</title>
    {{-- Standalone offline fallback (RF-29): no @vite, no manifest, no service
         worker registration. It must render from the SW cache with zero network
         and no build assets. --}}
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f9fafb;
            color: #111827;
        }

        .card {
            max-width: 22rem;
            text-align: center;
        }

        h1 {
            font-size: 1.25rem;
            margin: 0 0 .5rem;
        }

        p {
            margin: 0;
            font-size: .95rem;
            color: #4b5563;
        }
    </style>
</head>

<body>
    <main class="card">
        <h1>Sin conexión</h1>
        <p>
            No hay conexión a internet y esta lista todavía no se guardó en el
            dispositivo. Vuelve a intentarlo cuando recuperes la red.
        </p>
    </main>
</body>

</html>
