{{-- split: The opening is two halves — what the business is on one side, when it is open and how to get there on the other — then its sections, and the footer as the close. For a shop, a bakery or a farm people walk into. --}}
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

        {{-- The opening is two columns only while the visit side holds something; the hero takes the width alone otherwise. Neither column is main's frame or the hero band, so a direction draws the components in them and leaves the columns alone. --}}
        <main>
            <div class="py-12 sm:py-16 [&:not(:has(>div>*>*))]:hidden">
                <div class="wrap grid items-center gap-10 lg:[&:has(>aside>*)]:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
                    <div class="min-w-0 [&:not(:has(>*))]:hidden">
                        {{-- region:hero --}}
                    </div>

                    <aside class="min-w-0 space-y-6 [&:not(:has(>*))]:hidden">
                        {{-- region:visit --}}
                    </aside>
                </div>
            </div>

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
