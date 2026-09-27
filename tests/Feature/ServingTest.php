<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

// Trusted hosts are a static on Symfony's request, so one test's list would
// otherwise judge the next test's requests.
afterEach(fn () => Request::setTrustedHosts([]));

// Laravel leaves hosts alone in `local` and in tests, so each request below is
// judged as a deployed site's would be, debug page off. The route writes an
// absolute URL, which is what a foreign Host header poisons.
function served(string $env, ?string $appUrl, string $url): TestResponse
{
    app()['env'] = $env;
    config(['app.url' => $appUrl, 'app.debug' => false]);
    Route::get('/link', fn () => url('/reset'));

    return test()->get($url);
}

test('a request naming somebody else\'s host is refused', function () {
    served('production', 'https://bakery.example', 'https://attacker.example/link')
        ->assertBadRequest()
        ->assertDontSee('attacker.example');
});

test('APP_URL\'s host and its subdomains are served', function (string $url) {
    served('production', 'https://bakery.example', $url)->assertOk();
})->with(['https://bakery.example/link', 'https://www.bakery.example/link']);

test('a laptop and an APP_URL with no host refuse nobody', function (string $env, ?string $appUrl) {
    served($env, $appUrl, 'http://127.0.0.1:8000/link')->assertOk();
})->with([
    'local' => ['local', 'https://bakery.example'],
    'no host' => ['production', null],
]);

// php-fpm reads public/.user.ini per directory: without it PHP refuses any
// file over 2M before Laravel is asked, and the media library takes 16 MB.
test('an image the media library accepts reaches PHP', function () {
    $ini = parse_ini_file(public_path('.user.ini'));

    expect(ini_parse_quantity($ini['upload_max_filesize']))->toBeGreaterThanOrEqual(16 * 1024 * 1024)
        ->and(ini_parse_quantity($ini['post_max_size']))->toBeGreaterThan(ini_parse_quantity($ini['upload_max_filesize']));
});

// nginx and `artisan serve` hand out a file that exists before PHP is asked,
// so a static robots.txt would answer in place of alpha-omega-corp/site's
// route for ever — while without the package the file is the only answer.
test('robots.txt is answered by exactly one thing', function () {
    expect(Route::has('site.robots') xor file_exists(public_path('robots.txt')))
        ->toBeTrue('public/robots.txt must exist without the package\'s site.robots route, and must not with it');
});
