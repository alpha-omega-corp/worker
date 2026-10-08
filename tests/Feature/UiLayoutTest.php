<?php

use AlphaOmega\Site\Content\Content;
use App\Support\Mockups;
use App\Support\Pages;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => $this->site = aSiteOfItsOwn());

afterEach(fn () => File::deleteDirectory($this->site));

test('a page is built in its mockup\'s palette and direction, and marked built as deployer marks it', function () {
    $schema = ['layout' => 'split', 'theme' => 'harbour', 'business' => 'bakery', 'direction' => 'hearth', 'regions' => [
        'nav' => ['site-header'], 'hero' => ['hero'], 'visit' => [], 'footer' => ['site-footer'],
    ]];
    aMockup('pages.menu', $schema);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.menu'])->assertSuccessful();

    expect(file_get_contents(resource_path('views/pages/menu.blade.php')))
        ->toContain('" data-palette="harbour" data-direction="hearth">', '<x-kit.hero />');

    // The digest deployer's uikit.version gives this schema.
    expect(mockupOf('pages.menu'))->toBe([...$schema, 'builtAs' => '2dd0e62c6614']);
});

test('a schema built into another view marks neither mockup built', function () {
    aMockup('pages.menu', ['layout' => 'carte', 'regions' => ['lead' => ['section']]]);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.draft'])->assertSuccessful();

    expect(mockupOf('pages.menu'))->not->toHaveKey('builtAs')
        ->and(resource_path('layouts/pages.draft.json'))->not->toBeFile();
});

test('--replace builds a page again and keeps the components the site has made its own', function () {
    aMockup('pages.menu', ['layout' => 'carte', 'regions' => ['lead' => ['section']]]);
    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.menu'])->assertSuccessful();
    File::put(resource_path('views/components/kit/section.blade.php'), 'the site\'s own section');
    File::put(resource_path('views/pages/menu.blade.php'), 'filled in since');

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.menu'])
        ->expectsOutputToContain('pages.menu already exists; pass --replace to build it again')
        ->assertFailed();

    expect(file_get_contents(resource_path('views/pages/menu.blade.php')))->toBe('filled in since');

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.menu', '--replace' => true])->assertSuccessful();

    expect(file_get_contents(resource_path('views/pages/menu.blade.php')))->toContain('<x-kit.section />')
        ->and(file_get_contents(resource_path('views/components/kit/section.blade.php')))->toBe('the site\'s own section');
});

test('a palette the kit does not draw is refused, and nothing is written', function () {
    aMockup('pages.menu', ['layout' => 'carte', 'theme' => 'teal', 'regions' => ['lead' => ['section']]]);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.menu'])
        ->expectsOutputToContain('There is no palette called [teal]')
        ->assertFailed();

    expect(resource_path('views/pages/menu.blade.php'))->not->toBeFile()
        ->and(resource_path('views/components/kit/section.blade.php'))->not->toBeFile()
        ->and(mockupOf('pages.menu'))->not->toHaveKey('builtAs');
});

// A site hands every page the same frame, so a page with no hero or header is
// still given their arrangements and the picker.
test('a page is built in its mockup\'s arrangements, passing over a part it does not place and the picker on a page with no header', function () {
    aMockup('pages.menu', ['layout' => 'carte', 'regions' => ['lead' => ['section']], 'variants' => ['section' => 'split', 'hero' => 'cover'], 'themePicker' => true]);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.menu'])->assertSuccessful();

    expect(file_get_contents(resource_path('views/pages/menu.blade.php')))->toContain('<x-kit.section variant="split" />')
        ->not->toContain('variant="cover"')
        ->not->toContain('theme-picker');
});

test('the arrangements and the picker are digested as deployer digests them, and a schema with neither as before them', function () {
    $schema = ['layout' => 'marketing', 'theme' => 'orchard', 'direction' => 'counter', 'regions' => [
        'nav' => ['site-header'], 'hero' => ['hero'], 'main' => [], 'footer' => ['site-footer'],
    ]];

    // Each the digest deployer's uikit.version gives.
    expect(Mockups::version([...$schema, 'variants' => ['site-header' => 'centred', 'hero' => 'cover', 'site-footer' => 'compact'], 'themePicker' => true]))->toBe('b38b04c781c8')
        ->and(Mockups::version($schema))->toBe('e1045ae0dd96')
        ->and(Mockups::version([...$schema, 'variants' => ['hero' => ''], 'themePicker' => false]))->toBe('e1045ae0dd96');
});

