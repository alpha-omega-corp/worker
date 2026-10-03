{{-- board: A board of tiles instead of a stack of sections — the first tile large, the rest one tile each — under a short opening. For a café, a shop or a market stall with several things to say at once. --}}
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

        {{-- The board is a grid inside main's frame rather than the frame itself, so a direction that lays main out as a grid of its own spans the board whole and the tiles keep this one. --}}
        <main id="main" tabindex="-1" class="outline-none">
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
