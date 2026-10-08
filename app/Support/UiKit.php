<?php

namespace App\Support;

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The shared UI kit as this application imports it: which components exist and
 * what each one needs (resources/components.php), which layouts they can be
 * placed into (stubs/layouts), which palettes and directions a page can be drawn
 * in (the kit's themes.css and kit.css), and the two acts built on those —
 * importing a set of components with everything they require, and rendering a
 * layout with the components in their regions and arrangements, in its palette
 * and direction.
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

    /**
     * A palette is a `[data-palette='<name>']` block in themes.css and a
     * direction a `[data-direction='<name>']` root block in kit.css: deployer's
     * own patterns, so the two offer and refuse the same names.
     */
    private const PALETTE = "/\\[data-palette='([a-z][a-z0-9-]*)'\\]/";

    private const DIRECTION = "/\\[data-direction='([a-z][a-z0-9-]*)'\\]/";

    /**
     * The page's `<html …>` tag, whole. Its values are read as quoted strings,
     * since the stubs' `lang` holds Blade with a `->` in it.
     */
    private const HTML = '/<html\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/';

    /**
     * An arrangement, as a schema names one. It is written into the tag as
     * `variant="…"`, so it is a word: nothing in it can end the attribute.
     */
    private const WORD = '/^[a-z][a-z0-9-]*$/D';

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
            // Which faces each palette is drawn in, read by vite.config.js. The
            // server installs with --no-dev and builds the front end there, so
            // the site needs its own copy; a kit older than the map has none.
            ...array_filter(
                ['resources/css/fonts.json'],
                fn (string $path): bool => $this->files->exists($package.'/'.$path),
            ),
            ...array_map(
                fn (SplFileInfo $file): string => 'lang/'.str_replace('\\', '/', $file->getRelativePathname()),
                $this->files->allFiles($package.'/lang'),
            ),
        ];

        $paths = [
            ...$shared,
            ...array_map(fn (string $name): string => 'resources/views/components/'.$components[$name]['blade'].'.blade.php', $closure),
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
     * The palettes a page can be drawn in: the application's themes.css, or the
     * package's before the first import copies it.
     *
     * @return list<string>
     */
    public function palettes(): array
    {
        return $this->names('resources/css/themes.css', self::PALETTE);
    }

    /**
     * The directions a page can be drawn in, read the same way off kit.css:
     * none for a copy older than them.
     *
     * @return list<string>
     */
    public function directions(): array
    {
        return $this->names('resources/css/kit.css', self::DIRECTION);
    }

    /**
     * Refuse a palette or a direction the kit cannot draw, in deployer's words,
     * naming the ones it can. An empty direction is none, which is always
     * drawable; null asks nothing.
     *
     * @throws InvalidArgumentException
     */
    public function ensureLook(?string $palette, ?string $direction): void
    {
        if ($palette !== null && ! in_array($palette, $palettes = $this->palettes(), true)) {
            throw new InvalidArgumentException("There is no palette called [{$palette}] in this application's themes.css. There is: ".(implode(', ', $palettes) ?: 'none').'.');
        }

        if ($direction === null || $direction === '' || in_array($direction, $directions = $this->directions(), true)) {
            return;
        }

        throw new InvalidArgumentException($directions === []
            ? "This application's kit.css defines no direction — it is older than them: leave direction out, or copy the kit's resources/css/kit.css over the application's."
            : "There is no direction called [{$direction}] in this application's kit.css. There is: ".implode(', ', $directions).'.');
    }

    /**
     * A page drawn in a palette and a direction, both on its `<html>` and
     * nowhere else: a built page is somebody's to fill, and a swatch's
     * `data-palette` or a marquee's `data-direction` inside it is theirs.
     * Null leaves what the page has, and an empty direction takes it off.
     *
     * @throws InvalidArgumentException for a palette or a direction the kit cannot draw
     */
    public function dress(string $page, ?string $palette, ?string $direction): string
    {
        $this->ensureLook($palette, $direction);

        return (string) preg_replace_callback(self::HTML, function (array $tag) use ($palette, $direction): string {
            $html = $palette === null ? $tag[0] : self::withAttribute($tag[0], 'data-palette', $palette);

            return $direction === null ? $html : self::withAttribute($html, 'data-direction', $direction);
        }, $page, 1);
    }

    /**
     * The palette and the direction a page is drawn in, off its `<html>`.
     *
     * @return array{palette: ?string, direction: ?string}
     */
    public function look(string $page): array
    {
        preg_match(self::HTML, $page, $tag);
        preg_match('/\sdata-palette="([^"]*)"/', $tag[0] ?? '', $palette);
        preg_match('/\sdata-direction="([^"]*)"/', $tag[0] ?? '', $direction);

        return ['palette' => ($palette[1] ?? '') ?: null, 'direction' => ($direction[1] ?? '') ?: null];
    }

    /**
     * A layout's stub with each region's components written in as tags, at the
     * marker's indentation, drawn in the palette and the direction given — the
     * stub's own palette and no direction otherwise. A region nothing was
     * placed in is left empty.
     *
     * A component given an arrangement carries it as `variant`, and the site
     * header carries `:theme-picker` when the picker is asked for. Any other
     * tag is written bare, as before either existed, so a page that built then
     * builds to the same bytes. An arrangement for a component the page does
     * not place is passed over, since a site hands every page the same frame.
     * The word is not checked against what the component draws — the
     * component ignores one it does not take, and deployer asks before it
     * saves — only that it is a word, because it lands in an attribute.
     *
     * @param  array<string, list<string>>  $regions
     * @param  array<mixed>  $variants  each component's arrangement by its name, as the schema gives it; an empty one is none
     *
     * @throws InvalidArgumentException for a layout, a region, a component, a palette or a direction there is not, or an arrangement that is not a word
     */
    public function render(string $layout, array $regions, ?string $palette = null, ?string $direction = null, array $variants = [], bool $themePicker = false): string
    {
        $layouts = $this->layouts();

        if (! isset($layouts[$layout])) {
            throw new InvalidArgumentException("There is no layout called [{$layout}]. There is: ".implode(', ', array_keys($layouts)).'.');
        }

        $unknown = array_diff(array_keys($regions), $layouts[$layout]['regions']);

        if ($unknown !== []) {
            throw new InvalidArgumentException("The {$layout} layout has no region called [".implode(', ', $unknown).']. It has: '.implode(', ', $layouts[$layout]['regions']).'.');
        }

        $placed = array_values(array_unique(array_merge([], ...array_values($regions))));
        $this->closure($placed);

        foreach ($placed as $name) {
            if (($needs = $this->requiredProps($name)) !== []) {
                throw new InvalidArgumentException('<'.$this->tag($name).' /> needs '.implode(' and ', $needs).', and a layout writes every tag bare: the page would answer 500 on every request. Leave it out of the schema.');
            }
        }

        if ($variants !== [] && array_is_list($variants)) {
            throw new InvalidArgumentException('variants is a list, and it names each arrangement by the component it is for: {"hero": "cover"}.');
        }

        foreach ($variants as $name => $word) {
            if (! is_string($word) || ($word !== '' && preg_match(self::WORD, $word) !== 1)) {
                throw new InvalidArgumentException('The arrangement ['.(is_string($word) ? $word : json_encode($word)).'] for '.$name.' is not a word: it is written into the tag as variant="…", so it is lowercase letters, digits and hyphens, starting with a letter.');
            }
        }

        $stub = $this->files->get(base_path("stubs/layouts/{$layout}.blade.php"));

        $page = (string) preg_replace_callback(self::REGION, function (array $match) use ($regions, $variants, $themePicker): string {
            $tags = array_map(function (string $name) use ($match, $variants, $themePicker): string {
                $variant = ($variants[$name] ?? '') === '' ? '' : " variant=\"{$variants[$name]}\"";
                $picker = $themePicker && $name === 'site-header' ? ' :theme-picker="true"' : '';

                return $match[1].'<'.$this->tag($name).$variant.$picker.' />';
            }, $regions[$match[2]] ?? []);

            return implode("\n", $tags);
        }, $stub);

        return $palette === null && $direction === null ? $page : $this->dress($page, $palette, $direction);
    }

    /**
     * A component's Blade tag, read off the file it is published as:
     * kit/button is x-kit.button, ui/layout/cards/01-basic-card is
     * x-ui.layout.cards.01-basic-card.
     */
    public function tag(string $name): string
    {
        return 'x-'.str_replace('/', '.', $this->components()[$name]['blade']);
    }

    /**
     * What a component cannot render without: each prop its `@props` names with
     * no default, read off the package's file — deployer's own reading, so the
     * two refuse the same components. None without the package.
     *
     * @return list<string>
     */
    public function requiredProps(string $name): array
    {
        $package = InstalledVersions::isInstalled(self::PACKAGE) ? InstalledVersions::getInstallPath(self::PACKAGE) : null;
        $file = "{$package}/resources/views/components/{$this->components()[$name]['blade']}.blade.php";

        if ($package === null || ! $this->files->exists($file) || preg_match('/@props\(\[(.*?)\]\)/s', $this->files->get($file), $props) !== 1) {
            return [];
        }

        // `@props(['reference'])` has no default at all; otherwise one prop a
        // line, and a line that is only a name has none.
        preg_match_all(
            str_contains($props[1], '=>') ? "/^\\s*'([A-Za-z_]\\w*)'\\s*,?\\s*$/m" : "/'([A-Za-z_]\\w*)'/",
            $props[1],
            $names,
        );

        return $names[1];
    }

    /**
     * Whether a component is the themed kit's, which a layout is built from,
     * rather than one of the raw reference library's.
     */
    public function isKit(string $name): bool
    {
        return str_starts_with($this->components()[$name]['blade'] ?? '', 'kit/');
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
     * Every name a pattern finds in one of the kit's stylesheets, once each, in
     * the order it first appears: the application's copy, once imported its
     * own and perhaps edited, else the package's. None without either, as on a
     * server that installed without the kit before anything imported it.
     *
     * @return list<string>
     */
    private function names(string $stylesheet, string $pattern): array
    {
        $path = $this->path($stylesheet);
        $package = InstalledVersions::isInstalled(self::PACKAGE) ? InstalledVersions::getInstallPath(self::PACKAGE) : null;

        if (! $this->files->exists($path) && $package !== null) {
            $path = "{$package}/{$stylesheet}";
        }

        preg_match_all($pattern, $this->files->exists($path) ? $this->files->get($path) : '', $found);

        return array_values(array_unique($found[1]));
    }

    /**
     * An attribute set on a tag: replaced where it is, added before its end,
     * or taken off for an empty value.
     */
    private static function withAttribute(string $tag, string $name, string $value): string
    {
        $set = $value === '' ? '' : " {$name}=\"{$value}\"";
        $existing = "/\\s+{$name}=\"[^\"]*\"/";

        if (preg_match($existing, $tag) === 1) {
            return (string) preg_replace($existing, $set, $tag, 1);
        }

        return $set === '' ? $tag : substr($tag, 0, -1).$set.'>';
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
