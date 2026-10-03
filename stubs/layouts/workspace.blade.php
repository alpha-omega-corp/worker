{{-- workspace: A narrow icon rail, a list column, and a detail column beside it. For inboxes, triage and collaboration. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-palette="orchard">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-body text-ink">
        @include('partials.skip-link')

        {{-- Side by side from md, one screen tall, the list and the detail each scrolling on its own. On a phone a 4rem rail and a 20rem list left the detail no width at all, so the three stack: the rail a strip across the top, the list, then the detail. --}}
        <div class="flex min-h-screen flex-col md:h-screen md:flex-row">
            <nav class="flex shrink-0 border-b border-rule md:w-16 md:flex-col md:border-r md:border-b-0">
                {{-- region:rail --}}
            </nav>

            <section class="shrink-0 border-b border-rule md:w-80 md:overflow-y-auto md:border-r md:border-b-0">
                {{-- region:list --}}
            </section>

            <main id="main" tabindex="-1" class="min-w-0 flex-1 space-y-6 px-6 py-6 outline-none md:overflow-y-auto">
                {{-- region:detail --}}
            </main>
        </div>
    </body>
</html>
