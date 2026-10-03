<?php

use App\Support\Pages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A site of its own for a test to build into, as the application's base path:
 * the template's stubs and component graph, and nothing else — so the views,
 * mockups, components and config a command writes land in it rather than in
 * the site these tests ship with. The test deletes it.
 */
function aSiteOfItsOwn(): string
{
    $site = sys_get_temp_dir().'/site-'.uniqid();
    File::ensureDirectoryExists("{$site}/resources/layouts");
    File::ensureDirectoryExists("{$site}/config");
    File::link(base_path('stubs'), "{$site}/stubs");
    File::link(base_path('resources/components.php'), "{$site}/resources/components.php");
    app()->setBasePath($site);
    app()->instance(Pages::class, new Pages(resource_path('pages.json')));
    View::addLocation("{$site}/resources/views");

    return $site;
}

/**
 * A mockup in the site's resources/layouts, as deployer saves one.
 *
 * @param  array<string, mixed>  $schema
 */
function aMockup(string $view, array $schema): void
{
    File::put(resource_path("layouts/{$view}.json"), json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

/**
 * @return array<string, mixed>
 */
function mockupOf(string $view): array
{
    return json_decode((string) file_get_contents(resource_path("layouts/{$view}.json")), true);
}
