# Project

## uikit 
composer config repositories.ui-kit vcs https://github.com/alpha-omega-corp/laravel
composer require --dev alpha-omega-corp/ui-kit:dev-production

## Pages

A page is three things, named by one view:

- its entry in `resources/pages.json`: the path it is served at, its title (the head writes "Title · Name"), its label in the navigation (none keeps it out), its role and its description;
- its view, `resources/views/pages/menu.blade.php` for `pages.menu`;
- its mockup, `resources/layouts/pages.menu.json`, the layout and components the view was built from.

`routes/web.php` serves every page with `Route::view`, named after its path (`home` for `/`, `la-maison` for `/la-maison`). Every view, components included, is handed `$siteNav` (the pages with a label, the one being served marked `current`), `$sitePage` and `$sitePages`; the layouts put the page's title and description in the head, and `/sitemap.xml` lists every path. Without the file the site serves welcome at `/` alone, as it always has. Deployer writes the same file.

Build a page and route it at once, or route a view that is already there:

    php artisan ui:layout resources/layouts/pages.menu.json --view=pages.menu --path=/menu --title=Menu --label=Menu --role=offer
    php artisan ui:page pages.menu --path=/menu --title=Menu --label=Menu
    php artisan ui:page pages.menu --title=     # an empty option clears it
    php artisan ui:page pages.menu --remove     # its view and its mockup stay
    php artisan ui:pages                        # every page, its route, whether its view and mockup exist, and what is wrong

A broken entry is skipped and logged rather than answered with a 500, and a page whose view is not built yet answers 404 until it is; `ui:pages` says what is wrong. `php artisan optimize` caches the routes: run it again once a page is added or its view built.
