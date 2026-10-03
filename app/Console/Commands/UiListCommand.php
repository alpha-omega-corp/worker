<?php

namespace App\Console\Commands;

use App\Support\UiKit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Everything ui:import and ui:layout accept: the kit's components with what each
 * one requires, the layouts with their regions, and the palettes and directions
 * a page is drawn in. --json is what deployer's uikit MCP server reads.
 */
#[Signature('ui:list {--json : Print it as JSON}')]
#[Description('List the kit components, what each requires, the layouts with their regions, and the palettes and directions')]
class UiListCommand extends Command
{
    public function handle(UiKit $kit): int
    {
        $components = $kit->components();
        $layouts = $kit->layouts();
        $palettes = $kit->palettes();
        $directions = $kit->directions();

        if ($this->option('json')) {
            $this->line((string) json_encode(compact('components', 'layouts', 'palettes', 'directions'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Component', 'Requires', 'JS'], array_map(
            fn (string $name, array $component): array => [$name, implode(', ', $component['requires']), $component['js'] ?? ''],
            array_keys($components),
            $components,
        ));

        $this->table(['Layout', 'Regions', 'Summary'], array_map(
            fn (string $name, array $layout): array => [$name, implode(', ', $layout['regions']), $layout['summary']],
            array_keys($layouts),
            $layouts,
        ));

        $this->components->twoColumnDetail('Palettes', implode(', ', $palettes));
        $this->components->twoColumnDetail('Directions', implode(', ', $directions) ?: 'none: this kit.css is older than them');

        return self::SUCCESS;
    }
}
