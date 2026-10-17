<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <title>Algo salió mal</title>
    {{-- Standalone like offline.blade.php: no layout and no @vite. A 500 caused
         by a missing or broken build would otherwise fail again while rendering
         this view and answer with a blank page. --}}
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

        .code {
            margin: 0 0 .5rem;
            font-size: .875rem;
            font-weight: 600;
            color: #1d4ed8;
        }

        h1 {
            font-size: 1.5rem;
            margin: 0 0 .5rem;
        }

        p {
            margin: 0;
            font-size: .95rem;
            color: #4b5563;
        }

        a {
            display: inline-block;
            margin-top: 1.5rem;
            padding: .6rem 1rem;
            border-radius: .5rem;
            background: #1d4ed8;
            color: #fff;
            font-weight: 600;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <main class="card">
        <p class="code">500</p>
        <h1>Algo salió mal</h1>
        <p>Tuvimos un problema de nuestro lado. Inténtalo de nuevo en un momento.</p>
        <a href="">Reintentar</a>
    </main>
</body>

</html>
