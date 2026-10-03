{{-- stacked: A top navbar, a page header band, and grids of cards. For dashboards and product apps. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-palette="orchard">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-body text-ink">
        @include('partials.skip-link')

        <nav class="border-b border-rule">
            {{-- region:nav --}}
        </nav>

        <header class="border-b border-rule bg-canvas-alt">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {{-- region:header --}}
            </div>
        </header>

        <main id="main" tabindex="-1" class="mx-auto grid max-w-7xl gap-6 px-4 py-8 outline-none sm:px-6 md:grid-cols-2 lg:grid-cols-3 lg:px-8">
            {{-- region:main --}}
        </main>
    </body>
</html>
