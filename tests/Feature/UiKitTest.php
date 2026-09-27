<?php

use App\Support\UiKit;
use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;

function kit(?string $target = null): UiKit
{
    return new UiKit(new Filesystem, $target);
}

// The graph is curated, so it can drift from what the package ships: a
// component nobody wrote down cannot be imported, and an entry for one the
// package dropped imports a file that is not there.
test('the graph names exactly the components the package ships', function () {
    $components = InstalledVersions::getInstallPath(UiKit::PACKAGE).'/resources/views/components';

    $shipped = collect((new Filesystem)->allFiles($components))
        ->map(fn (SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePath()).'/'.$file->getBasename('.blade.php'))
        ->sort()->values()->all();

    $graph = kit()->components();

    expect(collect($graph)->pluck('blade')->sort()->values()->all())->toBe($shipped);

    foreach ($graph as $name => $component) {
        foreach ($component['requires'] as $required) {
            expect(isset($graph[$required]))->toBeTrue("{$name} requires {$required}, which is not in the graph");
        }

        $usesElements = str_contains((string) file_get_contents("{$components}/{$component['blade']}.blade.php"), '<el-');

        expect($component['js'])->toBe($usesElements ? '@tailwindplus/elements' : null, "{$name} says js {$component['js']}");
    }
});

test('an import brings what a component renders, before it, once', function () {
    expect(kit()->closure(['pagination', 'tabs', 'button', 'vertical-nav']))
        ->toBe(['button', 'pagination', 'badge', 'tabs', 'vertical-nav']);
});

test('a section that draws a photograph brings media before it', function () {
    expect(kit()->closure(['hero', 'features']))->toBe(['media', 'hero', 'features']);
});

test('an unknown component is refused rather than skipped', function () {
    kit()->closure(['pagination', 'carousel']);
})->throws(InvalidArgumentException::class, 'carousel');

test('a layout\'s regions are the markers in its stub', function () {
    $layouts = kit()->layouts();

    expect(array_keys($layouts))->toBe(['console', 'focus', 'marketing', 'stacked', 'workspace'])
        ->and($layouts['console']['regions'])->toBe(['nav', 'header', 'main'])
        ->and($layouts['console']['summary'])->toStartWith('A persistent sidebar')
        ->and($layouts['marketing']['regions'])->toBe(['nav', 'hero', 'main', 'band', 'footer']);
});

// hero and band were added to marketing later; a schema written before them
// names only the old three and has to go on building.
test('a marketing schema older than hero and band still renders, with those two empty', function () {
    $page = kit()->render('marketing', ['nav' => ['navbar'], 'main' => ['page-heading'], 'footer' => ['vertical-nav']]);

    expect($page)
        ->toContain('<x-kit.navbar />', '<x-kit.page-heading />', '<x-kit.vertical-nav />')
        ->not->toContain('region:')
        ->not->toContain('max-w-7xl');
});

/**
 * The marketing stub as a DOM, with the queries for the five frames a
 * direction styles, keyed by the region each one holds.
 *
 * @return array{DOMXPath, array<string, string>}
 */
function marketingFrames(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return [new DOMXPath($document), [
        'nav' => "/html/body/header[contains(concat(' ',@class,' '),' wrap ')]",
        'hero' => "//main/section[1]/div[@class='wrap']",
        'main' => "//main/div[contains(concat(' ',@class,' '),' wrap ')]",
        'band' => "//main/section[2]/div[@class='wrap']",
        'footer' => "/html/body/footer/div[contains(concat(' ',@class,' '),' wrap ')]",
    ]];
}

// A direction's CSS reaches the page through these frames and nothing else,
// so a stub edit that moves a region out of one restyles a site silently.
test('each marketing region sits in the frame a direction styles', function () {
    [$xpath, $frames] = marketingFrames((string) file_get_contents(base_path('stubs/layouts/marketing.blade.php')));

    expect($xpath->query("//main/section/div[@class='wrap']")->length)->toBe(2);

    foreach ($frames as $region => $query) {
        $frame = $xpath->query($query);

        expect($frame->length)->toBe(1, "no single frame for {$region}")
            ->and($frame->item(0)->textContent)->toContain("region:{$region}");
    }
});

test('a page of the seven sections and the prefabs puts every tag in its region\'s frame', function () {
    $regions = [
        'nav' => ['site-header'],
        'hero' => ['hero'],
        'main' => ['features', 'menu', 'schedule', 'map'],
        'band' => ['cta-band'],
        'footer' => ['site-footer'],
    ];

    $page = kit()->render('marketing', $regions);

    expect($page)->not->toContain('region:');

    [$xpath, $frames] = marketingFrames($page);

    foreach ($regions as $region => $names) {
        $frame = $xpath->query($frames[$region])->item(0);
        $tags = collect($xpath->query('./*', $frame))->map(fn (DOMElement $tag): string => $tag->tagName)->all();

        expect($tags)->toBe(array_map(fn (string $name): string => kit()->tag($name), $names), "the {$region} frame");
    }
});

test('each component is written into its region, at the region\'s indentation', function () {
    $page = kit()->render('console', ['nav' => ['side-nav'], 'main' => ['table', 'pagination', 'layout/cards/01-basic-card']]);

    expect($page)
        ->toContain('                <x-kit.side-nav />')
        ->toContain("                    <x-kit.table />\n                    <x-kit.pagination />\n                    <x-ui.layout.cards.01-basic-card />")
        ->not->toContain('region:');
});

test('a region the layout does not have is refused', function () {
    kit()->render('console', ['sidebar' => ['side-nav']]);
})->throws(InvalidArgumentException::class, 'no region called [sidebar]');

test('an import copies the closure and the kit it reads, and keeps what the application already has', function () {
    $target = sys_get_temp_dir().'/ui-kit-'.uniqid();
    (new Filesystem)->ensureDirectoryExists($target.'/resources/css');
    file_put_contents($target.'/resources/css/app.css', "@import 'tailwindcss';\n\n@source '../views';\n");

    $first = kit($target)->import(['dropdown', 'pagination', 'elements/dropdowns/01-simple']);

    expect($first['imported'])->toContain(
        'resources/views/components/kit/button.blade.php',
        'resources/views/components/kit/pagination.blade.php',
        'resources/views/components/ui/elements/dropdowns/01-simple.blade.php',
        'resources/css/kit.css',
        'lang/en/kit.php',
    )
        ->and(in_array('resources/css/fonts.json', $first['imported'], true))
        ->toBe(file_exists(InstalledVersions::getInstallPath(UiKit::PACKAGE).'/resources/css/fonts.json'))
        ->and($first['js'])->toBe(['@tailwindplus/elements'])
        ->and(file_get_contents($target.'/resources/css/app.css'))->toBe("@import 'tailwindcss';\n@import './kit.css';\n\n@source '../views';\n");

    $second = kit($target)->import(['pagination']);

    expect($second['imported'])->toBe([])
        ->and($second['skipped'])->toContain('resources/views/components/kit/pagination.blade.php')
        ->and(substr_count((string) file_get_contents($target.'/resources/css/app.css'), 'kit.css'))->toBe(1);

    (new Filesystem)->deleteDirectory($target);
});
