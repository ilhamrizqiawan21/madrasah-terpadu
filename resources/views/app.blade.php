<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script>
            (function() {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.colorScheme = 'light';
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(0.96 0.006 85);
            }

            html.dark {
                background-color: oklch(0.17 0.01 160);
            }
            @if ($brandingPrimaryColor ?? null)
            :root {
                --primary: {{ $brandingPrimaryColor }};
                --sidebar-primary: {{ $brandingPrimaryColor }};
            }
            .dark {
                --primary: color-mix(in oklch, {{ $brandingPrimaryColor }} 62%, white);
                --sidebar-primary: color-mix(in oklch, {{ $brandingPrimaryColor }} 62%, white);
            }
            @endif
        </style>

        @if ($brandingFaviconUrl ?? null)
            <link rel="icon" href="{{ $brandingFaviconUrl }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @endif
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="{{ $brandingPrimaryColor ?? '#135B42' }}">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
