{{-- focus: One narrow column, a progress indicator, and nothing else. For onboarding, checkout and wizards. --}}
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
        <div class="mx-auto max-w-xl space-y-8 px-4 py-16 sm:px-6">
            <header>
                {{-- region:header --}}
            </header>

            <main class="space-y-6">
                {{-- region:main --}}
            </main>
        </div>
    </body>
</html>