// The word lands in an attribute, so only that is asked of it: whether the
// component draws it is deployer's to check before it saves.
test('an arrangement that is not a word, variants that are not an object or a picker that is not a boolean is refused, and nothing is written', function (array $given, string $said) {
    aMockup('welcome', ['layout' => 'marketing', 'regions' => ['nav' => ['site-header'], 'hero' => ['hero']], ...$given]);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/welcome.json'])
        ->expectsOutputToContain($said)
        ->assertFailed();

    expect(resource_path('views/welcome.blade.php'))->not->toBeFile()
        ->and(resource_path('views/components/kit/hero.blade.php'))->not->toBeFile()
        ->and(mockupOf('welcome'))->not->toHaveKey('builtAs');
})->with([
    'a word that is not one' => [['variants' => ['hero' => 'Cover!']], 'The arrangement [Cover!] for hero is not a word'],
    'variants that are a list' => [['variants' => ['cover']], 'variants is a list'],
    'variants that are a word' => [['variants' => 'cover'], 'The schema reads'],
    'a picker that is not a boolean' => [['themePicker' => 'yes'], 'The schema reads'],
]);

test('on the base, the prefabs a page places are switched on, and building it again finds them on', function () {
    aMockup('welcome', ['layout' => 'split', 'regions' => ['visit' => ['schedule', 'map']]]);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/welcome.json'])
        ->expectsOutputToContain('Switched on hours, map in config/site.php: run php artisan migrate')
        ->assertSuccessful();

    expect(file_get_contents(config_path('site.php')))->toContain("'prefabs' => ['hours', 'map']");

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/welcome.json', '--replace' => true])
        ->expectsOutputToContain('Its prefabs are on: hours, map.')
        ->assertSuccessful();
});

// The page is written by then, so the command still succeeds: deployer reads a
// failed ui:layout as a page it may put back as it was.
test('a prefab the base refuses to switch on is said, and the page stands unmarked', function (Closure $breakTheConfig) {
    $breakTheConfig();
    aMockup('welcome', ['layout' => 'split', 'regions' => ['visit' => ['schedule']]]);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/welcome.json'])
        ->expectsOutputToContain('site:prefab enable hours refused, so the prefabs on this page draw nothing')
        ->assertSuccessful();

    expect(file_get_contents(resource_path('views/welcome.blade.php')))->toContain('<x-kit.schedule />')
        ->and(mockupOf('welcome'))->not->toHaveKey('builtAs');
})->with([
    'its prefabs line edited away' => fn () => File::put(config_path('site.php'), "<?php\n\nreturn [];\n"),
    'no config folder to publish into' => fn () => File::deleteDirectory(config_path()),
]);

