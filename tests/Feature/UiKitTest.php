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
    $shipped = collect((new Filesystem)->files(InstalledVersions::getInstallPath(UiKit::PACKAGE).'/resources/views/components/kit'))
        ->map(fn (SplFileInfo $file): string => $file->getBasename('.blade.php'))
        ->sort()->values()->all();

    $graph = kit()->components();

    expect(collect($graph)->pluck('blade')->sort()->values()->all())->toBe($shipped);

    foreach ($graph as $name => $component) {
        foreach ($component['requires'] as $required) {
            expect(isset($graph[$required]))->toBeTrue("{$name} requires {$required}, which is not in the graph");
        }
    }
});

test('an import brings what a component renders, before it, once', function () {
    expect(kit()->closure(['pagination', 'tabs', 'button', 'vertical-nav']))
        ->toBe(['button', 'pagination', 'badge', 'tabs', 'vertical-nav']);
});

test('an unknown component is refused rather than skipped', function () {
    kit()->closure(['pagination', 'carousel']);
})->throws(InvalidArgumentException::class, 'carousel');

test('a layout\'s regions are the markers in its stub', function () {
    $layouts = kit()->layouts();

    expect(array_keys($layouts))->toBe(['console', 'focus', 'marketing', 'stacked', 'workspace'])
        ->and($layouts['console']['regions'])->toBe(['nav', 'header', 'main'])
        ->and($layouts['console']['summary'])->toStartWith('A persistent sidebar');
});

test('each component is written into its region, at the region\'s indentation', function () {
    $page = kit()->render('console', ['nav' => ['side-nav'], 'main' => ['table', 'pagination']]);

    expect($page)
        ->toContain('                <x-kit.side-nav />')
        ->toContain("                    <x-kit.table />\n                    <x-kit.pagination />")
        ->not->toContain('region:');
});

test('a region the layout does not have is refused', function () {
    kit()->render('console', ['sidebar' => ['side-nav']]);
})->throws(InvalidArgumentException::class, 'no region called [sidebar]');

test('an import copies the closure and the kit it reads, and keeps what the application already has', function () {
    $target = sys_get_temp_dir().'/ui-kit-'.uniqid();
    (new Filesystem)->ensureDirectoryExists($target.'/resources/css');
    file_put_contents($target.'/resources/css/app.css', "@import 'tailwindcss';\n\n@source '../views';\n");

    $first = kit($target)->import(['dropdown', 'pagination']);

    expect($first['imported'])->toContain(
        'resources/views/components/kit/button.blade.php',
        'resources/views/components/kit/pagination.blade.php',
        'resources/css/kit.css',
        'lang/en/kit.php',
    )
        ->and($first['js'])->toBe(['@tailwindplus/elements'])
        ->and(file_get_contents($target.'/resources/css/app.css'))->toBe("@import 'tailwindcss';\n@import './kit.css';\n\n@source '../views';\n");

    $second = kit($target)->import(['pagination']);

    expect($second['imported'])->toBe([])
        ->and($second['skipped'])->toContain('resources/views/components/kit/pagination.blade.php')
        ->and(substr_count((string) file_get_contents($target.'/resources/css/app.css'), 'kit.css'))->toBe(1);

    (new Filesystem)->deleteDirectory($target);
});
