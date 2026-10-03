{{-- marketing: No app shell at all: a navbar, a full-width hero, a stack of sections, a band, and a footer. For public sites and landing pages. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-palette="orchard">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-body text-ink">
        @include('partials.skip-link')

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
