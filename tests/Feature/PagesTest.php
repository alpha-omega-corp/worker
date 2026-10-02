<?php

use AlphaOmega\Site\Identity\Identity;
use App\Support\Pages;
use App\Support\UiKit;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

// The manifest the site-factory contract shows, as deployer writes it: four
// spaces, every key, a trailing newline.
const MENU_MANIFEST = <<<'JSON'
{
    "pages": [
        {
            "view": "welcome",
            "path": "/",
            "role": "home",
            "title": null,
            "label": null,
            "description": null
        },
        {
            "view": "pages.menu",
            "path": "/menu",
            "role": "offer",
            "title": "Menu",
            "label": "Menu",
            "description": null
        }
    ]
}

JSON;

// Every test keeps its pages in a folder of its own rather than in
// resources/: these tests ship with each site made from this template, and
// that site's own manifest is not theirs to read or write.
beforeEach(function () {
    $this->folder = sys_get_temp_dir().'/pages-'.uniqid();
    File::ensureDirectoryExists("{$this->folder}/views/components");
});

afterEach(fn () => File::deleteDirectory($this->folder));

/**
 * A page as the manifest holds it, every key present.
 *
 * @param  array<string, mixed>  $keys
 * @return array<string, mixed>
 */
function aPage(string $view, string $path, array $keys = []): array
{
    return [...array_fill_keys(Pages::KEYS, null), 'view' => $view, 'path' => $path, ...$keys];
}

/**
 * These pages, served as the site serves its own: bound in place of the
 * site's manifest, shared with every view and routed. A page whose view the
 * site does not have prints what views are handed, through a component,
 * since components are what read it. Its composer runs after the site's own,
 * so what a view sees is this manifest's.
 *
 * @param  list<mixed>  $entries
 */
function servePages(string $folder, array $entries): Pages
{
    File::put("{$folder}/pages.json", (string) json_encode(['pages' => $entries]));
    File::put("{$folder}/views/components/probe.blade.php", "{!! json_encode(['nav' => \$siteNav, 'page' => \$sitePage, 'pages' => \$sitePages]) !!}");

    foreach ($entries as $entry) {
        if (is_array($entry) && is_string($entry['view'] ?? null) && ! str_contains($entry['view'], '/')) {
            File::ensureDirectoryExists(dirname($view = "{$folder}/views/".str_replace('.', '/', $entry['view']).'.blade.php'));
            File::put($view, '<x-probe />');
        }
    }

    View::addLocation("{$folder}/views");
    app()->instance(Pages::class, $pages = new Pages("{$folder}/pages.json"));
    $pages->share();
    $pages->routes();
    Route::getRoutes()->refreshNameLookups();

    return $pages;
}

test('a manifest keeps the pages that hold, and logs why it skips each other one', function () {
    Log::spy();

    $pages = servePages($this->folder, [
        aPage('welcome', '/', ['role' => 'home']),
        aPage('pages.menu', '/menu', ['label' => 'Menu']),
        aPage('pages/carte', '/carte'),
        aPage('pages.carte', '/Carte'),
        aPage('pages.story', '/histoire/'),
        aPage('pages.book', '/book/{day}'),
        aPage('pages.menu', '/menu-du-jour'),
        aPage('pages.again', '/menu'),
        aPage('landing', '/'),
        aPage('pages.home', '/home'),
        ['view' => 'pages.bare', 'path' => '/bare'],
        aPage('pages.titled', '/titled', ['title' => ['Menu']]),
        'pages.loose',
    ]);

    expect(array_column($pages->all(), 'view'))->toBe(['welcome', 'pages.menu'])
        ->and($pages->problems())->toBe([
            'Page 3: "pages/carte" is not a view name. Use lowercase letters, digits, - and _, with dots for folders: pages.menu.',
            'Page 4 (pages.carte): "/Carte" is not a path. Use / or lowercase words joined by hyphens, each after a slash: /la-carte.',
            'Page 5 (pages.story): "/histoire/" is not a path. Use / or lowercase words joined by hyphens, each after a slash: /la-carte.',
            'Page 6 (pages.book): "/book/{day}" is not a path. Use / or lowercase words joined by hyphens, each after a slash: /la-carte.',
            'Page 7 (pages.menu): page 2 is pages.menu already. A view is one page: remove one of them.',
            'Page 8 (pages.again): /menu is page 2\'s path already. A path is one page\'s: give one of them another.',
            'Page 9 (landing): / is page 1\'s path already. A path is one page\'s: give one of them another.',
            'Page 10 (pages.home): /home would be named home, like the page at /. Choose another path.',
            'Page 11 (pages.bare): role, title, label, description are missing. Each page names its view, path, role, title, label and description, null when it has none.',
            'Page 12 (pages.titled): its title is neither text nor null. Write it as a string, or null when there is none.',
            'Page 13: it is not an object. Each page names its view, path, role, title, label and description.',
        ]);

    Log::shouldHaveReceived('warning')->times(11);

    $this->get('/menu')->assertOk();
    $this->get('/titled')->assertNotFound();
});

