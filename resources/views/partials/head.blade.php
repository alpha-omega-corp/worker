{{--
    Every page's head: the base's title, description, sharing tags and structured
    data from the page's entry in resources/pages.json, the site's icons, and the
    faces and stylesheet it is drawn in. The layout stubs include it rather than
    carry a copy each, so the pages built from them share one head; deployer's
    mockup preview drops the line, as it drops @vite.
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<x-site::head :title="$sitePage['title'] ?? null" :description="$sitePage['description'] ?? null" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
