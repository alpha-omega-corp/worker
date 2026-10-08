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

## Building a page

`ui:layout` builds a page whole from its mockup, whoever runs it — deployer's Build, a session, or a person:

- the layout's stub, each component's tag in its region and every component imported with what it requires;
- the mockup's `variants`, each component's arrangement, on its tag (`<x-kit.hero variant="cover" />`), and with `themePicker` the theme picker on the site header's (`:theme-picker="true"`); any other tag is written bare;
- the mockup's `theme` and `direction` on the page's `<html>` (`data-palette`, `data-direction`), refused unless the kit's `themes.css` and `kit.css` draw them;
- on the base, the prefabs it places switched on (`site:prefab enable`): run `php artisan migrate` when it says so;
- built from its own mockup, the mockup marked `builtAs` exactly as deployer marks it, so the Design tab reads the page as built.

An existing view is replaced only with `--replace`, which leaves every component the site already has alone; `--force` also writes the package's components back over the site's. `php artisan ui:list` names the components, the layouts with their regions, the palettes and the directions.

Every stub includes `resources/views/partials/head.blade.php` (the base's title, description and structured data, the icons, the fonts and the stylesheet) and `partials/skip-link.blade.php`, so the pages built from them share one head. Their `site-header` and `site-footer` name the business from the base's identity, edited at `/admin`, wherever the tag leaves it out: the name, and the footer's address, phone and email. What a tag gives still wins.

## The look

A site has one look: a palette and a direction, on every page. Change it in one command, keeping what is written in the pages:

    php artisan ui:theme                              # what each page is drawn in, and the palettes and directions there are
    php artisan ui:theme harbour --direction=hearth   # every mockup, and every view on its <html> alone
    php artisan ui:theme --direction=                 # no direction

A page built from its mockup stays built at the new look. The admin wears the palette of the home page's mockup unless `config/site.php` names one, and Vite loads the faces of the palettes the mockups name on its next build.
