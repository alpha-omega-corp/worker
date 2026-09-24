{{-- marketing: No app shell at all, a navbar, a stack of sections, and a footer. For public sites and landing pages. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-palette="orchard">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas font-body text-ink">
        <header>
            {{-- region:nav --}}
        </header>

        <main class="mx-auto max-w-7xl space-y-24 px-4 py-16 sm:px-6 lg:px-8">
            {{-- region:main --}}
        </main>

        <footer class="border-t border-rule">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                {{-- region:footer --}}
            </div>
        </footer>
    </body>
</html>
