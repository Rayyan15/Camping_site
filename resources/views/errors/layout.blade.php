<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') - {{ config('site.name') }}</title>
    <style>
        :root {
            --forest-950: #14261b;
            --forest-900: #1c3526;
            --forest-700: #2f5a3d;
            --cream: #f7f1e5;
            --cream-deep: #efe5d1;
            --sand-dark: #7a6a47;
            --ember: #b3441f;
            --ember-dark: #8f3416;
            --ink: #1f2a22;
            --ink-soft: #4a5a4f;
        }

        * { box-sizing: border-box; margin: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: var(--cream);
            color: var(--ink);
            font-family: Georgia, "Times New Roman", serif;
            line-height: 1.6;
        }

        main {
            width: 100%;
            max-width: 34rem;
            padding: 32px 24px;
            background: var(--cream-deep);
            border-left: 6px solid var(--forest-900);
            border-radius: 4px;
        }

        .code {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: var(--sand-dark);
        }

        h1 {
            margin-top: 8px;
            font-size: 1.75rem;
            line-height: 1.25;
            color: var(--forest-900);
        }

        p {
            margin-top: 12px;
            max-width: 65ch;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--ink-soft);
        }

        .actions { margin-top: 24px; }

        a {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 6px;
            background: var(--forest-900);
            color: var(--cream);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            font-weight: 600;
            text-decoration: none;
        }

        a:hover { background: var(--forest-700); }

        a:focus-visible { outline: 3px solid var(--ember); outline-offset: 2px; }

        @media (prefers-reduced-motion: no-preference) {
            a { transition: background-color 150ms ease; }
        }
    </style>
</head>
<body>
    <main>
        <p class="code" style="margin-top: 0">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @hasSection('action')
                @yield('action')
            @else
                <a href="/">Kembali ke beranda</a>
            @endif
        </div>
    </main>
</body>
</html>
