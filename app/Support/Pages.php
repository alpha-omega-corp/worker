<?php

namespace App\Support;

use Exception;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The site's pages, as resources/pages.json lists them: which view each path
 * serves, the title and description its head carries, and its label in the
 * navigation. Deployer writes the file and so does `ui:page`; this reads it,
 * routes it, and hands it to every view.
 *
 * A site without the file serves welcome at / alone, as every site did before
 * there was one. A broken entry is skipped and logged rather than answered with
 * a 500, so one bad line never takes the other pages down.
 *
 * @phpstan-type Page array{view: string, path: string, role: ?string, title: ?string, label: ?string, description: ?string}
 */
class Pages
{
    /** Every key a page has, in the order both writers put them. */
    public const KEYS = ['view', 'path', 'role', 'title', 'label', 'description'];

    /** A Blade view name, dots for folders: deployer's pattern, so both sides refuse the same names. */
    private const VIEW = '/^[a-z0-9_-]+(\.[a-z0-9_-]+)*$/D';

    /** Lowercase words joined by hyphens after each slash: no trailing slash, no parameters. */
    private const PATH = '#^(/[a-z0-9]+(-[a-z0-9]+)*)+$#D';

    /** @var array{exists: bool, pages: list<Page>, problems: list<string>}|null */
    private ?array $read = null;

    public function __construct(private string $manifest) {}

    /**
     * Whether the site has a manifest it could read. Without one it serves
     * welcome at / alone.
     */
    public function exists(): bool
    {
        return $this->read()['exists'];
    }

    /**
     * Every page that holds, in the navigation's order.
     *
     * @return list<Page>
     */
    public function all(): array
    {
        return $this->read()['pages'];
    }

    /**
     * What is wrong with the manifest: each page it skips, and why; or why the
     * file could not be read at all.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return $this->read()['problems'];
    }

    /**
     * Route::view for every page, so each route keeps its view in its defaults
     * — which is how deployer's check_site maps a view to its path — and the
     * routes can be cached.
     *
     * A page can be listed before its view is built, and a route to a view
     * that is not there answers 500: until it is built, the page answers 404
     * and the log says why.
     */
    public function routes(): void
    {
        if (! $this->exists()) {
            Route::view('/', 'welcome')->name('home');

            return;
        }

        foreach ($this->all() as $page) {
            if (! self::isView($page['view'])) {
                Log::warning("{$page['view']} is not a view yet, so {$page['path']} is not served until it is: ui:layout builds it.", ['manifest' => $this->manifest]);

                continue;
            }

            Route::view($page['path'], $page['view'])->name(self::routeName($page['path']));
        }
    }

    /**
     * home for /, otherwise the path's segments joined by dots: /la-maison is
     * la-maison, /la-maison/histoire is la-maison.histoire.
     */
    public static function routeName(string $path): string
    {
        return $path === '/' ? 'home' : str_replace('/', '.', ltrim($path, '/'));
    }

    /**
     * The page being served. Nothing is, without a route: in the console, or on
     * an error page.
     *
     * @return Page|null
     */
    public function current(): ?array
    {
        $route = Route::currentRouteName();

        foreach ($this->all() as $page) {
            if (self::routeName($page['path']) === $route) {
                return $page;
            }
        }

        return null;
    }

    /**
     * The navigation: every page with a label, in order, the one being served
     * marked current.
     *
     * @return list<array{label: string, href: string, current: bool}>
     */
    public function nav(): array
    {
        $current = $this->current();
        $nav = [];

        foreach ($this->all() as $page) {
            if ($page['label'] !== null) {
                $nav[] = ['label' => $page['label'], 'href' => $page['path'], 'current' => $page === $current];
            }
        }

        return $nav;
    }

