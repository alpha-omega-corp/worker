<?php

namespace App\Support;

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The shared UI kit as this application imports it: which components exist and
 * what each one needs (resources/components.php), which layouts they can be
 * placed into (stubs/layouts), and the two acts built on those — importing a set
 * of components with everything they require, and rendering a layout with the
 * components in their regions.
 *
 * The markup itself stays in the ui-kit package; this copies it into the
 * application, where it becomes the application's own to edit.
 */
class UiKit
{
    public const PACKAGE = 'alpha-omega-corp/ui-kit';

    /**
     * A region in a layout stub: a Blade comment, so a stub with nothing placed
     * in it still renders.
     */
    private const REGION = '/^([ \t]*)\{\{--\s*region:([a-z][a-z0-9-]*)\s*--\}\}[ \t]*$/m';

    public function __construct(private Filesystem $files, private ?string $target = null) {}

    /**
     * @return array<string, array{requires: list<string>, js: ?string, blade: string}>
     */
    public function components(): array
    {
        /** @var array<string, array{requires: list<string>, js: ?string, blade: string}> */
        return require base_path('resources/components.php');
    }

    /**
     * Every component a set needs, each once, with what it requires before it.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public function closure(array $names): array
    {
        $graph = $this->components();
        $out = [];

        $visit = function (string $name, array $path) use (&$visit, &$out, $graph): void {
            if (! isset($graph[$name])) {
                throw new InvalidArgumentException("There is no kit component called [{$name}].");
            }

            if (in_array($name, $out, true)) {
                return;
            }

            if (in_array($name, $path, true)) {
                throw new InvalidArgumentException('resources/components.php requires in a circle: '.implode(' → ', [...$path, $name]).'.');
            }

            foreach ($graph[$name]['requires'] as $required) {
                $visit($required, [...$path, $name]);
            }

            $out[] = $name;
        };

        foreach ($names as $name) {
            $visit($name, []);
        }

        return $out;
    }

    /**
     * The layouts, read off the stubs. A stub's regions are its markers and its
     * summary is its first comment, so there is no second list to disagree with
     * the markup.
     *
     * @return array<string, array{summary: string, regions: list<string>}>
     */
    public function layouts(): array
    {
        $layouts = [];

        foreach ($this->files->glob(base_path('stubs/layouts/*.blade.php')) as $path) {
            $stub = $this->files->get($path);

            preg_match_all(self::REGION, $stub, $regions);
            preg_match('/^\{\{--\s*[a-z-]+:\s*(.+?)\s*--\}\}/', $stub, $summary);

            $layouts[basename($path, '.blade.php')] = [
                'summary' => $summary[1] ?? '',
                'regions' => array_values(array_unique($regions[2])),
            ];
        }

        ksort($layouts);

        return $layouts;
    }

    /**
     * Copy a set of components, and everything they require, into the
     * application, with the stylesheet and strings every kit component reads.
     *
     * @param  list<string>  $names
     * @return array{imported: list<string>, skipped: list<string>, js: list<string>}
     */
    public function import(array $names, bool $force = false): array
    {
        $components = $this->components();
        $closure = $this->closure($names);
        $package = $this->package();

        // What every kit component reads. Kept silently once it is there: it is
        // the same on every import, and listing it each time buries the one
        // component that really was kept.
        $shared = [
            'resources/css/kit.css',
            'resources/css/themes.css',
            ...array_map(
                fn (SplFileInfo $file): string => 'lang/'.str_replace('\\', '/', $file->getRelativePathname()),
                $this->files->allFiles($package.'/lang'),
            ),
        ];

        $paths = [
            ...$shared,
            ...array_map(fn (string $name): string => 'resources/views/components/kit/'.$components[$name]['blade'].'.blade.php', $closure),
        ];

        $result = ['imported' => [], 'skipped' => [], 'js' => []];

        foreach ($paths as $path) {
            $target = $this->path($path);

            if ($this->files->exists($target) && ! $force) {
                if (! in_array($path, $shared, true)) {
                    $result['skipped'][] = $path;
                }

                continue;
            }

            $this->files->ensureDirectoryExists(dirname($target));
            $this->files->copy($package.'/'.$path, $target);
            $result['imported'][] = $path;
        }

        $this->addImport("@import './kit.css';");

        $result['js'] = array_values(array_unique(array_filter(
            array_map(fn (string $name): ?string => $components[$name]['js'], $closure),
        )));

        return $result;
    }

    /**
     * A layout's stub with each region's components written in as tags, at the
     * marker's indentation. A region nothing was placed in is left empty.
     *
     * @param  array<string, list<string>>  $regions
     */
    public function render(string $layout, array $regions): string
    {
        $layouts = $this->layouts();

        if (! isset($layouts[$layout])) {
            throw new InvalidArgumentException("There is no layout called [{$layout}]. There is: ".implode(', ', array_keys($layouts)).'.');
        }

        $unknown = array_diff(array_keys($regions), $layouts[$layout]['regions']);

        if ($unknown !== []) {
            throw new InvalidArgumentException("The {$layout} layout has no region called [".implode(', ', $unknown).']. It has: '.implode(', ', $layouts[$layout]['regions']).'.');
        }

        $this->closure(array_merge([], ...array_values($regions)));

        $stub = $this->files->get(base_path("stubs/layouts/{$layout}.blade.php"));

        return (string) preg_replace_callback(self::REGION, function (array $match) use ($regions): string {
            $tags = array_map(fn (string $name): string => $match[1]."<x-kit.{$name} />", $regions[$match[2]] ?? []);

            return implode("\n", $tags);
        }, $stub);
    }

    private function package(): string
    {
        $path = InstalledVersions::isInstalled(self::PACKAGE) ? InstalledVersions::getInstallPath(self::PACKAGE) : null;

        if ($path === null || ! $this->files->isDirectory($path)) {
            throw new InvalidArgumentException('The ui-kit package is not installed: composer require --dev '.self::PACKAGE.':dev-production.');
        }

        return $path;
    }

    private function path(string $relative): string
    {
        return rtrim($this->target ?? base_path(), '/').'/'.$relative;
    }

    /**
     * Put the kit's stylesheet straight after Tailwind's, once. An import has to
     * come before any other rule.
     */
    private function addImport(string $line): void
    {
        $stylesheet = $this->path('resources/css/app.css');

        if (! $this->files->exists($stylesheet)) {
            return;
        }

        $css = $this->files->get($stylesheet);

        if (str_contains($css, $line)) {
            return;
        }

        $this->files->put($stylesheet, (string) preg_replace("/^@import\\s+['\"]tailwindcss['\"][^;]*;\\R/m", "\$0{$line}\n", $css, 1));
    }
}
