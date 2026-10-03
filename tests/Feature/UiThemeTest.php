<?php

use App\Support\Pages;
use Illuminate\Support\Facades\File;

beforeEach(fn () => $this->site = aSiteOfItsOwn());

afterEach(fn () => File::deleteDirectory($this->site));

/**
 * A view as a session leaves it once it has filled it in.
 */
function aFilledView(string $view, string $html, string $body): void
{
    File::ensureDirectoryExists(dirname($path = resource_path('views/'.str_replace('.', '/', $view).'.blade.php')));
    File::put($path, "<!DOCTYPE html>\n{$html}\n    <body>{$body}</body>\n</html>\n");
}

function viewOf(string $view): string
{
    return (string) file_get_contents(resource_path('views/'.str_replace('.', '/', $view).'.blade.php'));
}

test('every page is drawn in the new look, keeping what is written in it and whether its mockup was built', function () {
    $home = ['layout' => 'marketing', 'theme' => 'orchard', 'business' => 'bakery', 'direction' => 'counter', 'regions' => ['nav' => ['site-header'], 'hero' => ['hero']]];
    $menu = ['layout' => 'carte', 'theme' => 'orchard', 'direction' => 'counter', 'regions' => ['lead' => ['section'], 'offer' => ['menu']]];

    // c5ba0e95dc14 is deployer's version of the home page as it was built; the
    // menu's view was changed since its mockup was, so it is not built.
    aMockup('welcome', [...$home, 'builtAs' => 'c5ba0e95dc14']);
    aMockup('pages.menu', [...$menu, 'builtAs' => '000000000000']);
    aMockup('pages.draft', ['layout' => 'board', 'theme' => 'orchard', 'regions' => (object) []]);
    aFilledView('welcome', '<html lang="fr" data-palette="orchard" data-direction="counter">', '<p data-palette="vellum">Pain au levain, cuit chaque matin.</p>');
    aFilledView('pages.menu', '<html lang="fr" data-palette="orchard" data-direction="counter">', '<p>La carte</p>');
    aFilledView('pages.visit', '<html lang="fr" data-palette="orchard" data-direction="counter">', '<p>Rue du Bourg 3</p>');
    app(Pages::class)->save([
        ['view' => 'welcome', 'path' => '/', 'role' => 'home', 'title' => null, 'label' => null, 'description' => null],
        ['view' => 'pages.menu', 'path' => '/menu', 'role' => 'offer', 'title' => 'La carte', 'label' => 'La carte', 'description' => null],
        ['view' => 'pages.visit', 'path' => '/visit', 'role' => 'visit', 'title' => 'Venir', 'label' => 'Venir', 'description' => null],
    ]);

    $this->artisan('ui:theme', ['palette' => 'harbour', '--direction' => 'hearth'])
        ->expectsOutputToContain('Every page is drawn in the harbour palette and the hearth direction.')
        ->assertSuccessful();

    foreach (['welcome', 'pages.menu', 'pages.visit'] as $view) {
        expect(viewOf($view))->toContain('<html lang="fr" data-palette="harbour" data-direction="hearth">');
    }

    expect(viewOf('welcome'))->toContain('<p data-palette="vellum">Pain au levain, cuit chaque matin.</p>');

    // 840ef15f491b is deployer's version of the home page in the new look.
    expect(mockupOf('welcome'))->toBe([...$home, 'theme' => 'harbour', 'direction' => 'hearth', 'builtAs' => '840ef15f491b'])
        ->and(mockupOf('pages.menu'))->toBe([...$menu, 'theme' => 'harbour', 'direction' => 'hearth', 'builtAs' => '000000000000'])
        ->and(mockupOf('pages.draft'))->toBe(['layout' => 'board', 'theme' => 'harbour', 'regions' => [], 'direction' => 'hearth']);

    // No region is an object, never a list, which deployer reads as no schema.
    expect(file_get_contents(resource_path('layouts/pages.draft.json')))->toContain('"regions": {}');
});

test('--direction= takes the direction off every page and keeps the palette', function () {
    $home = ['layout' => 'marketing', 'theme' => 'orchard', 'direction' => 'counter', 'regions' => ['nav' => ['site-header'], 'hero' => ['hero']]];
    aMockup('welcome', [...$home, 'builtAs' => 'c5ba0e95dc14']);
    aFilledView('welcome', '<html lang="fr" data-palette="orchard" data-direction="counter">', '<p>Pain</p>');

    $this->artisan('ui:theme', ['--direction' => ''])->assertSuccessful();

    // 6ba0e5a8fe0c is deployer's version of the home page with no direction.
    expect(viewOf('welcome'))->toContain('<html lang="fr" data-palette="orchard">')
        ->and(mockupOf('welcome'))->toBe(['layout' => 'marketing', 'theme' => 'orchard', 'regions' => $home['regions'], 'builtAs' => '6ba0e5a8fe0c']);
});

test('a look the kit does not draw is refused, and nothing is written', function (array $arguments, string $said) {
    aMockup('welcome', ['layout' => 'marketing', 'theme' => 'orchard', 'regions' => (object) []]);
    aFilledView('welcome', '<html lang="fr" data-palette="orchard">', '<p>Pain</p>');
    $mockup = file_get_contents(resource_path('layouts/welcome.json'));
    $view = viewOf('welcome');

    $this->artisan('ui:theme', $arguments)->expectsOutputToContain($said)->assertFailed();

    expect(file_get_contents(resource_path('layouts/welcome.json')))->toBe($mockup)
        ->and(viewOf('welcome'))->toBe($view);
})->with([
    'a palette' => [['palette' => 'teal'], 'There is no palette called [teal]'],
    'a direction' => [['palette' => 'harbour', '--direction' => 'lamplight'], 'There is no direction called [lamplight]'],
]);

test('with no look given it says what each page is drawn in, and fails while they are not one', function () {
    aMockup('pages.draft', ['layout' => 'board', 'theme' => 'harbour', 'direction' => 'hearth', 'regions' => (object) []]);
    aFilledView('welcome', '<html lang="fr" data-palette="orchard" data-direction="counter">', '<p>Pain</p>');
    app(Pages::class)->save([['view' => 'welcome', 'path' => '/', 'role' => 'home', 'title' => null, 'label' => null, 'description' => null]]);

    $this->artisan('ui:theme')
        ->expectsTable(['View', 'Palette', 'Direction'], [['welcome', 'orchard', 'counter'], ['pages.draft', 'harbour', 'hearth']])
        ->expectsOutputToContain('The pages are not drawn in one look')
        ->assertFailed();
});
