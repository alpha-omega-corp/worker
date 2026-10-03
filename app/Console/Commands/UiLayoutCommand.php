<?php

namespace App\Console\Commands;

use AlphaOmega\Site\Prefabs\Prefabs;
use App\Support\Mockups;
use App\Support\Pages;
use App\Support\UiKit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Build a page from a layout schema, whole: import every component it places,
 * with what they require; write the layout's stub with each component in its
 * region, on an <html> in the schema's palette and direction; and, on the base,
 * switch on the prefabs it places, so their bare tags draw the site's own rows.
 *
 * The schema is a file in the application, because it is the record of what the
 * page was built from and a session reuses it to build the next one:
 *
 *     {"layout": "split", "theme": "harbour", "direction": "hearth", "regions": {"nav": ["site-header"], "hero": ["hero"]}}
 *
 * Built from the view's own mockup, resources/layouts/<view>.json, the page is
 * marked built there as deployer marks it, so the Design tab reads it built
 * whoever built it.
 *
 * With --path the view is also served as a page, through the same writer as
 * ui:page, so one command builds a page and routes it:
 *
 *     php artisan ui:layout resources/layouts/pages.menu.json --view=pages.menu --path=/menu --title=Menu --label=Menu
 */
#[Signature('ui:layout
    {schema : The layout schema, a JSON file relative to the application, e.g. resources/layouts/welcome.json}
    {--view=welcome : The view to write, under resources/views}
    {--path= : Also serve the view as a page at this path, in resources/pages.json}
    {--title= : The page\'s title, which the head shows as "Title · Name"}
    {--label= : The page\'s item in the navigation}
    {--role= : The page\'s job on the site, e.g. offer}
    {--replace : Build the view again if it exists, replacing what was written in it and nothing else}
    {--force : Overwrite the view, and any component the application already has}')]
#[Description('Render a layout with kit components placed in its regions, in its palette and direction, importing what they need')]
class UiLayoutCommand extends Command
{
    public function handle(UiKit $kit, Filesystem $files, Pages $pages): int
    {
        $mockups = $pages->mockups();
        $schema = base_path((string) $this->argument('schema'));
        $name = (string) $this->option('view');
        $view = resource_path('views/'.str_replace('.', '/', $name).'.blade.php');

        if (! $files->exists($schema)) {
            $this->components->error("There is no schema at {$this->argument('schema')}.");

            return self::FAILURE;
        }

        /** @var array{layout?: mixed, regions?: mixed, theme?: mixed, direction?: mixed}|null $decoded */
        $decoded = json_decode($files->get($schema), true);

        if (! is_string($decoded['layout'] ?? null) || ! is_array($decoded['regions'] ?? [])) {
            $this->components->error('The schema reads {"layout": "<name>", "theme": "<palette>", "direction": "<direction>", "regions": {"<region>": ["<component>", …]}}.');

            return self::FAILURE;
        }

        /** @var array<string, list<string>> $regions */
        $regions = $decoded['regions'] ?? [];

        // --force would also write every component the page places back to the
        // package's, over whatever the site has since made of them.
        if ($files->exists($view) && ! $this->option('replace') && ! $this->option('force')) {
            $this->components->error("{$name} already exists; pass --replace to build it again, which replaces what was written in it.");

            return self::FAILURE;
        }

        $changes = UiPageCommand::changes($this);

        try {
            // Refused before anything is written: a page that cannot be routed
            // leaves no view behind to be found later with no path to it.
            $manifest = $changes === [] ? null : $pages->with($name, $changes);
            $page = $kit->render($decoded['layout'], $regions, self::named($decoded['theme'] ?? null), self::named($decoded['direction'] ?? null));
            $placed = array_merge([], ...array_values($regions));
            $result = $kit->import($placed, (bool) $this->option('force'));
        } catch (InvalidArgumentException $e) {
            // A manifest refused names each page that would not hold on a line of its own.
            foreach (explode("\n", $e->getMessage()) as $reason) {
                $this->components->error($reason);
            }

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($view));
        $files->replace($view, $page);

        // A component the site already has is its own, shared with its other
        // pages, and keeping it is the point: not offered to --force, which
        // would put the package's copy back over whatever the site made of it.
        UiImportCommand::report($this, $kit, $kit->closure($placed), [...$result, 'skipped' => []]);
        $this->components->info("Wrote the {$decoded['layout']} layout to resources/views/".str_replace('.', '/', $name).'.blade.php.');

        if ($manifest !== null) {
            $pages->save($manifest);
            UiPageCommand::report($this, $name, $manifest);
        }

        // Built once the page is whole, and in the view's own mockup alone: a
        // schema built into another view says nothing about the one it is for.
        // Stamped with what was read, so a mockup proposed again meanwhile
        // reads unbuilt rather than as the page that is there.
        if ($this->enablePrefabs($placed) && realpath($schema) === realpath($mockups->path($name)) && ($mockup = $mockups->find($name)) !== null) {
            $mockups->save($name, [...$mockup, 'builtAs' => Mockups::version($decoded)]);
        }

        return self::SUCCESS;
    }

    /**
     * A palette or a direction as the schema names it; none for an empty one.
     */
    private static function named(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * On the base, switch on the prefabs the page places — each kit component
     * the base binds to a prefab, read off the base — so their bare tags draw
     * the site's own rows rather than nothing. `site:prefab` is idempotent, and
     * what it says of the components it imports again is noise after this
     * import, so only whether it changed anything is said.
     *
     * A refusal — a config/site.php edited out of the one line it rewrites — is
     * said, and leaves the page unmarked, but the page stands, as deployer's
     * own build leaves it: once the file is fixed, building it again finishes.
     *
     * @param  list<string>  $placed
     */
    private function enablePrefabs(array $placed): bool
    {
        if (! array_key_exists('site:prefab', Artisan::all())) {
            return true;
        }

        $prefabs = app(Prefabs::class);
        $names = array_values(array_filter(
            $prefabs->names(),
            fn (string $name): bool => array_intersect(array_keys($prefabs->find($name)?->presenters() ?? []), $placed) !== [],
        ));

        if ($names === []) {
            return true;
        }

        $config = config_path('site.php');
        $before = is_file($config) ? file_get_contents($config) : null;

        if ($this->runCommand('site:prefab', ['action' => 'enable', 'names' => $names], $said = new BufferedOutput) !== self::SUCCESS) {
            $this->components->error('site:prefab enable '.implode(' ', $names).' refused, so the prefabs on this page draw nothing:');
            $this->line(trim($said->fetch()));

            return false;
        }

        if ((is_file($config) ? file_get_contents($config) : null) === $before) {
            $this->components->info('Its prefabs are on: '.implode(', ', $names).'.');
        } else {
            $this->components->warn('Switched on '.implode(', ', $names).' in config/site.php: run php artisan migrate before opening the page, or every page answers 500 until their tables exist.');
        }

        return true;
    }
}
