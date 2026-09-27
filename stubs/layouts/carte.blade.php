{{-- carte: No hero: the page opens on what is offered — the menu, the price list or the goods — with the hours and the way in held beside it. For a restaurant, a café or a salon whose list is the reason people come. --}}
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

        <main>
            <div class="wrap pt-12 sm:pt-16 [&:not(:has(>*))]:hidden">
                {{-- region:lead --}}
            </div>

            {{-- The offer and the visit side by side on a wide screen, the visit held in view while the list scrolls; on a phone the visit comes after the offer. --}}
            <div class="py-12 sm:py-16 [&:not(:has(>div>*>*))]:hidden">
                <div class="wrap grid items-start gap-12 lg:[&:has(>aside>*)]:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="min-w-0 space-y-16 [&:not(:has(>*))]:hidden">
                        {{-- region:offer --}}
                    </div>

                    <aside class="min-w-0 space-y-6 lg:sticky lg:top-6 [&:not(:has(>*))]:hidden">
                        {{-- region:visit --}}
                    </aside>
                </div>
            </div>

            <div class="wrap space-y-24 pb-16 [&:not(:has(>*))]:hidden">
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
