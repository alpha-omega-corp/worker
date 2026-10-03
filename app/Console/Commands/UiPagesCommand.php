<?php

namespace App\Console\Commands;

use App\Support\Pages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * The pages resources/pages.json serves: each one's path, view and route name,
 * whether its view exists yet and whether it has a mockup to be built from —
 * and whatever in the file keeps a page from being served, which the site
 * itself only logs.
 */
#[Signature('ui:pages')]
#[Description('List the pages in resources/pages.json, with their route, view and mockup')]
class UiPagesCommand extends Command
{
    public function handle(Pages $pages): int
    {
        if (! $pages->exists() && $pages->problems() === []) {
            $this->components->info('There is no resources/pages.json: the site serves welcome at / alone. ui:page <view> --path=/<path> adds a page.');

            return self::SUCCESS;
        }

        if ($pages->all() !== []) {
            $this->table(['Path', 'View', 'Route', 'Title', 'Label', 'View file', 'Mockup'], array_map(fn (array $page): array => [
                $page['path'],
                $page['view'],
                Pages::routeName($page['path']),
                $page['title'] ?? '',
                $page['label'] ?? '',
                View::exists($page['view']) ? 'yes' : 'missing',
                is_file($pages->mockups()->path($page['view'])) ? 'yes' : 'none',
            ], $pages->all()));
        }

        foreach ($pages->problems() as $problem) {
            $this->components->error($problem);
        }

        return $pages->problems() === [] ? self::SUCCESS : self::FAILURE;
    }
}
