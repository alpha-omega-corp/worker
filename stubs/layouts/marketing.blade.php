{{-- marketing: No app shell at all: a navbar, a full-width hero, a stack of sections, a band, and a footer. For public sites and landing pages. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-palette="orchard">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-site::head />

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas font-body text-ink">
        {{-- The page's first link, shown only to the keyboard: past the header and its links, to the page itself. A site read with a keyboard or a screen reader otherwise starts every page by tabbing through the whole navigation. --}}
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-canvas focus:px-4 focus:py-2 focus:text-ink focus:shadow-lg">{{ __('kit.skip') }}</a>

        <header class="wrap pt-4">
            {{-- region:nav --}}
        </header>

        {{-- Each part runs the full width and sets its own measure with .wrap; one nothing was placed in is hidden, so a schema older than hero and band builds the page it always did. --}}
        <main id="main" tabindex="-1" class="outline-none">
            <section class="py-16 sm:py-24 [&:not(:has(>.wrap>*))]:hidden [&_h1]:text-display [&_h2]:text-display">
                <div class="wrap">
                    {{-- region:hero --}}
                </div>
            </section>

            <div class="wrap space-y-24 py-16 [&:not(:has(>*))]:hidden">
                {{-- region:main --}}
            </div>

            <section class="bg-canvas-alt py-16 sm:py-24 [&:not(:has(>.wrap>*))]:hidden">
                <div class="wrap">
                    {{-- region:band --}}
                </div>
            </section>
        </main>

        <footer class="border-t border-rule">
            <div class="wrap py-12">
                {{-- region:footer --}}
            </div>
        </footer>
    </body>
</html>
