<?php

namespace App\Console\Commands;

use App\Support\Pages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Add a page to resources/pages.json, change one, or take one out: the file the
 * routes, the head's titles, the navigation and the sitemap are all read from.
 * Deployer writes the same file; this is how it is written by hand.
 *
 *     php artisan ui:page pages.menu --path=/menu --title=Menu --label=Menu --role=offer
 *
 * @phpstan-import-type Page from Pages
 */
#[Signature('ui:page
    {view : The page\'s view, e.g. pages.menu for resources/views/pages/menu.blade.php}
    {--path= : Where it is served, e.g. /menu}
    {--title= : Its title, which the head shows as "Title · Name"}
    {--label= : Its item in the navigation; a page without one is not in it}
    {--role= : Its job on the site, e.g. offer}
    {--description= : Its meta description}
    {--remove : Take it out of the manifest; its view and its mockup are kept}')]
#[Description('Add, change or remove a page in resources/pages.json')]
class UiPageCommand extends Command
{
    public function handle(Pages $pages): int
    {
        $view = (string) $this->argument('view');

        try {
            $manifest = $this->option('remove') ? $pages->without($view) : $pages->with($view, self::changes($this));
        } catch (InvalidArgumentException $e) {
            // A manifest refused names each page that would not hold on a line of its own.
            foreach (explode("\n", $e->getMessage()) as $reason) {
                $this->components->error($reason);
            }

            return self::FAILURE;
        }

        $pages->save($manifest);
        self::report($this, $view, $manifest);

        return self::SUCCESS;
    }

    /**
     * The page's keys a command line gives: an option left out keeps what the
     * page has, and an empty one (--title=) clears it.
     *
     * @return array<string, ?string>
     */
    public static function changes(Command $command): array
    {
        $changes = [];

        foreach (['path', 'title', 'label', 'role', 'description'] as $key) {
            $value = $command->hasOption($key) ? $command->option($key) : null;

            if (is_string($value)) {
                $changes[$key] = $value === '' ? null : $value;
            }
        }

        return $changes;
    }

    /**
     * Where a page is served now, said the same way by both commands that route one.
     *
     * @param  list<Page>  $manifest
     */
    public static function report(Command $command, string $view, array $manifest): void
    {
        $page = collect($manifest)->firstWhere('view', $view);

        if ($page === null) {
            $command->components->info("{$view} is no longer a page; its view and its mockup are kept.");

            return;
        }

        $command->components->info("{$view} is served at {$page['path']}, as the route ".Pages::routeName($page['path']).'.');

        if (! View::exists($view)) {
            $command->components->warn("{$page['path']} answers 404 until its view exists: ui:layout builds it.");
        }
    }
}
