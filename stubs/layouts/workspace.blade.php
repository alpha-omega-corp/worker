{{-- workspace: A narrow icon rail, a list column, and a detail column beside it. For inboxes, triage and collaboration. --}}
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
        <div class="flex min-h-screen">
            <nav class="w-16 shrink-0 border-r border-rule">
                {{-- region:rail --}}
            </nav>

            <section class="w-80 shrink-0 overflow-y-auto border-r border-rule">
                {{-- region:list --}}
            </section>

            <main class="min-w-0 flex-1 space-y-6 overflow-y-auto px-6 py-6">
                {{-- region:detail --}}
            </main>
        </div>
    </body>
</html>