test('a page built with bare tags names the business in its header and footer from its identity, and a tag\'s own word wins', function () {
    Content::save('identity', ['name' => 'Boulangerie Favre', 'street' => 'Rue du Bourg 3', 'postcode' => '1003', 'city' => 'Lausanne', 'phone' => '021 312 45 67', 'email' => 'bonjour@favre.ch']);
    aMockup('pages.visit', ['layout' => 'marketing', 'regions' => ['nav' => ['site-header'], 'footer' => ['site-footer']]]);
    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.visit.json', '--view' => 'pages.visit', '--path' => '/visit'])->assertSuccessful();
    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.visit.json', '--view' => 'pages.story', '--path' => '/story'])->assertSuccessful();
    File::put($story = resource_path('views/pages/story.blade.php'), str_replace('<x-kit.site-header />', '<x-kit.site-header brand="Chez Favre" />', (string) file_get_contents($story)));
    app(Pages::class)->routes();
    Route::getRoutes()->refreshNameLookups();
    $this->withoutVite();

    $read = function (string $path): DOMXPath {
        $document = new DOMDocument;
        $document->loadHTML((string) $this->get($path)->assertOk()->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    };

    $visit = $read('/visit');

    expect(trim($visit->evaluate('string(//*[@data-kit-part="site-header-brand"])')))->toBe('Boulangerie Favre')
        ->and(trim($visit->evaluate('string(//*[@data-kit-part="site-footer-brand"])')))->toBe('Boulangerie Favre')
        ->and(trim($visit->evaluate('string(//*[@data-kit-part="site-footer-contact"]//p)')))->toBe('Rue du Bourg 3, 1003 Lausanne')
        ->and($visit->evaluate('string(//*[@data-kit-part="site-footer-contact"]//a[starts-with(@href, "tel:")]/@href)'))->toBe('tel:0213124567')
        ->and($visit->evaluate('string(//*[@data-kit-part="site-footer-contact"]//a[starts-with(@href, "mailto:")]/@href)'))->toBe('mailto:bonjour@favre.ch');

    $story = $read('/story');

    expect(trim($story->evaluate('string(//*[@data-kit-part="site-header-brand"])')))->toBe('Chez Favre')
        ->and(trim($story->evaluate('string(//*[@data-kit-part="site-footer-brand"])')))->toBe('Boulangerie Favre');
});

// The frame end to end: written on the tags by the command, stamped with
// deployer's digest, and drawn by the kit's own components when the page is
// served — the theme menu among them, since the base binds it.
test('a page built in its frame is served in it, its header offering the theme picker', function () {
    Content::save('identity', ['name' => 'Boulangerie Favre']);
    aMockup('pages.visit', ['layout' => 'marketing', 'theme' => 'orchard', 'direction' => 'counter', 'regions' => [
        'nav' => ['site-header'], 'hero' => ['hero'], 'main' => [], 'footer' => ['site-footer'],
    ], 'variants' => ['site-header' => 'centred', 'hero' => 'cover', 'site-footer' => 'compact'], 'themePicker' => true]);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.visit.json', '--view' => 'pages.visit', '--path' => '/visit'])->assertSuccessful();

    expect(file_get_contents(resource_path('views/pages/visit.blade.php')))
        ->toContain('<x-kit.site-header variant="centred" :theme-picker="true" />', '<x-kit.hero variant="cover" />', '<x-kit.site-footer variant="compact" />')
        ->and(mockupOf('pages.visit')['builtAs'])->toBe('b38b04c781c8');

    app(Pages::class)->routes();
    Route::getRoutes()->refreshNameLookups();
    $this->withoutVite();

    $document = new DOMDocument;
    $document->loadHTML((string) $this->get('/visit')->assertOk()->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $page = new DOMXPath($document);

    expect($page->evaluate('string(//*[@data-kit="site-header"]/@data-variant)'))->toBe('centred')
        ->and($page->evaluate('string(//*[@data-kit="hero"]/@data-variant)'))->toBe('cover')
        ->and($page->evaluate('string(//*[@data-kit="site-footer"]/@data-variant)'))->toBe('compact')
        ->and($page->evaluate('count(//*[@data-kit="theme-picker"])'))->toBeGreaterThan(0);
});

test('--description gives the page its meta description', function () {
    aMockup('pages.menu', ['layout' => 'carte', 'regions' => ['lead' => ['section']]]);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/pages.menu.json', '--view' => 'pages.menu', '--path' => '/menu', '--description' => 'La carte de saison.'])
        ->assertSuccessful();

    expect(collect(app(Pages::class)->all())->firstWhere('view', 'pages.menu')['description'])->toBe('La carte de saison.');
});

// Each prefab's tag is written bare, so each has to draw the site's rows, or
// none, from its own tables alone: one that throws is every request a 500.
test('a page of all nine prefabs, built bare and migrated, answers', function () {
    aMockup('welcome', ['layout' => 'marketing', 'regions' => [
        'main' => ['menu', 'schedule', 'catalogue', 'map', 'gallery', 'team', 'events', 'faq', 'booking'],
    ]]);

    $this->artisan('ui:layout', ['schema' => 'resources/layouts/welcome.json', '--path' => '/'])->assertSuccessful();
    $this->artisan('migrate')->assertSuccessful();
    app(Pages::class)->routes();
    Route::getRoutes()->refreshNameLookups();
    $this->withoutVite();

    $this->get('/')->assertOk();
});
