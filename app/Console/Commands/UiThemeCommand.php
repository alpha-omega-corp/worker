<?php

namespace App\Console\Commands;

use App\Support\Mockups;
use App\Support\Pages;
use App\Support\UiKit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

/**
 * The site's look, which is one: the palette and the direction every page is
 * drawn in. With neither, what each page is drawn in; with either, every page
 * is drawn in it — each mockup, and each view on its <html> alone, so what was
 * written in the pages stays — and a page that was built from its mockup is
 * marked built at the new look, as deployer marks a page it recolours.
 *
 *     php artisan ui:theme harbour --direction=hearth
 *     php artisan ui:theme --direction=       # no direction
 *
 * The pages are every mockup in resources/layouts and every page
 * resources/pages.json lists. The base's admin wears the palette of the home
 * page's mockup, so it follows.
 */
#[Signature('ui:theme
    {palette? : The palette every page is drawn in, e.g. harbour}
    {--direction= : The direction every page is drawn in, e.g. hearth; empty for none}')]
#[Description('Draw every page of the site in one palette and one direction, keeping what is written in them')]
class UiThemeCommand extends Command
{
    public function handle(UiKit $kit, Pages $pages, Filesystem $files): int
    {
        $mockups = $pages->mockups();

        /** @var string|null $palette */
        $palette = $this->argument('palette');

        /** @var string|null $direction */
        $direction = $this->option('direction');

        $schemas = $mockups->all();
        $views = array_values(array_unique([...array_column($pages->all(), 'view'), ...array_keys($schemas)]));

        try {
            if ($palette === null && $direction === null) {
                return $this->describe($kit, $files, $schemas, $views);
            }

            $kit->ensureLook($palette, $direction);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];

        foreach ($views as $view) {
            $file = self::file($view);
            $exists = $files->exists($file);
            $schema = $schemas[$view] ?? null;

            if ($exists) {
                $files->replace($file, $kit->dress($files->get($file), $palette, $direction));
            }

            if ($schema !== null) {
                $built = $exists && Mockups::isBuilt($schema);
                $schema = self::dressed($schema, $palette, $direction);

                if ($built) {
                    $schema['builtAs'] = Mockups::version($schema);
                }

                $mockups->save($view, $schema);
            }

            $rows[] = [$view, $exists ? 'dressed' : 'no view yet', match (true) {
                $schema === null => 'none',
                Mockups::isBuilt($schema) => 'dressed, built',
                default => 'dressed, not built',
            }];
        }

        if ($rows === []) {
            $this->components->info('There is no page to dress: no mockup in resources/layouts, and no page in resources/pages.json.');

            return self::SUCCESS;
        }

        $this->table(['View', 'Page', 'Mockup'], $rows);
        $this->components->info('Every page is drawn in '.self::said($palette, $direction).'. Vite loads the faces of the palettes the mockups name on its next build.');

        // A picture from deployer's library is saved with the page's colours
        // written into it, so the new look does not reach it.
        $pictures = array_map(fn (string $path): string => substr($path, strlen(public_path()) + 1), $files->glob(public_path('images/*/*.svg')));

        if ($pictures !== []) {
            $this->components->warn('These pictures are still drawn in the old look; take each again from deployer\'s library (get-ui-picture with replace): '.implode(', ', $pictures).'.');
        }

        return self::SUCCESS;
    }

    /**
     * What each page is drawn in — its view's own <html>, or its mockup's
     * until it is built — what it could be, and whether the pages are one look.
     *
     * @param  array<string, array<string, mixed>>  $schemas
     * @param  list<string>  $views
     */
    private function describe(UiKit $kit, Filesystem $files, array $schemas, array $views): int
    {
        $looks = [];

        foreach ($views as $view) {
            if ($files->exists($file = self::file($view))) {
                $looks[$view] = $kit->look($files->get($file));
            } elseif (isset($schemas[$view])) {
                $looks[$view] = ['palette' => $schemas[$view]['theme'] ?? null, 'direction' => $schemas[$view]['direction'] ?? null];
            }
        }

        if ($looks !== []) {
            $this->table(['View', 'Palette', 'Direction'], array_map(
                fn (string $view, array $look): array => [$view, $look['palette'] ?? '', $look['direction'] ?? ''],
                array_keys($looks),
                $looks,
            ));
        }

        $this->components->twoColumnDetail('Palettes', implode(', ', $kit->palettes()));
        $this->components->twoColumnDetail('Directions', implode(', ', $kit->directions()) ?: 'none: this kit.css is older than them');

        if (count(array_unique(array_map('serialize', $looks))) > 1) {
            $this->components->warn('The pages are not drawn in one look: ui:theme <palette> --direction=<direction> draws them all in one.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * A schema in the look: the palette replaced, the direction replaced or,
     * when empty, taken out — the key deployer leaves out for none.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function dressed(array $schema, ?string $palette, ?string $direction): array
    {
        if ($palette !== null) {
            $schema['theme'] = $palette;
        }

        if ($direction === '') {
            unset($schema['direction']);
        } elseif ($direction !== null) {
            $schema['direction'] = $direction;
        }

        return $schema;
    }

    private static function file(string $view): string
    {
        return resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
    }

    private static function said(?string $palette, ?string $direction): string
    {
        return implode(' and ', array_filter([
            $palette === null ? null : "the {$palette} palette",
            match ($direction) {
                null => null,
                '' => 'no direction',
                default => "the {$direction} direction",
            },
        ]));
    }
}
