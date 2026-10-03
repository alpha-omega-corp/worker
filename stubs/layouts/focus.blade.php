{{-- focus: One narrow column, a progress indicator, and nothing else. For onboarding, checkout and wizards. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-palette="orchard">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-body text-ink">
        @include('partials.skip-link')

        <div class="mx-auto max-w-xl space-y-8 px-4 py-16 sm:px-6">
            <header>
                {{-- region:header --}}
            </header>

            <main id="main" tabindex="-1" class="space-y-6 outline-none">
                {{-- region:main --}}
            </main>
        </div>
    </body>
</html>
