{{-- console: A persistent sidebar, a page header, and dense data under it. For admin screens, back offices and CRUD. --}}
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
        <div class="lg:flex">
            <aside class="border-b border-rule lg:min-h-screen lg:w-72 lg:shrink-0 lg:border-r lg:border-b-0">
                {{-- region:nav --}}
            </aside>

            <div class="min-w-0 flex-1">
                <header class="border-b border-rule px-4 py-6 sm:px-6 lg:px-8">
                    {{-- region:header --}}
                </header>

                <main class="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                    {{-- region:main --}}
                </main>
            </div>
        </div>
    </body>
</html>