test('a page listed before its view is built answers 404 rather than 500, and the log says why', function () {
    Log::spy();
    File::put("{$this->folder}/pages.json", (string) json_encode(['pages' => [aPage('welcome', '/'), aPage('pages.unbuilt', '/menu', ['label' => 'Menu'])]]));
    app()->instance(Pages::class, $pages = new Pages("{$this->folder}/pages.json"));
    $pages->routes();

    $this->get('/menu')->assertNotFound();

    expect($pages->nav())->toBe([['label' => 'Menu', 'href' => '/menu', 'current' => false]]);

    Log::shouldHaveReceived('warning')->with('pages.unbuilt is not a view yet, so /menu is not served until it is: ui:layout builds it.', Mockery::any())->once();
});

test('a manifest that cannot be read is logged, and the site serves welcome at / alone', function (string $content) {
    Log::spy();
    File::put("{$this->folder}/pages.json", $content);
    Route::setRoutes(new RouteCollection);

    $pages = new Pages("{$this->folder}/pages.json");
    $pages->routes();

    expect($pages->exists())->toBeFalse()
        ->and($pages->problems())->toHaveCount(1)
        ->and($pages->problems()[0])->toStartWith('resources/pages.json is not a page manifest: ')
        ->and(array_map(fn ($route) => [$route->uri(), $route->getName()], Route::getRoutes()->getRoutes()))->toBe([['/', 'home']]);

    Log::shouldHaveReceived('warning')->once();
})->with([
    'not JSON' => '{"pages": [',
    'a bare list' => '[]',
    'no list of pages' => '{"pages": {"view": "welcome"}}',
]);

test('a site without a manifest serves welcome at / and nothing else, as it always has', function () {
    $sitemap = config('site.sitemap');
    Route::setRoutes(new RouteCollection);

    $pages = new Pages("{$this->folder}/pages.json");
    $pages->share();
    $pages->routes();

    $routes = Route::getRoutes()->getRoutes();

    expect($routes)->toHaveCount(1)
        ->and($routes[0]->uri())->toBe('/')
        ->and($routes[0]->getName())->toBe('home')
        ->and($routes[0]->defaults['view'])->toBe('welcome')
        ->and(config('site.sitemap'))->toBe($sitemap)
        ->and([$pages->nav(), $pages->current(), $pages->all(), $pages->problems()])->toBe([[], null, [], []]);
});

test('every page answers with its own view, under the route its path names', function () {
    $this->withoutVite();
    servePages($this->folder, [
        aPage('welcome', '/', ['role' => 'home']),
        aPage('pages.menu', '/menu', ['label' => 'Menu']),
        aPage('pages.story', '/la-maison/histoire', ['label' => 'Histoire']),
    ]);

    $this->get('/')->assertOk()->assertViewIs('welcome');
    $this->get('/menu')->assertOk()->assertViewIs('pages.menu');
    $this->get('/la-maison/histoire')->assertOk()->assertViewIs('pages.story');

    expect(route('home', absolute: false))->toBe('/')
        ->and(route('menu', absolute: false))->toBe('/menu')
        ->and(route('la-maison.histoire', absolute: false))->toBe('/la-maison/histoire');
});

test('every view, a component\'s too, is handed the navigation with the page being served marked', function () {
    servePages($this->folder, [
        aPage('welcome', '/', ['role' => 'home']),
        aPage('pages.menu', '/menu', ['role' => 'offer', 'title' => 'Menu', 'label' => 'Menu']),
        aPage('pages.visit', '/contact', ['role' => 'visit', 'label' => 'Contact']),
    ]);

    $seen = $this->get('/menu')->assertOk()->json();

    expect($seen['nav'])->toBe([
        ['label' => 'Menu', 'href' => '/menu', 'current' => true],
        ['label' => 'Contact', 'href' => '/contact', 'current' => false],
    ])
        ->and($seen['page'])->toBe(aPage('pages.menu', '/menu', ['role' => 'offer', 'title' => 'Menu', 'label' => 'Menu']))
        ->and(array_column($seen['pages'], 'view'))->toBe(['welcome', 'pages.menu', 'pages.visit'])
        ->and($this->get('/contact')->json('nav.*.current'))->toBe([false, true]);
});