    /**
     * Hand the pages to what reads them while the site runs: every view, its
     * components too, since the kit's site-header draws $siteNav; and the
     * base's /sitemap.xml, which reads config('site.sitemap') per request.
     *
     * A composer rather than View::share, because which page is current is
     * known only once the request is routed, after boot.
     */
    public function share(): void
    {
        if ($this->exists()) {
            config(['site.sitemap' => array_column($this->all(), 'path')]);
        }

        View::composer('*', function (ViewContract $view): void {
            $view->with([
                'siteNav' => $this->nav(),
                'sitePage' => $this->current(),
                'sitePages' => $this->all(),
            ]);
        });
    }

    /**
     * The mockup a page is built from: resources/layouts/<view>.json, beside
     * the manifest.
     */
    public function mockup(string $view): string
    {
        return dirname($this->manifest)."/layouts/{$view}.json";
    }

    /**
     * The manifest with one page added, or changed where it stands: each key
     * given replaces the page's own (null clears it), the others are kept.
     *
     * @param  array<string, ?string>  $changes
     * @return list<Page>
     *
     * @throws InvalidArgumentException with every reason the result would not hold
     */
    public function with(string $view, array $changes): array
    {
        $entries = $this->entries();

        foreach ($entries as $index => $entry) {
            if (is_array($entry) && ($entry['view'] ?? null) === $view) {
                $entries[$index] = [...array_fill_keys(self::KEYS, null), ...$entry, ...$changes, 'view' => $view];

                return self::valid($entries);
            }
        }

        if (! isset($changes['path'])) {
            throw new InvalidArgumentException("{$view} is not a page yet. Give it a path: --path=/".Str::afterLast($view, '.').'.');
        }

        $entries[] = [...array_fill_keys(self::KEYS, null), ...$changes, 'view' => $view];

        return self::valid($entries);
    }

    /**
     * The manifest without a page. Its view and its mockup stay where they are.
     *
     * @return list<Page>
     *
     * @throws InvalidArgumentException when there is no such page, or the rest would not hold
     */
    public function without(string $view): array
    {
        $entries = $this->entries();
        $kept = array_values(array_filter($entries, fn (mixed $entry): bool => ! is_array($entry) || ($entry['view'] ?? null) !== $view));

        if (count($kept) === count($entries)) {
            throw new InvalidArgumentException("There is no page {$view} to remove. ui:pages lists the pages there are.");
        }

        return self::valid($kept);
    }

