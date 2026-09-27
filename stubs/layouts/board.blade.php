{{-- board: A board of tiles instead of a stack of sections — the first tile large, the rest one tile each — under a short opening. For a café, a shop or a market stall with several things to say at once. --}}
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
        <header class="wrap pt-4">
            {{-- region:nav --}}
        </header>

        {{-- The board is a grid inside main's frame rather than the frame itself, so a direction that lays main out as a grid of its own spans the board whole and the tiles keep this one. --}}
        <main>
            <section class="py-12 sm:py-16 [&:not(:has(>.wrap>*))]:hidden">
                <div class="wrap">
                    {{-- region:hero --}}
                </div>
            </section>

            <div class="wrap pb-16 [&:not(:has(>div>*))]:hidden">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:[&>:first-child]:col-span-2 lg:[&>:first-child]:row-span-2 [&>*]:min-w-0">
                    {{-- region:main --}}
                </div>
            </div>
        </main>

        <footer class="border-t border-rule">
            <div class="wrap py-12">
                {{-- region:footer --}}
            </div>
        </footer>
    </body>
</html>
