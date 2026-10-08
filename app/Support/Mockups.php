<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The mockups a site's pages are built from: resources/layouts/<view>.json, one
 * per view, which deployer draws on its Design tab and ui:layout builds. A
 * schema is `layout` and `regions`, which the build places; `variants`, each
 * component's arrangement, and `themePicker`, whether the site header offers
 * the theme picker, which it writes on their tags; `theme` and `direction`, the
 * look it puts on the page's <html>; `business`; and `builtAs`, the version of
 * it the view was last built at. Deployer reads a page as built while its
 * `builtAs` is the version of what the file says now.
 *
 * Both write the file, so this writes it as deployer does — four spaces, a
 * trailing newline, the regions and the arrangements objects — and replaces it
 * whole, so the Design tab's poll never reads half of one.
 */
class Mockups
{
    /** A palette's name, the only kind of value handed on from a file anybody can edit. */
    private const NAME = '/^[a-z][a-z0-9-]*$/D';

    public function __construct(private string $directory) {}

    public function path(string $view): string
    {
        return "{$this->directory}/{$view}.json";
    }

    /**
     * Every mockup that reads as a schema, by view. A dot-file is deployer's
     * staged write, and glob passes over it.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $mockups = [];

        foreach (File::glob("{$this->directory}/*.json") as $path) {
            if (($schema = $this->find($view = basename($path, '.json'))) !== null) {
                $mockups[$view] = $schema;
            }
        }

        return $mockups;
    }

    /**
     * One view's schema, or null when it has none or its file is not one.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $view): ?array
    {
        $path = $this->path($view);
        $schema = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($schema) && is_string($schema['layout'] ?? null) && is_array($schema['regions'] ?? []) ? $schema : null;
    }

    /**
     * The palette a view's mockup draws it in, or null for none.
     */
    public function palette(string $view): ?string
    {
        $theme = $this->find($view)['theme'] ?? null;

        return is_string($theme) && preg_match(self::NAME, $theme) === 1 ? $theme : null;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function save(string $view, array $schema): void
    {
        // `{}` with no region, never `[]`: deployer reads a list as no schema at all.
        $schema['regions'] = (object) ($schema['regions'] ?? []);

        // The arrangements likewise, and none is no key, as deployer leaves it
        // out: an empty object decodes to `[]` and would go back out as a list.
        if (($schema['variants'] ?? []) === []) {
            unset($schema['variants']);
        } elseif (is_array($schema['variants'])) {
            $schema['variants'] = (object) $schema['variants'];
        }

        File::replace($this->path($view), json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * What a build writes — the layout, the palette, the direction, the
     * regions, the arrangements and the theme picker — as the first six bytes
     * of its SHA-256: deployer's `uikit.version`, byte for byte. An empty
     * region is left out, and so is a palette or a direction that is not there,
     * an empty arrangement and a picker not asked for, which is why a mockup
     * built before directions, arrangements or the picker existed still reads
     * built.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function version(array $schema): string
    {
        $placed = array_filter((array) ($schema['regions'] ?? []), fn (mixed $components): bool => is_array($components) && $components !== []);
        ksort($placed, SORT_STRING);

        $written = ['layout' => (string) ($schema['layout'] ?? '')];

        foreach (['theme', 'direction'] as $key) {
            if (is_string($schema[$key] ?? null) && $schema[$key] !== '') {
                $written[$key] = $schema[$key];
            }
        }

        $written['regions'] = (object) $placed;

        $variants = array_filter((array) ($schema['variants'] ?? []), fn (mixed $word): bool => is_string($word) && $word !== '');
        ksort($variants, SORT_STRING);

        if ($variants !== []) {
            $written['variants'] = (object) $variants;
        }

        if (($schema['themePicker'] ?? false) === true) {
            $written['themePicker'] = true;
        }

        return substr(hash('sha256', json_encode($written, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 12);
    }

    /**
     * Whether the view was built from the schema as it reads now. A file from
     * before `builtAs` that says `built` was built at whatever it says.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function isBuilt(array $schema): bool
    {
        return ($schema['builtAs'] ?? '') === ''
            ? ($schema['built'] ?? false) === true
            : $schema['builtAs'] === self::version($schema);
    }
}
