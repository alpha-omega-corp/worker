<?php

namespace App\Console\Commands;

use App\Support\Pages;
use App\Support\UiKit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

/**
 * Build a page from a layout schema: import every component it places, with
 * what they require, and write the layout's stub with each component in its
 * region.
 *
 * The schema is a file in the application, because it is the record of what the
 * page was built from and a session reuses it to build the next one:
 *
 *     {"layout": "console", "regions": {"nav": ["side-nav"], "main": ["table", "pagination"]}}
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
    {--force : Overwrite the view, and any component the application already has}')]
#[Description('Render a layout with kit components placed in its regions, importing what they need')]
class UiLayoutCommand extends Command
{
    public function handle(UiKit $kit, Filesystem $files, Pages $pages): int
    {
        $schema = base_path((string) $this->argument('schema'));
        $view = resource_path('views/'.str_replace('.', '/', (string) $this->option('view')).'.blade.php');

        if (! $files->exists($schema)) {
            $this->components->error("There is no schema at {$this->argument('schema')}.");

            return self::FAILURE;
        }

        /** @var array{layout?: mixed, regions?: mixed}|null $decoded */
        $decoded = json_decode($files->get($schema), true);

        if (! is_string($decoded['layout'] ?? null) || ! is_array($decoded['regions'] ?? [])) {
            $this->components->error('The schema reads {"layout": "<name>", "regions": {"<region>": ["<component>", …]}}.');

            return self::FAILURE;
        }

        /** @var array<string, list<string>> $regions */
        $regions = $decoded['regions'] ?? [];

        if ($files->exists($view) && ! $this->option('force')) {
            $this->components->error("{$this->option('view')} already exists; pass --force to write over it.");

            return self::FAILURE;
        }

        $changes = UiPageCommand::changes($this);

        try {
            // Refused before anything is written: a page that cannot be routed
            // leaves no view behind to be found later with no path to it.
            $manifest = $changes === [] ? null : $pages->with((string) $this->option('view'), $changes);
            $page = $kit->render($decoded['layout'], $regions);
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
        $files->put($view, $page);

        UiImportCommand::report($this, $kit, $kit->closure($placed), $result);
        $this->components->info("Wrote the {$decoded['layout']} layout to resources/views/".str_replace('.', '/', (string) $this->option('view')).'.blade.php.');

        if ($manifest !== null) {
            $pages->save($manifest);
            UiPageCommand::report($this, (string) $this->option('view'), $manifest);
        }

        return self::SUCCESS;
    }
}