test('without a route nothing is current, as in the console', function () {
    $pages = servePages($this->folder, [aPage('welcome', '/'), aPage('pages.menu', '/menu', ['label' => 'Menu'])]);

    expect($pages->current())->toBeNull()
        ->and($pages->nav())->toBe([['label' => 'Menu', 'href' => '/menu', 'current' => false]]);
});

test('each layout puts its page\'s title and description in the head, the title before the site\'s name', function (string $layout) {
    $this->withoutVite();
    servePages($this->folder, [
        aPage('welcome', '/'),
        aPage('pages.menu', '/menu', ['title' => 'Menu', 'description' => 'What we cook this week.']),
    ]);
    File::put("{$this->folder}/views/pages/menu.blade.php", app(UiKit::class)->render($layout, []));

    $this->get('/menu')->assertOk()
        ->assertSee('<title>Menu · '.e(Identity::current()->name).'</title>', false)
        ->assertSee('<meta name="description" content="What we cook this week.">', false);
})->with(array_map(fn (string $stub): string => basename($stub, '.blade.php'), glob(__DIR__.'/../../stubs/layouts/*.blade.php') ?: []));

test('welcome puts its page\'s title in the head as the layouts do', function () {
    $this->withoutVite();
    servePages($this->folder, [aPage('welcome', '/', ['title' => 'Accueil'])]);

    $this->get('/')->assertOk()->assertSee('<title>Accueil · '.e(Identity::current()->name).'</title>', false);
});

test('the sitemap lists every page', function () {
    servePages($this->folder, [aPage('welcome', '/'), aPage('pages.menu', '/menu', ['label' => 'Menu']), aPage('pages.story', '/la-maison')]);

    expect(config('site.sitemap'))->toBe(['/', '/menu', '/la-maison']);

    $this->get('/sitemap.xml')->assertOk()->assertSee('/la-maison</loc>', false);
});

test('ui:page writes the manifest as deployer does, byte for byte, and back', function () {
    app()->instance(Pages::class, new Pages($manifest = "{$this->folder}/pages.json"));
    $menu = ['view' => 'pages.menu', '--path' => '/menu', '--title' => 'Menu', '--label' => 'Menu', '--role' => 'offer'];

    $this->artisan('ui:page', $menu)
        ->expectsOutputToContain('pages.menu is served at /menu, as the route menu.')
        ->assertSuccessful();

    expect(file_get_contents($manifest))->toBe(MENU_MANIFEST);

    $this->artisan('ui:page', ['view' => 'pages.menu', '--title' => ''])->assertSuccessful();

    expect(json_decode((string) file_get_contents($manifest), true)['pages'][1]['title'])->toBeNull();

    $this->artisan('ui:page', ['view' => 'pages.menu', '--title' => 'Menu'])->assertSuccessful();

    expect(file_get_contents($manifest))->toBe(MENU_MANIFEST);

    $this->artisan('ui:page', ['view' => 'pages.menu', '--remove' => true])
        ->expectsOutputToContain('pages.menu is no longer a page')
        ->assertSuccessful();

    expect(json_decode((string) file_get_contents($manifest), true)['pages'])->toBe([aPage('welcome', '/', ['role' => 'home'])]);

    $this->artisan('ui:page', $menu)->assertSuccessful();

    expect(file_get_contents($manifest))->toBe(MENU_MANIFEST);
});

test('ui:page refuses what the manifest refuses, says how to fix it, and writes nothing', function (array $arguments, string $said) {
    File::put($manifest = "{$this->folder}/pages.json", MENU_MANIFEST);
    app()->instance(Pages::class, new Pages($manifest));

    $this->artisan('ui:page', $arguments)->expectsOutputToContain($said)->assertFailed();

    expect(file_get_contents($manifest))->toBe(MENU_MANIFEST);
})->with([
    'a slash in a view name' => [['view' => 'pages/visit', '--path' => '/visit'], '"pages/visit" is not a view name.'],
    'a capital in a path' => [['view' => 'pages.visit', '--path' => '/Visit'], '"/Visit" is not a path.'],
    'a trailing slash' => [['view' => 'pages.visit', '--path' => '/visit/'], '"/visit/" is not a path.'],
    'a path another page has' => [['view' => 'pages.visit', '--path' => '/menu'], "/menu is page 2's path already."],
    'a second page at /' => [['view' => 'landing', '--path' => '/'], "/ is page 1's path already."],
    'the home page\'s route name' => [['view' => 'pages.home', '--path' => '/home'], '/home would be named home'],
    'a new page with no path' => [['view' => 'pages.visit', '--label' => 'Visit'], 'pages.visit is not a page yet. Give it a path: --path=/visit.'],
    'a page that is not there' => [['view' => 'pages.visit', '--remove' => true], 'There is no page pages.visit to remove.'],
]);

