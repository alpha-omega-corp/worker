<?php

namespace App\Console\Commands;

use App\Support\UiKit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Import kit components by name, with every component their markup renders.
 *
 * `ui:kit` copies the whole kit or one raw component; this copies exactly the
 * set a page uses, which is what makes resources/components.php worth keeping.
 */
#[Signature('ui:import
    {components* : Kit components by name, e.g. pagination table}
    {--force : Overwrite files that already exist}')]
#[Description('Import kit components and everything they require')]
class UiImportCommand extends Command
{
    public function handle(UiKit $kit): int
    {
        /** @var list<string> $names */
        $names = $this->argument('components');

        try {
            $result = $kit->import($names, (bool) $this->option('force'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        self::report($this, $kit->closure($names), $result);

        return self::SUCCESS;
    }

    /**
     * What an import did, said the same way by both commands that import.
     *
     * @param  list<string>  $closure
     * @param  array{imported: list<string>, skipped: list<string>, js: list<string>}  $result
     */
    public static function report(Command $command, array $closure, array $result): void
    {
        $command->components->info('Imported '.implode(', ', array_map(fn (string $name): string => "<x-kit.{$name}>", $closure)).'.');

        if ($result['skipped'] !== []) {
            $command->components->warn('Kept the application\'s own copy of these; pass --force to overwrite them:');
            $command->components->bulletList($result['skipped']);
        }

        foreach ($result['js'] as $package) {
            $command->components->warn("Needs {$package}: npm install {$package}, then import it in resources/js/app.js.");
        }
    }
}
