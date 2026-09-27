{{-- journal: A magazine's rhythm — a lead photograph across the page, the story in one narrow column, broken once by a band of pictures — closing on a colophon rather than a sales band. For a winery, a chef's restaurant or a farm with something to tell. --}}
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
        <header class="wrap pt-4">
            {{-- region:nav --}}
        </header>

        <main>
            <section class="py-16 sm:py-24 [&:not(:has(>.wrap>*))]:hidden [&_h1]:text-display">
                <div class="wrap">
                    {{-- region:hero --}}
                </div>
            </section>

            <div class="wrap max-w-3xl space-y-20 py-16 [&:not(:has(>*))]:hidden">
                {{-- region:story --}}
            </div>

            <section class="bg-canvas-alt py-16 sm:py-24 [&:not(:has(>.wrap>*))]:hidden">
                <div class="wrap">
                    {{-- region:gallery --}}
                </div>
            </section>

            <div class="wrap max-w-3xl space-y-20 py-16 [&:not(:has(>*))]:hidden">
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
