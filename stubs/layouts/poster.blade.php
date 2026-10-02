{{-- poster: The whole business on one screen — the name large, what it is, and the hours and the way in along the foot of that screen — with whatever else it has quietly below. For a small café, a food truck or a barber. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-palette="orchard">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <x-site::head :title="$sitePage['title'] ?? null" :description="$sitePage['description'] ?? null" />

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas font-body text-ink">
        {{-- The page's first link, shown only to the keyboard: past the header and its links, to the page itself. A site read with a keyboard or a screen reader otherwise starts every page by tabbing through the whole navigation. --}}
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-canvas focus:px-4 focus:py-2 focus:text-ink focus:shadow-lg">{{ __('kit.skip') }}</a>

        <header class="wrap pt-4">
            {{-- region:nav --}}
        </header>

        {{-- The cover is the first screen: the hero in the frame a direction draws as the hero band, the facts along its foot in a column of their own, since a direction may let the hero's .wrap out to the whole page. --}}
        <main id="main" tabindex="-1" class="outline-none">
            <section class="grid min-h-[calc(100svh-5rem)] content-between gap-10 py-16 [&:not(:has(>div>*))]:hidden">
                <div class="wrap self-center">
                    {{-- region:hero --}}
                </div>

                <div class="mx-auto grid w-full max-w-(--container-wrap) gap-8 border-t border-rule px-5 pt-8 md:grid-cols-2 md:px-10 [&:not(:has(>*))]:hidden">
                    {{-- region:facts --}}
                </div>
            </section>

            <div class="wrap space-y-24 py-16 [&:not(:has(>*))]:hidden">
                {{-- region:main --}}
            </div>
        </main>

        <footer class="border-t border-rule">
            <div class="wrap py-12">
                {{-- region:footer --}}
            </div>
        </footer>
    </body>
</html>