    /**
     * Write the manifest as deployer writes it: four spaces, every key, a
     * trailing newline — so the two writers leave the same file behind.
     *
     * @param  list<Page>  $pages
     */
    public function save(array $pages): void
    {
        File::put($this->manifest, json_encode(['pages' => $pages], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

        $this->read = null;
    }

    /**
     * Once per request: the composer runs for every component a page renders.
     *
     * @return array{exists: bool, pages: list<Page>, problems: list<string>}
     */
    private function read(): array
    {
        return $this->read ??= $this->load();
    }

    /**
     * @return array{exists: bool, pages: list<Page>, problems: list<string>}
     */
    private function load(): array
    {
        if (! is_file($this->manifest)) {
            return ['exists' => false, 'pages' => [], 'problems' => []];
        }

        try {
            [$pages, $problems] = self::validate($this->decode());
            $read = ['exists' => true, 'pages' => $pages, 'problems' => $problems];
        } catch (InvalidArgumentException $e) {
            $read = ['exists' => false, 'pages' => [], 'problems' => [$e->getMessage().' The site serves welcome at / alone until it is fixed.']];
        }

        foreach ($read['problems'] as $problem) {
            Log::warning($problem, ['manifest' => $this->manifest]);
        }

        return $read;
    }

    /**
     * The entries a writer starts from: the file's as written, or — before
     * there is a file — the home page every site already serves.
     *
     * @return list<mixed>
     */
    private function entries(): array
    {
        if (! is_file($this->manifest)) {
            return [['view' => 'welcome', 'path' => '/', 'role' => 'home', 'title' => null, 'label' => null, 'description' => null]];
        }

        try {
            return $this->decode();
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage().' Fix it, or delete it to start again from welcome at /.');
        }
    }

    /**
     * @return list<mixed>
     *
     * @throws InvalidArgumentException when the file is not a manifest
     */
    private function decode(): array
    {
        try {
            $manifest = json_decode(File::get($this->manifest), true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new InvalidArgumentException("resources/pages.json is not a page manifest: {$e->getMessage()}.");
        }

        if (! is_array($manifest) || ! is_array($manifest['pages'] ?? null) || ! array_is_list($manifest['pages'])) {
            throw new InvalidArgumentException('resources/pages.json is not a page manifest: it holds no "pages" list.');
        }

        return $manifest['pages'];
    }

    /**
     * View::exists, said so to phpstan: Route::view takes only a view that is there.
     *
     * @phpstan-assert-if-true view-string $view
     */
    private static function isView(string $view): bool
    {
        return View::exists($view);
    }

    /**
     * A writer's result, refused whole unless every page in it holds: what it
     * writes is served as written, with nothing skipped.
     *
     * @param  list<mixed>  $entries
     * @return list<Page>
     */
    private static function valid(array $entries): array
    {
        [$pages, $problems] = self::validate($entries);

        if ($problems !== []) {
            throw new InvalidArgumentException(implode("\n", $problems));
        }

        return $pages;
    }

    /**
     * The pages that hold and, for each one that does not, why. An earlier page
     * keeps its view and its path; a later one claiming either is skipped.
     *
     * @param  list<mixed>  $entries
     * @return array{list<Page>, list<string>}
     */
    private static function validate(array $entries): array
    {
        $pages = $problems = $views = $paths = [];

        foreach ($entries as $index => $entry) {
            $number = $index + 1;
            $page = self::page($entry, $views, $paths);

            if (is_string($page)) {
                $view = is_array($entry) && is_string($entry['view'] ?? null) && preg_match(self::VIEW, $entry['view']) === 1 ? " ({$entry['view']})" : '';
                $problems[] = "Page {$number}{$view}: {$page}";

                continue;
            }

            $views[$page['view']] = $paths[$page['path']] = $number;
            $pages[] = $page;
        }

        return [$pages, $problems];
    }

    /**
     * An entry as a page, with exactly the manifest's keys, or why it is not
     * one.
     *
     * @param  array<string, int>  $views  each earlier page's view, with its number
     * @param  array<string, int>  $paths  each earlier page's path, with its number
     * @return Page|string
     */
    private static function page(mixed $entry, array $views, array $paths): array|string
    {
        if (! is_array($entry)) {
            return 'it is not an object. Each page names its view, path, role, title, label and description.';
        }

        $missing = array_diff(self::KEYS, array_keys($entry));

        if ($missing !== []) {
            return implode(', ', $missing).(count($missing) > 1 ? ' are' : ' is').' missing. Each page names its view, path, role, title, label and description, null when it has none.';
        }

        ['view' => $view, 'path' => $path] = $entry;

        if (! is_string($view) || preg_match(self::VIEW, $view) !== 1) {
            return (string) json_encode($view, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' is not a view name. Use lowercase letters, digits, - and _, with dots for folders: pages.menu.';
        }

        if (! is_string($path) || ($path !== '/' && preg_match(self::PATH, $path) !== 1)) {
            return (string) json_encode($path, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' is not a path. Use / or lowercase words joined by hyphens, each after a slash: /la-carte.';
        }

        // Its route name would be the home page's, and two routes of one name
        // is what `route:cache` refuses outright.
        if ($path === '/home') {
            return '/home would be named home, like the page at /. Choose another path.';
        }

        foreach (['role', 'title', 'label', 'description'] as $key) {
            if ($entry[$key] !== null && ! is_string($entry[$key])) {
                return "its {$key} is neither text nor null. Write it as a string, or null when there is none.";
            }
        }

        if (isset($views[$view])) {
            return "page {$views[$view]} is {$view} already. A view is one page: remove one of them.";
        }

        if (isset($paths[$path])) {
            return "{$path} is page {$paths[$path]}'s path already. A path is one page's: give one of them another.";
        }

        return ['view' => $view, 'path' => $path, 'role' => $entry['role'], 'title' => $entry['title'], 'label' => $entry['label'], 'description' => $entry['description']];
    }
}