test('ui:page will not write over a file that is not a manifest', function () {
    File::put($manifest = "{$this->folder}/pages.json", '{"pages": [');
    app()->instance(Pages::class, new Pages($manifest));

    $this->artisan('ui:page', ['view' => 'pages.menu', '--path' => '/menu'])
        ->expectsOutputToContain('delete it to start again from welcome at /.')
        ->assertFailed();

    expect(file_get_contents($manifest))->toBe('{"pages": [');
});

test('ui:pages lists each page with its route, whether its view and its mockup exist, and what is wrong', function () {
    File::put($manifest = "{$this->folder}/pages.json", (string) json_encode(['pages' => [
        aPage('welcome', '/'),
        aPage('pages.menu', '/menu', ['title' => 'Menu', 'label' => 'Menu']),
        aPage('pages.visit', '/contact', ['label' => 'Contact']),
        aPage('pages.menu', '/carte'),
    ]]));
    File::ensureDirectoryExists("{$this->folder}/views/pages");
    File::put("{$this->folder}/views/pages/menu.blade.php", 'Menu');
    File::ensureDirectoryExists("{$this->folder}/layouts");
    File::put("{$this->folder}/layouts/pages.menu.json", '{"layout": "carte", "regions": {}}');
    View::addLocation("{$this->folder}/views");
    app()->instance(Pages::class, new Pages($manifest));

    $this->artisan('ui:pages')
        ->expectsTable(['Path', 'View', 'Route', 'Title', 'Label', 'View file', 'Mockup'], [
            ['/', 'welcome', 'home', '', '', 'yes', 'none'],
            ['/menu', 'pages.menu', 'menu', 'Menu', 'Menu', 'yes', 'yes'],
            ['/contact', 'pages.visit', 'contact', '', 'Contact', 'missing', 'none'],
        ])
        ->expectsOutputToContain('Page 4 (pages.menu): page 2 is pages.menu already.')
        ->assertFailed();
});

test('ui:pages says so when there is no manifest', function () {
    app()->instance(Pages::class, new Pages("{$this->folder}/pages.json"));

    $this->artisan('ui:pages')
        ->expectsOutputToContain('There is no resources/pages.json: the site serves welcome at / alone.')
        ->assertSuccessful();
});

test('ui:layout --path builds a page and routes it in one command, and nothing when the page is refused', function () {
    // A site of its own: the stubs and the graph ui:layout reads, and nothing
    // else, so the view and the components it imports land in the test's folder.
    $site = $this->folder;
    File::ensureDirectoryExists("{$site}/resources/layouts");
    File::link(base_path('stubs'), "{$site}/stubs");
    File::link(base_path('resources/components.php'), "{$site}/resources/components.php");
    File::put("{$site}/resources/layouts/pages.menu.json", '{"layout": "carte", "regions": {"lead": ["section"]}}');
    app()->setBasePath($site);
    app()->instance(Pages::class, new Pages(resource_path('pages.json')));
    View::addLocation("{$site}/resources/views");

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.draft'])->assertSuccessful();

    expect("{$site}/resources/pages.json")->not->toBeFile();

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.visit', '--path' => '/Visit'])
        ->expectsOutputToContain('"/Visit" is not a path.')
        ->assertFailed();

    expect("{$site}/resources/views/pages/visit.blade.php")->not->toBeFile()
        ->and("{$site}/resources/pages.json")->not->toBeFile();

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.menu', '--path' => '/menu', '--title' => 'Menu', '--label' => 'Menu', '--role' => 'offer'])
        ->expectsOutputToContain('pages.menu is served at /menu, as the route menu.')
        ->assertSuccessful();

    expect(file_get_contents("{$site}/resources/pages.json"))->toBe(MENU_MANIFEST)
        ->and(file_get_contents("{$site}/resources/views/pages/menu.blade.php"))->toContain('<x-kit.section />');
});
